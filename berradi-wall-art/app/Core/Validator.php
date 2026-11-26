<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rule-based input validator.
 *
 *   $v = Validator::make($data, ['email' => 'required|email|max:191', 'qty' => 'required|integer|min:1']);
 *   if ($v->fails()) { $v->errors(); }
 *
 * Supported rules: required, nullable, string, email, integer, numeric, decimal:2, boolean, min:n, max:n,
 * between:a,b, in:a,b,c, confirmed, same:field, different:field, regex:/x/, alpha_dash, slug, url, phone,
 * date, unique:table,column[,ignoreId[,idColumn]], exists:table,column, password (strong), hex_color, locale.
 */
final class Validator
{
    /** @var array<string,list<string>> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $validated = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string|list<string>> $rules
     * @param array<string,string> $labels field => human label
     */
    public function __construct(private array $data, private array $rules, private array $labels = [])
    {
        $this->run();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string|list<string>> $rules
     * @param array<string,string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): Validator
    {
        return new Validator($data, $rules, $labels);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $isEmpty = $value === null || $value === '' || $value === [];
            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            if ($isEmpty) {
                if ($required) {
                    $this->addError($field, 'required');
                } else {
                    $this->validated[$field] = $nullable ? null : ($value ?? null);
                }
                continue;
            }

            $numericContext = (bool) array_intersect(['integer', 'numeric'], $rules) || (bool) preg_grep('/^decimal/', $rules);
            $ok = true;
            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if (!$this->check($field, $value, $name, $param, $numericContext)) {
                    $ok = false;
                    break; // one error per field is enough for users
                }
            }
            if ($ok) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function check(string $field, mixed $value, string $rule, ?string $param, bool $numeric): bool
    {
        $len = is_string($value) ? mb_strlen($value) : 0;
        $pass = match ($rule) {
            'string' => is_string($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false && $len <= 191,
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'decimal' => is_string($value) || is_int($value) || is_float($value)
                ? (bool) preg_match('/^-?\d{1,10}(\.\d{1,' . (int) ($param ?: 2) . '})?$/', (string) $value)
                : false,
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off', 'true', 'false'], true),
            'min' => $numeric ? (is_numeric($value) && (float) $value >= (float) $param) : $len >= (int) $param,
            'max' => $numeric ? (is_numeric($value) && (float) $value <= (float) $param) : (is_array($value) ? count($value) <= (int) $param : $len <= (int) $param),
            'between' => $this->between($value, (string) $param, $numeric, $len),
            'in' => in_array((string) (is_scalar($value) ? $value : ''), explode(',', (string) $param), true),
            'confirmed' => isset($this->data[$field . '_confirmation']) && $value === $this->data[$field . '_confirmation'],
            'same' => isset($this->data[(string) $param]) && $value === $this->data[(string) $param],
            'different' => !isset($this->data[(string) $param]) || $value !== $this->data[(string) $param],
            'regex' => is_string($value) && (bool) preg_match((string) $param, $value),
            'alpha_dash' => is_string($value) && (bool) preg_match('/^[\pL\pN_-]+$/u', $value),
            'slug' => is_string($value) && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value),
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value),
            'phone' => is_string($value) && (bool) preg_match('/^\+?[0-9 ().\-]{6,20}$/', $value),
            'date' => is_string($value) && $this->isDate($value),
            'hex_color' => is_string($value) && (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $value),
            'locale' => in_array($value, Translator::SUPPORTED, true),
            'password' => is_string($value) && $len >= 8 && preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value),
            'unique' => $this->unique($value, (string) $param),
            'exists' => $this->existsIn($value, (string) $param),
            default => throw new \InvalidArgumentException('Unknown validation rule: ' . $rule),
        };
        if (!$pass) {
            $this->addError($field, $rule, $param);
        }
        return (bool) $pass;
    }

    private function between(mixed $value, string $param, bool $numeric, int $len): bool
    {
        [$a, $b] = array_map('floatval', array_pad(explode(',', $param), 2, 0));
        $n = $numeric ? (is_numeric($value) ? (float) $value : null) : $len;
        return $n !== null && $n >= $a && $n <= $b;
    }

    private function isDate(string $value): bool
    {
        foreach (['Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $value);
            if ($d !== false && $d->format($fmt) === $value) {
                return true;
            }
        }
        return false;
    }

    private function unique(mixed $value, string $param): bool
    {
        $parts = explode(',', $param);
        $table = $parts[0];
        $column = $parts[1] ?? 'id';
        $q = Database::instance()->table($table)->where($column, $value);
        if (!empty($parts[2]) && $parts[2] !== 'NULL') {
            $q->where($parts[3] ?? 'id', '!=', $parts[2]);
        }
        return !$q->exists();
    }

    private function existsIn(mixed $value, string $param): bool
    {
        [$table, $column] = array_pad(explode(',', $param), 2, 'id');
        return Database::instance()->table($table)->where($column, $value)->exists();
    }

    private function addError(string $field, string $rule, ?string $param = null): void
    {
        $label = $this->labels[$field] ?? match (true) {
            Translator::has('validation.attributes.' . $field) => Translator::get('validation.attributes.' . $field),
            Translator::has('settings.' . $field) => Translator::get('settings.' . $field),
            default => str_replace('_', ' ', $field),
        };
        $replace = ['attribute' => $label];
        if ($param !== null) {
            $p = explode(',', $param);
            $replace['min'] = $p[0];
            $replace['max'] = $rule === 'between' ? ($p[1] ?? '') : $p[0];
            $replace['values'] = str_replace(',', ', ', $param);
            $replace['other'] = $param;
        }
        $this->errors[$field][] = Translator::get('validation.' . $rule, $replace);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,string> first error per field */
    public function firstErrors(): array
    {
        return array_map(fn ($e) => $e[0], $this->errors);
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }
}
