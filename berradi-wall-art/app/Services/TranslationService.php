<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Translator;

/**
 * Content translations stored in the `translations` table.
 * Main columns hold French (default) content; other locales override per field.
 */
final class TranslationService
{
    /**
     * Replace translatable fields of many rows in one query (no N+1).
     *
     * @param list<array<string,mixed>> $rows rows with an "id" key
     * @param list<string> $fields
     * @return list<array<string,mixed>>
     */
    public static function apply(string $entityType, array $rows, array $fields, ?string $locale = null, string $idKey = 'id'): array
    {
        $locale ??= Translator::locale();
        if ($rows === [] || $locale === 'fr') {
            return $rows;
        }
        $ids = array_values(array_unique(array_map(fn ($r) => (int) $r[$idKey], $rows)));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $fph = implode(',', array_fill(0, count($fields), '?'));
        $map = [];
        foreach (Database::instance()->fetchAll(
            "SELECT entity_id, field, value FROM translations WHERE entity_type = ? AND locale = ? AND entity_id IN ({$ph}) AND field IN ({$fph})",
            array_merge([$entityType, $locale], $ids, $fields)
        ) as $t) {
            if ($t['value'] !== null && $t['value'] !== '') {
                $map[(int) $t['entity_id']][$t['field']] = $t['value'];
            }
        }
        foreach ($rows as &$row) {
            foreach ($map[(int) $row[$idKey]] ?? [] as $field => $value) {
                $row[$field] = $value;
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $fields
     * @return array<string,mixed>
     */
    public static function applyOne(string $entityType, array $row, array $fields, ?string $locale = null): array
    {
        return self::apply($entityType, [$row], $fields, $locale)[0];
    }

    /** @return array<string,string> field => value for one entity/locale */
    public static function get(string $entityType, int $entityId, string $locale): array
    {
        $out = [];
        foreach (Database::instance()->fetchAll(
            'SELECT field, value FROM translations WHERE entity_type = ? AND entity_id = ? AND locale = ?',
            [$entityType, $entityId, $locale]
        ) as $t) {
            $out[$t['field']] = (string) $t['value'];
        }
        return $out;
    }

    /** Upsert (empty value deletes the translation). @param array<string,?string> $values */
    public static function save(string $entityType, int $entityId, string $locale, array $values): void
    {
        $db = Database::instance();
        foreach ($values as $field => $value) {
            if ($value === null || trim($value) === '') {
                $db->query('DELETE FROM translations WHERE entity_type = ? AND entity_id = ? AND locale = ? AND field = ?', [$entityType, $entityId, $locale, $field]);
                continue;
            }
            $db->query(
                'INSERT INTO translations (entity_type, entity_id, locale, field, value) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$entityType, $entityId, $locale, $field, $value]
            );
        }
    }
}
