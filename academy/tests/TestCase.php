<?php

namespace Tests;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Settings::flush();
        $this->withoutVite();
    }

    protected function makeCourse(array $attrs = [], bool $published = true): Course
    {
        $course = new Course(array_merge([
            'title' => ['ar' => 'دورة اختبار', 'en' => 'Test course'],
            'description' => ['ar' => '<p>وصف</p>'],
            'level' => 'beginner',
            'language' => 'ar',
        ], $attrs));
        $course->slug = $attrs['slug'] ?? 'test-course-'.uniqid();
        $course->status = $published ? 'published' : 'draft';
        $course->published_at = $published ? now()->subDay() : null;
        $course->save();

        return $course;
    }

    protected function makeLesson(Course $course, array $attrs = []): Lesson
    {
        $section = $course->sections()->first() ?? Section::create(['course_id' => $course->id, 'title' => ['ar' => 'القسم 1'], 'sort_order' => 1]);
        $lesson = new Lesson(array_merge([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => ['ar' => 'درس'],
            'type' => 'video',
            'is_required' => true,
            'is_preview' => false,
            'status' => 'published',
            'sort_order' => 1,
        ], $attrs));
        $lesson->save();

        return $lesson;
    }

    protected function attachFakeVideo(Lesson $lesson): void
    {
        $path = 'videos/'.$lesson->course_id.'/l'.$lesson->id.'.mp4';
        Storage::disk('local')->put($path, $this->fakeMp4());
        $lesson->forceFill(['video_disk' => 'local', 'video_path' => $path, 'video_size' => strlen($this->fakeMp4())])->save();
    }

    /** A minimal ISO-BMFF byte sequence: ftyp + moov/mvhd (10s) + mdat. */
    protected function fakeMp4(): string
    {
        $ftyp = pack('N', 24).'ftyp'.'isom'.pack('N', 512).'isom'.'mp41';
        $mvhdBody = chr(0)."\0\0\0".pack('N', 0).pack('N', 0).pack('N', 1000).pack('N', 10000).str_repeat("\0", 80);
        $mvhd = pack('N', 8 + strlen($mvhdBody)).'mvhd'.$mvhdBody;
        $moov = pack('N', 8 + strlen($mvhd)).'moov'.$mvhd;
        $payload = str_repeat("\x11", 4000);
        $mdat = pack('N', 8 + strlen($payload)).'mdat'.$payload;

        return $ftyp.$moov.$mdat;
    }

    protected function addQuiz(Lesson $lesson, int $pass = 60): Quiz
    {
        $quiz = Quiz::create(['course_id' => $lesson->course_id, 'lesson_id' => $lesson->id, 'pass_score' => $pass]);
        $quiz->questions()->create(['question' => 'س1', 'options' => ['أ', 'ب'], 'correct' => [1], 'sort_order' => 1]);
        $quiz->questions()->create(['question' => 'س2', 'options' => ['أ', 'ب', 'ج'], 'correct' => [0, 2], 'sort_order' => 2]);

        return $quiz;
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function student(): User
    {
        return User::factory()->create();
    }
}
