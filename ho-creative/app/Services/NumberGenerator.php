<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free yearly numbering per document type. Pattern tokens:
 * {YYYY} {YY} {MM} {SEQ:n}. Must run inside the caller's transaction so the
 * number is only consumed if the document is actually created.
 */
class NumberGenerator
{
    public function __construct(protected Settings $settings) {}

    public function next(string $type): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($type, $year) {
            $seq = DocumentSequence::query()->where('type', $type)->where('year', $year)->lockForUpdate()->first();
            if (! $seq) {
                try {
                    DocumentSequence::create(['type' => $type, 'year' => $year, 'next_number' => 1]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                }
                $seq = DocumentSequence::query()->where('type', $type)->where('year', $year)->lockForUpdate()->first();
            }
            $n = $seq->next_number;
            $seq->increment('next_number');
            $pattern = (string) $this->settings->get('numbering.'.$type, strtoupper(substr($type, 0, 3)).'-{YYYY}-{SEQ:5}');

            return preg_replace_callback('/\{(YYYY|YY|MM|SEQ(?::(\d+))?)\}/', function ($m) use ($n) {
                return match (true) {
                    $m[1] === 'YYYY' => now()->format('Y'),
                    $m[1] === 'YY' => now()->format('y'),
                    $m[1] === 'MM' => now()->format('m'),
                    default => str_pad((string) $n, (int) ($m[2] ?? 5), '0', STR_PAD_LEFT),
                };
            }, $pattern);
        });
    }
}
