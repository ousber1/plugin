<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\Auth;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PdfService;
use App\Services\Finance\QuotationService;
use App\Services\SeoService;

/**
 * Customer access to documents: quotation page from the e-mailed link (view, PDF, accept / decline),
 * invoice PDF from the e-mailed link, and invoices of the logged-in customer's own orders.
 */
final class DocumentController extends Controller
{
    private function noindex(Response $r): Response
    {
        return $r->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function quote(string $token): Response
    {
        $q = QuotationService::findByToken($token);
        return $this->noindex($this->view('storefront/quote', [
            'q' => $q, 'token' => $token, 'lines' => $q ? QuotationService::items((int) $q['id']) : [],
            'open' => $q !== null && $q['status'] === 'sent' && $q['valid_until'] >= date('Y-m-d'),
            'seo' => SeoService::page(__('finance.your_quote'), null, ['noindex' => true]),
        ], $q === null ? 404 : 200));
    }

    public function quotePdf(string $token): Response
    {
        $q = QuotationService::findByToken($token);
        if ($q === null || $q['status'] === 'cancelled') {
            throw new HttpException(404);
        }
        return $this->noindex(new Response(PdfService::quotation($q), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $q['quotation_number'] . '.pdf"', 'Cache-Control' => 'private, no-store',
        ]));
    }

    public function quoteRespond(string $token): Response
    {
        $decision = $this->request->str('decision');
        if (!in_array($decision, ['accept', 'reject'], true)) {
            throw new HttpException(400);
        }
        $ok = QuotationService::respond($token, $decision === 'accept', $this->request->ip(), mb_substr($this->request->str('comment'), 0, 1000));
        $this->flash($ok ? 'success' : 'danger', $ok ? __($decision === 'accept' ? 'finance.quote_thanks_accept' : 'finance.quote_thanks_reject') : __('finance.quote_closed'));
        return $this->redirect(url('quote/' . $token));
    }

    public function invoiceLink(string $id, string $token): Response
    {
        $inv = InvoiceService::findByToken((int) $id, $token);
        if ($inv === null) {
            throw new HttpException(404);
        }
        return $this->pdfResponse($inv);
    }

    /** Logged-in customer: invoices and credit notes of their own orders. */
    public function customerInvoice(string $id): Response
    {
        $customer = Auth::customer();
        $inv = InvoiceService::find((int) $id);
        if ($customer === null || $inv === null || $inv['status'] === 'draft' || (int) $inv['customer_id'] !== (int) $customer['id']) {
            throw new HttpException(404);
        }
        return $this->pdfResponse($inv);
    }

    /** @param array<string,mixed> $inv */
    private function pdfResponse(array $inv): Response
    {
        return $this->noindex(new Response(PdfService::invoice($inv), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $inv['invoice_number'] . '.pdf"', 'Cache-Control' => 'private, no-store',
        ]));
    }
}
