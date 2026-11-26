<?php

declare(strict_types=1);

/*
 * HTTP end-to-end tests against a running instance on a FRESH (empty) database:
 * walks the 10-step installer, then exercises storefront, auth, RBAC, settings and security behaviour.
 *
 *   php -S 127.0.0.1:8099 -t public tests/server-router.php   (with no .env / installed.lock)
 *   BWA_HTTP_URL=http://127.0.0.1:8099 BWA_TEST_DB_DATABASE=bwa_install BWA_TEST_DB_USERNAME=u BWA_TEST_DB_PASSWORD=p php tests/http.php
 */

require __DIR__ . '/TestRunner.php';

final class Browser
{
    private string $jar;
    public int $status = 0;
    public string $body = '';
    /** @var array<string,string> */
    public array $headers = [];

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'bwajar');
    }

    /** @param array<string,mixed>|null $post */
    public function request(string $method, string $path, ?array $post = null, array $files = [], array $extraHeaders = []): self
    {
        $ch = curl_init(str_starts_with($path, 'http') ? $path : $this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'BWA-HTTP-Tests',
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $extraHeaders,
        ]);
        if ($post !== null) {
            $fields = $post;
            foreach ($files as $k => $f) {
                $fields[$k] = new CURLFile($f[0], $f[1], $f[2]);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $files ? $fields : http_build_query($fields));
        }
        $raw = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $this->headers = [];
        foreach (explode("\r\n", substr($raw, 0, $hsize)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $this->headers[strtolower(trim($k))] = trim($v);
            }
        }
        $this->body = substr($raw, $hsize);
        return $this;
    }

    public function get(string $path): self
    {
        return $this->request('GET', $path);
    }

    /** POST with the CSRF token taken from the last page (or from $tokenPage). */
    public function post(string $path, array $data = [], ?string $tokenPage = null, array $files = []): self
    {
        if (!array_key_exists('_token', $data)) {
            if ($tokenPage !== null) {
                $this->get($tokenPage);
            }
            $data['_token'] = $this->token();
        }
        return $this->request('POST', $path, $data, $files);
    }

    public function token(): string
    {
        preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $this->body, $m);
        return $m[1] ?? '';
    }

    public function location(): string
    {
        $loc = $this->headers['location'] ?? '';
        return (string) preg_replace('#^https?://[^/]+#', '', $loc);
    }

    public function follow(): self
    {
        $i = 0;
        while (in_array($this->status, [301, 302, 303], true) && $i++ < 5) {
            $this->get($this->location());
        }
        return $this;
    }
}

$base = rtrim((string) (getenv('BWA_HTTP_URL') ?: 'http://127.0.0.1:8099'), '/');
$dbName = (string) getenv('BWA_TEST_DB_DATABASE');
if ($dbName === '') {
    fwrite(STDERR, "BWA_TEST_DB_DATABASE is required (fresh database used by the installer)\n");
    exit(2);
}
$db = [
    'host' => getenv('BWA_TEST_DB_HOST') ?: 'localhost',
    'port' => (string) (getenv('BWA_TEST_DB_PORT') ?: '3306'),
    'name' => $dbName,
    'user' => getenv('BWA_TEST_DB_USERNAME') ?: 'root',
    'pass' => getenv('BWA_TEST_DB_PASSWORD') ?: '',
];
$t = new TestRunner();
$b = new Browser($base);
$adminPass = 'Admin2026!x';

