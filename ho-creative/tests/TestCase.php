<?php

namespace Tests;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seedCatalog = true;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->seedCatalog) {
            $this->seed([RolesSeeder::class, ContentSeeder::class, CatalogSeeder::class]);
        }
        app(\App\Support\Settings::class)->flush();
    }

    protected function staff(string $role = 'owner', array $attrs = []): User
    {
        $u = User::create(['name' => ucfirst($role).' Test', 'email' => $role.uniqid().'@test.local', 'password' => 'Password123'] + $attrs);
        $u->forceFill(['is_staff' => true, 'is_active' => true])->save();
        $u->assignRole($role);

        return $u;
    }

    protected function customerUser(string $name = 'Client'): User
    {
        $c = Customer::create(['name' => $name, 'email' => strtolower($name).uniqid().'@test.local', 'phone' => '0600000000']);

        return User::create(['name' => $name, 'email' => $c->email, 'password' => 'Password123', 'customer_id' => $c->id]);
    }

    /** A real PNG of the requested pixel size (for resolution checks). */
    protected function png(int $w = 1075, int $h = 720, string $name = 'carte.png'): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 228, 0, 124));
        $path = tempnam(sys_get_temp_dir(), 'png');
        imagepng($im, $path);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    protected function cartCookie($response): ?string
    {
        return $response->getCookie(\App\Services\CartService::COOKIE, true)?->getValue();
    }
}
