<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\NotificationService;

/**
 * Centre de notifications de l'utilisateur connecté.
 */
final class NotificationController extends Controller
{
    public function index(): void
    {
        $unreadOnly = Request::string('filter') === 'unread';
        $p = Paginator::fromRequest(NotificationService::count($unreadOnly));
        $this->view('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => NotificationService::list($unreadOnly, $p->limit(), $p->offset()),
            'unreadOnly'    => $unreadOnly,
            'unread'        => NotificationService::unreadCount(),
            'p'             => $p,
        ]);
    }

    /** Liste courte pour le menu déroulant de la barre supérieure (AJAX). */
    public function feed(): void
    {
        $items = array_map(static fn(array $n): array => [
            'id'       => (int) $n['id'],
            'title'    => $n['title'],
            'message'  => $n['message'],
            'severity' => $n['severity'],
            'link'     => $n['link'] && preg_match('#^[a-z0-9_\-/?=&]+$#i', $n['link']) ? url($n['link']) : null,
            'date'     => format_date($n['created_at'], true),
            'read'     => (bool) $n['is_read'],
        ], NotificationService::latest(8));
        Response::json(['success' => true, 'unread' => NotificationService::unreadCount(), 'items' => $items]);
    }

    public function read(int $id): void
    {
        $ok = NotificationService::markRead($id);
        if (Request::isAjax()) {
            Response::json(['success' => $ok, 'unread' => NotificationService::unreadCount()]);
        }
        $this->back('notifications');
    }

    public function readAll(): void
    {
        $count = NotificationService::markAllRead();
        if (Request::isAjax()) {
            Response::json(['success' => true, 'unread' => 0]);
        }
        Session::flash('success', $count . ' notification(s) marquée(s) comme lue(s).');
        $this->redirect('notifications');
    }
}
