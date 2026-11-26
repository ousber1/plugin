<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Gap-free sequential document numbers (orders, invoices, quotations, payments...).
 * Uses a row lock (SELECT ... FOR UPDATE) inside a transaction, safe under concurrency.
 * Format: PREFIX-YYYY-000123
 */
final class SequenceService
{
    private const PREFIX_SETTINGS = [
        'order' => ['order_prefix', 'BWA'],
        'invoice' => ['invoice_prefix', 'FAC'],
        'credit_note' => ['credit_note_prefix', 'AV'],
        'quotation' => ['quotation_prefix', 'DEV'],
        'payment' => ['payment_prefix', 'REC'],
        'purchase' => ['purchase_prefix', 'ACH'],
        'production' => ['production_prefix', 'PROD'],
    ];

    public static function next(string $type, ?int $year = null, int $padding = 6): string
    {
        if (!isset(self::PREFIX_SETTINGS[$type])) {
            throw new \InvalidArgumentException('Unknown sequence type ' . $type);
        }
        $year ??= (int) date('Y');
        $db = Database::instance();
        $number = $db->transaction(function (Database $db) use ($type, $year): int {
            $db->query('INSERT IGNORE INTO document_sequences (sequence_type, period_year, last_number) VALUES (?, ?, 0)', [$type, $year]);
            $current = (int) $db->value(
                'SELECT last_number FROM document_sequences WHERE sequence_type = ? AND period_year = ? FOR UPDATE',
                [$type, $year]
            );
            $next = $current + 1;
            $db->query('UPDATE document_sequences SET last_number = ? WHERE sequence_type = ? AND period_year = ?', [$next, $type, $year]);
            return $next;
        });
        [$settingKey, $default] = self::PREFIX_SETTINGS[$type];
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string) setting($settingKey, $default)) ?: $default;
        return sprintf('%s-%d-%0' . $padding . 'd', strtoupper($prefix), $year, $number);
    }
}
