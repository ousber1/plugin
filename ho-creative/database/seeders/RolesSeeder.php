<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (config('hocreative.permissions') as $p) {
            Permission::findOrCreate($p, 'web');
        }
        foreach (config('hocreative.roles') as $role => $perms) {
            $r = Role::findOrCreate($role, 'web');
            $r->syncPermissions($perms === ['*'] ? config('hocreative.permissions') : $perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
