<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\Mailer;
use App\Services\Notifications\StaffNotifier;

/** Team notifications (bell) and the log of every e-mail the application sent or queued. */
final class NotificationController extends Controller
{
    private function uid(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function index(): Response
    {
        $db = Database::instance();
        $per = 30;
        $page = max(1, $this->request->int('page', 1));
        $total = (int) $db->value("SELECT COUNT(*) FROM notifications WHERE channel = 'internal' AND user_id = ?", [$this->uid()]);
        $rows = $db->fetchAll("SELECT * FROM notifications WHERE channel = 'internal' AND user_id = ? ORDER BY id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), [$this->uid()]);
        return $this->view('admin/notifications/index', ['rows' => new Paginator($rows, $total, $per, $page)]);
    }

    /** Open a notification: mark it read and go to the record it is about. */
    public function open(string $id): Response
    {
        $n = Database::instance()->fetch("SELECT * FROM notifications WHERE id = ? AND channel = 'internal' AND user_id = ?", [(int) $id, $this->uid()]);
        if ($n === null) {
            throw new HttpException(404);
        }
        StaffNotifier::markRead($this->uid(), (int) $n['id']);
        $link = (string) $n['body'];
        // only links inside the administration are followed
        if (!preg_match('#^[a-z0-9/_-]+$#', $link)) {
            return $this->redirectRoute('admin.notifications.index');
        }
        return $this->redirect(admin_url($link));
    }

    public function readAll(): Response
    {
        StaffNotifier::markRead($this->uid());
        return $this->back();
    }

    public function emails(): Response
    {
        $db = Database::instance();
        $status = in_array($this->request->str('status'), ['queued', 'sent', 'failed'], true) ? $this->request->str('status') : '';
        $q = trim($this->request->str('q'));
        $where = ["channel = 'email'"];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(recipient LIKE ? OR subject LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $per = 40;
        $page = max(1, $this->request->int('page', 1));
        $sql = ' FROM notifications WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        return $this->view('admin/notifications/emails', [
            'rows' => new Paginator($db->fetchAll('SELECT id, event, recipient, subject, status, attempts, last_error, related_type, related_id, sent_at, created_at' . $sql . " ORDER BY id DESC LIMIT {$per} OFFSET " . (($page - 1) * $per), $params), $total, $per, $page),
            'status' => $status, 'q' => $q,
            'counts' => array_column($db->fetchAll("SELECT status, COUNT(*) AS n FROM notifications WHERE channel = 'email' GROUP BY status"), 'n', 'status'),
            'driver' => (string) setting('mail_driver', 'mail'),
        ]);
    }

    public function email(string $id): Response
    {
        $n = Database::instance()->fetch("SELECT * FROM notifications WHERE id = ? AND channel = 'email'", [(int) $id]);
        if ($n === null) {
            throw new HttpException(404);
        }
        // shown as text: stored HTML is never rendered in the back office
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/tr|/h\d|/div)[^>]*>#i', "\n", (string) $n['body']) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? '') ?? '';
        return $this->view('admin/notifications/email', ['n' => $n, 'text' => $text]);
    }

    public function retry(string $id): Response
    {
        $db = Database::instance();
        $n = $db->fetch("SELECT * FROM notifications WHERE id = ? AND channel = 'email' AND status IN ('queued','failed')", [(int) $id]);
        if ($n === null) {
            throw new HttpException(404);
        }
        $ok = Mailer::send((string) $n['recipient'], (string) $n['subject'], (string) $n['body']);
        $db->update('notifications', ['status' => $ok ? 'sent' : 'failed', 'attempts' => (int) $n['attempts'] + 1, 'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
            'last_error' => $ok ? null : 'manual retry failed'], ['id' => (int) $n['id']]);
        ActivityLogger::log('notification.retry', 'notification', (int) $n['id'], $ok ? 'sent' : 'failed');
        $this->flash($ok ? 'success' : 'danger', __($ok ? 'notifications.retry_ok' : 'notifications.retry_ko'));
        return $this->redirectRoute('admin.notifications.email', ['id' => $n['id']]);
    }
}
