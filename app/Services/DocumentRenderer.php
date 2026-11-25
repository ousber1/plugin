<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;
use App\Core\View;

/**
 * Rendu des documents commerciaux : données → HTML (aperçu / impression) → PDF.
 * Un seul gabarit (app/Views/documents/document.php) pour l'écran et le PDF,
 * compatible dompdf (tableaux, CSS simple, polices DejaVu, images en data URI).
 */
final class DocumentRenderer
{
    /** Données complètes d'un document prêtes pour le gabarit. */
    public static function data(string $type, int $id, bool $withQr = true): array
    {
        $db = Database::instance();
        $d = match ($type) {
            'quotation'   => $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$id]),
            'invoice'     => $db->fetch('SELECT * FROM invoices WHERE id = ?', [$id]),
            'credit_note' => $db->fetch('SELECT cn.*, i.number AS invoice_number, i.issue_date AS invoice_date FROM credit_notes cn LEFT JOIN invoices i ON i.id = cn.invoice_id WHERE cn.id = ?', [$id]),
            'sales_order' => $db->fetch('SELECT * FROM sales_orders WHERE id = ?', [$id]),
            'payment'     => $db->fetch('SELECT p.*, pm.name AS method FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.id = ?', [$id]),
            default       => null,
        };
        if (!$d) {
            throw new \DomainException('Document introuvable.');
        }
        $snapshot = !empty($d['customer_snapshot']) ? (json_decode((string) $d['customer_snapshot'], true) ?: []) : [];
        $live = $db->fetch('SELECT code, full_name, company_name, phone, email, address, city, ice, if_number, rc, whatsapp FROM customers WHERE id = ?', [$d['customer_id']]) ?: [];
        // Une facture validée affiche les coordonnées figées à la validation
        $customer = ($type === 'invoice' || $type === 'credit_note') && $snapshot ? $snapshot + $live : $live;
        $business = $type === 'invoice' && !empty($d['business_snapshot']) ? (json_decode((string) $d['business_snapshot'], true) ?: DocumentService::businessSnapshot()) : DocumentService::businessSnapshot();

        $templateKey = $type === 'invoice' ? (['deposit' => 'deposit', 'balance' => 'balance'][$d['invoice_type']] ?? 'invoice') : ($type === 'payment' ? 'receipt' : $type);
        $tpl = $db->fetch('SELECT * FROM document_templates WHERE doc_type = ? ORDER BY is_default DESC, id LIMIT 1', [$templateKey]) ?: [];
        $title = $tpl['title'] ?? mb_strtoupper(['quotation' => 'Devis', 'invoice' => 'Facture', 'credit_note' => 'Avoir', 'sales_order' => 'Bon de commande', 'payment' => 'Reçu de paiement'][$type]);
        $isDraft = ($type === 'invoice' && $d['status'] === 'draft');
        $number = $d['number'] ?? null;

        $items = [];
        $table = ['quotation' => ['quotation_items', 'quotation_id'], 'invoice' => ['invoice_items', 'invoice_id'],
            'credit_note' => ['credit_note_items', 'credit_note_id'], 'sales_order' => ['sales_order_items', 'sales_order_id']][$type] ?? null;
        if ($table) {
            $items = $db->fetchAll("SELECT i.*, u.symbol FROM {$table[0]} i LEFT JOIN units u ON u.id = i.unit_id WHERE i.{$table[1]} = ? ORDER BY i.sort_order, i.id", [$id]);
        }

        // Méta-informations (bloc d'en-tête)
        $meta = [];
        $status = null;
        switch ($type) {
            case 'quotation':
                $meta = ['Date' => format_date($d['issue_date']), 'Valable jusqu\'au' => format_date($d['valid_until'])];
                if ($d['delivery_date']) {
                    $meta['Livraison souhaitée'] = format_date($d['delivery_date']);
                }
                if ($d['production_days']) {
                    $meta['Délai de réalisation'] = (int) $d['production_days'] . ' jour(s) ouvré(s)';
                }
                $status = $d['status'];
                break;
            case 'invoice':
                $meta = ['Date' => format_date($d['issue_date']), 'Échéance' => format_date($d['due_date'])];
                if ($d['quotation_id']) {
                    $meta['Devis'] = (string) $db->fetchColumn('SELECT number FROM quotations WHERE id = ?', [$d['quotation_id']]);
                }
                if ($d['sale_id']) {
                    $meta['Ticket'] = (string) $db->fetchColumn('SELECT number FROM sales WHERE id = ?', [$d['sale_id']]);
                }
                $status = $d['status'];
                break;
            case 'credit_note':
                $meta = ['Date' => format_date($d['issue_date']), 'Facture d\'origine' => ($d['invoice_number'] ?? '—') . ($d['invoice_date'] ? ' du ' . format_date($d['invoice_date']) : ''), 'Motif' => $d['reason']];
                break;
            case 'sales_order':
                $meta = ['Date' => format_date($d['order_date']), 'Livraison prévue' => format_date($d['due_date'])];
                if ($d['quotation_id']) {
                    $meta['Devis'] = (string) $db->fetchColumn('SELECT number FROM quotations WHERE id = ?', [$d['quotation_id']]);
                }
                break;
            case 'payment':
                $meta = ['Date' => format_date($d['payment_date'], true), 'Mode de paiement' => $d['method']];
                if ($d['reference']) {
                    $meta['Référence'] = $d['reference'];
                }
                break;
        }

        // Ventilation de TVA (lignes + frais)
        $vat = [];
        foreach ($items as $it) {
            $k = number_format((float) $it['tax_rate'], 3);
            $vat[$k] ??= ['rate' => (float) $it['tax_rate'], 'base' => 0.0, 'tax' => 0.0];
            $vat[$k]['base'] += (float) $it['line_subtotal'];
            $vat[$k]['tax'] += (float) $it['line_tax'];
        }
        $fees = [];
        foreach (DocumentService::FEES as $k => $label) {
            if (isset($d[$k]) && (float) $d[$k] > 0) {
                $fees[$label] = (float) $d[$k];
            }
        }
        if ($fees) {
            $feeTotal = array_sum($fees);
            $feeTax = round((float) $d['tax_total'] - array_sum(array_column($vat, 'tax')), 2);
            $rate = $feeTotal > 0 ? round($feeTax / $feeTotal * 100, 1) : 0;
            $k = number_format($rate, 3);
            $vat[$k] ??= ['rate' => $rate, 'base' => 0.0, 'tax' => 0.0];
            $vat[$k]['base'] += $feeTotal;
            $vat[$k]['tax'] += $feeTax;
        }
        krsort($vat);

        // Paiements (factures) / ventilation (reçus)
        $payments = [];
        $allocations = [];
        if ($type === 'invoice' && !$isDraft) {
            $payments = $db->fetchAll(
                "SELECT p.number, p.payment_date, p.reference, pm.name AS method, pa.amount FROM payment_allocations pa
                   JOIN payments p ON p.id = pa.payment_id JOIN payment_methods pm ON pm.id = p.payment_method_id
                  WHERE pa.invoice_id = ? AND p.status = 'valid' ORDER BY p.payment_date",
                [$id]
            );
            foreach ($db->fetchAll("SELECT c.number, c.issue_date, a.amount FROM credit_note_allocations a JOIN credit_notes c ON c.id = a.credit_note_id WHERE a.invoice_id = ? AND c.status <> 'cancelled'", [$id]) as $c) {
                $payments[] = ['number' => $c['number'], 'payment_date' => $c['issue_date'], 'reference' => null, 'method' => 'Avoir', 'amount' => $c['amount']];
            }
        }
        if ($type === 'payment') {
            $group = $d['payment_group'] ? $db->fetchAll("SELECT p.*, pm.name AS method FROM payments p JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.payment_group = ? AND p.status = 'valid' ORDER BY p.id", [$d['payment_group']]) : [$d];
            $d['group_total'] = array_sum(array_map(static fn($p) => (float) $p['amount'], $group));
            $d['group_numbers'] = array_column($group, 'number');
            foreach ($group as $p) {
                foreach ($db->fetchAll('SELECT i.number, i.issue_date, i.total, i.balance_due, pa.amount FROM payment_allocations pa JOIN invoices i ON i.id = pa.invoice_id WHERE pa.payment_id = ?', [$p['id']]) as $a) {
                    $allocations[] = ['label' => 'Facture ' . $a['number'] . ' du ' . format_date($a['issue_date']), 'total' => $a['total'], 'amount' => $a['amount'], 'rest' => $a['balance_due']];
                }
                if ($p['sale_id']) {
                    $s = $db->fetch('SELECT number, sale_date, total, paid_amount FROM sales WHERE id = ?', [$p['sale_id']]);
                    if ($s) {
                        $allocations[] = ['label' => 'Ticket ' . $s['number'] . ' du ' . format_date($s['sale_date']), 'total' => $s['total'], 'amount' => $p['amount'], 'rest' => max(0, (float) $s['total'] - (float) $s['paid_amount'])];
                    }
                }
                if ((float) $p['unallocated_amount'] > 0) {
                    $allocations[] = ['label' => 'Avance au crédit du compte client', 'total' => null, 'amount' => $p['unallocated_amount'], 'rest' => null];
                }
            }
            if ($d['credit_note_id']) {
                $allocations[] = ['label' => 'Remboursement de l\'avoir ' . $db->fetchColumn('SELECT number FROM credit_notes WHERE id = ?', [$d['credit_note_id']]), 'total' => null, 'amount' => $d['amount'], 'rest' => null];
            }
        }

        // QR code de vérification (documents numérotés uniquement)
        $qr = null;
        $shareUrl = null;
        if ($withQr && $number && !$isDraft && ($tpl['show_qr'] ?? 1) && Settings::get('show_qr_code', true)) {
            $shareUrl = ShareService::url($type, $id);
            $qr = self::qr($shareUrl);
        }
        return [
            'type' => $type, 'd' => $d, 'items' => $items, 'customer' => $customer, 'business' => $business, 'tpl' => $tpl,
            'title' => $title . ($isDraft ? ' (BROUILLON)' : ''), 'number' => $number, 'meta' => $meta, 'status' => $status,
            'vat' => array_values($vat), 'fees' => $fees, 'payments' => $payments, 'allocations' => $allocations,
            'qr' => $qr, 'shareUrl' => $shareUrl, 'isDraft' => $isDraft,
            'images' => [
                'logo'      => ($tpl['show_logo'] ?? 1) ? self::dataUri((string) Settings::get('business_logo', '')) : null,
                'stamp'     => ($tpl['show_stamp'] ?? 1) && Settings::get('show_stamp', true) ? self::dataUri((string) Settings::get('stamp_image', '')) : null,
                'signature' => ($tpl['show_signature'] ?? 1) && Settings::get('show_signature', true) ? self::dataUri((string) Settings::get('signature_image', '')) : null,
            ],
            'showSignature' => ($tpl['show_signature'] ?? 1) && Settings::get('show_signature', true),
            'accent' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($tpl['accent_color'] ?? '')) ? $tpl['accent_color'] : '#111111',
            'filename' => self::filename($type, $d),
        ];
    }

    public static function html(string $type, int $id, bool $forPdf = false): string
    {
        return View::render('documents/document', self::data($type, $id) + ['forPdf' => $forPdf], null);
    }

    /** @return array{filename: string, content: string} */
    public static function pdf(string $type, int $id): array
    {
        require_once BASE_PATH . '/vendor/autoload.php';
        $data = self::data($type, $id);
        $html = View::render('documents/document', $data + ['forPdf' => true], null);
        $cache = BASE_PATH . '/storage/cache/dompdf';
        if (!is_dir($cache)) {
            @mkdir($cache, 0755, true);
        }
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);        // aucune ressource externe : sécurité et rapidité
        $options->set('isPhpEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('fontCache', $cache);
        $options->set('tempDir', $cache);
        $options->set('chroot', [BASE_PATH . '/vendor/dompdf/dompdf']);
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper((string) Settings::get('paper_size', 'A4'), 'portrait');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $canvas->page_text(520, 815, 'Page {PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.45, 0.45, 0.45]);
        return ['filename' => $data['filename'], 'content' => $pdf->output()];
    }

    private static function filename(string $type, array $d): string
    {
        $prefix = ['quotation' => 'Devis', 'invoice' => 'Facture', 'credit_note' => 'Avoir', 'sales_order' => 'Bon-de-commande', 'payment' => 'Recu'][$type];
        $num = $d['number'] ?? ('brouillon-' . $d['id']);
        return preg_replace('/[^A-Za-z0-9_\-]/', '-', $prefix . '-' . $num) . '.pdf';
    }

    /** Image publique (assets/uploads) en data URI, ou null. */
    public static function dataUri(string $relative): ?string
    {
        if ($relative === '' || !preg_match('#^uploads/[a-z0-9_\-]+/[a-f0-9]{24}\.(png|jpg|webp)$#', $relative)) {
            return null;
        }
        $path = BASE_PATH . '/assets/' . $relative;
        if (!is_file($path)) {
            return null;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $content = (string) file_get_contents($path);
        if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            // dompdf ne lit pas le WebP : conversion en PNG
            $img = @imagecreatefromwebp($path);
            if ($img) {
                ob_start();
                imagepng($img);
                $content = (string) ob_get_clean();
                imagedestroy($img);
                $ext = 'png';
            }
        }
        return 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode($content);
    }

    /** QR code PNG (data URI). */
    public static function qr(string $text): ?string
    {
        require_once BASE_PATH . '/vendor/autoload.php';
        try {
            $options = new \chillerlan\QRCode\QROptions(['outputType' => 'png', 'scale' => 4, 'outputBase64' => true, 'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M, 'quietzoneSize' => 1]);
            return (new \chillerlan\QRCode\QRCode($options))->render($text);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('QR code : ' . $e->getMessage());
            return null;
        }
    }
}
