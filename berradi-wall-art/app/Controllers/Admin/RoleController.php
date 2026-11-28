<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\ActivityLogger;

/**
 * Roles & permission matrix. The super_admin role is immutable (always all permissions).
 */
final class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Database::instance()->fetchAll(
            'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permissions_count
             FROM roles r ORDER BY r.id'
        );
        $totalPermissions = (int) Database::instance()->value('SELECT COUNT(*) FROM permissions');
        return $this->view('admin/roles/index', compact('roles', 'totalPermissions'));
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function groupedPermissions(): array
    {
        $grouped = [];
        foreach (Database::instance()->table('permissions')->orderBy('module')->orderBy('id')->get() as $p) {
            $grouped[(string) $p['module']][] = $p;
        }
        return $grouped;
    }

    public function create(): Response
    {
        return $this->view('admin/roles/form', ['role' => null, 'grouped' => $this->groupedPermissions(), 'granted' => []]);
    }

    public function store(): Response
    {
        $data = $this->validate([
            'name' => 'required|max:120',
            'slug' => 'required|slug|max:64|unique:roles,slug',
            'description' => 'nullable|max:255',
        ]);
        $db = Database::instance();
        $id = $db->transaction(function (Database $db) use ($data): int {
            $id = $db->insert('roles', [
                'slug' => str_replace('-', '_', (string) $data['slug']),
                'name' => $data['name'],
                'description' => $data['description'],
                'is_system' => 0,
            ]);
            $this->syncPermissions($db, $id);
            return $id;
        });
        ActivityLogger::log('role.created', 'role', $id, (string) $data['name']);
        $this->flash('success', __('common.created'));
        return $this->redirectRoute('admin.roles.index');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $role = Database::instance()->fetch('SELECT * FROM roles WHERE id = ?', [$id]);
        if ($role === null) {
            throw new HttpException(404);
        }
        return $role;
    }

    public function edit(string $id): Response
    {
        $role = $this->find((int) $id);
        $granted = array_map('intval', Database::instance()->table('role_permissions')->where('role_id', (int) $role['id'])->pluck('permission_id'));
        return $this->view('admin/roles/form', ['role' => $role, 'grouped' => $this->groupedPermissions(), 'granted' => $granted]);
    }

    public function update(string $id): Response
    {
        $role = $this->find((int) $id);
        if ($role['slug'] === 'super_admin') {
            $this->flash('warning', __('admin.super_admin_locked'));
            return $this->redirectRoute('admin.roles.index');
        }
        $data = $this->validate([
            'name' => 'required|max:120',
            'description' => 'nullable|max:255',
        ]);
        Database::instance()->transaction(function (Database $db) use ($role, $data): void {
            $db->update('roles', ['name' => $data['name'], 'description' => $data['description']], ['id' => (int) $role['id']]);
            $this->syncPermissions($db, (int) $role['id']);
        });
        ActivityLogger::log('role.updated', 'role', (int) $role['id'], (string) $data['name']);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.roles.index');
    }

    private function syncPermissions(Database $db, int $roleId): void
    {
        $ids = $this->request->input('permissions', []);
        $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
        $valid = array_map('intval', $db->table('permissions')->whereIn('id', $ids === [] ? [0] : $ids)->pluck('id'));
        $db->delete('role_permissions', ['role_id' => $roleId]);
        foreach ($valid as $pid) {
            $db->query('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $pid]);
        }
    }

    public function destroy(string $id): Response
    {
        $role = $this->find((int) $id);
        if ((int) $role['is_system'] === 1) {
            $this->flash('danger', __('admin.system_role'));
            return $this->redirectRoute('admin.roles.index');
        }
        $users = (int) Database::instance()->value('SELECT COUNT(*) FROM users WHERE role_id = ?', [(int) $role['id']]);
        if ($users > 0) {
            $this->flash('danger', __('admin.role_has_users'));
            return $this->redirectRoute('admin.roles.index');
        }
        Database::instance()->delete('roles', ['id' => (int) $role['id']]);
        ActivityLogger::log('role.deleted', 'role', (int) $role['id'], (string) $role['name']);
        $this->flash('success', __('common.deleted'));
        return $this->redirectRoute('admin.roles.index');
    }
}
