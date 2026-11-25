<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validation des données de formulaire, messages en français.
 *
 * Règles : required, nullable, email, numeric, integer, min:x, max:x (valeur),
 * minlen:x, maxlen:x (longueur), in:a,b, date (Y-m-d), phone, ice, alpha_dash,
 * color, url, same:champ, unique:table,colonne,idIgnoré, exists:table,colonne
 *
 * Exemple :
 *   $v = Validator::make($_POST, [
 *       'full_name' => 'required|maxlen:150',
 *       'email'     => 'nullable|email',
 *       'ice'       => 'nullable|ice',
 *   ], ['full_name' => 'Nom complet']);
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    private array $errors = [];

    private function __construct(private array $data, private array $rules, private array $labels)
    {
        $this->run();
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $isEmpty = $value === null || $value === '' || $value === [];

            if (in_array('nullable', $rules, true) && $isEmpty) {
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'nullable') {
                    continue;
                }
                $error = $this->check($name, $param, $value, $isEmpty, $field);
                if ($error !== null) {
                    $this->errors[$field][] = $error;
                    break; // une erreur par champ suffit
                }
            }
        }
    }

    private function check(string $rule, ?string $param, mixed $value, bool $isEmpty, string $field): ?string
    {
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $num = is_scalar($value) ? str_replace([' ', ','], ['', '.'], (string) $value) : '';

        return match ($rule) {
            'required' => $isEmpty ? "Le champ « {$label} » est obligatoire." : null,
            'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "Le champ « {$label} » doit être une adresse e-mail valide.",
            'numeric'  => is_numeric($num) ? null : "Le champ « {$label} » doit être un nombre.",
            'integer'  => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "Le champ « {$label} » doit être un nombre entier.",
            'min'      => is_numeric($num) && (float) $num >= (float) $param ? null : "Le champ « {$label} » doit être supérieur ou égal à {$param}.",
            'max'      => is_numeric($num) && (float) $num <= (float) $param ? null : "Le champ « {$label} » doit être inférieur ou égal à {$param}.",
            'maxlen'   => mb_strlen((string) $value) <= (int) $param ? null : "Le champ « {$label} » ne doit pas dépasser {$param} caractères.",
            'minlen'   => mb_strlen((string) $value) >= (int) $param ? null : "Le champ « {$label} » doit contenir au moins {$param} caractères.",
            'in'       => in_array((string) $value, explode(',', (string) $param), true) ? null : "La valeur du champ « {$label} » est invalide.",
            'date'     => self::isDate((string) $value) ? null : "Le champ « {$label} » doit être une date valide.",
            'phone'    => preg_match('/^\+?[0-9 ().\-]{6,20}$/', (string) $value) ? null : "Le champ « {$label} » doit être un numéro de téléphone valide.",
            'ice'      => preg_match('/^\d{15}$/', (string) $value) ? null : "L'ICE doit contenir exactement 15 chiffres.",
            'alpha_dash' => preg_match('/^[A-Za-z0-9_\-.]+$/', (string) $value) ? null : "Le champ « {$label} » ne peut contenir que lettres, chiffres, tirets et points.",
            'color'    => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? null : "Le champ « {$label} » doit être une couleur (#RRGGBB).",
            'url'      => filter_var($value, FILTER_VALIDATE_URL) ? null : "Le champ « {$label} » doit être une URL valide.",
            'same'     => (string) $value === (string) ($this->data[$param] ?? null) ? null : "La confirmation du champ « {$label} » ne correspond pas.",
            'unique'   => $this->isUnique((string) $param, $field, $value) ? null : "Cette valeur de « {$label} » est déjà utilisée.",
            'exists'   => $this->exists((string) $param, $value) ? null : "La valeur sélectionnée pour « {$label} » n'existe pas.",
            default    => throw new \InvalidArgumentException("Règle de validation inconnue : {$rule}"),
        };
    }

    private static function isDate(string $value): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    /** unique:table,column,ignoreId */
    private function isUnique(string $param, string $field, mixed $value): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $param), 3, null);
        $column = $column ?: $field;
        Database::assertIdentifier($table);
        Database::assertIdentifier($column);
        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
        $params = [$value];
        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= ' AND id <> ?';
            $params[] = (int) $ignoreId;
        }
        return (int) Database::instance()->fetchColumn($sql, $params) === 0;
    }

    /** exists:table,column */
    private function exists(string $param, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', $param), 2, 'id');
        Database::assertIdentifier($table);
        Database::assertIdentifier($column);
        return (int) Database::instance()->fetchColumn("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?", [$value]) > 0;
    }
}
