<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;
use App\Services\CustomerService;
use App\Services\DocumentRenderer;
use App\Services\ShareService;

/**
 * Actions communes aux documents : PDF, aperçu, envoi par e-mail (PDF joint),
 * partage WhatsApp (lien sécurisé), révocation des liens.
 */
final class DocumentController extends Controller
{
    /** Type de document => [permission de consultation, permission d'envoi, route de la fiche, libellé]. */
    public const TYPES = [
        'quotation'   => ['quotations.view', 'quotations.send', 'quotations', 'Devis'],
        'invoice'     => ['invoices.view', 'invoices.view', 'invoices', 'Facture'],
        'credit_note' => ['invoices.view', 'invoices.view', 'credit-notes', 'Avoir'],
        'payment'     => ['payments.view', 'payments.view', 'payments', 'Reçu de paiement'],
        'sales_order' => ['quotations.view', 'quotations.send', 'sales-orders', 'Bon de commande'],
    ];

    /** Recherche catalogue (produits et packs, prix du client) pour les formulaires de documents. */
    public function catalog(): void
    {
        if (!can('quotations.create') && !can('quotations.edit') && !can('invoices.create') && !can('invoices.edit')) {
            $this->authorize('quotations.create');
        }
        (new PosController())->products();
    }

    /** Prix catalogue des produits pour le client choisi. */
    public function prices(): void
    {
        if (!can('quotations.create') && !can('quotations.edit') && !can('invoices.create') && !can('invoices.edit')) {
            $this->authorize('quotations.create');
        }
        (new PosController())->prices();
    }

