<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrollmentAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registers_enrolls_for_free_and_watches_lesson(): void
    {
        $course = $this->makeCourse(['slug' => 'python']);
        $lesson = $this->makeLesson($course);
        $this->attachFakeVideo($lesson);

        // Guest sees the free CTA that returns them to the course after registering.
        $this->get(route('courses.show', $course))->assertSee('سجّل مجانًا')->assertSee(urlencode(route('courses.show', $course)), false);
        $this->get(route('register', ['intended' => route('courses.show', $course)]))->assertOk();
        $this->post(route('register'), [
            'name' => 'سارة', 'email' => 'sara@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'terms' => '1',
        ])->assertRedirect(route('courses.show', $course));

        $user = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertSame('student', $user->role->value);

        $this->post(route('courses.enroll', $course))->assertRedirect(route('learn.show', [$course, $lesson]));
        $this->post(route('courses.enroll', $course)); // idempotent
        $this->assertSame(1, Enrollment::where('user_id', $user->id)->count());

        $this->get(route('courses.show', $course))->assertSee('تابع التعلم');
        $this->get(route('learn.show', [$course, $lesson]))->assertOk()->assertSee(route('media.video', $lesson));

        $response = $this->withHeader('Range', 'bytes=0-99')->get(route('media.video', $lesson));
        $response->assertStatus(206);
        $this->assertSame('bytes 0-99/'.strlen($this->fakeMp4()), $response->headers->get('Content-Range'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_unauthorized_users_cannot_play_protected_video_or_download_files(): void
    {
        $course = $this->makeCourse();
        $lesson = $this->makeLesson($course);
        $this->attachFakeVideo($lesson);
        Storage::disk('local')->put('attachments/x.pdf', '%PDF-1.4 test');
        $attachment = $lesson->attachments()->create(['title' => 'ملف', 'disk' => 'local', 'path' => 'attachments/x.pdf', 'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 13]);
        Storage::disk('local')->put('library/r.pdf', '%PDF-1.4');
        $resource = new Resource(['title' => ['ar' => 'مورد'], 'access' => 'enrolled', 'status' => 'published']);
        $resource->slug = 'restricted';
        $resource->course_id = $course->id;
        $resource->forceFill(['disk' => 'local', 'path' => 'library/r.pdf', 'original_name' => 'r.pdf'])->save();

        // Guest
        $this->get(route('media.video', $lesson))->assertForbidden();
        $this->get(route('media.attachment', $attachment))->assertForbidden();
        $this->get(route('library.download', $resource))->assertRedirect();
        $this->get(route('learn.show', [$course, $lesson]))->assertRedirect();

        // Logged in but not enrolled
        $this->actingAs($this->student());
        $this->get(route('media.video', $lesson))->assertForbidden();
        $this->get(route('media.attachment', $attachment))->assertForbidden();
        $this->get(route('library.download', $resource))->assertForbidden();
        $this->post(route('learn.complete', [$course, $lesson]))->assertForbidden();

        // Enrolled student
        $student = $this->student();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id]);
        $this->actingAs($student)->get(route('media.attachment', $attachment))->assertOk();
        $this->get(route('library.download', $resource))->assertOk();
    }

    public function test_preview_lessons_are_public_and_drafts_are_hidden(): void
    {
        $course = $this->makeCourse(['slug' => 'pub']);
        $preview = $this->makeLesson($course, ['is_preview' => true]);
        $this->attachFakeVideo($preview);
        $draftLesson = $this->makeLesson($course, ['is_preview' => true, 'status' => 'draft', 'sort_order' => 2]);
        $this->attachFakeVideo($draftLesson);

        $this->get(route('media.video', $preview))->assertOk();
        $this->get(route('learn.show', [$course, $preview]))->assertOk()->assertSee('معاينة');
        $this->get(route('media.video', $draftLesson))->assertForbidden();

        $draft = $this->makeCourse(['slug' => 'secret-draft', 'title' => ['ar' => 'دورة سرية']], published: false);
        $draftPreview = $this->makeLesson($draft, ['is_preview' => true]);
        $this->attachFakeVideo($draftPreview);
        $this->get(route('courses.show', $draft))->assertNotFound();
        $this->get(route('courses.index'))->assertDontSee('دورة سرية');
        $this->get(route('courses.index', ['q' => 'سرية']))->assertDontSee('دورة سرية');
        $this->get(route('sitemap'))->assertDontSee('secret-draft')->assertSee('/courses/pub', false);
        $this->get(route('media.video', $draftPreview))->assertForbidden();
    }

    public function test_catalog_search_and_filters_work_with_arabic_and_latin_text(): void
    {
        $this->makeCourse(['slug' => 'a', 'title' => ['ar' => 'أساسيات البرمجة بلغة Python'], 'level' => 'beginner']);
        $this->makeCourse(['slug' => 'b', 'title' => ['fr' => 'Excel pour la gestion'], 'level' => 'intermediate']);

        $this->get(route('courses.index', ['q' => 'البرمجة']))->assertSee('أساسيات البرمجة')->assertDontSee('Excel pour');
        $this->get(route('courses.index', ['q' => 'python']))->assertSee('أساسيات البرمجة');
        $this->get(route('courses.index', ['q' => 'gestion']))->assertSee('Excel pour la gestion');
        $this->get(route('courses.index', ['level' => 'intermediate']))->assertSee('Excel pour')->assertDontSee('أساسيات البرمجة');
        $this->get(route('courses.index', ['q' => 'zzzz']))->assertSee('لا توجد دورات مطابقة');
        // Filters are preserved in pagination/query string
        $this->get(route('courses.index', ['level' => 'beginner', 'sort' => 'title']))->assertOk()->assertSee('name="level"', false);
    }

    public function test_intended_redirect_rejects_external_urls(): void
    {
        $user = $this->student();
        $this->get(route('login', ['intended' => 'https://evil.example/phish']));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->student();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    }
}
