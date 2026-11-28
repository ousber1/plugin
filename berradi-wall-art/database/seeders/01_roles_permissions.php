<?php

/*
 * Seeds roles, permissions and the role <-> permission matrix from config/permissions.php.
 * Idempotent: existing custom changes to non-system roles are preserved; system roles are re-synced.
 */

use App\Core\Database;

return static function (Database $db): void {
    $config = require dirname(__DIR__, 2) . '/config/permissions.php';

    $all = [];
    foreach ($config['permissions'] as $module => $slugs) {
        foreach ($slugs as $slug) {
            $all[] = $slug;
            $db->query(
                'INSERT INTO permissions (slug, module, name) VALUES (:slug, :module, :name)
                 ON DUPLICATE KEY UPDATE module = VALUES(module)',
                ['slug' => $slug, 'module' => $module, 'name' => ucwords(str_replace(['.', '_'], ' ', $slug))]
            );
        }
    }
    $permIds = [];
    foreach ($db->fetchAll('SELECT id, slug FROM permissions') as $p) {
        $permIds[$p['slug']] = (int) $p['id'];
    }

    foreach ($config['roles'] as $slug => $role) {
        $existing = $db->fetch('SELECT id FROM roles WHERE slug = ?', [$slug]);
        if ($existing) {
            $roleId = (int) $existing['id'];
            $db->update('roles', ['name' => $role['name'], 'description' => $role['description'], 'is_system' => 1], ['id' => $roleId]);
        } else {
            $roleId = $db->insert('roles', [
                'slug' => $slug,
                'name' => $role['name'],
                'description' => $role['description'],
                'is_system' => 1,
            ]);
        }
        if ($slug === 'super_admin') {
            continue; // bypasses all checks, no rows needed
        }
        $granted = [];
        $rules = $role['permissions'];
        if (in_array('*', $rules, true)) {
            $granted = $all;
        }
        foreach ($rules as $rule) {
            if ($rule === '*') {
                continue;
            }
            if (str_starts_with($rule, '!')) {
                $granted = array_values(array_diff($granted, [substr($rule, 1)]));
            } else {
                $granted[] = $rule;
            }
        }
        $db->delete('role_permissions', ['role_id' => $roleId]);
        foreach (array_unique($granted) as $perm) {
            if (isset($permIds[$perm])) {
                $db->query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $permIds[$perm]]);
            }
        }
    }
};
