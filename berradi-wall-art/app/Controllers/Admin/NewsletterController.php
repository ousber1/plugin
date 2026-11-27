<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Reports\ReportService;

/** Newsletter subscribers (consent recorded at sign-up): list, CSV export for the e-mailing tool, unsubscribe. */
final class NewsletterController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $status = in_array($this->request->str('status'), ['subscribed', 'unsubscribed'], true) ? $this->request->str('status') : 'subscribed';
        $q = trim($this->request->str('q'));
        $where = 'status = ?';
        $params = [$status];
        if ($q !== '') {
            $where .= ' AND email LIKE ?';
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        if ($this->request->str('export') === 'csv') {
            $rows = $db->fetchAll("SELECT email, locale, source, subscribed_at FROM newsletter_subscribers WHERE {$where} ORDER BY subscribed_at", $params);
            ActivityLogger::log('newsletter.exported', null, null, (string) count($rows));
            $csv = ReportService::csv(['columns' => [['key' => 'email', 'label' => 'email', 'type' => 'text'], ['key' => 'locale', 'label' => 'langue', 'type' => 'text'],
                ['key' => 'source', 'label' => 'source', 'type' => 'text'], ['key' => 'subscribed_at', 'label' => 'inscription', 'type' => 'text']], 'rows' => $rows]);
            return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="newsletter-' . date('Y-m-d') . '.csv"', 'Cache-Control' => 'private, no-store']);
        }
        $per = 50;
        $page = max(1, $this->request->int('page', 1));
        $total = (int) $db->value("SELECT COUNT(*) FROM newsletter_subscribers WHERE {$where}", $params);
        return $this->view('admin/newsletter/index', [
            'rows' => new Paginator($db->fetchAll("SELECT * FROM newsletter_subscribers WHERE {$where} ORDER BY id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params), $total, $per, $page),
            'status' => $status, 'q' => $q,
            'counts' => array_column($db->fetchAll('SELECT status, COUNT(*) AS n FROM newsletter_subscribers GROUP BY status'), 'n', 'status'),
        ]);
    }

    public function unsubscribe(string $id): Response
    {
        $db = Database::instance();
        $s = $db->fetch("SELECT id, email FROM newsletter_subscribers WHERE id = ? AND status = 'subscribed'", [(int) $id]);
        if ($s === null) {
            throw new HttpException(404);
        }
        $db->update('newsletter_subscribers', ['status' => 'unsubscribed', 'unsubscribed_at' => date('Y-m-d H:i:s')], ['id' => (int) $id]);
        ActivityLogger::log('newsletter.unsubscribed_by_staff', null, (int) $id, (string) $s['email']);
        $this->flash('success', __('common.saved'));
        return $this->back();
    }
}
