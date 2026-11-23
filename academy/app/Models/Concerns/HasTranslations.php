<?php

namespace App\Models\Concerns;

use App\Casts\Json;
use App\Support\Locales;

/**
 * Content translations stored as {"ar": ..., "fr": ..., "en": ...}.
 * Missing translations fall back to Arabic, then to any filled locale.
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        foreach ($this->translatable as $attribute) {
            $this->mergeCasts([$attribute => Json::class]);
        }
    }

    /** Translated scalar value (string) for the current locale. */
    public function tr(string $attribute, ?string $locale = null): string
    {
        $value = $this->trRaw($attribute, $locale);

        return is_string($value) ? $value : '';
    }

    /** Translated list value (array of strings) for the current locale. */
    public function trList(string $attribute, ?string $locale = null): array
    {
        $value = $this->trRaw($attribute, $locale);

        return is_array($value) ? array_values(array_filter($value, fn ($v) => trim((string) $v) !== '')) : [];
    }

    /** Whether the attribute has its own value for the given locale (no fallback). */
    public function hasTranslation(string $attribute, string $locale): bool
    {
        $values = $this->getAttribute($attribute) ?? [];
        $v = $values[$locale] ?? null;

        return is_array($v) ? count(array_filter($v)) > 0 : trim((string) $v) !== '';
    }

    protected function trRaw(string $attribute, ?string $locale)
    {
        $values = $this->getAttribute($attribute);
        if (! is_array($values)) {
            return $values;
        }
        $order = array_unique([$locale ?? app()->getLocale(), Locales::DEFAULT, ...Locales::codes()]);
        foreach ($order as $code) {
            $v = $values[$code] ?? null;
            if (is_array($v) ? count(array_filter($v)) > 0 : trim((string) $v) !== '') {
                return $v;
            }
        }

        return null;
    }

    /** Text direction of the language actually used for a value (for mixed-language rendering). */
    public function trLocale(string $attribute, ?string $locale = null): string
    {
        $values = $this->getAttribute($attribute) ?? [];
        foreach (array_unique([$locale ?? app()->getLocale(), Locales::DEFAULT, ...Locales::codes()]) as $code) {
            if (trim(is_array($values[$code] ?? null) ? implode('', $values[$code]) : (string) ($values[$code] ?? '')) !== '') {
                return $code;
            }
        }

        return Locales::DEFAULT;
    }

    /** Scope: LIKE search across translated JSON columns (unicode is stored unescaped). */
    public function scopeSearchTranslated($query, array $columns, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function ($q) use ($columns, $like) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
            }
        });
    }
}
