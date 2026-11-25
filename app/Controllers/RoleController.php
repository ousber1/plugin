<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

/**
 * Rôles et matrice de permissions.
 * Le rôle Administrateur possède toujours toutes les permissions (non modifiable).
 */
final class RoleController extends Controller
{
    /** Libellés français des modules de permissions. */
    public const MODULES = [
        'dashboard' => 'Tableau de bord', 'pos' => 'Point de vente', 'cash' => 'Caisse',
        'customers' => 'Clients', 'suppliers' => 'Fournisseurs', 'products' => 'Produits & services',
        'packs' => 'Packs', 'stock' => 'Stock', 'purchases' => 'Achats', 'quotations' => 'Devis',
        'invoices' => 'Factures', 'payments' => 'Paiements', 'production' => 'Production',
        'design' => 'Design', 'deliveries' => 'Livraisons', 'machines' => 'Machines',
        'calculator' => 'Calculateur de coûts', 'expenses' => 'Dépenses', 'reports' => 'Rapports',
        'users' => 'Utilisateurs', 'settings' => 'Paramètres', 'backups' => 'Sauvegardes',
        'logs' => 'Journal d\'activité', 'notifications' => 'Notifications',
    ];

    public function index(): void
    {
        $roles = $this->db->fetchAll(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS users_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perms_count
               FROM roles r ORDER BY r.id'
        );
        $this->view('roles/index', [
            'title' => 'Rôles et permissions',
            'roles' => $roles,
            'totalPermissions' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM permissions'),
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(int $id): void
    {
        $this->form($this->find($id));
    }

    private function form(?array $role): void
    {
        $permissions = [];
        foreach ($this->db->fetchAll('SELECT id, code, module, name FROM permissions ORDER BY id') as $p) {
            $permissions[$p['module']][] = $p;
        }
        $granted = $role ? array_map('intval', array_column(
            $this->db->fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$role['id']]),
            'permission_id'
        )) : [];
        $this->view('roles/form', [
            'title'       => $role ? 'Modifier le rôle' : 'Nouveau rôle',
            'role'        => $role,
            'permissions' => $permissions,
            'granted'     => $granted,
            'modules'     => self::MODULES,
        ]);
    }

    public function store(): void
    {
        [$name, $description] = $this->validated(null);
        $code = $this->uniqueCode($name);
        $id = $this->db->transaction(function () use ($code, $name, $description): int {
            $id = $this->db->insert('roles', ['code' => $code, 'name' => $name, 'description' => $description, 'is_system' => 0]);
            $this->syncPermissions($id);
            return $id;
        });
        AuditLog::log('create', 'users', 'role', $id, 'Rôle créé : ' . $name);
        Session::flash('success', 'Rôle « ' . $name . ' » créé.');
        $this->redirect('roles');
    }

    public function update(int $id): void
    {
        $role = $this->find($id);
        [$name, $description] = $this->validated($id);
        $before = $this->db->fetchAll('SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [$id]);
        $this->db->transaction(function () use ($id, $role, $name, $description): void {
            $this->db->update('roles', ['name' => $name, 'description' => $description], 'id = :id', ['id' => $id]);
            if ($role['code'] !== 'admin') {
                $this->syncPermissions($id);
            }
        });
        $after = $this->db->fetchAll('SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [$id]);
        $b = array_column($before, 'code');
        $a = array_column($after, 'code');
        AuditLog::log('update', 'users', 'role', $id, 'Rôle modifié : ' . $name,
            ['retirées' => array_values(array_diff($b, $a))], ['ajoutées' => array_values(array_diff($a, $b))]);
        Auth::refreshPermissions();
        Session::flash('success', 'Rôle mis à jour. Les nouveaux droits s\'appliquent immédiatement.');
        $this->redirect('roles');
    }

    public function delete(int $id): void
    {
        $role = $this->find($id);
        if ((int) $role['is_system'] === 1) {
            Session::flash('danger', 'Les rôles système ne peuvent pas être supprimés.');
            $this->redirect('roles');
        }
        $users = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id]);
        if ($users > 0) {
            Session::flash('danger', 'Ce rôle est attribué à ' . $users . ' utilisateur(s) (y compris supprimés). Réattribuez-les d\'abord.');
            $this->redirect('roles');
        }
        $this->db->query('DELETE FROM roles WHERE id = ?', [$id]);
        AuditLog::log('delete', 'users', 'role', $id, 'Rôle supprimé : ' . $role['name']);
        Session::flash('success', 'Rôle supprimé.');
        $this->redirect('roles');
    }

    private function validated(?int $id): array
    {
        $name = Request::string('name');
        $description = Request::string('description');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            Session::flash('danger', 'Le nom du rôle doit contenir entre 2 et 100 caractères.');
            Session::flashInput($_POST);
            $this->redirect($id ? 'roles/' . $id . '/edit' : 'roles/create');
        }
        $exists = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM roles WHERE name = ? AND id <> ?', [$name, (int) $id]);
        if ($exists) {
            Session::flash('danger', 'Un rôle porte déjà ce nom.');
            Session::flashInput($_POST);
            $this->redirect($id ? 'roles/' . $id . '/edit' : 'roles/create');
        }
        return [$name, mb_substr($description, 0, 255) ?: null];
    }

    private function syncPermissions(int $roleId): void
    {
        $this->db->query('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
        $ids = array_map('intval', (array) ($_POST['permissions'] ?? []));
        $valid = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM permissions'), 'id'));
        foreach (array_unique(array_intersect($ids, $valid)) as $pid) {
            $this->db->insert('role_permissions', ['role_id' => $roleId, 'permission_id' => $pid]);
        }
    }

    private function uniqueCode(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: 'role')), '_') ?: 'role';
        $base = substr($base, 0, 40);
        $code = $base;
        $i = 2;
        while ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM roles WHERE code = ?', [$code]) > 0) {
            $code = $base . '_' . $i++;
        }
        return $code;
    }

    private function find(int $id): array
    {
        $role = $this->db->fetch('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) {
            Session::flash('danger', 'Rôle introuvable.');
            $this->redirect('roles');
        }
        return $role;
    }
}
