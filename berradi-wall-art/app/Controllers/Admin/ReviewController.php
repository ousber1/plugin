<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\ActivityLogger;

/** Moderation of customer reviews (only approved reviews are published; featured ones appear on the home page). */
final class ReviewController extends Controller
{
    public function index(): Response
    {
        $status = in_array($this->request->str('status'), ['pending', 'approved', 'all'], true) ? $this->request->str('status') : 'pending';
        $where = match ($status) {
            'approved' => 'WHERE r.is_approved = 1',
            'all' => '',
            default => 'WHERE r.is_approved = 0',
        };
        $db = Database::instance();
        return $this->view('admin/reviews/index', [
            'rows' => $db->fetchAll("SELECT r.*, p.title AS product_title, p.slug, o.order_number FROM reviews r LEFT JOIN products p ON p.id = r.product_id LEFT JOIN orders o ON o.id = r.order_id {$where} ORDER BY r.id DESC LIMIT 300"),
            'status' => $status, 'pending' => (int) $db->value('SELECT COUNT(*) FROM reviews WHERE is_approved = 0'),
        ]);
    }

    public function action(string $id): Response
    {
        $db = Database::instance();
        $r = $db->fetch('SELECT id, product_id FROM reviews WHERE id = ?', [(int) $id]);
        if ($r === null) {
            throw new HttpException(404);
        }
        $a = $this->request->str('action');
        match ($a) {
            'approve' => $db->update('reviews', ['is_approved' => 1], ['id' => (int) $id]),
            'unapprove' => $db->update('reviews', ['is_approved' => 0, 'is_featured' => 0], ['id' => (int) $id]),
            'feature' => $db->update('reviews', ['is_featured' => 1, 'is_approved' => 1], ['id' => (int) $id]),
            'unfeature' => $db->update('reviews', ['is_featured' => 0], ['id' => (int) $id]),
            'delete' => $db->delete('reviews', ['id' => (int) $id]),
            default => throw new HttpException(400),
        };
        ActivityLogger::log('review.' . $a, 'review', (int) $id);
        $this->flash('success', __('common.saved'));
        return $this->back();
    }
}
