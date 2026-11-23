<?php

namespace App\Models\Concerns;

/**
 * JSON columns holding {"fr": "...", "ar": "..."}; read with $model->t('name').
 */
trait HasTranslations
{
    public function t(string $attribute, ?string $locale = null): string
    {
        $value = $this->getAttribute($attribute);
        if (! is_array($value)) {
            return (string) $value;
        }
        $locale ??= app()->getLocale();

        return (string) (($value[$locale] ?? '') !== '' ? $value[$locale] : ($value['fr'] ?? (reset($value) ?: '')));
    }
}
