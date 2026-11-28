<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Core\Database;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Services\Builder\CustomerUploadService;
use App\Services\TranslationService;

/**
 * Personalisation of gifts and laser products: the fields a product asks for (name, message, date, font, photo,
 * choice…), their validation and their extra price, plus quantity price breaks.
 *
 * Everything is validated on the server: required fields, lengths, allowed choices, real dates, and photos that
 * belong to this visitor (uploaded through the private upload of the photo builder).
 */
final class Personalization
{
    public const TYPES = ['text', 'textarea', 'photo', 'font', 'select', 'date'];

    /** Fonts offered for engraving / printing when a "font" field has no list of its own. */
    public const DEFAULT_FONTS = ['Script élégante', 'Manuscrite', 'Serif classique', 'Sans moderne', 'Calligraphie arabe', 'Koufi'];

    /** @var array<int,list<array<string,mixed>>> */
    private static array $cache = [];

    /** @return list<array<string,mixed>> fields of a product, translated, choices decoded */
    public static function fields(int $productId): array
    {
        if (isset(self::$cache[$productId])) {
            return self::$cache[$productId];
        }
        $rows = Database::instance()->fetchAll('SELECT * FROM product_personalization_fields WHERE product_id = ? ORDER BY sort_order, id', [$productId]);
        $rows = TranslationService::apply('personalization_field', $rows, ['label', 'help', 'placeholder']);
        foreach ($rows as &$f) {
            $f['choices'] = json_decode((string) $f['choices'], true) ?: [];
            if ($f['field_type'] === 'font' && $f['choices'] === []) {
                $f['choices'] = self::DEFAULT_FONTS;
            }
            $f['is_required'] = (int) $f['is_required'] === 1;
        }
        unset($f);
        return self::$cache[$productId] = $rows;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /**
     * Validate what the customer typed.
     *
     * @param array<string,mixed> $input perso[field_key] => value (photo: the upload uuid)
     * @return array{items:list<array<string,mixed>>,addons:string}
     * @throws ValidationException
     */
    public static function validate(int $productId, array $input): array
    {
        $errors = [];
        $items = [];
        $addons = '0.00';
        foreach (self::fields($productId) as $f) {
            $key = (string) $f['field_key'];
            $raw = $input[$key] ?? '';
            $value = is_string($raw) ? trim(str_replace(["\r\n", "\r"], "\n", $raw)) : '';
            if ($f['field_type'] !== 'textarea') {
                $value = str_replace("\n", ' ', $value);
            }
            $label = (string) $f['label'];
            if ($value === '') {
                if ($f['is_required']) {
                    $errors['perso_' . $key] = __('personalize.err_required', ['field' => $label]);
                }
                continue;
            }
            $item = ['key' => $key, 'type' => $f['field_type'], 'label' => $label, 'value' => $value, 'price_add' => Money::normalize((string) $f['price_add'])];
            switch ($f['field_type']) {
                case 'text':
                case 'textarea':
                    $max = (int) ($f['max_length'] ?: ($f['field_type'] === 'text' ? 60 : 500));
                    if (mb_strlen($value) > $max) {
                        $errors['perso_' . $key] = __('personalize.err_length', ['field' => $label, 'max' => $max]);
                    }
                    break;
                case 'font':
                case 'select':
                    if (!in_array($value, $f['choices'], true)) {
                        $errors['perso_' . $key] = __('personalize.err_choice', ['field' => $label]);
                    }
                    break;
                case 'date':
                    $d = \DateTime::createFromFormat('!Y-m-d', $value);
                    if ($d === false || $d->format('Y-m-d') !== $value) {
                        $errors['perso_' . $key] = __('personalize.err_date', ['field' => $label]);
                    }
                    break;
                case 'photo':
                    $img = preg_match('/^[a-f0-9-]{36}$/', $value) ? CustomerUploadService::findAccessible($value, false) : null;
                    if ($img === null || !in_array($img['status'], ['temporary', 'attached'], true)) {
                        $errors['perso_' . $key] = __('personalize.err_photo', ['field' => $label]);
                        break;
                    }
                    $item['image_id'] = (int) $img['id'];
                    $item['value'] = __('personalize.photo_value', ['name' => mb_substr((string) $img['original_name'], 0, 60)]);
                    $item['uuid'] = $value;
                    $item['dpi'] = self::dpi($img, $f);
                    break;
            }
            $addons = Money::add($addons, $item['price_add']);
            $items[] = $item;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return ['items' => $items, 'addons' => $addons];
    }

    /** Print resolution of a photo on the printed area of the field (null when the area is not set). @param array<string,mixed> $img @param array<string,mixed> $f */
    public static function dpi(array $img, array $f): ?int
    {
        $w = (float) ($f['print_width_cm'] ?? 0);
        $h = (float) ($f['print_height_cm'] ?? 0);
        if ($w <= 0 || $h <= 0 || empty($img['width_px'])) {
            return null;
        }
        // the photo is cropped to fill the area: the limiting side decides
        return (int) floor(min((int) $img['width_px'] / ($w / 2.54), (int) $img['height_px'] / ($h / 2.54)));
    }

    /** One line for carts, e-mails and documents: "Prénom : Sara · Police : Script". @param list<array<string,mixed>> $items */
    public static function summary(array $items, int $max = 160): string
    {
        $parts = array_map(fn ($i) => $i['label'] . ' : ' . $i['value'], $items);
        $s = implode(' · ', $parts);
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }

    // ------------------------------------------------------------------ quantity price breaks

    /** @return list<array{min_qty:int,discount_percent:string}> highest threshold first */
    public static function tiers(int $productId): array
    {
        return array_map(fn ($r) => ['min_qty' => (int) $r['min_qty'], 'discount_percent' => (string) $r['discount_percent']],
            Database::instance()->fetchAll('SELECT min_qty, discount_percent FROM product_price_tiers WHERE product_id = ? ORDER BY min_qty DESC', [$productId]));
    }

    /** Unit price after the best applicable break (rounded to the cent). @param list<array{min_qty:int,discount_percent:string}> $tiers */
    public static function tieredPrice(string $unit, int $qty, array $tiers): string
    {
        foreach ($tiers as $t) {
            if ($qty >= $t['min_qty']) {
                $pct = (int) round((float) $t['discount_percent'] * 100); // basis points
                $cents = Money::toCents($unit);
                return Money::fromCents($cents - intdiv($cents * $pct + 5000, 10000));
            }
        }
        return Money::normalize($unit);
    }
}
