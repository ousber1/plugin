<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolSuggestion;
use App\Services\LessonFileReader;
use App\Services\QuizGenerator;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ToolController extends Controller
{
    public function index()
    {
        return view('tools.index', ['tools' => Tool::where('is_enabled', true)->orderBy('sort_order')->get()]);
    }

    public function show(Tool $tool)
    {
        abort_unless($tool->is_enabled && in_array($tool->key, Tool::KNOWN, true), 404);

        return view('tools.'.$tool->key, compact('tool'));
    }

    /** Whether visitors can use the AI quiz generator on the Tools page. */
    public static function aiToolEnabled(): bool
    {
        return (bool) Settings::get('ai_public_tool') && app(QuizGenerator::class)->enabled();
    }

    /**
     * Public AI quiz generator: pasted text or an uploaded lesson file
     * (PDF, Word, text, video subtitles) -> editable multiple-choice questions.
     * Logged-in users only, with a per-user daily limit (each call costs money).
     */
    public function generateQuiz(Request $request, QuizGenerator $generator, LessonFileReader $reader)
    {
        abort_unless(self::aiToolEnabled(), 403, __('مولّد الاختبارات بالذكاء الاصطناعي غير متاح حاليًا.'));

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:'.config('academy.ai.max_input_chars')],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:'.implode(',', LessonFileReader::EXTENSIONS)],
            'count' => ['required', 'integer', 'between:3,15'],
            'language' => ['required', 'in:ar,fr,en'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'consent' => ['accepted'],
        ], [
            'file.mimes' => __('ارفع ملف PDF أو Word (.docx) أو نصًا (.txt) أو ترجمة فيديو (.srt / .vtt).'),
            'file.max' => __('حجم الملف أكبر من 10 م.ب.'),
            'consent.accepted' => __('يجب الموافقة على إرسال المحتوى إلى مزوّد الذكاء الاصطناعي.'),
        ]);
        if (! $request->hasFile('file') && trim((string) ($data['text'] ?? '')) === '') {
            return response()->json(['message' => __('الصق نص الدرس أو ارفع ملفًا.')], 422);
        }

        $key = 'ai-tool:'.$request->user()->id;
        $limit = max(1, (int) Settings::get('ai_public_daily_limit', 10));
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(['message' => __('بلغت الحد اليومي لتوليد الاختبارات (:n). حاول غدًا.', ['n' => $limit])], 429);
        }

        try {
            $source = $request->hasFile('file') ? $reader->read($request->file('file')) : ['text' => '', 'pdf' => null];
            $text = trim($source['text']."\n\n".($data['text'] ?? ''));
            $questions = $generator->generate($text, (int) $data['count'], $data['language'], $source['pdf'], $data['difficulty']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        RateLimiter::hit($key, 86400);

        return response()->json([
            'questions' => $questions,
            'remaining' => RateLimiter::remaining($key, $limit),
        ]);
    }

    public function suggest(Request $request)
    {
        if (filled($request->input('website'))) {
            return back()->with('success', __('شكرًا! وصلتنا فكرتك.'));
        }
        $data = $request->validate([
            'idea' => ['required', 'string', 'min:10', 'max:1500'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);
        ToolSuggestion::create($data);

        return back()->with('success', __('شكرًا! وصلتنا فكرتك وسنراجعها قريبًا.'));
    }
}
