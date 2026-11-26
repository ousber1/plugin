<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\ActivityLogger;
use App\Services\Auth;

/**
 * Staff accounts management (RBAC users).
 */
final class UserController extends Controller
{
    public function index(): Response
    {
        $q = Database::instance()->table('users')
            ->select(['users.*', 'roles.name as role_name', 'roles.slug as role_slug'])
            ->join('roles', 'roles.id', '=', 'users.role_id');
        $search = $this->request->str('q');
        if ($search !== '') {
            $q->search(['users.name', 'users.email', 'users.phone'], $search);
        }
        $roleId = $this->request->int('role');
        if ($roleId > 0) {
            $q->where('users.role_id', $roleId);
        }
        $users = $q->orderBy('users.name')->paginate(25);
        return $this->view('admin/users/index', [
            'users' => $users,
            'roles' => $this->roles(),
            'search' => $search,
            'roleId' => $roleId,
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function roles(): array
    {
        $roles = Database::instance()->table('roles')->orderBy('id')->get();
        // only a super admin may assign the super admin role
        if (!Auth::isSuperAdmin()) {
            $roles = array_values(array_filter($roles, fn ($r) => $r['slug'] !== 'super_admin'));
        }
        return $roles;
    }

    public function create(): Response
    {
        return $this->view('admin/users/form', ['user' => null, 'roles' => $this->roles()]);
    }

    public function store(): Response
    {
        $data = $this->validate([
            'name' => 'required|max:150',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|phone',
            'role_id' => 'required|integer|exists:roles,id',
            'locale' => 'required|locale',
            'password' => 'required|password|confirmed|max:128',
        ]);
        $this->guardRole((int) $data['role_id']);
        $id = Database::instance()->insert('users', [
            'name' => $data['name'],
            'email' => mb_strtolower((string) $data['email']),
            'phone' => $data['phone'],
            'role_id' => (int) $data['role_id'],
            'locale' => $data['locale'],
            'password_hash' => Auth::hashPassword((string) $data['password']),
            'is_active' => $this->request->bool('is_active') ? 1 : 0,
        ]);
        ActivityLogger::log('user.created', 'user', $id, (string) $data['email']);
        $this->flash('success', __('common.created'));
        return $this->redirectRoute('admin.users.index');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $user = Database::instance()->fetch('SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
        if ($user === null) {
            throw new HttpException(404);
        }
        if ($user['role_slug'] === 'super_admin' && !Auth::isSuperAdmin()) {
            throw new HttpException(403, __('admin.forbidden'));
        }
        return $user;
    }

    private function guardRole(int $roleId): void
    {
        $slug = Database::instance()->value('SELECT slug FROM roles WHERE id = ?', [$roleId]);
        if ($slug === 'super_admin' && !Auth::isSuperAdmin()) {
            throw new HttpException(403, __('admin.forbidden'));
        }
    }

    public function edit(string $id): Response
    {
        return $this->view('admin/users/form', ['user' => $this->find((int) $id), 'roles' => $this->roles()]);
    }

    public function update(string $id): Response
    {
        $user = $this->find((int) $id);
        $uid = (int) $user['id'];
        $data = $this->validate([
            'name' => 'required|max:150',
            'email' => 'required|email|unique:users,email,' . $uid,
            'phone' => 'nullable|phone',
            'role_id' => 'required|integer|exists:roles,id',
            'locale' => 'required|locale',
            'password' => 'nullable|password|confirmed|max:128',
        ]);
        $this->guardRole((int) $data['role_id']);
        $isSelf = $uid === (int) Auth::user()['id'];
        $active = $this->request->bool('is_active') ? 1 : 0;
        if ($isSelf && ($active === 0 || (int) $data['role_id'] !== (int) $user['role_id'])) {
            throw new ValidationException(['role_id' => __('admin.cannot_demote_self')], $this->request->all());
        }
        $this->assertNotLastSuperAdmin($user, (int) $data['role_id'], $active);

        $update = [
            'name' => $data['name'],
            'email' => mb_strtolower((string) $data['email']),
            'phone' => $data['phone'],
            'role_id' => (int) $data['role_id'],
            'locale' => $data['locale'],
            'is_active' => $active,
        ];
        if (!empty($data['password'])) {
            $update['password_hash'] = Auth::hashPassword((string) $data['password']);
            $update['password_changed_at'] = date('Y-m-d H:i:s');
        }
        Database::instance()->update('users', $update, ['id' => $uid]);
        if ($isSelf && !empty($data['password'])) {
            Auth::touchAdminSession();
        }
        ActivityLogger::log('user.updated', 'user', $uid, (string) $data['email'], ['password_changed' => !empty($data['password'])]);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.users.index');
    }

    public function toggle(string $id): Response
    {
        $user = $this->find((int) $id);
        if ((int) $user['id'] === (int) Auth::user()['id']) {
            $this->flash('danger', __('admin.cannot_demote_self'));
            return $this->redirectRoute('admin.users.index');
        }
        $new = (int) $user['is_active'] === 1 ? 0 : 1;
        try {
            $this->assertNotLastSuperAdmin($user, (int) $user['role_id'], $new);
        } catch (ValidationException $e) {
            $this->flash('danger', (string) (array_values($e->errors)[0] ?? ''));
            return $this->redirectRoute('admin.users.index');
        }
        Database::instance()->update('users', ['is_active' => $new], ['id' => (int) $user['id']]);
        ActivityLogger::log($new ? 'user.activated' : 'user.deactivated', 'user', (int) $user['id']);
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.users.index');
    }

    /** @param array<string,mixed> $user */
    private function assertNotLastSuperAdmin(array $user, int $newRoleId, int $active): void
    {
        if ($user['role_slug'] !== 'super_admin') {
            return;
        }
        $superRole = (int) $user['role_id'];
        if ($newRoleId === $superRole && $active === 1) {
            return;
        }
        $others = (int) Database::instance()->value(
            'SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1 AND id != ?',
            [$superRole, (int) $user['id']]
        );
        if ($others === 0) {
            throw new ValidationException(['role_id' => __('admin.last_super_admin')], $this->request->all());
        }
    }
}
