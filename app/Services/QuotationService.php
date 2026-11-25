<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DocumentNumber;
use App\Core\Settings;

/**
 * Devis : création, modification, statuts (historisés), duplication, expiration,
 * conversions (facture, acompte, bon de commande, vente au POS).
 */
final class QuotationService
{
    /** Transitions de statut autorisées manuellement. */
    private const TRANSITIONS = [
        'draft'    => ['sent', 'pending', 'accepted', 'refused'],
        'sent'     => ['pending', 'accepted', 'refused', 'draft'],
        'pending'  => ['sent', 'accepted', 'refused'],
        'accepted' => ['pending', 'refused'],
        'refused'  => ['pending', 'draft'],
        'expired'  => ['pending', 'draft'],
        'converted' => [],
    ];

    public static function save(?int $id, array $input): int
    {
        $db = Database::instance();
        $data = DocumentService::readInput($input);
        $customer = DocumentService::requireCustomer($data['customer_id']);
        $before = null;
        if ($id) {
            $before = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$id]);
            if (!$before) {
                throw new \DomainException('Devis introuvable.');
            }
            if (in_array($before['status'], ['accepted', 'converted'], true)) {
                throw new \DomainException('Un devis accepté ou converti n\'est plus modifiable : dupliquez-le pour établir une nouvelle offre.');
            }
        }
        $doc = DocumentService::compute($data['items'], (int) $customer['id'], $data['discount_type'], $data['discount_value'], $data['fees']);
        $t = $doc['totals'];
        $valid = $data['valid_until'] ?? date('Y-m-d', strtotime($data['issue_date'] . ' +' . (int) Settings::get('quotation_validity_days', 30) . ' days'));
        if ($valid < $data['issue_date']) {
            throw new \DomainException('La date de validité précède la date du devis.');
        }
        $row = [
            'customer_id' => (int) $customer['id'], 'title' => $data['title'], 'issue_date' => $data['issue_date'], 'valid_until' => $valid,
            'delivery_date' => $data['delivery_date'], 'production_days' => $data['production_days'],
            'subtotal' => $t['subtotal'], 'discount_type' => $data['discount_type'], 'discount_value' => $data['discount_type'] === 'none' ? 0 : $data['discount_value'],
            'discount_total' => $t['discount_total'], 'design_fees' => $t['fees']['design_fees'], 'installation_fees' => $t['fees']['installation_fees'],
            'transport_fees' => $t['fees']['transport_fees'], 'tax_total' => $t['tax_total'], 'total' => $t['total'], 'cost_total' => $t['cost_total'],
            'payment_terms' => $data['payment_terms'], 'notes' => $data['notes'], 'internal_notes' => $data['internal_notes'],
            'customer_snapshot' => json_encode(DocumentService::customerSnapshot((int) $customer['id']), JSON_UNESCAPED_UNICODE),
        ];
        return $db->transaction(function (Database $db) use ($id, $row, $doc, $before): int {
            if ($id) {
                if ($before['status'] === 'expired' && $row['valid_until'] >= date('Y-m-d')) {
                    $row['status'] = 'pending';
                }
                $db->update('quotations', $row, 'id = :id', ['id' => $id]);
                self::history($id, 'update', null, null, 'Devis modifié — total ' . money($row['total']));
            } else {
                $row += ['number' => DocumentNumber::next('quotation', new \DateTimeImmutable($row['issue_date'])), 'user_id' => Auth::id(), 'status' => 'draft'];
                $id = $db->insert('quotations', $row);
                self::history($id, 'create', null, 'draft', 'Devis créé — total ' . money($row['total']));
                NotificationService::create('quotation_new', 'Nouveau devis ' . $row['number'], money($row['total']) . ' TTC.', 'quotations/' . $id, 'info');
            }
            DocumentService::saveItems('quotation_items', 'quotation_id', $id, $doc['lines']);
            return $id;
        });
    }

    public static function setStatus(int $id, string $status, ?string $note = null): void
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$q) {
            throw new \DomainException('Devis introuvable.');
        }
        if ($q['status'] === $status) {
            return;
        }
        if (!in_array($status, self::TRANSITIONS[$q['status']] ?? [], true)) {
            throw new \DomainException('Changement de statut impossible : « ' . (status_map('quotation')[$q['status']][0] ?? $q['status']) . ' » → « ' . (status_map('quotation')[$status][0] ?? $status) . ' ».');
        }
        if ($status === 'refused' && mb_strlen(trim((string) $note)) < 3) {
            throw new \DomainException('Indiquez le motif du refus (utile pour le suivi commercial).');
        }
        $update = ['status' => $status];
        if ($status === 'sent' && !$q['sent_at']) {
            $update['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'accepted') {
            $update['accepted_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'refused') {
            $update['refused_reason'] = mb_substr((string) $note, 0, 255);
        }
        $db->update('quotations', $update, 'id = :id', ['id' => $id]);
        self::history($id, 'status', $q['status'], $status, $note);
        AuditLog::log('status', 'quotations', 'quotation', $id, 'Devis ' . $q['number'] . ' : ' . $q['status'] . ' → ' . $status . ($note ? ' (' . $note . ')' : ''));
    }

    public static function markConverted(int $id, string $type, int $targetId): void
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT status, number FROM quotations WHERE id = ?', [$id]);
        if (!$q) {
            return;
        }
        $db->update('quotations', ['status' => 'converted', 'converted_type' => $type, 'converted_id' => $targetId,
            'accepted_at' => $db->fetchColumn('SELECT accepted_at FROM quotations WHERE id = ?', [$id]) ?: date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        $labels = ['invoice' => 'facture', 'sales_order' => 'bon de commande', 'sale' => 'vente au comptoir', 'production_order' => 'ordre de production'];
        self::history($id, 'convert', $q['status'], 'converted', 'Converti en ' . ($labels[$type] ?? $type));
    }

    public static function duplicate(int $id): int
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$q) {
            throw new \DomainException('Devis introuvable.');
        }
        $input = InvoiceService::inputFromDoc('quotation_items', 'quotation_id', $q) + [
            'title' => $q['title'], 'production_days' => $q['production_days'], 'internal_notes' => $q['internal_notes'],
        ];
        $newId = self::save(null, $input);
        $db->update('quotations', ['duplicated_from_id' => $id], 'id = :id', ['id' => $newId]);
        self::history($newId, 'duplicate', null, 'draft', 'Copie du devis ' . $q['number']);
        return $newId;
    }

    /** Suppression logique : brouillons, refusés ou expirés non convertis. */
    public static function delete(int $id): void
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$q || !in_array($q['status'], ['draft', 'refused', 'expired'], true)) {
            throw new \DomainException('Seul un devis brouillon, refusé ou expiré peut être supprimé.');
        }
        if ((int) $db->fetchColumn('SELECT COUNT(*) FROM invoices WHERE quotation_id = ?', [$id]) > 0) {
            throw new \DomainException('Des factures sont liées à ce devis.');
        }
        $db->update('quotations', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'quotations', 'quotation', $id, 'Devis ' . $q['number'] . ' supprimé');
    }

    /** Devis envoyés / en attente dont la validité est dépassée → « Expiré » ; alerte avant expiration. */
    public static function refreshExpired(): void
    {
        $db = Database::instance();
        foreach ($db->fetchAll("SELECT id, status, number FROM quotations WHERE deleted_at IS NULL AND status IN ('sent','pending') AND valid_until < CURDATE()") as $q) {
            $db->update('quotations', ['status' => 'expired'], 'id = :id', ['id' => $q['id']]);
            self::history((int) $q['id'], 'expire', $q['status'], 'expired', 'Date de validité dépassée');
        }
        $days = (int) ($db->fetchColumn("SELECT days_before FROM notification_settings WHERE type = 'quotation_expiring'") ?? 3);
        foreach ($db->fetchAll(
            "SELECT q.id, q.number, q.valid_until, q.total, COALESCE(NULLIF(c.company_name, ''), c.full_name) AS customer
               FROM quotations q JOIN customers c ON c.id = q.customer_id
              WHERE q.deleted_at IS NULL AND q.status IN ('sent','pending') AND q.valid_until BETWEEN CURDATE() AND (CURDATE() + INTERVAL ? DAY)",
            [$days]
        ) as $q) {
            NotificationService::create('quotation_expiring', 'Devis ' . $q['number'] . ' expire bientôt', $q['customer'] . ' — ' . money($q['total']) . ', valable jusqu\'au ' . format_date($q['valid_until']) . '. Pensez à relancer.',
                'quotations/' . $q['id'], 'warning', null, null, 'quotation_expiring:' . $q['id']);
        }
    }

    /** Conversion en bon de commande client. */
    public static function toSalesOrder(int $id): int
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$q || in_array($q['status'], ['refused', 'expired', 'converted'], true)) {
            throw new \DomainException('Seul un devis en cours ou accepté peut être converti.');
        }
        return $db->transaction(function (Database $db) use ($q): int {
            $soId = $db->insert('sales_orders', [
                'number' => DocumentNumber::next('sales_order'), 'customer_id' => $q['customer_id'], 'quotation_id' => $q['id'], 'user_id' => Auth::id(),
                'status' => 'confirmed', 'order_date' => date('Y-m-d'),
                'due_date' => $q['delivery_date'] ?: ($q['production_days'] ? date('Y-m-d', strtotime('+' . (int) $q['production_days'] . ' days')) : null),
                'subtotal' => $q['subtotal'], 'discount_total' => $q['discount_total'],
                'fees_total' => round((float) $q['design_fees'] + (float) $q['installation_fees'] + (float) $q['transport_fees'], 2),
                'tax_total' => $q['tax_total'], 'total' => $q['total'], 'notes' => $q['notes'], 'customer_snapshot' => $q['customer_snapshot'],
            ]);
            foreach ($db->fetchAll('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order', [$q['id']]) as $i => $it) {
                $db->insert('sales_order_items', array_intersect_key($it, array_flip(['product_id', 'pack_id', 'description', 'width', 'height', 'dimension_unit',
                    'quantity', 'unit_id', 'unit_price', 'discount_percent', 'tax_rate', 'line_subtotal', 'line_tax', 'line_total', 'unit_cost'])) + ['sales_order_id' => $soId, 'sort_order' => $i]);
            }
            self::markConverted((int) $q['id'], 'sales_order', $soId);
            AuditLog::log('convert', 'quotations', 'sales_order', $soId, 'Bon de commande créé depuis le devis ' . $q['number']);
            return $soId;
        });
    }

    /** Conversion en vente au comptoir : le devis est placé en attente au POS. */
    public static function toPosDraft(int $id): int
    {
        $db = Database::instance();
        $q = $db->fetch('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$q || in_array($q['status'], ['refused', 'converted'], true)) {
            throw new \DomainException('Ce devis ne peut pas être converti en vente.');
        }
        $input = InvoiceService::inputFromDoc('quotation_items', 'quotation_id', $q);
        // Remise reprise en montant : au POS les frais deviennent une ligne, qui ne doit pas être remisée
        if ((float) $q['discount_total'] > 0) {
            $input['discount_type'] = 'amount';
            $input['discount_value'] = (float) $q['discount_total'];
        }
        $fees = array_sum(array_map('floatval', $input['fees']));
        if ($fees > 0) {
            $rate = (float) ($db->fetchColumn('SELECT rate FROM taxes WHERE id = ?', [(int) Settings::get('default_tax_id', 1)]) ?? 0);
            $input['items'][] = ['type' => 'custom', 'description' => 'Frais (design, installation, transport) — devis ' . $q['number'], 'quantity' => 1,
                'unit_price' => $fees, 'discount_percent' => 0, 'tax_rate' => $rate];
        }
        $draftId = SaleService::suspend($input + ['label' => 'Devis ' . $q['number']]);
        $db->update('sales', ['quotation_id' => $id], 'id = :id', ['id' => $draftId]);
        self::history($id, 'pos', $q['status'], $q['status'], 'Chargé au point de vente pour encaissement');
        return $draftId;
    }

    public static function history(int $id, string $action, ?string $from, ?string $to, ?string $note): void
    {
        Database::instance()->insert('quotation_history', [
            'quotation_id' => $id, 'user_id' => Auth::id(), 'action' => $action, 'from_status' => $from, 'to_status' => $to,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null,
        ]);
    }
}
