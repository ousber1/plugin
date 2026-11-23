<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\Html;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class AdminController extends Controller
{
    /** Validation rules for a translatable field (Arabic required when $required). */
    protected function trRules(string $field, bool $required = false, int $max = 255): array
    {
        $rules = [$field => ['nullable', 'array']];
        foreach (Locales::codes() as $code) {
            $rules["$field.$code"] = [$code === Locales::DEFAULT && $required ? 'required' : 'nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /** Collect translatable input. $mode: text | rich | list */
    protected function trInput(Request $request, string $field, string $mode = 'text'): array
    {
        $out = [];
        foreach (Locales::codes() as $code) {
            $value = trim((string) $request->input("$field.$code", ''));
            $out[$code] = match ($mode) {
                'rich' => Html::clean($value),
                'list' => array_values(array_filter(array_map('trim', preg_split('/\R/u', $value)), fn ($v) => $v !== '')),
                default => $value,
            };
        }

        return $out;
    }

    protected function uniqueSlug(string $modelClass, string $source, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = Str::slug(Str::ascii($source)) ?: 'item';
        $base = Str::limit($base, 80, '');
        $slug = $base;
        $i = 2;
        while ($modelClass::where($column, $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function authorizeCourse(Course $course): void
    {
        abort_unless(auth()->user()->canManageCourse($course), 403);
    }
}
