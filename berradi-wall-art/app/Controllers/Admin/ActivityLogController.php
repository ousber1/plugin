<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;

final class ActivityLogController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $q = $db->table('activity_logs');
        $search = $this->request->str('q');
        if ($search !== '') {
            $q->search(['action', 'description', 'ip'], $search);
        }
        $actor = $this->request->str('actor');
        if (in_array($actor, ['user', 'customer', 'system'], true)) {
            $q->where('actor_type', $actor);
        }
        $logs = $q->orderBy('id', 'DESC')->paginate(30);

        // resolve actor names for the current page only (no N+1 queries)
        $userIds = $customerIds = [];
        foreach ($logs->items as $row) {
            if ($row['actor_id'] !== null) {
                if ($row['actor_type'] === 'user') {
                    $userIds[] = (int) $row['actor_id'];
                } elseif ($row['actor_type'] === 'customer') {
                    $customerIds[] = (int) $row['actor_id'];
                }
            }
        }
        $names = ['user' => [], 'customer' => []];
        foreach ($db->table('users')->select(['id', 'name'])->whereIn('id', array_unique($userIds))->get() as $u) {
            $names['user'][(int) $u['id']] = $u['name'];
        }
        foreach ($db->table('customers')->select(['id', 'first_name', 'last_name'])->whereIn('id', array_unique($customerIds))->get() as $c) {
            $names['customer'][(int) $c['id']] = trim($c['first_name'] . ' ' . $c['last_name']);
        }
        return $this->view('admin/logs/index', compact('logs', 'search', 'actor', 'names'));
    }
}
