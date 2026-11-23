<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BaseSeeder::class);
        $this->call(DemoSeeder::class);
    }
}
