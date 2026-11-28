<?php

declare(strict_types=1);

namespace App\Services\Finance;

/** Legal identity printed on documents (frozen into invoices when they are issued). */
final class CompanyInfo
{
    public const KEYS = ['company_name', 'company_legal_name', 'company_address', 'company_city', 'company_country', 'company_phone', 'company_email',
        'company_website', 'company_ice', 'company_rc', 'company_if', 'company_patente', 'company_cnss', 'company_bank_name', 'company_rib', 'company_logo'];

    /** @return array<string,string> */
    public static function snapshot(): array
    {
        $out = [];
        foreach (self::KEYS as $k) {
            $out[$k] = (string) setting($k, '');
        }
        return $out;
    }

    /** Legal identifiers still empty (Moroccan invoices must show ICE, and usually RC / IF). @return list<string> */
    public static function missingForInvoices(): array
    {
        $missing = [];
        foreach (['company_ice', 'company_rc', 'company_if', 'company_address'] as $k) {
            if (trim((string) setting($k, '')) === '') {
                $missing[] = $k;
            }
        }
        return $missing;
    }
}
