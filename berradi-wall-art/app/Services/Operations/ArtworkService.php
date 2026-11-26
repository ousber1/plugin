<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\Database;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Sales\OrderMailer;
use App\Services\Sales\OrderStatus;
use App\Services\Sales\OrderWorkflow;
use App\Services\UploadException;

/**
 * Customer artwork for ordered custom designs: staff review, proof sent to the customer
 * (single-use link, only its hash stored), approval or change requests, print-ready files.
 */
final class ArtworkService
{
    public const STATUSES = ['pending', 'sent', 'approved', 'changes_requested'];
    private const PRINT_EXT = ['pdf' => 'application/pdf', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    public static function printDir(): string
    {
        return storage_path('print_files');
    }

    /**
     * Customisations of an order line, with the source photo data.
     *
     * @return list<array<string,mixed>>
     */
    public static function forItem(int $orderItemId): array
    {
        return Database::instance()->fetchAll(
            "SELECT oc.*, u.uuid AS image_uuid, u.original_name, u.width_px, u.height_px, u.size_bytes, u.status AS image_status, us.name AS approved_by
             FROM order_customizations oc
             LEFT JOIN uploaded_images u ON u.id = oc.uploaded_image_id
             LEFT JOIN users us ON us.id = oc.approved_by_user_id
             WHERE oc.order_item_id = ? ORDER BY oc.panel_index",
            [$orderItemId]
        );
    }

    /**
     * Artwork queue: one row per order line holding customisations.
     *
     * @return list<array<string,mixed>>
     */
    public static function queue(string $status = 'open'): array
    {
        $having = match ($status) {
            'approved' => "HAVING SUM(oc.approval_status <> 'approved') = 0",
            'changes_requested' => "HAVING SUM(oc.approval_status = 'changes_requested') > 0",
            'sent' => "HAVING SUM(oc.approval_status = 'sent') > 0",
            'all' => '',
            default => "HAVING SUM(oc.approval_status <> 'approved') > 0",
        };
        return Database::instance()->fetchAll(
            "SELECT oi.id AS item_id, oi.title, oi.width_cm, oi.height_cm, oi.material_name, oi.frame_name, oi.panels, oi.quantity,
                    o.id AS order_id, o.order_number, o.customer_name, o.status AS order_status, o.placed_at,
                    COUNT(oc.id) AS images, MIN(oc.effective_dpi) AS min_dpi,
                    SUM(oc.approval_status = 'approved') AS approved, SUM(oc.approval_status = 'sent') AS sent,
                    SUM(oc.approval_status = 'changes_requested') AS changes, MAX(oc.updated_at) AS last_change
             FROM order_customizations oc
             JOIN order_items oi ON oi.id = oc.order_item_id
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status NOT IN ('cancelled','returned')
             GROUP BY oi.id {$having}
             ORDER BY o.placed_at ASC
             LIMIT 200"
        );
    }

    /** Staff approval of every image of a line. */
    public static function approve(int $orderItemId, int $userId, string $notes = ''): void
    {
        $db = Database::instance();
        $orderId = (int) $db->value('SELECT order_id FROM order_items WHERE id = ?', [$orderItemId]);
        if ($orderId === 0 || self::forItem($orderItemId) === []) {
            throw new ProductionException(__('artwork.err_none'));
        }
        $db->query(
            "UPDATE order_customizations SET approval_status = 'approved', approved_at = NOW(), approved_by_user_id = ?, approval_token = NULL,
                    notes = CASE WHEN ? = '' THEN notes ELSE CONCAT(COALESCE(CONCAT(notes, '\n'), ''), ?) END
             WHERE order_item_id = ? AND approval_status <> 'approved'",
            [$userId, $notes, $notes, $orderItemId]
        );
        ActivityLogger::log('artwork.approved', 'order_item', $orderItemId);
        self::afterApproval($orderId, $orderItemId, $userId);
    }

    public static function requestChanges(int $orderItemId, int $userId, string $notes): void
    {
        $db = Database::instance();
        $db->query(
            "UPDATE order_customizations SET approval_status = 'changes_requested', approval_token = NULL, notes = CONCAT(COALESCE(CONCAT(notes, '\n'), ''), ?)
             WHERE order_item_id = ?",
            [mb_substr($notes, 0, 2000), $orderItemId]
        );
        ActivityLogger::log('artwork.changes_requested', 'order_item', $orderItemId, $notes);
    }

    /** E-mail a proof link to the customer. Returns the raw link (shown once to staff, e.g. for WhatsApp). */
    public static function sendProof(int $orderItemId, int $userId, string $message = ''): string
    {
        $db = Database::instance();
        $order = $db->fetch('SELECT o.* FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.id = ?', [$orderItemId]);
        if ($order === null || self::forItem($orderItemId) === []) {
            throw new ProductionException(__('artwork.err_none'));
        }
        if (in_array($order['status'], [OrderStatus::CANCELLED, OrderStatus::RETURNED], true)) {
            throw new ProductionException(__('artwork.err_order_closed'));
        }
        $raw = bin2hex(random_bytes(32));
        $db->query(
            "UPDATE order_customizations SET approval_status = 'sent', approval_token = ?, customer_feedback = NULL WHERE order_item_id = ? AND approval_status <> 'approved'",
            [hash('sha256', $raw), $orderItemId]
        );
        if ($order['status'] === OrderStatus::CONFIRMED) {
            OrderWorkflow::change((int) $order['id'], OrderStatus::AWAITING_ARTWORK_APPROVAL, ['comment' => __('artwork.proof_sent_history'), 'user_id' => $userId]);
        }
        $url = absolute_url('artwork/' . $raw);
        OrderMailer::artworkProof($order, $url, $message);
        ActivityLogger::log('artwork.proof_sent', 'order_item', $orderItemId);
        return $url;
    }

    /**
     * The line a proof link belongs to (only while the proof is awaiting an answer).
     *
     * @return array{item:array<string,mixed>,order:array<string,mixed>,images:list<array<string,mixed>>}|null
     */
    public static function findByToken(string $raw): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return null;
        }
        $db = Database::instance();
        $itemId = $db->value("SELECT order_item_id FROM order_customizations WHERE approval_token = ? AND approval_status = 'sent' LIMIT 1", [hash('sha256', $raw)]);
        if ($itemId === null) {
            return null;
        }
        $item = $db->fetch('SELECT * FROM order_items WHERE id = ?', [(int) $itemId]);
        $order = $db->fetch('SELECT * FROM orders WHERE id = ?', [(int) $item['order_id']]);
        return ['item' => $item, 'order' => $order, 'images' => self::forItem((int) $itemId)];
    }

    /** Customer answer on the proof page. */
    public static function customerRespond(string $raw, bool $approve, string $feedback = ''): bool
    {
        $found = self::findByToken($raw);
        if ($found === null) {
            return false;
        }
        $db = Database::instance();
        $itemId = (int) $found['item']['id'];
        $feedback = mb_substr(trim($feedback), 0, 2000);
        $db->query(
            "UPDATE order_customizations SET approval_status = ?, approval_token = NULL, customer_feedback = ?, approved_at = ?
             WHERE order_item_id = ? AND approval_status = 'sent'",
            [$approve ? 'approved' : 'changes_requested', $feedback !== '' ? $feedback : null, $approve ? date('Y-m-d H:i:s') : null, $itemId]
        );
        ActivityLogger::log($approve ? 'artwork.customer_approved' : 'artwork.customer_changes', 'order_item', $itemId, $feedback);
        OrderMailer::internal($found['order'], $approve ? 'artwork_customer_approved' : 'artwork_customer_changes',
            ($approve ? __('artwork.mail_approved', ['title' => $found['item']['title']]) : __('artwork.mail_changes', ['title' => $found['item']['title']]))
            . ($feedback !== '' ? "\n\n« " . $feedback . ' »' : ''));
        if ($approve) {
            self::afterApproval((int) $found['order']['id'], $itemId, null);
        }
        return true;
    }

    /** When every image of the order is approved: close the artwork stages and release the order to production. */
    private static function afterApproval(int $orderId, int $orderItemId, ?int $userId): void
    {
        $db = Database::instance();
        foreach ($db->fetchAll("SELECT id FROM production_jobs WHERE order_item_id = ? AND status <> 'cancelled'", [$orderItemId]) as $j) {
            $db->query("UPDATE production_tasks SET status = 'done', completed_at = NOW() WHERE production_job_id = ? AND stage IN ('review_quality','approve_artwork') AND status IN ('pending','in_progress')", [(int) $j['id']]);
            ProductionService::refreshJob($db, (int) $j['id']);
        }
        $pending = (int) $db->value(
            "SELECT COUNT(*) FROM order_customizations oc JOIN order_items oi ON oi.id = oc.order_item_id WHERE oi.order_id = ? AND oc.approval_status <> 'approved'",
            [$orderId]
        );
        $status = (string) $db->value('SELECT status FROM orders WHERE id = ?', [$orderId]);
        if ($pending === 0 && in_array($status, [OrderStatus::AWAITING_ARTWORK_APPROVAL, OrderStatus::CONFIRMED], true)) {
            OrderWorkflow::change($orderId, OrderStatus::APPROVED_FOR_PRODUCTION, ['comment' => __('artwork.all_approved_history'), 'user_id' => $userId]);
        }
    }

    /**
     * Store a print-ready file prepared by the studio (private, never public).
     *
     * @param array{name:string,tmp_name:string,error:int,size:int} $file
     */
    public static function storePrintFile(int $customizationId, array $file, bool $trustedTmp = false): string
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException(__('validation.upload_failed'));
        }
        $max = (int) setting('print_file_max_mb', 200) * 1048576;
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $max) {
            throw new UploadException(__('validation.upload_too_large', ['max' => (int) ($max / 1048576)]));
        }
        if (!$trustedTmp && !is_uploaded_file($file['tmp_name'])) {
            throw new UploadException(__('validation.upload_failed'));
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::PRINT_EXT[$ext]) || self::PRINT_EXT[$ext] !== $mime) {
            throw new UploadException(__('artwork.err_print_type'));
        }
        $db = Database::instance();
        $row = $db->fetch('SELECT id, print_file_path FROM order_customizations WHERE id = ?', [$customizationId]);
        if ($row === null) {
            throw new UploadException(__('artwork.err_none'));
        }
        $dir = self::printDir() . '/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new UploadException(__('validation.upload_failed'));
        }
        $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : ($ext === 'tiff' ? 'tif' : $ext));
        $ok = $trustedTmp ? copy($file['tmp_name'], self::printDir() . '/' . $rel) : move_uploaded_file($file['tmp_name'], self::printDir() . '/' . $rel);
        if (!$ok) {
            throw new UploadException(__('validation.upload_failed'));
        }
        if ($row['print_file_path'] && !str_contains((string) $row['print_file_path'], '..') && is_file(self::printDir() . '/' . $row['print_file_path'])) {
            unlink(self::printDir() . '/' . $row['print_file_path']);
        }
        $db->update('order_customizations', ['print_file_path' => $rel], ['id' => $customizationId]);
        ActivityLogger::log('artwork.print_file', 'order_customization', $customizationId);
        return $rel;
    }

    /** Stream a stored print file. */
    public static function servePrintFile(array $c, string $orderNumber): Response
    {
        $rel = (string) ($c['print_file_path'] ?? '');
        $path = self::printDir() . '/' . $rel;
        if ($rel === '' || str_contains($rel, '..') || !is_file($path)) {
            throw new \App\Core\HttpException(404);
        }
        $ext = pathinfo($rel, PATHINFO_EXTENSION);
        return Response::file($path, self::PRINT_EXT[$ext] ?? 'application/octet-stream', $orderNumber . '-panel' . ((int) $c['panel_index'] + 1) . '-print.' . $ext, false);
    }

    /**
     * Mock-up of an ordered custom line; falls back to the customer's photo preview when the browser
     * could not produce a mock-up (both live in the private previews directory).
     */
    public static function previewPathFor(array $item): ?string
    {
        if (!empty($item['preview_path']) && is_file((string) config('uploads.preview_dir') . '/' . $item['preview_path'])) {
            return (string) $item['preview_path'];
        }
        $rel = Database::instance()->value(
            "SELECT u.preview_path FROM order_customizations oc JOIN uploaded_images u ON u.id = oc.uploaded_image_id
             WHERE oc.order_item_id = ? AND u.status <> 'purged' AND u.preview_path IS NOT NULL ORDER BY oc.panel_index LIMIT 1",
            [(int) $item['id']]
        );
        return $rel !== null ? (string) $rel : null;
    }

    /** Stream the mock-up preview of an ordered line (stored with the design, private directory). */
    public static function servePreview(string $rel): Response
    {
        $base = (string) config('uploads.preview_dir');
        $path = $base . '/' . $rel;
        if ($rel === '' || str_contains($rel, '..') || !is_file($path)) {
            throw new \App\Core\HttpException(404);
        }
        return Response::file($path, 'image/jpeg');
    }
}
