<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\ValidationException;
use App\Helpers\Money;

/**
 * Lines priced HT (quotations, invoices typed by staff): HT = qty × unit − line discount,
 * VAT = HT × rate, TTC = HT + VAT, each rounded to the cent; totals are sums of the rounded lines.
 */
final class DocumentLines
{
    /**
     * @param list<array<string,mixed>> $raw description, specifications?, quantity, unit_price (HT), discount?, tax_rate (%)
     * @return array{lines:list<array<string,mixed>>,subtotal:string,discount:string,tax:string,total:string,ht:string}
     */
    public static function compute(array $raw, string $field = 'lines'): array
    {
        $lines = [];
        $sub = '0.00';
        $disc = '0.00';
        $tax = '0.00';
        foreach (array_values($raw) as $i => $l) {
            $desc = trim((string) ($l['description'] ?? ''));
            $price = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) ($l['unit_price'] ?? '')));
            if ($desc === '' && $price === '') {
                continue; // empty form row
            }
            $n = $i + 1;
            $qty = str_replace(',', '.', trim((string) ($l['quantity'] ?? '1')));
            $rate = str_replace(',', '.', trim((string) ($l['tax_rate'] ?? '0')));
            $d = str_replace([' ', ','], ['', '.'], trim((string) ($l['discount'] ?? '')));
            if ($desc === '' || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $qty) || (float) $qty <= 0
                || !preg_match('/^\d{1,2}(\.\d{1,4})?$/', $rate) || ($d !== '' && !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $d))) {
                throw new ValidationException([$field => __('finance.err_line', ['n' => $n])]);
            }
            $gross = Money::mulQty($price, $qty);
            $discount = Money::normalize($d !== '' ? $d : '0');
            if (Money::compare($discount, $gross) > 0) {
                throw new ValidationException([$field => __('finance.err_line_discount', ['n' => $n])]);
            }
            $ht = Money::sub($gross, $discount);
            $lt = Money::percent($ht, $rate);
            $lines[] = [
                'description' => mb_substr($desc, 0, 500),
                'specifications' => ($s = trim((string) ($l['specifications'] ?? ''))) !== '' ? mb_substr($s, 0, 1000) : null,
                'product_id' => !empty($l['product_id']) ? (int) $l['product_id'] : null,
                'quantity' => number_format((float) $qty, 2, '.', ''), 'unit_price' => Money::normalize($price), 'discount' => $discount,
                'tax_rate' => number_format((float) $rate, 4, '.', ''), 'ht' => $ht, 'tax' => $lt, 'ttc' => Money::add($ht, $lt),
            ];
            $sub = Money::add($sub, $gross);
            $disc = Money::add($disc, $discount);
            $tax = Money::add($tax, $lt);
        }
        if ($lines === []) {
            throw new ValidationException([$field => __('finance.err_no_lines')]);
        }
        $ht = Money::sub($sub, $disc);
        return ['lines' => $lines, 'subtotal' => $sub, 'discount' => $disc, 'ht' => $ht, 'tax' => $tax, 'total' => Money::add($ht, $tax)];
    }

    /** VAT breakdown per rate (legal mention on invoices). @param list<array<string,mixed>> $items @return array<string,array{base:string,tax:string}> */
    public static function vatBreakdown(array $items, string $htKey, string $taxKey): array
    {
        $out = [];
        foreach ($items as $it) {
            $r = rtrim(rtrim(number_format((float) $it['tax_rate'], 2, '.', ''), '0'), '.');
            $out[$r] ??= ['base' => '0.00', 'tax' => '0.00'];
            $out[$r]['base'] = Money::add($out[$r]['base'], (string) $it[$htKey]);
            $out[$r]['tax'] = Money::add($out[$r]['tax'], (string) $it[$taxKey]);
        }
        ksort($out);
        return $out;
    }
}