$t->section('Installer (fresh database) — ' . $base);
$t->test('every page redirects to the installer before installation', function (TestRunner $t) use ($b): void {
    $b->get('/');
    $t->equals(302, $b->status);
    $t->equals('/install', $b->location());
    $b->get('/admin/login');
    $t->equals('/install', $b->location());
});
$t->test('step 1 welcome → choose Arabic → RTL installer', function (TestRunner $t) use ($b): void {
    $b->get('/install')->follow();
    $t->equals(200, $b->status);
    $t->contains('Assistant d’installation', $b->body);
    $b->post('/install/1', ['locale' => 'ar']);
    $t->equals('/install/2', $b->location());
    $b->get('/install/2');
    $t->contains('dir="rtl"', $b->body);
    $t->contains('bootstrap.rtl.min.css', $b->body);
    $b->post('/install/1', ['locale' => 'fr'], '/install/1');
});
$t->test('cannot skip ahead to later steps', function (TestRunner $t) use ($b): void {
    $b->get('/install/8');
    $t->equals(302, $b->status);
    $t->equals('/install/2', $b->location());
});
$t->test('steps 2-3 server, extensions & permission checks pass', function (TestRunner $t) use ($b): void {
    $b->get('/install/2');
    $t->contains(PHP_VERSION, $b->body, 'real PHP version shown');
    $b->post('/install/2');
    $t->equals('/install/3', $b->location());
    $b->get('/install/3');
    $t->contains('pdo_mysql', $b->body);
    $b->post('/install/3');
    $t->equals('/install/4', $b->location());
});
$t->test('step 4-5 wrong DB credentials are reported, then the correct ones pass', function (TestRunner $t) use ($b, $db, $base): void {
    $b->post('/install/4', ['db_host' => $db['host'], 'db_port' => $db['port'], 'db_database' => $db['name'], 'db_username' => $db['user'], 'db_password' => 'wrong-password', 'app_url' => $base], '/install/4');
    $b->get('/install/5');
    $t->contains('Connexion impossible', $b->body);
    $t->assert(!str_contains($b->body, 'wrong-password'), 'password never echoed');
    $b->post('/install/5');
    $t->equals('/install/5', $b->location(), 'cannot continue');
    $b->post('/install/4', ['db_host' => $db['host'], 'db_port' => $db['port'], 'db_database' => $db['name'], 'db_username' => $db['user'], 'db_password' => $db['pass'], 'app_url' => $base], '/install/4');
    $b->get('/install/5');
    $t->contains('Connexion réussie', $b->body);
    $b->post('/install/5');
    $t->equals('/install/6', $b->location());
});
$t->test('step 4 validation errors keep the user on the form', function (TestRunner $t) use ($b): void {
    $b->post('/install/4', ['db_host' => 'bad host!', 'db_port' => 'abc', 'db_database' => '', 'db_username' => '', 'app_url' => 'nope'], '/install/4');
    $t->equals(302, $b->status);
    $b->get('/install/4');
    $t->contains('is-invalid', $b->body);
});
$t->test('step 6 creates .env, tables and seed data', function (TestRunner $t) use ($b, $db, $base): void {
    // restore valid credentials after the validation test
    $b->post('/install/4', ['db_host' => $db['host'], 'db_port' => $db['port'], 'db_database' => $db['name'], 'db_username' => $db['user'], 'db_password' => $db['pass'], 'app_url' => $base], '/install/4');
    $b->post('/install/5', [], '/install/5');
    $b->post('/install/6', [], '/install/6');
    $t->equals('/install/6', $b->location());
    $b->get('/install/6');
    $t->contains('Migrated: 2026_01_01_000008_marketing_content', $b->body);
    $t->contains('Seeded: 06_print_options', $b->body);
    $t->contains('/install/7', $b->body);
});
$t->test('step 7 company settings with logo upload', function (TestRunner $t) use ($b): void {
    $img = imagecreatetruecolor(300, 120);
    imagefill($img, 0, 0, imagecolorallocate($img, 23, 23, 23));
    $logo = tempnam(sys_get_temp_dir(), 'logo') . '.png';
    imagepng($img, $logo);
    $b->post('/install/7', [
        'company_name' => 'BERRADI WALL ART', 'company_email' => 'contact@berradi.test', 'company_phone' => '+212 5 22 00 00 00',
        'company_whatsapp' => '+212 6 61 00 00 00', 'company_address' => '12 Rue Exemple', 'company_city' => 'Casablanca',
        'currency_code' => 'MAD', 'currency_symbol' => 'DH', 'default_language' => 'fr', 'tax_default_rate' => '20',
        'invoice_prefix' => 'FAC', 'quotation_prefix' => 'DEV', 'order_prefix' => 'BWA', 'shipping_flat_rate' => '40.00', 'shipping_free_threshold' => '1000.00',
    ], '/install/7', ['company_logo' => [$logo, 'image/png', 'logo.png']]);
    $t->equals('/install/8', $b->location());
});
$t->test('step 8 rejects weak password, then creates the super admin', function (TestRunner $t) use ($b, $adminPass): void {
    $b->post('/install/8', ['name' => 'Owner', 'email' => 'owner@berradi.test', 'password' => 'short', 'password_confirmation' => 'short'], '/install/8');
    $t->equals('/install/8', $b->location());
    $b->post('/install/8', ['name' => 'Owner Berradi', 'email' => 'owner@berradi.test', 'password' => $adminPass, 'password_confirmation' => $adminPass], '/install/8');
    $t->equals('/install/9', $b->location());
});
$t->test('step 9-10 finish locks the installer permanently', function (TestRunner $t) use ($b): void {
    $b->get('/install/9');
    $t->contains('cron:run', $b->body);
    $b->post('/install/9');
    $t->equals(200, $b->status);
    $t->contains('verrouillé de façon permanente', $b->body);
    $b->get('/install/1');
    $t->equals(404, $b->status, 'installer locked');
    $b->get('/install');
    $t->equals(404, $b->status);
});

