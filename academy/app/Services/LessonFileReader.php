<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Reads lesson material for the AI quiz generator.
 * - PDF: passed to the model as a document (base64)
 * - Word (.docx): text extracted from word/document.xml
 * - .txt / .md: read as text
 * - Video subtitles (.srt / .vtt): timestamps removed, spoken text kept
 */
class LessonFileReader
{
    public const EXTENSIONS = ['pdf', 'docx', 'txt', 'md', 'srt', 'vtt'];

    /** @return array{text:string, pdf:?string} */
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        return match ($ext) {
            'pdf' => $this->pdf($path),
            'docx' => ['text' => $this->docx($path), 'pdf' => null],
            'srt', 'vtt' => ['text' => $this->subtitles((string) file_get_contents($path)), 'pdf' => null],
            'txt', 'md' => ['text' => $this->utf8((string) file_get_contents($path)), 'pdf' => null],
            default => throw new \RuntimeException('نوع الملف غير مدعوم.'),
        };
    }

    private function pdf(string $path): array
    {
        $head = (string) file_get_contents($path, false, null, 0, 5);
        if ($head !== '%PDF-') {
            throw new \RuntimeException('الملف ليس PDF صالحًا.');
        }

        return ['text' => '', 'pdf' => base64_encode((string) file_get_contents($path))];
    }

    public function docx(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('قراءة ملفات Word تتطلب امتداد PHP zip على الخادم. الصق النص بدلًا من ذلك.');
        }
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
            throw new \RuntimeException('تعذّرت قراءة ملف Word. احفظه بصيغة .docx وحاول مجددًا.');
        }
        $zip->close();
        $xml = preg_replace(['/<w:tab[^>]*\/>/', '/<w:br[^>]*\/>/', '/<\/w:p>/'], ["\t", "\n", "\n"], $xml);

        return $this->clean(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    public function subtitles(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', $this->utf8($raw)) as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'WEBVTT' || ctype_digit($line) || str_contains($line, '-->') || preg_match('/^(NOTE|STYLE|REGION)\b/', $line)) {
                continue;
            }
            $line = trim(strip_tags($line));
            if ($line !== '' && end($lines) !== $line) {
                $lines[] = $line;
            }
        }

        return implode(' ', $lines);
    }

    private function utf8(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1256, ISO-8859-1');
        }

        return $this->clean($text);
    }

    private function clean(string $text): string
    {
        return trim(preg_replace(["/[ \t]+/u", "/\n{3,}/u"], [' ', "\n\n"], $text));
    }
}
