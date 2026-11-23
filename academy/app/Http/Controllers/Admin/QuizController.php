<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lesson;
use App\Models\Quiz;
use App\Services\QuizGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends AdminController
{
    public function edit(Lesson $lesson, QuizGenerator $generator)
    {
        $this->authorizeCourse($lesson->course);
        abort_unless($lesson->type === 'quiz', 404);
        $quiz = $lesson->quiz ?? Quiz::create(['course_id' => $lesson->course_id, 'lesson_id' => $lesson->id, 'pass_score' => 70]);

        return view('admin.quizzes.edit', [
            'lesson' => $lesson, 'quiz' => $quiz->load('questions'), 'course' => $lesson->course,
            'aiEnabled' => $generator->enabled(), 'generated' => session('generated'),
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        $data = $request->validate([
            'pass_score' => ['required', 'integer', 'between:1,100'],
            'questions' => ['nullable', 'array', 'max:100'],
            'questions.*.question' => ['required', 'string', 'max:1000'],
            'questions.*.options' => ['required', 'string', 'max:3000'],
            'questions.*.correct' => ['required', 'string', 'max:50'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
        ], [
            'questions.*.question.required' => 'نص السؤال مطلوب.',
            'questions.*.options.required' => 'أضف خيارات السؤال (خيار في كل سطر).',
            'questions.*.correct.required' => 'حدّد رقم الإجابة الصحيحة.',
        ]);

        $rows = [];
        foreach (array_values($data['questions'] ?? []) as $n => $q) {
            $options = array_values(array_filter(array_map('trim', preg_split('/\R/u', $q['options'])), fn ($o) => $o !== ''));
            $correct = array_values(array_unique(array_map(fn ($c) => (int) trim($c) - 1, array_filter(preg_split('/[,\s،]+/u', $q['correct']), fn ($c) => trim($c) !== ''))));
            if (count($options) < 2) {
                return back()->withInput()->with('error', 'السؤال رقم '.($n + 1).': أضف خيارين على الأقل.');
            }
            if ($correct === [] || max($correct) >= count($options) || min($correct) < 0) {
                return back()->withInput()->with('error', 'السؤال رقم '.($n + 1).': رقم الإجابة الصحيحة غير صالح.');
            }
            $rows[] = ['question' => trim($q['question']), 'options' => $options, 'correct' => $correct, 'explanation' => trim($q['explanation'] ?? '') ?: null, 'sort_order' => $n + 1];
        }

        $quiz = $lesson->quiz;
        DB::transaction(function () use ($quiz, $rows, $data) {
            $quiz->update(['pass_score' => $data['pass_score']]);
            $quiz->questions()->delete();
            foreach ($rows as $row) {
                $quiz->questions()->create($row);
            }
        });

        return redirect()->route('admin.quizzes.edit', $lesson)->with('success', 'تم حفظ الاختبار ('.count($rows).' سؤال).');
    }

    /** Optional AI generation: returns editable questions, never saves them. */
    public function generate(Request $request, Lesson $lesson, QuizGenerator $generator)
    {
        $this->authorizeCourse($lesson->course);
        $data = $request->validate([
            'source_text' => ['required', 'string', 'max:'.config('academy.ai.max_input_chars')],
            'count' => ['required', 'integer', 'between:1,15'],
            'language' => ['required', 'in:ar,fr,en'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'يجب الموافقة على إرسال النص إلى مزوّد الذكاء الاصطناعي.']);

        try {
            $questions = $generator->generate($data['source_text'], (int) $data['count'], $data['language']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('generated', $questions)->with('success', 'تم توليد '.count($questions).' سؤال. راجعها وعدّلها ثم احفظ الاختبار.');
    }
}