$t->section('Storefront, languages & security headers');
$t->test('home page renders in French (LTR) with real categories', function (TestRunner $t) use ($b): void {
    $b->get('/');
    $t->equals(200, $b->status);
    $t->contains('<html lang="fr" dir="ltr">', $b->body);
    $t->contains('Transformez vos souvenirs', $b->body);
    $t->contains('Calligraphie arabe', $b->body);
    $t->contains('wa.me/212661000000', $b->body, 'WhatsApp click-to-chat');
    $t->contains('/uploads/branding/', $b->body, 'uploaded logo used');
});
$t->test('language switch to Arabic gives RTL layout and Arabic content', function (TestRunner $t) use ($b): void {
    $b->get('/lang/ar');
    $t->equals(302, $b->status);
    $b->get('/');
    $t->contains('<html lang="ar" dir="rtl">', $b->body);
    $t->contains('حوّل ذكرياتك', $b->body);
    $t->contains('الخط العربي', $b->body);
    $t->contains('bootstrap.rtl.min.css', $b->body);
    $b->get('/lang/fr');
});
$t->test('security headers are sent', function (TestRunner $t) use ($b): void {
    $b->get('/');
    $t->equals('nosniff', $b->headers['x-content-type-options'] ?? '');
    $t->equals('SAMEORIGIN', $b->headers['x-frame-options'] ?? '');
    $t->contains("script-src 'self'", $b->headers['content-security-policy'] ?? '');
    $t->equals('strict-origin-when-cross-origin', $b->headers['referrer-policy'] ?? '');
});
$t->test('session cookie is HttpOnly + SameSite', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->get('/');
    $cookie = $c->headers['set-cookie'] ?? '';
    $t->contains('bwa_session=', $cookie);
    $t->contains('HttpOnly', $cookie);
    $t->contains('SameSite=Lax', $cookie);
});
$t->test('404 page is branded and non-existent routes return 404', function (TestRunner $t) use ($b): void {
    $b->get('/this-page-does-not-exist');
    $t->equals(404, $b->status);
    $t->contains('Page introuvable', $b->body);
});
$t->test('POST without CSRF token is refused (419)', function (TestRunner $t) use ($b): void {
    $b->request('POST', '/account/login', ['email' => 'x@y.ma', 'password' => 'x', '_token' => 'forged']);
    $t->equals(419, $b->status);
});
$t->test('private folders and dotfiles are not served', function (TestRunner $t) use ($b): void {
    $b->get('/.env');
    $t->assert(in_array($b->status, [403, 404], true), '.env status ' . $b->status);
    $t->assert(!str_contains($b->body, 'DB_PASSWORD'));
    $b->get('/../.env');
    $t->assert(!str_contains($b->body, 'DB_PASSWORD'));
});

