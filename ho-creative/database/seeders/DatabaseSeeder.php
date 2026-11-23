<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data required by every installation (idempotent).
 * Transactional demo data lives in DemoSeeder and is never loaded implicitly.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, ContentSeeder::class, CatalogSeeder::class]);
    }
}
