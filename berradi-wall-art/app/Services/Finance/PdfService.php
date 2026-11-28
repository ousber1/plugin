<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Core\Translator;
use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF documents (quotations, invoices, credit notes) rendered with Dompdf from server-side templates,
 * in the document's language (French or Arabic). No remote resources are fetched.
 */
final class PdfService
{
    public static function available(): bool
    {
        return class_exists(Dompdf::class);
    }

    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data, string $locale): string
    {
        if (!self::available()) {
            throw new \RuntimeException(__('finance.err_pdf_unavailable'));
        }
        $previous = Translator::locale();
        Translator::setLocale($locale);
        try {
            $html = View::make($view, $data + ['locale' => $locale]);
        } finally {
            Translator::setLocale($previous);
        }
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [public_path(), base_path('vendor/dompdf/dompdf/lib/fonts')]);
        $options->set('tempDir', storage_path('cache'));
        $options->set('fontCache', storage_path('cache'));
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * PDF of an invoice / credit note. Issued documents are generated once and kept in storage/invoices
     * (their content is frozen); drafts are rendered on the fly with a DRAFT watermark.
     */
    public static function invoice(array $inv): string
    {
        $db = Database::instance();
        if ($inv['status'] !== 'draft' && !empty($inv['pdf_path'])) {
            $path = storage_path('invoices/' . $inv['pdf_path']);
            if (!str_contains((string) $inv['pdf_path'], '..') && is_file($path)) {
                return (string) file_get_contents($path);
            }
        }
        $items = InvoiceService::items((int) $inv['id']);
        $company = $inv['company_snapshot'] ? (json_decode((string) $inv['company_snapshot'], true) ?: CompanyInfo::snapshot()) : CompanyInfo::snapshot();
        $original = $inv['original_invoice_id'] ? $db->fetch('SELECT invoice_number, issue_date FROM invoices WHERE id = ?', [(int) $inv['original_invoice_id']]) : null;
        $order = $inv['order_id'] ? $db->fetch('SELECT order_number, placed_at FROM orders WHERE id = ?', [(int) $inv['order_id']]) : null;
        $vat = $inv['order_id'] && $inv['invoice_type'] === 'invoice'
            ? [rtrim(rtrim(number_format((float) ($items[0]['tax_rate'] ?? 0), 2, '.', ''), '0'), '.') => ['base' => \App\Helpers\Money::add(\App\Helpers\Money::sub((string) $inv['subtotal_ht'], (string) $inv['discount_total']), (string) $inv['shipping_ht']), 'tax' => (string) $inv['tax_total']]]
            : DocumentLines::vatBreakdown($items, 'total_ht', 'total_tax');
        $pdf = self::render('pdf/document', [
            'kind' => $inv['invoice_type'], 'doc' => $inv, 'items' => array_map(fn ($i) => [
                'description' => $i['description'], 'specifications' => $i['specifications'], 'quantity' => $i['quantity'], 'unit' => $i['unit_price_ht'],
                'discount' => $i['discount_amount'], 'rate' => $i['tax_rate'], 'ht' => $i['total_ht'], 'ttc' => $i['total_ttc'],
            ], $items),
            'company' => $company, 'number' => $inv['invoice_number'], 'date' => $inv['issue_date'], 'due' => $inv['due_date'],
            'totals' => ['subtotal' => $inv['subtotal_ht'], 'discount' => $inv['discount_total'], 'shipping' => $inv['shipping_ht'], 'tax' => $inv['tax_total'], 'total' => $inv['total_ttc']],
            'vat' => $vat, 'original' => $original, 'order' => $order, 'draft' => $inv['status'] === 'draft',
            // the archived document is immutable: payment status lives in the application, not in the PDF
            'paid' => null, 'terms' => $inv['payment_terms'], 'notes' => $inv['notes'],
            'cancelled' => $inv['status'] === 'cancelled',
        ], (string) $inv['locale']);
        if ($inv['status'] !== 'draft' && $inv['invoice_number'] && $inv['status'] !== 'cancelled') {
            $rel = substr((string) $inv['issue_date'], 0, 4) . '/' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $inv['invoice_number']) . '.pdf';
            $dir = dirname(storage_path('invoices/' . $rel));
            if (is_dir($dir) || mkdir($dir, 0750, true)) {
                file_put_contents(storage_path('invoices/' . $rel), $pdf);
                $db->update('invoices', ['pdf_path' => $rel], ['id' => (int) $inv['id']]);
            }
        }
        return $pdf;
    }

    public static function quotation(array $q): string
    {
        $items = QuotationService::items((int) $q['id']);
        return self::render('pdf/document', [
            'kind' => 'quotation', 'doc' => $q, 'items' => array_map(fn ($i) => [
                'description' => $i['description'], 'specifications' => $i['material_name'], 'quantity' => $i['quantity'], 'unit' => $i['unit_price'],
                'discount' => $i['discount_amount'], 'rate' => $i['tax_rate'], 'ht' => $i['line_total_ht'], 'ttc' => $i['line_total_ttc'],
            ], $items),
            'company' => CompanyInfo::snapshot(), 'number' => $q['quotation_number'], 'date' => $q['issue_date'], 'due' => $q['valid_until'],
            'totals' => ['subtotal' => \App\Helpers\Money::sub((string) $q['subtotal'], (string) $q['discount_total']), 'discount' => '0.00', 'shipping' => '0.00', 'tax' => $q['tax_total'], 'total' => $q['total']],
            'vat' => DocumentLines::vatBreakdown($items, 'line_total_ht', 'line_tax'), 'original' => null, 'order' => null,
            'draft' => $q['status'] === 'draft', 'paid' => null, 'terms' => $q['terms'] ?: (string) setting('quotation_terms', ''), 'notes' => null, 'cancelled' => $q['status'] === 'cancelled',
        ], (string) $q['locale']);
    }
}
