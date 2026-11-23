<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Flow 9: install on a brand-new, empty database file (no RefreshDatabase transaction).
 */
class CleanInstallTest extends BaseTestCase
{
    public function test_install_command_on_clean_database_then_lock_and_purge_demo(): void
    {
        $db = sys_get_temp_dir().'/hoc-install-'.uniqid().'.sqlite';
        touch($db);
        config(['database.connections.sqlite.database' => $db]);
        DB::purge('sqlite');
        $lock = storage_path('app/installed.lock');
        $backup = file_exists($lock) ? file_get_contents($lock) : null;
        @unlink($lock);
        try {
            $code = Artisan::call('hocreative:install', ['--email' => 'owner@test.local', '--name' => 'Owner', '--password' => 'Sup3rSecret!', '--demo' => true]);
            $this->assertSame(0, $code, Artisan::output());
            $this->assertFileExists($lock);
            $this->assertTrue(User::where('email', 'owner@test.local')->first()->hasRole('owner'));
            $this->assertSame(17, Product::count());
            $this->assertGreaterThan(0, Order::where('is_demo', true)->count());
            $this->get('/install')->assertNotFound(); // web installer locked after setup
            $this->post('/install', ['token' => 'x'])->assertNotFound();
            Artisan::call('hocreative:purge-demo', ['--force' => true]);
            $this->assertSame(0, Order::count());
            $this->assertSame(0, \App\Models\Customer::where('is_demo', true)->count());
        } finally {
            $backup !== null ? file_put_contents($lock, $backup) : @unlink($lock);
            DB::disconnect('sqlite');
            @unlink($db);
        }
    }
}
