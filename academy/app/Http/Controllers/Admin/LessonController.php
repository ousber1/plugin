<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\Quiz;
use App\Models\Section;
use App\Services\MediaService;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonController extends AdminController
{
    public function create(Section $section)
    {
        $this->authorizeCourse($section->course);

        return view('admin.lessons.form', ['lesson' => new Lesson(['type' => 'video', 'is_required' => true, 'status' => 'draft']), 'section' => $section, 'course' => $section->course]);
    }

    public function store(Request $request, Section $section)
    {
        $this->authorizeCourse($section->course);
        $data = $this->validated($request, $section->course_id);
        $lesson = new Lesson(['course_id' => $section->course_id, 'section_id' => $section->id]);
        $this->fill($lesson, $request, $data);
        $lesson->sort_order = (int) Lesson::where('section_id', $data['section_id'] ?? $section->id)->max('sort_order') + 1;
        $lesson->save();
        $this->ensureQuiz($lesson);

        $msg = $lesson->type === 'video' ? 'تم إنشاء الدرس. ارفع الفيديو الآن.' : 'تم إنشاء الدرس.';

        return redirect()->route($lesson->type === 'quiz' ? 'admin.quizzes.edit' : 'admin.lessons.edit', $lesson)->with('success', $msg);
    }

    public function edit(Lesson $lesson, MediaService $media)
    {
        $this->authorizeCourse($lesson->course);
        $lesson->load(['attachments', 'course.sections', 'quiz']);

        return view('admin.lessons.form', [
            'lesson' => $lesson, 'section' => $lesson->section, 'course' => $lesson->course,
            'maxBytes' => config('academy.video.max_mb') * 1048576,
            'chunkBytes' => Uploads::chunkBytes(),
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        $data = $this->validated($request, $lesson->course_id);
        $this->fill($lesson, $request, $data);
        if ($lesson->status === 'published' && $lesson->type === 'video' && ! $lesson->video_path && ! $lesson->tr('body')) {
            return back()->withInput()->with('error', 'لا يمكن نشر درس فيديو بدون فيديو مرفوع أو محتوى نصي.');
        }
        $lesson->save();
        $this->ensureQuiz($lesson);

        return back()->with('success', 'تم حفظ الدرس.');
    }

    public function destroy(Lesson $lesson, MediaService $media)
    {
        $course = $lesson->course;
        $this->authorizeCourse($course);
        $media->delete($lesson->video_disk, $lesson->video_path);
        foreach ($lesson->attachments as $a) {
            $media->delete($a->disk, $a->path);
        }
        $lesson->delete();

        return redirect()->route('admin.courses.curriculum', $course)->with('success', 'تم حذف الدرس.');
    }

    public function move(Request $request, Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        $request->validate(['direction' => ['required', 'in:up,down']]);
        CurriculumController::swap($lesson->section->lessons()->get(), $lesson->id, $request->input('direction'));

        return back();
    }

    public function destroyVideo(Lesson $lesson, MediaService $media)
    {
        $this->authorizeCourse($lesson->course);
        $media->delete($lesson->video_disk, $lesson->video_path);
        $lesson->forceFill(['video_disk' => null, 'video_path' => null, 'video_size' => null])->save();
        if ($lesson->status === 'published' && $lesson->type === 'video' && ! $lesson->tr('body')) {
            $lesson->forceFill(['status' => 'draft'])->save();

            return back()->with('info', 'حُذف الفيديو وأُعيد الدرس إلى مسودة.');
        }

        return back()->with('success', 'تم حذف الفيديو.');
    }

    public function storeAttachment(Request $request, Lesson $lesson, MediaService $media)
    {
        $this->authorizeCourse($lesson->course);
        $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'file' => ['required', 'file', 'mimes:'.config('academy.attachments.mimes'), 'max:'.(config('academy.attachments.max_mb') * 1024)],
        ], [
            'file.mimes' => 'نوع الملف غير مسموح. الأنواع المقبولة: '.config('academy.attachments.mimes'),
            'file.max' => 'حجم الملف أكبر من '.config('academy.attachments.max_mb').' م.ب.',
            'file.uploaded' => 'فشل رفع الملف. قد يكون حجمه أكبر من الحد المسموح على الخادم.',
        ]);
        try {
            $stored = $media->storeUpload($request->file('file'), 'attachments/'.$lesson->course_id);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذّر حفظ الملف على الخادم. تحقّق من صلاحيات مجلد التخزين.');
        }
        $lesson->attachments()->create($stored + ['title' => $request->input('title') ?: $stored['original_name']]);

        return back()->with('success', 'تمت إضافة الملف المرفق.');
    }

    public function destroyAttachment(LessonAttachment $attachment, MediaService $media)
    {
        $this->authorizeCourse($attachment->lesson->course);
        $media->delete($attachment->disk, $attachment->path);
        $attachment->delete();

        return back()->with('success', 'تم حذف الملف.');
    }

    private function validated(Request $request, int $courseId): array
    {
        return $request->validate([
            ...$this->trRules('title', true, 190),
            ...$this->trRules('body', false, 100000),
            'type' => ['required', Rule::in(Lesson::TYPES)],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('course_id', $courseId)],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'duration_extra_seconds' => ['nullable', 'integer', 'min:0', 'max:59'],
            'is_preview' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'status' => ['required', 'in:draft,published'],
        ]);
    }

    private function fill(Lesson $lesson, Request $request, array $data): void
    {
        $lesson->fill([
            'title' => $this->trInput($request, 'title'),
            'body' => $this->trInput($request, 'body', 'rich'),
            'type' => $data['type'],
            'is_preview' => $request->boolean('is_preview'),
            'is_required' => $request->boolean('is_required'),
            'status' => $data['status'],
        ]);
        if (! empty($data['section_id'])) {
            $lesson->section_id = $data['section_id'];
        }
        if ($request->filled('duration_minutes') || $request->filled('duration_extra_seconds')) {
            $lesson->duration_seconds = ((int) $request->input('duration_minutes')) * 60 + (int) $request->input('duration_extra_seconds');
        }
    }

    private function ensureQuiz(Lesson $lesson): void
    {
        if ($lesson->type === 'quiz' && ! $lesson->quiz()->exists()) {
            Quiz::create(['course_id' => $lesson->course_id, 'lesson_id' => $lesson->id, 'pass_score' => 70]);
        }
    }
}
