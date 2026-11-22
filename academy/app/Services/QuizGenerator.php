<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Support\Settings;

/**
 * Optional AI quiz generator. Enabled only when an administrator saves an
 * Anthropic API key in the dashboard (or ANTHROPIC_API_KEY is set).
 * The pasted lesson text is sent to Anthropic; the UI states this clearly.
 * Generated questions are validated and returned for editing — never saved
 * automatically.
 */
class QuizGenerator
{
    public function apiKey(): ?string
    {
        $key = Settings::get('ai_api_key') ?: env('ANTHROPIC_API_KEY');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public function enabled(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @return array<int, array{question:string, options:array<int,string>, correct:array<int,int>, explanation:string}>
     *
     * @throws \RuntimeException with an Arabic, user-facing message
     */
    public function generate(string $text, int $count = 5, string $language = 'ar'): array
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('مولّد الاختبارات بالذكاء الاصطناعي غير مفعّل. أضف مفتاح API من الإعدادات.');
        }
        $text = trim($text);
        if (mb_strlen($text) < 200) {
            throw new \RuntimeException('النص قصير جدًا. الصق 200 حرف على الأقل من محتوى الدرس.');
        }
        if (mb_strlen($text) > config('academy.ai.max_input_chars')) {
            throw new \RuntimeException('النص طويل جدًا. قسّمه إلى أجزاء أصغر من '.number_format(config('academy.ai.max_input_chars')).' حرف.');
        }
        $count = max(1, min(15, $count));
        $languageName = ['ar' => 'Arabic', 'fr' => 'French', 'en' => 'English'][$language] ?? 'Arabic';

        $schema = [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'correct' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'explanation' => ['type' => 'string'],
                        ],
                        'required' => ['question', 'options', 'correct', 'explanation'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['questions'],
            'additionalProperties' => false,
        ];

        try {
            $client = new Client(apiKey: $this->apiKey());
            $message = $client->beta->messages->create(
                maxTokens: 16000,
                model: config('academy.ai.model'),
                messages: [[
                    'role' => 'user',
                    'content' => "Write {$count} multiple-choice quiz questions in {$languageName} that check understanding of the lesson below. "
                        .'Each question has 3 to 5 options; `correct` lists the zero-based indexes of the correct options (usually one). '
                        ."Base every question only on the lesson text, and add a one-sentence explanation of the answer.\n\n<lesson>\n{$text}\n</lesson>",
                ]],
                outputConfig: [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new \RuntimeException('مفتاح API غير صالح. تحقّق منه في الإعدادات.');
        } catch (RateLimitException) {
            throw new \RuntimeException('تم تجاوز حد الطلبات لدى مزوّد الذكاء الاصطناعي. حاول بعد قليل.');
        } catch (APIStatusException $e) {
            report($e);
            throw new \RuntimeException('رفض مزوّد الذكاء الاصطناعي الطلب. حاول مجددًا لاحقًا.');
        } catch (APIConnectionException $e) {
            report($e);
            throw new \RuntimeException('تعذّر الاتصال بمزوّد الذكاء الاصطناعي. تحقّق من اتصال الخادم بالإنترنت.');
        }

        if ($message->stopReason === 'refusal') {
            throw new \RuntimeException('لم يتمكن المزوّد من إنشاء أسئلة لهذا النص.');
        }

        $json = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json = json_decode($block->text, true);
                break;
            }
        }

        return $this->validate($json);
    }

    /** Validate the provider response shape strictly; drop malformed questions. */
    public function validate(mixed $json): array
    {
        if (! is_array($json) || ! isset($json['questions']) || ! is_array($json['questions'])) {
            throw new \RuntimeException('استجابة غير صالحة من مزوّد الذكاء الاصطناعي.');
        }
        $clean = [];
        foreach ($json['questions'] as $q) {
            if (! is_array($q) || ! is_string($q['question'] ?? null) || ! is_array($q['options'] ?? null) || ! is_array($q['correct'] ?? null)) {
                continue;
            }
            $options = array_values(array_filter(array_map(fn ($o) => is_string($o) ? trim(mb_substr($o, 0, 500)) : '', $q['options']), fn ($o) => $o !== ''));
            if (count($options) < 2 || count($options) > 6) {
                continue;
            }
            $correct = array_values(array_unique(array_filter($q['correct'], fn ($i) => is_int($i) && $i >= 0 && $i < count($options))));
            if ($correct === [] || trim($q['question']) === '') {
                continue;
            }
            $clean[] = [
                'question' => trim(mb_substr($q['question'], 0, 1000)),
                'options' => $options,
                'correct' => $correct,
                'explanation' => is_string($q['explanation'] ?? null) ? trim(mb_substr($q['explanation'], 0, 1000)) : '',
            ];
        }
        if ($clean === []) {
            throw new \RuntimeException('لم يُرجع المزوّد أي سؤال صالح. حاول مجددًا.');
        }

        return $clean;
    }
}
