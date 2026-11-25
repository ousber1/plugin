<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;

/**
 * Consultation du journal d'audit (lecture seule, permission logs.view).
 */
final class ActivityLogController extends Controller
{
    public function index(): void
    {
        $filters = [
            'user'   => Request::int('user'),
            'module' => Request::string('module'),
            'action' => Request::string('action'),
            'from'   => Request::string('from'),
            'to'     => Request::string('to'),
            'q'      => Request::string('q'),
        ];
        $where = ['1 = 1'];
        $params = [];
        if ($filters['user'] > 0) {
            $where[] = 'l.user_id = :user';
            $params['user'] = $filters['user'];
        }
        if ($filters['module'] !== '') {
            $where[] = 'l.module = :module';
            $params['module'] = $filters['module'];
        }
        if ($filters['action'] !== '') {
            $where[] = 'l.action = :action';
            $params['action'] = $filters['action'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
            $where[] = 'l.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
            $where[] = 'l.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        if ($filters['q'] !== '') {
            $where[] = '(l.description LIKE :q1 OR l.ip_address LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }
        $whereSql = implode(' AND ', $where);
        $p = Paginator::fromRequest((int) $this->db->fetchColumn("SELECT COUNT(*) FROM activity_logs l WHERE {$whereSql}", $params), 50);
        $logs = $this->db->fetchAll(
            "SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
              WHERE {$whereSql} ORDER BY l.id DESC LIMIT {$p->limit()} OFFSET {$p->offset()}",
            $params
        );
        $this->view('logs/index', [
            'title'   => 'Journal d\'activité',
            'logs'    => $logs,
            'p'       => $p,
            'filters' => $filters,
            'users'   => $this->db->fetchAll('SELECT id, full_name FROM users ORDER BY full_name'),
            'modules' => array_column($this->db->fetchAll('SELECT DISTINCT module FROM activity_logs ORDER BY module'), 'module'),
            'actions' => array_column($this->db->fetchAll('SELECT DISTINCT action FROM activity_logs ORDER BY action'), 'action'),
        ]);
    }
}
