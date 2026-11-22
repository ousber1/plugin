<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Services\AccessService;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class LearnController extends Controller
{
    public function __construct(private AccessService $access, private ProgressService $progress) {}

    public function show(Request $request, Course $course, ?Lesson $lesson = null)
    {
        $user = $request->user();
        $canManage = $user?->canManageCourse($course) ?? false;
        abort_unless($course->isPublished() || $canManage, 404);

        $sections = $course->sections()->with(['lessons' => fn ($q) => $canManage ? $q : $q->where('status', 'published')])->get();
        $ordered = $sections->flatMap->lessons->values();

        if (! $lesson) {
            $enrollment = $user?->enrollments()->where('course_id', $course->id)->first();
            $lesson = ($enrollment?->last_lesson_id ? $ordered->firstWhere('id', $enrollment->last_lesson_id) : null) ?? $ordered->first();
            if (! $lesson) {
                return redirect()->route('courses.show', $course)->with('info', __('لا توجد دروس منشورة بعد في هذه الدورة.'));
            }

            return redirect()->route('learn.show', [$course, $lesson]);
        }

        abort_unless($lesson->course_id === $course->id, 404);
        $lesson->setRelation('course', $course);

        if (! $this->access->canViewLesson($user, $lesson)) {
            if (! $user) {
                return redirect()->route('login', ['intended' => route('courses.show', $course)])
                    ->with('info', __('سجّل الدخول ثم سجّل في الدورة مجانًا لمتابعة هذا الدرس.'));
            }

            return redirect()->route('courses.show', $course)->with('info', __('سجّل في الدورة مجانًا لمشاهدة هذا الدرس.'));
        }

        $enrolled = $user?->isEnrolledIn($course) ?? false;
        if ($enrolled) {
            $this->progress->touch($user, $lesson);
        }

        $index = $ordered->search(fn ($l) => $l->id === $lesson->id);
        $lesson->load(['attachments', 'quiz.questions']);

        $quizState = null;
        if ($lesson->type === 'quiz' && $lesson->quiz && $user) {
            $quizState = [
                'best' => $lesson->quiz->bestAttemptFor($user),
                'last' => session('quiz_result'),
            ];
        }

        return view('learn.show', [
            'course' => $course,
            'lesson' => $lesson,
            'sections' => $sections,
            'prev' => $index > 0 ? $ordered[$index - 1] : null,
            'next' => $ordered[$index + 1] ?? null,
            'enrolled' => $enrolled,
            'completed' => $user ? $this->progress->completedLessonIds($user, $course) : [],
            'progress' => $enrolled ? $this->progress->progress($user, $course) : null,
            'bookmarked' => $user ? Bookmark::where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists() : false,
            'quizState' => $quizState,
            'canManage' => $canManage,
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson)
    {
        $this->ensureEnrolledAccess($request, $course, $lesson);
        if ($lesson->type === 'quiz' && $lesson->quiz?->questions()->exists()) {
            return back()->with('error', __('يكتمل درس الاختبار تلقائيًا عند النجاح فيه.'));
        }
        $this->progress->markComplete($request->user(), $lesson);

        $next = $this->nextLesson($course, $lesson);

        return redirect($next ? route('learn.show', [$course, $next]) : route('courses.show', $course))
            ->with('success', $next ? __('أحسنت! تم إكمال الدرس.') : __('أحسنت! أنهيت آخر درس في الدورة.'));
    }

    public function bookmark(Request $request, Course $course, Lesson $lesson)
    {
        $this->ensureEnrolledAccess($request, $course, $lesson);
        $existing = Bookmark::where('user_id', $request->user()->id)->where('lesson_id', $lesson->id)->first();
        if ($existing) {
            $existing->delete();

            return back()->with('success', __('أُزيلت الإشارة المرجعية.'));
        }
        Bookmark::firstOrCreate(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id]);

        return back()->with('success', __('تم حفظ الدرس في الإشارات المرجعية.'));
    }

    public function quiz(Request $request, Course $course, Lesson $lesson)
    {
        $this->ensureEnrolledAccess($request, $course, $lesson);
        $quiz = $lesson->quiz()->with('questions')->first();
        abort_unless($lesson->type === 'quiz' && $quiz && $quiz->questions->isNotEmpty(), 404);

        $input = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['array'], 'answers.*.*' => ['integer', 'min:0', 'max:10']]);
        $answers = $input['answers'] ?? [];

        $correctCount = 0;
        $review = [];
        foreach ($quiz->questions as $q) {
            $given = array_map('intval', $answers[$q->id] ?? []);
            sort($given);
            $expected = $q->correct ?? [];
            sort($expected);
            $ok = $given === $expected;
            $correctCount += $ok ? 1 : 0;
            $review[$q->id] = ['given' => $given, 'ok' => $ok];
        }
        $score = (int) round($correctCount * 100 / $quiz->questions->count());
        $passed = $score >= $quiz->pass_score;

        QuizAttempt::create(['quiz_id' => $quiz->id, 'user_id' => $request->user()->id, 'score' => $score, 'passed' => $passed, 'answers' => $answers]);
        if ($passed) {
            $this->progress->markComplete($request->user(), $lesson);
        }

        return back()->with('quiz_result', ['score' => $score, 'passed' => $passed, 'review' => $review])
            ->with($passed ? 'success' : 'error', $passed
                ? __('نجحت في الاختبار بنتيجة :score%.', ['score' => $score])
                : __('نتيجتك :score%. النسبة المطلوبة :pass%. راجع الدرس وحاول مجددًا.', ['score' => $score, 'pass' => $quiz->pass_score]));
    }

    private function ensureEnrolledAccess(Request $request, Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless($course->isPublished() && $lesson->isPublished() && $request->user()->isEnrolledIn($course), 403);
    }

    private function nextLesson(Course $course, Lesson $lesson): ?Lesson
    {
        $ordered = $course->sections()->with(['lessons' => fn ($q) => $q->where('status', 'published')])->get()->flatMap->lessons->values();
        $i = $ordered->search(fn ($l) => $l->id === $lesson->id);

        return $i === false ? null : ($ordered[$i + 1] ?? null);
    }
}
