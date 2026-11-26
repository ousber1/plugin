<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\Auth;
use App\Services\Builder\CustomerUploadService;
use App\Services\Operations\ArtworkService;
use App\Services\Operations\ProductionException;
use App\Services\UploadException;

/**
 * Artwork review: queue of ordered custom designs, per-line review page, approval, proofs, print files.
 */
final class ArtworkController extends Controller
{
    private function userId(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function index(): Response
    {
        $status = in_array($this->request->str('status'), ['open', 'sent', 'changes_requested', 'approved', 'all'], true) ? $this->request->str('status') : 'open';
        return $this->view('admin/artwork/index', ['rows' => ArtworkService::queue($status), 'status' => $status]);
    }

    /** @return array{item:array<string,mixed>,order:array<string,mixed>} */
    private function item(string $itemId): array
    {
        $db = Database::instance();
        $item = $db->fetch('SELECT * FROM order_items WHERE id = ? AND custom_design_id IS NOT NULL', [(int) $itemId]);
        if ($item === null) {
            throw new HttpException(404);
        }
        return ['item' => $item, 'order' => $db->fetch('SELECT * FROM orders WHERE id = ?', [(int) $item['order_id']])];
    }

    public function show(string $itemId): Response
    {
        ['item' => $item, 'order' => $order] = $this->item($itemId);
        return $this->view('admin/artwork/show', [
            'item' => $item, 'order' => $order, 'images' => ArtworkService::forItem((int) $item['id']),
            'options' => json_decode((string) $item['options'], true) ?: [],
            'minDpi' => (int) setting('builder_min_dpi', 100), 'warnDpi' => (int) setting('builder_warn_dpi', 150),
        ]);
    }

    public function approve(string $itemId): Response
    {
        ['item' => $item] = $this->item($itemId);
        try {
            ArtworkService::approve((int) $item['id'], $this->userId(), mb_substr($this->request->str('notes'), 0, 1000));
            $this->flash('success', __('artwork.approved_flash'));
        } catch (ProductionException|\App\Services\Sales\WorkflowException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirectRoute('admin.artwork.show', ['itemId' => $item['id']]);
    }

    public function changes(string $itemId): Response
    {
        ['item' => $item] = $this->item($itemId);
        $d = $this->validate(['notes' => 'required|max:2000'], ['notes' => __('artwork.notes')]);
        ArtworkService::requestChanges((int) $item['id'], $this->userId(), (string) $d['notes']);
        $this->flash('success', __('artwork.changes_flash'));
        return $this->redirectRoute('admin.artwork.show', ['itemId' => $item['id']]);
    }

    public function proof(string $itemId): Response
    {
        ['item' => $item, 'order' => $order] = $this->item($itemId);
        try {
            $url = ArtworkService::sendProof((int) $item['id'], $this->userId(), mb_substr($this->request->str('message'), 0, 2000));
            // shown once so it can also be sent by WhatsApp; never stored in clear
            $this->flash('success', __('artwork.proof_flash', ['email' => (string) ($order['customer_email'] ?: '—')]));
            \App\Core\Session::flash('proof_url', $url);
        } catch (ProductionException|\App\Services\Sales\WorkflowException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirectRoute('admin.artwork.show', ['itemId' => $item['id']]);
    }

    public function printFile(string $itemId, string $cid): Response
    {
        ['item' => $item] = $this->item($itemId);
        $c = Database::instance()->fetch('SELECT id FROM order_customizations WHERE id = ? AND order_item_id = ?', [(int) $cid, (int) $item['id']]);
        if ($c === null) {
            throw new HttpException(404);
        }
        $file = $this->request->file('print_file');
        try {
            if ($file === null) {
                throw new UploadException(__('validation.upload_failed'));
            }
            ArtworkService::storePrintFile((int) $c['id'], $file);
            $this->flash('success', __('artwork.print_file_saved'));
        } catch (UploadException $e) {
            $this->flash('danger', $e->getMessage());
        }
        return $this->redirectRoute('admin.artwork.show', ['itemId' => $item['id']]);
    }

    public function downloadPrintFile(string $itemId, string $cid): Response
    {
        ['item' => $item, 'order' => $order] = $this->item($itemId);
        $c = Database::instance()->fetch('SELECT * FROM order_customizations WHERE id = ? AND order_item_id = ?', [(int) $cid, (int) $item['id']]);
        if ($c === null) {
            throw new HttpException(404);
        }
        \App\Services\ActivityLogger::log('artwork.print_file_downloaded', 'order_customization', (int) $c['id']);
        return ArtworkService::servePrintFile($c, (string) $order['order_number']);
    }

    /** Original customer photo (download is logged: personal data). */
    public function original(string $itemId, string $cid): Response
    {
        ['item' => $item] = $this->item($itemId);
        $row = Database::instance()->fetch(
            "SELECT u.* FROM order_customizations oc JOIN uploaded_images u ON u.id = oc.uploaded_image_id WHERE oc.id = ? AND oc.order_item_id = ? AND u.status <> 'purged'",
            [(int) $cid, (int) $item['id']]
        );
        if ($row === null) {
            throw new HttpException(404);
        }
        \App\Services\ActivityLogger::log('artwork.original_downloaded', 'order_customization', (int) $cid);
        return (new CustomerUploadService())->serve($row, $this->request->str('v') === 'preview' ? 'preview' : 'original');
    }

    /** Delete the customer's original photo once production no longer needs it (privacy). */
    public function purge(string $itemId, string $cid): Response
    {
        ['item' => $item, 'order' => $order] = $this->item($itemId);
        $row = Database::instance()->fetch(
            "SELECT u.* FROM order_customizations oc JOIN uploaded_images u ON u.id = oc.uploaded_image_id WHERE oc.id = ? AND oc.order_item_id = ? AND u.status <> 'purged'",
            [(int) $cid, (int) $item['id']]
        );
        if ($row === null) {
            throw new HttpException(404);
        }
        if (!in_array($order['status'], ['shipped', 'delivered', 'cancelled', 'returned'], true) && !$this->request->bool('confirm_early')) {
            $this->flash('danger', __('artwork.purge_too_early'));
        } else {
            (new CustomerUploadService())->purge($row, 'staff');
            \App\Services\ActivityLogger::log('artwork.original_purged', 'order_customization', (int) $cid);
            $this->flash('success', __('artwork.purged'));
        }
        return $this->redirectRoute('admin.artwork.show', ['itemId' => $item['id']]);
    }
}