$t->section('Admin authentication, RBAC & settings');
$t->test('protected admin pages redirect to login', function (TestRunner $t) use ($b): void {
    $b->get('/admin/settings');
    $t->equals('/admin/login', $b->location());
});
$t->test('wrong admin password shows a generic error', function (TestRunner $t) use ($b): void {
    $b->post('/admin/login', ['email' => 'owner@berradi.test', 'password' => 'bad'], '/admin/login');
    $t->equals(302, $b->status);
    $b->get('/admin/login');
    $t->contains('Ces identifiants ne correspondent', $b->body);
});
$t->test('admin login → intended page → dashboard', function (TestRunner $t) use ($b, $adminPass): void {
    $b->post('/admin/login', ['email' => 'owner@berradi.test', 'password' => $adminPass], '/admin/login');
    $t->equals('/admin/settings', $b->location(), 'redirect to intended URL');
    $b->get('/admin');
    $t->equals(200, $b->status);
    $t->contains('Bonjour, Owner Berradi', $b->body);
    $t->contains('Configuration de la boutique', $b->body);
    $t->assert(!preg_match('/chiffre d.affaires\s*:\s*\d/i', $b->body), 'no fake statistics');
});
$t->test('all settings tabs render and company tab saves', function (TestRunner $t) use ($b): void {
    foreach (['company', 'localization', 'taxes', 'documents', 'shipping', 'orders', 'mail', 'seo'] as $tab) {
        $b->get('/admin/settings/' . $tab);
        $t->equals(200, $b->status, "tab {$tab}");
    }
    $b->post('/admin/settings/taxes', ['tax_enabled' => '1', 'tax_default_rate' => '14', 'tax_prices_include_tax' => '0', 'tax_label' => 'TVA'], '/admin/settings/taxes');
    $t->equals('/admin/settings/taxes', $b->location());
    $b->get('/admin/settings/taxes');
    $t->contains('value="14"', $b->body);
    $t->contains('Modifications enregistrées', $b->body);
    $b->post('/admin/settings/taxes', ['tax_default_rate' => '150', 'tax_label' => 'TVA'], '/admin/settings/taxes');
    $b->get('/admin/settings/taxes');
    $t->contains('is-invalid', $b->body, 'rate > 100 rejected');
});
$t->test('SMTP password is stored encrypted and never re-displayed', function (TestRunner $t) use ($b): void {
    $b->post('/admin/settings/mail', ['mail_driver' => 'log', 'mail_host' => 'mail.berradi.test', 'mail_port' => '465', 'mail_encryption' => 'ssl', 'mail_username' => 'noreply', 'mail_password' => 'Sup3rSecretSMTP'], '/admin/settings/mail');
    $b->get('/admin/settings/mail');
    $t->assert(!str_contains($b->body, 'Sup3rSecretSMTP'), 'not in HTML');
    $t->contains('Laisser vide pour conserver', $b->body);
    $b->post('/admin/settings-test-mail', [], '/admin/settings/mail');
    $b->follow();
    $t->contains('E-mail de test envoyé', $b->body);
});
$t->test('create a Designer staff account', function (TestRunner $t) use ($b): void {
    $b->get('/admin/users/create');
    preg_match('/<option value="(\d+)"[^>]*>Designer<\/option>/', $b->body, $m);
    $t->assert(isset($m[1]), 'designer role option');
    $b->post('/admin/users', ['name' => 'Yasmine Designer', 'email' => 'designer@berradi.test', 'phone' => '0600000000', 'role_id' => $m[1] ?? '0', 'locale' => 'fr', 'is_active' => '1', 'password' => 'Design2026', 'password_confirmation' => 'Design2026']);
    $t->equals('/admin/users', $b->location());
    $b->get('/admin/users');
    $t->contains('designer@berradi.test', $b->body);
});
$t->test('duplicate e-mail is rejected', function (TestRunner $t) use ($b): void {
    $b->post('/admin/users', ['name' => 'X', 'email' => 'designer@berradi.test', 'role_id' => '1', 'locale' => 'fr', 'password' => 'Design2026', 'password_confirmation' => 'Design2026'], '/admin/users/create');
    $b->get('/admin/users/create');
    $t->contains('déjà utilisée', $b->body);
});
$t->test('roles matrix renders; super admin role is locked', function (TestRunner $t) use ($b): void {
    $b->get('/admin/roles');
    $t->equals(200, $b->status);
    $t->contains('Production Manager', $b->body);
    $b->get('/admin/roles/1/edit');
    $b->post('/admin/roles/1', ['name' => 'Hacked', 'permissions' => []]);
    $b->follow();
    $t->contains('ne peut pas être modifié', $b->body);
});
$t->test('activity log records the actions', function (TestRunner $t) use ($b): void {
    $b->get('/admin/activity-logs');
    $t->equals(200, $b->status);
    $t->contains('settings.updated', $b->body);
    $t->contains('user.created', $b->body);
    $t->contains('auth.failed', $b->body);
});
$t->test('logout requires POST + CSRF and ends the session', function (TestRunner $t) use ($b): void {
    $b->get('/admin/logout');
    $t->equals(405, $b->status, 'GET logout not allowed');
    $b->post('/admin/logout', [], '/admin');
    $t->equals('/admin/login', $b->location());
    $b->get('/admin');
    $t->equals('/admin/login', $b->location());
});
$t->test('designer cannot access settings, users or roles (403)', function (TestRunner $t) use ($base): void {
    $d = new Browser($base);
    $d->post('/admin/login', ['email' => 'designer@berradi.test', 'password' => 'Design2026'], '/admin/login');
    $t->equals('/admin', $d->location());
    $d->get('/admin');
    $t->equals(200, $d->status);
    $t->assert(!str_contains($d->body, '/admin/settings"'), 'settings hidden in menu');
    foreach (['/admin/settings', '/admin/users', '/admin/roles', '/admin/activity-logs'] as $p) {
        $d->get($p);
        $t->equals(403, $d->status, $p);
    }
    $d->post('/admin/settings/company', ['company_name' => 'Hacked'], '/admin');
    $t->equals(403, $d->status, 'POST settings forbidden');
});
$t->test('admin password reset request + invalid token refused', function (TestRunner $t) use ($base): void {
    $r = new Browser($base);
    $r->post('/admin/forgot-password', ['email' => 'designer@berradi.test'], '/admin/forgot-password');
    $t->equals('/admin/login', $r->location());
    $r->get('/admin/reset-password/' . str_repeat('a', 64));
    $t->equals('/admin/forgot-password', $r->location(), 'invalid token refused');
});