    /** Calcul serveur des lignes et totaux (aperçu en direct du formulaire, mêmes règles que l'enregistrement). */
    public function compute(): void
    {
        if (!can('quotations.create') && !can('quotations.edit') && !can('invoices.create') && !can('invoices.edit')) {
            $this->authorize('quotations.create');
        }
        $in = Request::json();
        $data = \App\Services\DocumentService::readInput($in);
        $customerId = $data['customer_id'] ?: (int) Settings::get('walk_in_customer_id', 1);
        if (!$data['items']) {
            $this->json(['success' => true, 'lines' => [], 'totals' => null]);
        }
        try {
            $doc = \App\Services\DocumentService::compute($data['items'], $customerId, $data['discount_type'], $data['discount_value'], $data['fees']);
        } catch (\DomainException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $this->json(['success' => true, 'totals' => $doc['totals'], 'lines' => array_map(static fn($l) => [
            'description' => $l['description'], 'quantity' => $l['quantity'], 'line_subtotal' => $l['line_subtotal'],
            'line_tax' => $l['line_tax'], 'line_total' => $l['line_total'], 'tax_rate' => $l['tax_rate'],
        ], $doc['lines'])]);
    }

    private function guard(string $type, bool $send = false): array
    {
        $cfg = self::TYPES[$type] ?? null;
        if (!$cfg) {
            http_response_code(404);
            $this->view('errors/404');
            exit;
        }
        $this->authorize($send ? $cfg[1] : $cfg[0]);
        return $cfg;
    }

    public function pdf(string $type, int $id): void
    {
        $this->guard($type);
        try {
            $pdf = DocumentRenderer::pdf($type, $id);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), self::TYPES[$type][2]);
        }
        self::sendPdf($pdf, Request::get('download') === '1');
    }

    public static function sendPdf(array $pdf, bool $download): never
    {
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf['content']));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $pdf['filename'] . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $pdf['content'];
        exit;
    }

    /** Aperçu HTML (affiché dans la fiche du document, imprimable). */
    public function preview(string $type, int $id): void
    {
        $this->guard($type);
        header("Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; frame-ancestors 'self'");
        try {
            echo DocumentRenderer::html($type, $id);
        } catch (\DomainException $e) {
            echo e($e->getMessage());
        }
    }

    /** Envoi par e-mail avec le PDF en pièce jointe et le lien de consultation. */
    public function email(string $type, int $id): void
    {
        $cfg = $this->guard($type, true);
        $back = $cfg[2] . '/' . $id;
        $this->assertSendable($type, $id, $back);
        $to = mb_strtolower(Request::string('to'));
        $subject = mb_substr(Request::string('subject'), 0, 200);
        $message = mb_substr(Request::string('message'), 0, 5000);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '') {
            $this->fail('Adresse e-mail du destinataire et objet obligatoires.', $back);
        }
        try {
            $pdf = DocumentRenderer::pdf($type, $id);
            $link = ShareService::url($type, $id);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), $back);
        }
        $html = Mailer::template($subject, nl2br(e($message))
            . '<p style="margin:22px 0"><a href="' . e($link) . '" style="background:#0b0b0c;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;font-weight:bold">Consulter le document en ligne</a></p>'
            . '<p style="color:#71717a;font-size:13px">Le document est joint au format PDF.</p>');
        $mailer = new Mailer();
        $ok = $mailer->send($to, $subject, $html, [['name' => $pdf['filename'], 'content' => $pdf['content'], 'mime' => 'application/pdf']],
            (string) Settings::get('business_email', '') ?: null);
        $this->db->insert('document_emails', ['doc_type' => $type, 'doc_id' => $id, 'channel' => 'email', 'recipient' => $to, 'subject' => $subject,
            'status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : mb_substr($mailer->lastError(), 0, 500), 'user_id' => auth_user()['id']]);
        if (!$ok) {
            $this->fail('Échec de l\'envoi : ' . rtrim($mailer->lastError(), '.') . '. Vous pouvez télécharger le PDF et l\'envoyer vous-même.', $back);
        }
        $this->afterSend($type, $id, 'email', $to);
        Session::flash('success', 'Document envoyé à ' . $to . '.');
        $this->redirect($back);
    }

    /** Partage WhatsApp : message pré-rempli avec le lien sécurisé du document. */
    public function whatsapp(string $type, int $id): void
    {
        $cfg = $this->guard($type, true);
        $this->assertSendable($type, $id, $cfg[2] . '/' . $id);
        $phone = Request::string('phone');
        $message = mb_substr(Request::string('message'), 0, 2000);
        if (!preg_match('/^\+?[0-9 ().\-]{6,20}$/', $phone)) {
            $this->fail('Numéro WhatsApp invalide.', $cfg[2] . '/' . $id);
        }
        try {
            DocumentRenderer::data($type, $id, false); // le document existe et peut être partagé
            $message = rtrim($message) . "\n\n" . ShareService::url($type, $id);
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), $cfg[2] . '/' . $id);
        }
        $this->db->insert('document_emails', ['doc_type' => $type, 'doc_id' => $id, 'channel' => 'whatsapp', 'recipient' => $phone,
            'subject' => mb_substr($message, 0, 255), 'status' => 'sent', 'user_id' => auth_user()['id']]);
        $this->afterSend($type, $id, 'whatsapp', $phone);
        header('Location: ' . whatsapp_link($phone, $message), true, 302);
        exit;
    }

    /** Une facture brouillon n'a pas de numéro : elle doit être validée avant d'être envoyée. */
    private function assertSendable(string $type, int $id, string $back): void
    {
        if ($type === 'invoice' && $this->db->fetchColumn('SELECT status FROM invoices WHERE id = ?', [$id]) === 'draft') {
            $this->fail('Validez la facture avant de l\'envoyer au client (une facture brouillon n\'a pas de numéro).', $back);
        }
    }

    private function afterSend(string $type, int $id, string $channel, string $to): void
    {
        if ($type === 'quotation') {
            $status = $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$id]);
            if ($status === 'draft') {
                \App\Services\QuotationService::setStatus($id, 'sent', 'Envoyé par ' . ($channel === 'email' ? 'e-mail' : 'WhatsApp') . ' à ' . $to);
            } else {
                \App\Services\QuotationService::history($id, 'send', $status, $status, 'Renvoyé par ' . ($channel === 'email' ? 'e-mail' : 'WhatsApp') . ' à ' . $to);
            }
        }
        if ($type === 'invoice' && Request::post('reminder')) {
            $inv = $this->db->fetch('SELECT customer_id, number FROM invoices WHERE id = ?', [$id]);
            $this->db->insert('payment_reminders', ['invoice_id' => $id, 'customer_id' => $inv['customer_id'], 'channel' => $channel,
                'message' => mb_substr(Request::string('message'), 0, 5000), 'user_id' => auth_user()['id']]);
            $this->db->update('invoices', ['last_reminder_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        }
        AuditLog::log('send_' . $channel, 'documents', $type, $id, self::TYPES[$type][3] . ' envoyé(e) par ' . $channel . ' à ' . $to);
    }

    public function revoke(string $type, int $id): void
    {
        $cfg = $this->guard($type, true);
        $n = ShareService::revoke($type, $id);
        AuditLog::log('revoke', 'documents', $type, $id, 'Liens de partage révoqués : ' . $n);
        Session::flash('success', $n . ' lien(s) de partage désactivé(s). Un nouveau lien sera créé au prochain envoi.');
        $this->redirect($cfg[2] . '/' . $id);
    }

    /**
     * Données du formulaire d'envoi (destinataires et messages pré-remplis).
     * @return array{email: string, phone: string, subject: string, message: string, wa: string, history: array}
     */
    public static function sendDefaults(string $type, array $doc, bool $reminder = false): array
    {
        $db = \App\Core\Database::instance();
        $c = $db->fetch('SELECT * FROM customers WHERE id = ?', [$doc['customer_id']]) ?: [];
        $name = $c ? CustomerService::displayName($c) : '';
        $business = (string) Settings::get('business_name', 'HO CREATIVE');
        $label = self::TYPES[$type][3];
        $number = $doc['number'] ?? '';
        $amount = isset($doc['total']) ? money($doc['total']) : (isset($doc['amount']) ? money($doc['amount']) : '');
        $intro = 'Bonjour ' . ($c['full_name'] ?? '') . ',';
        $body = match (true) {
            $reminder => "Sauf erreur de notre part, la facture {$number} d'un montant de " . money($doc['balance_due'] ?? 0) . ' reste impayée à ce jour (échéance du ' . format_date($doc['due_date'] ?? null) . ").\nNous vous remercions de bien vouloir procéder à son règlement.",
            $type === 'quotation' => "Veuillez trouver ci-joint notre devis {$number} d'un montant de {$amount} TTC" . (!empty($doc['valid_until']) ? ', valable jusqu\'au ' . format_date($doc['valid_until']) : '') . ".\nNous restons à votre disposition pour toute question.",
            $type === 'invoice' => "Veuillez trouver ci-joint notre facture {$number} d'un montant de {$amount} TTC.\nMerci pour votre confiance.",
            $type === 'payment' => "Veuillez trouver ci-joint le reçu {$number} de votre règlement de {$amount}.\nMerci.",
            default => "Veuillez trouver ci-joint le document {$label} {$number}.",
        };
        $signature = trim((string) Settings::get('email_signature', 'Cordialement,')) . "\n" . $business;
        return [
            'email' => (string) ($c['email'] ?? ''), 'phone' => (string) (($c['whatsapp'] ?? '') ?: ($c['phone'] ?? '')),
            'subject' => ($reminder ? 'Relance — ' : '') . $label . ' ' . $number . ' — ' . $business,
            'message' => $intro . "\n\n" . $body . "\n\n" . $signature,
            'wa' => $intro . "\n" . $body . "\n",
            'history' => $db->fetchAll('SELECT de.*, u.full_name FROM document_emails de LEFT JOIN users u ON u.id = de.user_id WHERE de.doc_type = ? AND de.doc_id = ? ORDER BY de.id DESC LIMIT 10', [$type, $doc['id']]),
            'customerName' => $name,
        ];
    }
}
