<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Validator;

/**
 * Gestion des utilisateurs : liste, création, modification, activation,
 * déverrouillage, réinitialisation du mot de passe, suppression logique,
 * permissions particulières.
 */
final class UserController extends Controller
{
    public function index(): void
    {
        $q = Request::string('q');
        $role = Request::int('role');
        $status = Request::string('status');

        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.full_name LIKE :q1 OR u.username LIKE :q2 OR u.email LIKE :q3 OR u.phone LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
                $params[$k] = '%' . $q . '%';
            }
        }
        if ($role > 0) {
            $where[] = 'u.role_id = :role';
            $params['role'] = $role;
        }
        if ($status === 'active') {
            $where[] = 'u.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'u.is_active = 0';
        } elseif ($status === 'locked') {
            $where[] = 'u.locked_until > NOW()';
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users u WHERE {$whereSql}", $params);
        $p = Paginator::fromRequest($total);
        $users = $this->db->fetchAll(
            "SELECT u.*, r.name AS role_name, r.code AS role_code
               FROM users u JOIN roles r ON r.id = u.role_id
              WHERE {$whereSql} ORDER BY u.is_active DESC, u.full_name
              LIMIT {$p->limit()} OFFSET {$p->offset()}",
            $params
        );

        $this->view('users/index', [
            'title'  => 'Utilisateurs',
            'users'  => $users,
            'roles'  => $this->roles(),
            'p'      => $p,
            'filters'=> compact('q', 'role', 'status'),
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', [
            'title'      => 'Nouvel utilisateur',
            'user'       => null,
            'roles'      => $this->roles(),
            'overrides'  => [],
            'permissions'=> $this->permissionsByModule(),
            'rolePerms'  => [],
        ]);
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $password = (string) Request::post('password', '');
        $pwdErrors = Auth::passwordErrors($password, (int) Settings::get('password_min_length', 10));
        if ($password !== (string) Request::post('password_confirm', '')) {
            $pwdErrors[] = 'Les mots de passe ne correspondent pas.';
        }
        if ($pwdErrors) {
            $this->fail(implode(' ', $pwdErrors), 'users/create');
        }
        $data['password_hash'] = Auth::hashPassword($password);
        $data['password_changed_at'] = date('Y-m-d H:i:s');

        $id = $this->db->transaction(function () use ($data): int {
            $id = $this->db->insert('users', $data);
            $this->saveOverrides($id);
            return $id;
        });
        unset($data['password_hash']);
        Auth::refreshPermissions();
        AuditLog::log('create', 'users', 'user', $id, 'Utilisateur créé : ' . $data['username'], null, $data);
        Session::flash('success', 'Utilisateur « ' . $data['full_name'] . ' » créé.');
        $this->redirect('users');
    }

    public function edit(int $id): void
    {
        $user = $this->find($id);
        $overrides = [];
        foreach ($this->db->fetchAll('SELECT permission_id, allowed FROM user_permissions WHERE user_id = ?', [$id]) as $o) {
            $overrides[(int) $o['permission_id']] = (int) $o['allowed'];
        }
        $this->view('users/form', [
            'title'      => 'Modifier l\'utilisateur',
            'user'       => $user,
            'roles'      => $this->roles(),
            'overrides'  => $overrides,
            'permissions'=> $this->permissionsByModule(),
            'rolePerms'  => $this->rolePermissionIds((int) $user['role_id']),
        ]);
    }

    public function update(int $id): void
    {
        $before = $this->find($id);
        $data = $this->validated($id);

        // Ne jamais retirer le dernier administrateur actif
        if ($before['role_code'] === 'admin' && ((int) $data['role_id'] !== (int) $before['role_id'] || (int) $data['is_active'] === 0)
            && $this->activeAdminCount() <= 1) {
            $this->fail('Impossible : c\'est le dernier administrateur actif.', 'users/' . $id . '/edit');
        }
        if ($id === Auth::id() && (int) $data['is_active'] === 0) {
            $this->fail('Vous ne pouvez pas désactiver votre propre compte.', 'users/' . $id . '/edit');
        }

        $this->db->transaction(function () use ($id, $data): void {
            $this->db->update('users', $data, 'id = :id', ['id' => $id]);
            $this->saveOverrides($id);
        });
        Auth::refreshPermissions();
        [$old, $new] = AuditLog::diff($before, $data);
        AuditLog::log('update', 'users', 'user', $id, 'Utilisateur modifié : ' . $data['username'], $old, $new);
        Session::flash('success', 'Utilisateur mis à jour.');
        $this->redirect('users');
    }

    public function toggle(int $id): void
    {
        $user = $this->find($id);
        $activate = (int) $user['is_active'] === 0;
        if (!$activate && $id === Auth::id()) {
            $this->fail('Vous ne pouvez pas désactiver votre propre compte.', 'users');
        }
        if (!$activate && $user['role_code'] === 'admin' && $this->activeAdminCount() <= 1) {
            $this->fail('Impossible : c\'est le dernier administrateur actif.', 'users');
        }
        $this->db->update('users', ['is_active' => $activate ? 1 : 0], 'id = :id', ['id' => $id]);
        AuditLog::log($activate ? 'activate' : 'deactivate', 'users', 'user', $id, ($activate ? 'Compte activé : ' : 'Compte désactivé : ') . $user['username']);
        Session::flash('success', $activate ? 'Compte activé.' : 'Compte désactivé. L\'utilisateur ne peut plus se connecter.');
        $this->redirect('users');
    }

    public function unlock(int $id): void
    {
        $user = $this->find($id);
        $this->db->update('users', ['locked_until' => null, 'failed_attempts' => 0], 'id = :id', ['id' => $id]);
        AuditLog::log('unlock', 'users', 'user', $id, 'Compte déverrouillé : ' . $user['username']);
        Session::flash('success', 'Compte déverrouillé.');
        $this->redirect('users');
    }

    public function password(int $id): void
    {
        $user = $this->find($id);
        $password = (string) Request::post('password', '');
        $errors = Auth::passwordErrors($password, (int) Settings::get('password_min_length', 10));
        if ($password !== (string) Request::post('password_confirm', '')) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }
        if ($errors) {
            $this->fail(implode(' ', $errors), 'users/' . $id . '/edit');
        }
        $this->db->update('users', [
            'password_hash'       => Auth::hashPassword($password),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'failed_attempts'     => 0,
            'locked_until'        => null,
        ], 'id = :id', ['id' => $id]);
        // Invalide les liens de réinitialisation en attente
        $this->db->query('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$id]);
        AuditLog::log('password_reset', 'users', 'user', $id, 'Mot de passe réinitialisé par un administrateur : ' . $user['username']);
        Session::flash('success', 'Mot de passe de « ' . $user['full_name'] . ' » réinitialisé.');
        $this->redirect('users/' . $id . '/edit');
    }

    public function delete(int $id): void
    {
        $user = $this->find($id);
        if ($id === Auth::id()) {
            $this->fail('Vous ne pouvez pas supprimer votre propre compte.', 'users');
        }
        if ($user['role_code'] === 'admin' && (int) $user['is_active'] === 1 && $this->activeAdminCount() <= 1) {
            $this->fail('Impossible : c\'est le dernier administrateur actif.', 'users');
        }
        // Suppression logique : l'historique (ventes, factures, journal) reste rattaché.
        // Identifiant et e-mail libérés pour pouvoir être réutilisés.
        $suffix = '#del' . $id;
        $this->db->update('users', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'is_active'  => 0,
            'username'   => mb_substr($user['username'], 0, 60 - strlen($suffix)) . $suffix,
            'email'      => mb_substr($user['email'], 0, 190 - strlen($suffix)) . $suffix,
        ], 'id = :id', ['id' => $id]);
        AuditLog::log('delete', 'users', 'user', $id, 'Utilisateur supprimé : ' . $user['username']);
        Session::flash('success', 'Utilisateur supprimé. Son historique est conservé.');
        $this->redirect('users');
    }

    /* ------------------------------------------------------------------ */

    private function validated(?int $id): array
    {
        $input = [
            'full_name' => Request::string('full_name'),
            'username'  => mb_strtolower(Request::string('username')),
            'email'     => mb_strtolower(Request::string('email')),
            'phone'     => Request::string('phone'),
            'role_id'   => Request::int('role_id'),
        ];
        $v = Validator::make($input, [
            'full_name' => 'required|minlen:2|maxlen:150',
            'username'  => 'required|minlen:3|maxlen:60|alpha_dash|unique:users,username' . ($id ? ',' . $id : ''),
            'email'     => 'required|email|maxlen:190|unique:users,email' . ($id ? ',' . $id : ''),
            'phone'     => 'nullable|phone',
            'role_id'   => 'required|exists:roles,id',
        ], [
            'full_name' => 'Nom complet', 'username' => 'Nom d\'utilisateur', 'email' => 'E-mail',
            'phone' => 'Téléphone', 'role_id' => 'Rôle',
        ]);
        if ($v->fails()) {
            $this->fail((string) $v->first(), $id ? 'users/' . $id . '/edit' : 'users/create');
        }
        $input['phone'] = $input['phone'] !== '' ? $input['phone'] : null;
        $input['is_active'] = Request::post('is_active') ? 1 : 0;
        return $input;
    }

    /** Surcharges : perm[ID] = inherit | allow | deny */
    private function saveOverrides(int $userId): void
    {
        $this->db->query('DELETE FROM user_permissions WHERE user_id = ?', [$userId]);
        $perms = $_POST['perm'] ?? [];
        if (!is_array($perms)) {
            return;
        }
        $valid = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM permissions'), 'id'));
        foreach ($perms as $pid => $mode) {
            $pid = (int) $pid;
            if (!in_array($pid, $valid, true) || !in_array($mode, ['allow', 'deny'], true)) {
                continue;
            }
            $this->db->insert('user_permissions', ['user_id' => $userId, 'permission_id' => $pid, 'allowed' => $mode === 'allow' ? 1 : 0]);
        }
    }

    private function fail(string $message, string $route): never
    {
        Session::flash('danger', $message);
        Session::flashInput($_POST);
        $this->redirect($route);
    }

    private function find(int $id): array
    {
        $user = $this->db->fetch(
            'SELECT u.*, r.code AS role_code, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
        if (!$user) {
            Session::flash('danger', 'Utilisateur introuvable.');
            $this->redirect('users');
        }
        return $user;
    }

    private function activeAdminCount(): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.is_active = 1 AND u.deleted_at IS NULL"
        );
    }

    private function roles(): array
    {
        return $this->db->fetchAll('SELECT id, code, name FROM roles ORDER BY id');
    }

    private function rolePermissionIds(int $roleId): array
    {
        return array_map('intval', array_column(
            $this->db->fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]),
            'permission_id'
        ));
    }

    public function permissionsByModule(): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT id, code, module, name FROM permissions ORDER BY id') as $p) {
            $out[$p['module']][] = $p;
        }
        return $out;
    }
}
