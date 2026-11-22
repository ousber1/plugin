<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\User;
use App\Support\Settings;

class ProgressService
{
    /** IDs of required, published lessons that count toward progress. */
    public function requiredLessonIds(Course $course): array
    {
        return $course->lessons()->where('status', 'published')->where('is_required', true)->pluck('id')->all();
    }

    public function completedLessonIds(User $user, Course $course): array
    {
        return LessonProgress::where('user_id', $user->id)->where('course_id', $course->id)
            ->whereNotNull('completed_at')->pluck('lesson_id')->all();
    }

    /** @return array{percent:int, completed:int, total:int} */
    public function progress(User $user, Course $course): array
    {
        $required = $this->requiredLessonIds($course);
        $done = array_intersect($required, $this->completedLessonIds($user, $course));
        $total = count($required);

        return [
            'percent' => $total ? (int) floor(count($done) * 100 / $total) : 0,
            'completed' => count($done),
            'total' => $total,
        ];
    }

    /** Quizzes that must be passed (published quiz lessons) when the course requires it. */
    public function requiredQuizzes(Course $course)
    {
        return Quiz::where('course_id', $course->id)
            ->whereHas('lesson', fn ($q) => $q->where('status', 'published'))
            ->whereHas('questions')
            ->with('lesson')->get();
    }

    /**
     * Certificate eligibility with human-readable outstanding requirements.
     *
     * @return array{eligible:bool, missing:array<int,string>, progress:array}
     */
    public function eligibility(User $user, Course $course): array
    {
        $missing = [];
        $progress = $this->progress($user, $course);

        if (! $user->isEnrolledIn($course)) {
            $missing[] = __('سجّل في الدورة أولًا.');
        }
        if ($progress['total'] === 0) {
            $missing[] = __('لا تحتوي الدورة بعد على دروس مطلوبة منشورة.');
        } elseif ($progress['completed'] < $progress['total']) {
            $missing[] = __('أكمل جميع الدروس المطلوبة (:done من :total).', ['done' => $progress['completed'], 'total' => $progress['total']]);
        }
        if ($course->requires_quiz_pass) {
            foreach ($this->requiredQuizzes($course) as $quiz) {
                if (! $quiz->passedBy($user)) {
                    $missing[] = __('انجح في اختبار «:title» بنسبة :score% على الأقل.', ['title' => $quiz->lesson->tr('title'), 'score' => $quiz->pass_score]);
                }
            }
        }
        if ((bool) Settings::get('require_verified_email_for_certificate') && ! $user->hasVerifiedEmail()) {
            $missing[] = __('أكّد بريدك الإلكتروني من خلال الرابط المرسل إليك.');
        }

        return ['eligible' => $missing === [], 'missing' => $missing, 'progress' => $progress];
    }

    public function markComplete(User $user, Lesson $lesson): void
    {
        LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['course_id' => $lesson->course_id, 'completed_at' => now()],
        );
        $this->touch($user, $lesson);
    }

    /** Record the last opened lesson and set completion time when everything required is done. */
    public function touch(User $user, Lesson $lesson): void
    {
        $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $lesson->course_id)->first();
        if (! $enrollment) {
            return;
        }
        $progress = $this->progress($user, $lesson->course);
        $enrollment->forceFill([
            'last_lesson_id' => $lesson->id,
            'last_activity_at' => now(),
            'completed_at' => $progress['total'] > 0 && $progress['percent'] === 100 ? ($enrollment->completed_at ?? now()) : null,
        ])->save();
    }
}