$t->section('Customer accounts');
$t->test('registration validates, creates the account and logs in', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->post('/account/register', ['first_name' => 'Salma', 'last_name' => 'Alaoui', 'email' => 'bad', 'phone' => '0612', 'password' => 'x', 'password_confirmation' => 'y'], '/account/register');
    $c->get('/account/register');
    $t->contains('is-invalid', $c->body);
    $t->contains('value="Salma"', $c->body, 'old input kept');
    $t->assert(!str_contains($c->body, 'value="x"'), 'password not re-filled');
    $c->post('/account/register', ['first_name' => 'Salma', 'last_name' => 'Alaoui', 'email' => 'salma@client.test', 'phone' => '0612345678', 'password' => 'Client2026', 'password_confirmation' => 'Client2026', 'accept_terms' => '1'], '/account/register');
    $t->equals('/account', $c->location());
    $c->get('/account');
    $t->contains('Bonjour Salma', $c->body);
    $c->post('/account/profile', ['first_name' => 'Salma', 'last_name' => 'Alaoui', 'email' => 'salma@client.test', 'phone' => '0612345678', 'locale' => 'ar', 'ice' => '001234567000089'], '/account/profile');
    $c->get('/account/profile');
    $t->contains('001234567000089', $c->body);
    $c->post('/account/logout', [], '/account');
    $c->get('/account');
    $t->equals('/account/login', $c->location());
});
$t->test('customer login works; customer cannot reach admin', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->post('/account/login', ['email' => 'salma@client.test', 'password' => 'Client2026'], '/account/login');
    $t->equals('/account', $c->location());
    $c->get('/admin');
    $t->equals('/admin/login', $c->location());
    $c->post('/admin/login', ['email' => 'salma@client.test', 'password' => 'Client2026'], '/admin/login');
    $c->get('/admin');
    $t->equals('/admin/login', $c->location(), 'customer credentials are not staff credentials');
});
$t->test('XSS payloads are escaped on output', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->post('/account/register', ['first_name' => '<script>alert(1)</script>', 'last_name' => 'X', 'email' => 'xss@client.test', 'phone' => '0612345679', 'password' => 'Client2026', 'password_confirmation' => 'Client2026', 'accept_terms' => '1'], '/account/register');
    $c->get('/account');
    $t->assert(!str_contains($c->body, '<script>alert(1)</script>'), 'raw script not present');
    $t->contains('&lt;script&gt;alert(1)&lt;/script&gt;', $c->body);
});

exit($t->finish());
