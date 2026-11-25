<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\DocumentRenderer;
use App\Services\NotificationService;
use App\Services\QuotationService;
use App\Services\ShareService;

/**
 * Consultation publique d'un document via son lien sécurisé (WhatsApp, e-mail, QR code).
 * Aucune connexion requise ; seul le document du lien est accessible.
 * Le client peut télécharger le PDF et accepter un devis en ligne.
 */
final class PublicDocumentController extends Controller
{
    public function show(string $token): void
    {
        $share = ShareService::resolve($token);
        if (!$share) {
            http_response_code(404);
            $this->view('public/expired', ['title' => 'Lien expiré'], 'public');
            return;
        }
        try {
            $data = DocumentRenderer::data($share['doc_type'], (int) $share['doc_id'], false);
        } catch (\DomainException) {
            http_response_code(404);
            $this->view('public/expired', ['title' => 'Document indisponible'], 'public');
            return;
        }
        $doc = $data['d'];
        $canAccept = $share['doc_type'] === 'quotation' && in_array($doc['status'], ['draft', 'sent', 'pending'], true)
            && (!$doc['valid_until'] || $doc['valid_until'] >= date('Y-m-d'));
        header('X-Robots-Tag: noindex, nofollow');
        $this->view('public/document', ['title' => strip_tags($data['title']) . ' ' . ($data['number'] ?? ''), 'data' => $data, 'token' => $token,
            'type' => $share['doc_type'], 'doc' => $doc, 'canAccept' => $canAccept], 'public');
    }

    /** Rendu HTML du document (affiché dans la page de consultation). */
    public function html(string $token): void
    {
        $share = ShareService::resolve($token, false);
        if (!$share) {
            http_response_code(404);
            exit('Lien invalide ou expiré.');
        }
        header("Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; frame-ancestors 'self'");
        header('X-Robots-Tag: noindex, nofollow');
        echo DocumentRenderer::html($share['doc_type'], (int) $share['doc_id']);
    }

    public function pdf(string $token): void
    {
        $share = ShareService::resolve($token, false);
        if (!$share) {
            http_response_code(404);
            exit('Lien invalide ou expiré.');
        }
        DocumentController::sendPdf(DocumentRenderer::pdf($share['doc_type'], (int) $share['doc_id']), Request::get('download') === '1');
    }

    /** Acceptation d'un devis par le client (nom saisi, horodatage, IP). */
    public function accept(string $token): void
    {
        $share = ShareService::resolve($token, false);
        if (!$share || $share['doc_type'] !== 'quotation') {
            http_response_code(404);
            exit('Lien invalide.');
        }
        $name = mb_substr(Request::string('signer'), 0, 120);
        if (mb_strlen($name) < 3 || !Request::post('agree')) {
            Session::flash('danger', 'Indiquez votre nom et cochez la case d\'acceptation.');
            $this->redirect('d/' . $token);
        }
        $q = $this->db->fetch('SELECT id, number, status, valid_until, customer_id FROM quotations WHERE id = ?', [$share['doc_id']]);
        if (!$q || !in_array($q['status'], ['draft', 'sent', 'pending'], true) || ($q['valid_until'] && $q['valid_until'] < date('Y-m-d'))) {
            Session::flash('danger', 'Ce devis ne peut plus être accepté en ligne (déjà traité ou expiré). Contactez-nous.');
            $this->redirect('d/' . $token);
        }
        QuotationService::setStatus((int) $q['id'], 'accepted', 'Accepté en ligne par « ' . $name . ' » (IP ' . Request::ip() . ')');
        AuditLog::log('accept_online', 'quotations', 'quotation', (int) $q['id'], 'Devis ' . $q['number'] . ' accepté en ligne par ' . $name, null, null, null);
        NotificationService::create('quotation_new', 'Devis ' . $q['number'] . ' accepté en ligne', 'Par ' . $name . '. Lancez la commande ou la facture d\'acompte.', 'quotations/' . $q['id'], 'success');
        Session::flash('success', 'Merci ' . $name . ' ! Votre acceptation du devis ' . $q['number'] . ' a bien été enregistrée. Nous revenons vers vous rapidement.');
        $this->redirect('d/' . $token);
    }
}
