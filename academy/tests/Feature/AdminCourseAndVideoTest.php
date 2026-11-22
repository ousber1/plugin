<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminCourseAndVideoTest extends TestCase
{
    use RefreshDatabase;

    private function uploadVideo(User $user, Lesson $lesson, string $bytes, int $chunk = 1500): TestResponse
    {
        $start = $this->actingAs($user)->postJson(route('admin.uploads.start', $lesson), ['name' => 'lesson.mp4', 'size' => strlen($bytes)]);
        $start->assertOk();
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $part = substr($bytes, $offset, $chunk);
            $this->postJson($start->json('chunk_url'), [
                'offset' => $offset,
                'chunk' => UploadedFile::fake()->createWithContent('chunk', $part),
            ])->assertOk()->assertJson(['received' => $offset + strlen($part)]);
            $offset += strlen($part);
        }

        return $this->postJson($start->json('complete_url'));
    }

    public function test_admin_creates_course_uploads_video_and_publishes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => ['ar' => 'دورة جديدة', 'fr' => 'Nouveau cours', 'en' => 'New course'],
            'level' => 'beginner', 'language' => 'ar',
            'outcomes' => ['ar' => "نتيجة 1\nنتيجة 2"],
            'description' => ['ar' => '<p>مرحبا</p><script>alert(1)</script>'],
        ])->assertRedirect();

        $course = Course::firstOrFail();
        $this->assertSame('draft', $course->status);
        $this->assertSame('new-course', $course->slug);
        $this->assertSame(['نتيجة 1', 'نتيجة 2'], $course->outcomes['ar']);
        $this->assertStringNotContainsString('<script', $course->description['ar']); // sanitized

        $this->post(route('admin.sections.store', $course), ['title' => ['ar' => 'القسم الأول']])->assertRedirect();
        $section = $course->sections()->firstOrFail();
        $this->post(route('admin.lessons.store', $section), ['title' => ['ar' => 'الدرس الأول'], 'type' => 'video', 'status' => 'draft', 'is_required' => 1])->assertRedirect();
        $lesson = Lesson::firstOrFail();

        $this->uploadVideo($admin, $lesson, $this->fakeMp4())->assertOk()->assertJson(['ok' => true, 'duration' => 10]);
        $lesson->refresh();
        $this->assertNotNull($lesson->video_path);
        $this->assertSame(10, $lesson->duration_seconds);
        Storage::disk('local')->assertExists($lesson->video_path);

        // Admin can preview the private video before publishing.
        $this->get(route('media.video', $lesson))->assertOk();

        $this->put(route('admin.lessons.update', $lesson), ['title' => ['ar' => 'الدرس الأول'], 'type' => 'video', 'status' => 'published', 'is_required' => 1])->assertRedirect();
        $this->post(route('admin.courses.publish', $course))->assertRedirect();
        $this->assertTrue($course->fresh()->isPublished());
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.published', 'subject_id' => $course->id]);

        auth()->logout();
        $this->get(route('courses.index'))->assertOk()->assertSee('دورة جديدة')->assertSee('مجانية');
        $this->get(route('courses.show', $course))->assertOk()->assertSee('سجّل مجانًا');
    }

    public function test_invalid_upload_is_rejected_and_existing_video_is_kept(): void
    {
        $admin = $this->admin();
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->attachFakeVideo($lesson);
        $original = $lesson->video_path;

        // Wrong extension
        $this->actingAs($admin)->postJson(route('admin.uploads.start', $lesson), ['name' => 'movie.avi', 'size' => 100])
            ->assertStatus(422)->assertJsonPath('errors.name.0', 'صيغة غير مدعومة. ارفع ملف فيديو بصيغة MP4.');
        // Too large
        config(['academy.video.max_mb' => 1]);
        $this->postJson(route('admin.uploads.start', $lesson), ['name' => 'big.mp4', 'size' => 5 * 1048576])->assertStatus(422);
        // Not an MP4 container
        $start = $this->postJson(route('admin.uploads.start', $lesson), ['name' => 'fake.mp4', 'size' => 20])->assertOk();
        $this->postJson($start->json('chunk_url'), ['offset' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', str_repeat('x', 20))])
            ->assertStatus(422);

        $this->assertSame($original, $lesson->fresh()->video_path);
        Storage::disk('local')->assertExists($original);
    }

    public function test_upload_resumes_from_server_offset_and_replaces_video_after_success(): void
    {
        $admin = $this->admin();
        $lesson = $this->makeLesson($this->makeCourse());
        $this->attachFakeVideo($lesson);
        $old = $lesson->video_path;
        $bytes = $this->fakeMp4();

        $start = $this->actingAs($admin)->postJson(route('admin.uploads.start', $lesson), ['name' => 'new.mp4', 'size' => strlen($bytes)])->assertOk();
        $this->postJson($start->json('chunk_url'), ['offset' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', substr($bytes, 0, 1000))])->assertOk();
        // Interrupted client retries with a stale offset: server reports where to resume.
        $this->postJson($start->json('chunk_url'), ['offset' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', substr($bytes, 0, 1000))])
            ->assertStatus(409)->assertJson(['received' => 1000]);
        // Starting again with the same file resumes the same upload.
        $this->postJson(route('admin.uploads.start', $lesson), ['name' => 'new.mp4', 'size' => strlen($bytes)])->assertJson(['received' => 1000]);
        $this->postJson($start->json('chunk_url'), ['offset' => 1000, 'chunk' => UploadedFile::fake()->createWithContent('c', substr($bytes, 1000))])->assertOk();
        // Old video is still there until completion.
        Storage::disk('local')->assertExists($old);
        $this->postJson($start->json('complete_url'))->assertOk();

        $lesson->refresh();
        $this->assertNotSame($old, $lesson->video_path);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($lesson->video_path);
    }

    public function test_instructor_only_manages_assigned_courses(): void
    {
        $instructor = User::factory()->instructor()->create();
        $mine = $this->makeCourse();
        $mine->assignees()->attach($instructor);
        $other = $this->makeCourse();

        $this->actingAs($instructor)->get(route('admin.courses.edit', $mine))->assertOk();
        $this->get(route('admin.courses.edit', $other))->assertForbidden();
        $this->get(route('admin.courses.curriculum', $other))->assertForbidden();
        $this->postJson(route('admin.uploads.start', $this->makeLesson($other)), ['name' => 'a.mp4', 'size' => 10])->assertForbidden();
        $this->get(route('admin.courses.index'))->assertOk()->assertSee($mine->tr('title'));
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.certificate-requests.index'))->assertForbidden();
        $this->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_students_cannot_access_dashboard(): void
    {
        $this->actingAs($this->student())->get(route('admin.dashboard'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
