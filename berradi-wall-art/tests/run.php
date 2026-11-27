<?php

declare(strict_types=1);

/*
 * BERRADI WALL ART — unit & integration tests.
 *
 *   BWA_TEST_DB_DATABASE=bwa_test BWA_TEST_DB_USERNAME=user BWA_TEST_DB_PASSWORD=secret php tests/run.php
 *
 * WARNING: the test database is wiped. Never point it at the production database.
 * Without BWA_TEST_DB_DATABASE only the unit tests (no database) are executed.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\Translator;
use App\Core\Validator;
use App\Helpers\Money;
use App\Helpers\Str;
use App\Services\Auth;
use App\Services\CatalogService;
use App\Services\Crypt;
use App\Services\ImageUploadService;
use App\Services\Installer\InstallerService;
use App\Services\PasswordResetService;
use App\Services\RateLimiter;
use App\Services\SequenceService;
use App\Services\SettingsService;
use App\Services\UploadException;

putenv('BWA_TESTING=1');
require __DIR__ . '/TestRunner.php';
$boot = require dirname(__DIR__) . '/app/bootstrap.php';
$tmpPublic = sys_get_temp_dir() . '/bwa_public_' . bin2hex(random_bytes(4));
mkdir($tmpPublic . '/uploads', 0755, true);
$app = $boot($tmpPublic);
Config::set('app.key', Crypt::generateKey());
Config::set('app.debug', true);
Translator::setLocale('fr');

$t = new TestRunner();
$base = dirname(__DIR__);

// --------------------------------------------------------------------- unit
$t->section('Money (integer cents, no floats)');
$t->test('parses decimal strings and French formats', function (TestRunner $t): void {
    $t->equals(125050, Money::toCents('1250.50'));
    $t->equals(125050, Money::toCents('1 250,50'));
    $t->equals(125050, Money::toCents('1.250,50'));
    $t->equals(100000, Money::toCents(1000));
    $t->equals(-1999, Money::toCents('-19.99'));
    $t->equals(1000, Money::toCents('9.995'), 'half-up rounding on 3rd decimal');
    $t->throws(fn () => Money::toCents('abc'), InvalidArgumentException::class);
});
$t->test('arithmetic is exact', function (TestRunner $t): void {
    $t->equals('0.30', Money::add('0.10', '0.20'));
    $t->equals('300.00', Money::mul('100.00', 3));
    $t->equals('20.00', Money::percent('100.00', '20'));
    $t->equals('1.75', Money::percent('17.50', '10'));
    $t->equals('7.50', Money::percent('100', '7.5'));
    $t->equals('100.00', Money::htFromTtc('120.00', '20'));
    $t->equals('83.33', Money::htFromTtc('100.00', '20'));
    $t->equals('-5.00', Money::sub('10', '15'));
    $t->equals(0, Money::compare('10.00', '10'));
});
$t->test('formats amounts with DH symbol', function (TestRunner $t): void {
    $t->equals("1\u{202F}250,50 DH", Money::format('1250.5'));
    $t->equals('0,00 DH', Money::format(null));
});

$t->section('Core helpers');
$t->test('Env build/parse round-trip keeps special characters', function (TestRunner $t): void {
    $file = sys_get_temp_dir() . '/bwa_env_test';
    file_put_contents($file, Env::build(['A' => 'simple', 'B' => 'p@ss "w0rd" #1', 'C' => true, 'D' => '']));
    Env::load($file);
    $t->equals('simple', Env::get('A'));
    $t->equals('p@ss "w0rd" #1', Env::get('B'));
    $t->equals(true, Env::get('C'));
    $t->equals('', Env::get('D'));
    unlink($file);
});
$t->test('Str::slug transliterates French and Arabic', function (TestRunner $t): void {
    $t->equals('cadeaux-personnalises', Str::slug('Cadeaux personnalisés'));
    $t->equals('noir-blanc', Str::slug('Noir & Blanc'));
    $t->assert(preg_match('/^[a-z0-9-]+$/', Str::slug('الخط العربي')) === 1, 'arabic slug ascii');
    $t->equals(36, strlen(Str::uuid()));
});
$t->test('Router matches params, optional segments and builds URLs', function (TestRunner $t): void {
    $r = new Router();
    $r->get('/admin/users/{id}/edit', fn () => 'x')->name('u.edit');
    $r->get('/settings/{tab?}', fn () => 'x')->name('settings');
    $m = $r->dispatch('GET', '/admin/users/42/edit');
    $t->equals(['id' => '42'], $m[1]);
    $t->equals('/settings', $r->url('settings'));
    $t->equals('/settings/mail', $r->url('settings', ['tab' => 'mail']));
    $t->equals('/admin/users/7/edit?x=1', $r->url('u.edit', ['id' => 7, 'x' => 1]));
    $t->equals(null, $r->dispatch('GET', '/nope'));
    $t->throws(fn () => $r->dispatch('POST', '/admin/users/42/edit'), \App\Core\HttpException::class, '405');
});
$t->test('Request path handles sub-folder installs', function (TestRunner $t): void {
    $req = new Request([], [], ['REQUEST_URI' => '/shop/admin/login?x=1', 'SCRIPT_NAME' => '/shop/index.php', 'REQUEST_METHOD' => 'GET']);
    $t->equals('/shop', $req->basePath());
    $t->equals('/admin/login', $req->path());
    $post = new Request([], ['_method' => 'DELETE'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php']);
    $t->equals('DELETE', $post->method());
});
$t->test('Translator: FR/AR, RTL, fallback, placeholders', function (TestRunner $t): void {
    Translator::setLocale('ar');
    $t->equals('rtl', Translator::dir());
    $t->equals('الرئيسية', __('nav.home'));
    Translator::setLocale('fr');
    $t->equals('ltr', Translator::dir());
    $t->equals('Accueil', __('nav.home'));
    $t->equals('Bonjour Sara', __('account.hello', ['name' => 'Sara']));
    $t->equals('missing.key', __('missing.key'));
    Translator::setLocale('xx');
    $t->equals('fr', Translator::locale());
});
$t->test('every translation key used in code exists in FR and AR', function (TestRunner $t) use ($base): void {
    $keys = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = (string) $f;
        if (!str_ends_with($p, '.php') || str_contains($p, '/vendor/') || str_contains($p, '/tests/') || str_contains($p, '/lang/')) {
            continue;
        }
        preg_match_all("/__\\(\\s*'([a-z_]+\\.[A-Za-z0-9_.]+)'\\s*[,)]/", (string) file_get_contents($p), $m);
        foreach ($m[1] as $k) {
            $keys[$k] = true;
        }
    }
    // dynamic keys
    foreach (\App\Controllers\Installer\InstallController::STEPS as $s) {
        $keys['installer.step_' . $s] = true;
    }
    foreach (['company', 'localization', 'taxes', 'documents', 'shipping', 'orders', 'mail', 'seo'] as $tab) {
        $keys['settings.tab_' . $tab] = true;
    }
    $perms = require $base . '/config/permissions.php';
    foreach ($perms['permissions'] as $module => $slugs) {
        $keys['permissions.module_' . $module] = true;
        foreach ($slugs as $slug) {
            $keys['permissions.' . str_replace('.', '_', $slug)] = true;
        }
    }
    foreach (['user', 'customer', 'system'] as $a) {
        $keys['admin.actor_' . $a] = true;
    }
    foreach ([1, 2, 3, 4] as $i) {
        $keys['home.how_' . $i . '_title'] = true;
        $keys['home.how_' . $i . '_text'] = true;
    }
    foreach (['required', 'email', 'integer', 'numeric', 'decimal', 'boolean', 'min', 'max', 'between', 'in', 'confirmed', 'same', 'different', 'regex', 'slug', 'url', 'phone', 'date', 'password', 'unique', 'exists', 'locale'] as $rule) {
        $keys['validation.' . $rule] = true;
    }
    $missing = [];
    foreach (['fr', 'ar'] as $loc) {
        foreach (array_keys($keys) as $k) {
            if (!Translator::has($k, $loc)) {
                $missing[] = "{$loc}:{$k}";
            }
        }
    }
    $t->assert(count($keys) > 250, 'key scan found keys');
    $t->assert($missing === [], 'missing translations: ' . implode(', ', $missing));
});
$t->test('Validator rules', function (TestRunner $t): void {
    $v = Validator::make(
        ['email' => 'bad', 'qty' => '0', 'price' => '12.345', 'pass' => 'abc', 'pass_confirmation' => 'abd', 'slug' => 'Bad Slug', 'opt' => ''],
        ['email' => 'required|email', 'qty' => 'required|integer|min:1', 'price' => 'required|decimal:2', 'pass' => 'required|password|confirmed', 'slug' => 'slug', 'opt' => 'nullable|email', 'need' => 'required']
    );
    $t->equals(['email', 'qty', 'price', 'pass', 'slug', 'need'], array_keys($v->errors()));
    $ok = Validator::make(['email' => ' a@b.ma ', 'qty' => '3', 'price' => '99.90', 'phone' => '+212 6 12 34 56 78', 'lang' => 'ar'], ['email' => 'required|email', 'qty' => 'integer|between:1,10', 'price' => 'decimal:2|min:0', 'phone' => 'phone', 'lang' => 'locale']);
    $t->assert($ok->passes(), 'valid data passes: ' . json_encode($ok->errors()));
    $t->equals('a@b.ma', $ok->validated()['email'], 'trimmed');
    $t->contains('obligatoire', $v->errors()['need'][0]);
});
$t->test('Crypt AES-256-GCM round trip and tamper detection', function (TestRunner $t): void {
    $enc = Crypt::encrypt('smtp-secret');
    $t->assert($enc !== 'smtp-secret' && str_starts_with($enc, 'v1:'));
    $t->equals('smtp-secret', Crypt::decrypt($enc));
    $raw = base64_decode(substr($enc, 3));
    $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
    $t->throws(fn () => Crypt::decrypt('v1:' . base64_encode($raw)), RuntimeException::class);
});
$t->test('escaping helper neutralizes XSS', function (TestRunner $t): void {
    $t->equals('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
    $t->assert(!str_contains(e("' onmouseover='x"), "'"), 'single quotes encoded');
    $t->assert(!str_contains(e('"><img src=x onerror=1>'), '<'), 'tags encoded');
});

// -------------------------------------------------------------- integration
$dbName = getenv('BWA_TEST_DB_DATABASE') ?: '';
if ($dbName === '') {
    fwrite(STDOUT, "\n(BWA_TEST_DB_DATABASE not set: database tests skipped)\n");
    exit($t->finish());
}
$cfg = [
    'host' => getenv('BWA_TEST_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('BWA_TEST_DB_PORT') ?: 3306),
    'database' => $dbName,
    'username' => getenv('BWA_TEST_DB_USERNAME') ?: 'root',
    'password' => getenv('BWA_TEST_DB_PASSWORD') ?: '',
];
$db = new Database(Database::connect($cfg));
Database::setInstance($db);
Config::set('database', $cfg);
$app->markInstalledForTesting(); // setting() now reads the test database instead of returning defaults

// wipe test database
$db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($db->fetchAll('SHOW TABLES') as $row) {
    $db->pdo()->exec('DROP TABLE `' . array_values($row)[0] . '`');
}
$db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');

$migrator = new Migrator($db, $base . '/database/migrations', $base . '/database/seeders');
$required = ['users', 'roles', 'permissions', 'role_permissions', 'customers', 'customer_addresses', 'categories', 'collections', 'products',
    'product_images', 'product_variants', 'product_materials', 'frames', 'customization_options', 'custom_designs', 'uploaded_images', 'carts',
    'cart_items', 'coupons', 'orders', 'order_items', 'order_customizations', 'order_status_history', 'payments', 'payment_methods', 'invoices',
    'invoice_items', 'quotations', 'quotation_items', 'production_jobs', 'production_tasks', 'machines', 'materials', 'stock_movements',
    'suppliers', 'purchases', 'purchase_items', 'expenses', 'shipping_zones', 'shipping_rates', 'shipments', 'reviews', 'newsletter_subscribers',
    'notifications', 'settings', 'translations', 'activity_logs'];

$t->section('Database: migrations & seeders (' . $db->value('SELECT VERSION()') . ')');
$t->test('all migrations run on an empty database', function (TestRunner $t) use ($migrator, $required): void {
    $done = $migrator->migrate();
    $t->equals(count($migrator->files()), count($done));
    $tables = $migrator->tables();
    $missing = array_diff($required, $tables);
    $t->assert($missing === [], 'missing tables: ' . implode(',', $missing));
    $t->assert(count($tables) >= 60, 'table count ' . count($tables));
    $t->equals([], $migrator->pending());
});
$t->test('every table is InnoDB utf8mb4 and money columns are DECIMAL', function (TestRunner $t) use ($db, $cfg): void {
    $bad = $db->fetchAll("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND (ENGINE <> 'InnoDB' OR TABLE_COLLATION NOT LIKE 'utf8mb4%')", [$cfg['database']]);
    $t->equals([], $bad);
    $floats = $db->fetchAll("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND DATA_TYPE IN ('float','double','real')", [$cfg['database']]);
    $t->equals([], $floats, 'no floating point columns');
    $fks = (int) $db->value('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?', [$cfg['database']]);
    $t->assert($fks >= 100, "foreign keys: {$fks}");
});
$t->test('rollback of every batch then re-migrate works', function (TestRunner $t) use ($migrator): void {
    $rolled = $migrator->rollback();
    $t->equals(count($migrator->files()), count($rolled));
    $t->equals([], array_values(array_diff($migrator->tables(), ['migrations'])));
    $t->equals(count($migrator->files()), count($migrator->migrate()));
});
$t->test('seeders run and are idempotent', function (TestRunner $t) use ($migrator, $db): void {
    $migrator->seed();
    $counts = fn () => array_map(fn ($tb) => (int) $db->value("SELECT COUNT(*) FROM {$tb}"), ['roles', 'permissions', 'role_permissions', 'settings', 'payment_methods', 'shipping_zones', 'shipping_rates', 'categories', 'translations', 'product_materials', 'frames', 'customization_options']);
    $first = $counts();
    $migrator->seed();
    $t->equals($first, $counts(), 'second run changes nothing');
    $t->equals(7, $first[0], 'roles');
    $t->equals(13, $first[7], 'categories');
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM payment_methods WHERE is_online = 1 AND is_active = 1"), 'online payments inactive by default');
});

$t->section('Settings, sequences & transactions');
$t->test('settings typed get/set and encrypted secrets', function (TestRunner $t) use ($db): void {
    SettingsService::flush();
    $t->equals('20', SettingsService::get('tax_default_rate'));
    $t->equals(true, SettingsService::get('tax_enabled'));
    SettingsService::set('mail_password', 'S3cret!', 'mail', 'encrypted');
    $raw = (string) $db->value("SELECT setting_value FROM settings WHERE setting_key = 'mail_password'");
    $t->assert(!str_contains($raw, 'S3cret'), 'stored encrypted');
    SettingsService::flush();
    $t->equals('S3cret!', SettingsService::get('mail_password'));
    $t->equals(['new', 'awaiting_confirmation', 'confirmed'], SettingsService::get('order_cancel_allowed_statuses'));
});
$t->test('document numbers are sequential per type and year', function (TestRunner $t): void {
    $a = SequenceService::next('invoice', 2026);
    $b = SequenceService::next('invoice', 2026);
    $c = SequenceService::next('quotation', 2026);
    $d = SequenceService::next('invoice', 2027);
    $t->equals('FAC-2026-000001', $a);
    $t->equals('FAC-2026-000002', $b);
    $t->equals('DEV-2026-000001', $c);
    $t->equals('FAC-2027-000001', $d);
});
$t->test('transactions roll back (including nested savepoints)', function (TestRunner $t) use ($db): void {
    $before = (int) $db->value('SELECT COUNT(*) FROM suppliers');
    try {
        $db->transaction(function (Database $db): void {
            $db->insert('suppliers', ['name' => 'Outer']);
            try {
                $db->transaction(function (Database $db): void {
                    $db->insert('suppliers', ['name' => 'Inner']);
                    throw new RuntimeException('inner fail');
                });
            } catch (RuntimeException) {
            }
            $t = (int) $db->value("SELECT COUNT(*) FROM suppliers WHERE name = 'Inner'");
            if ($t !== 0) {
                throw new LogicException('savepoint not rolled back');
            }
            throw new RuntimeException('outer fail');
        });
    } catch (RuntimeException $e) {
        $t->equals('outer fail', $e->getMessage());
    }
    $t->equals($before, (int) $db->value('SELECT COUNT(*) FROM suppliers'));
    $t->assert(!$db->pdo()->inTransaction());
});
$t->test('query builder binds values safely', function (TestRunner $t) use ($db): void {
    $evil = "x' OR '1'='1";
    $t->equals(0, $db->table('users')->where('email', $evil)->count());
    $t->throws(fn () => $db->table('users')->where('email; DROP TABLE users', 'x')->count(), InvalidArgumentException::class);
    $t->throws(fn () => $db->table('users')->orderBy('id; --')->get(), InvalidArgumentException::class);
    $t->throws(fn () => $db->table('users')->delete(), LogicException::class);
    $p = $db->table('categories')->orderBy('sort_order')->paginate(5, 2);
    $t->equals(13, $p->total);
    $t->equals(5, count($p->items));
    $t->equals(3, $p->lastPage());
});

$t->section('Authentication & RBAC');
$installer = new InstallerService($base);
$adminId = $installer->createAdmin($db, 'Owner', 'Owner@Example.ma', 'Secret123');
$mkUser = function (string $role, string $email) use ($db): int {
    return $db->insert('users', [
        'role_id' => (int) $db->value('SELECT id FROM roles WHERE slug = ?', [$role]),
        'name' => ucfirst($role), 'email' => $email, 'password_hash' => Auth::hashPassword('Password1'), 'is_active' => 1,
    ]);
};
$designerId = $mkUser('designer', 'designer@example.ma');
$accountantId = $mkUser('accountant', 'compta@example.ma');
$adminRoleId = $mkUser('administrator', 'admin2@example.ma');
$request = new Request([], [], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_USER_AGENT' => 'tests', 'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php']);
Request::setCurrent($request);
Session::start($request);

$t->test('admin login with correct and wrong credentials', function (TestRunner $t) use ($request, $adminId, $db): void {
    $t->equals(['ok' => false, 'error' => 'invalid'], Auth::attemptAdmin('owner@example.ma', 'wrong', $request));
    $t->equals(['ok' => true], Auth::attemptAdmin(' OWNER@example.ma ', 'Secret123', $request), 'case-insensitive email');
    $t->equals($adminId, (int) Auth::user()['id']);
    $t->equals('super_admin', Auth::user()['role_slug']);
    $t->assert($db->value('SELECT last_login_at FROM users WHERE id = ?', [$adminId]) !== null);
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM login_attempts WHERE throttle_key = 'login_admin:owner@example.ma' AND successful = 0"), 'failures cleared on success');
    Auth::logoutAdmin();
    $t->equals(null, Auth::user());
});
$t->test('passwords are stored with password_hash()', function (TestRunner $t) use ($db, $adminId): void {
    $hash = (string) $db->value('SELECT password_hash FROM users WHERE id = ?', [$adminId]);
    $t->assert(password_get_info($hash)['algo'] !== null && $hash !== 'Secret123' && password_verify('Secret123', $hash));
});
$t->test('login is rate limited after 5 failures', function (TestRunner $t) use ($request): void {
    for ($i = 0; $i < 5; $i++) {
        Auth::attemptAdmin('designer@example.ma', 'nope' . $i, $request);
    }
    $r = Auth::attemptAdmin('designer@example.ma', 'Password1', $request);
    $t->equals('throttled', $r['error'] ?? null, 'correct password refused while locked');
    $t->assert(($r['retry_after'] ?? 0) > 0);
    RateLimiter::clear(RateLimiter::key('login_admin', 'designer@example.ma'));
    $t->equals(['ok' => true], Auth::attemptAdmin('designer@example.ma', 'Password1', $request));
    Auth::logoutAdmin();
});
$t->test('request throttles are independent per route (heavy use of one never blocks another)', function (TestRunner $t): void {
    for ($i = 0; $i < 30; $i++) {
        RateLimiter::hit('throttle:/builder/upload|10.9.9.9', '10.9.9.9');
    }
    $t->equals(30, RateLimiter::hitsFor('throttle:/builder/upload|10.9.9.9', 10));
    $t->equals(0, RateLimiter::hitsFor('throttle:/admin/forgot-password|10.9.9.9', 10));
    $t->assert(!RateLimiter::tooManyAttempts(RateLimiter::key('login_admin', 'someone@example.ma'), '10.9.9.9', 5, 15), 'login limiter unaffected');
});
$t->test('inactive accounts cannot log in', function (TestRunner $t) use ($request, $db, $accountantId): void {
    $db->update('users', ['is_active' => 0], ['id' => $accountantId]);
    $t->equals('inactive', Auth::attemptAdmin('compta@example.ma', 'Password1', $request)['error'] ?? null);
    $db->update('users', ['is_active' => 1], ['id' => $accountantId]);
});
$t->test('role permissions are enforced', function (TestRunner $t) use ($request): void {
    $check = function (string $email, string $pass) use ($request): array {
        Auth::attemptAdmin($email, $pass, $request);
        $r = [];
        foreach (['settings.manage', 'roles.manage', 'products.manage', 'invoices.manage', 'production.manage', 'artwork.approve'] as $p) {
            $r[$p] = Auth::can($p);
        }
        Auth::logoutAdmin();
        return $r;
    };
    $t->equals(['settings.manage' => true, 'roles.manage' => true, 'products.manage' => true, 'invoices.manage' => true, 'production.manage' => true, 'artwork.approve' => true], $check('owner@example.ma', 'Secret123'), 'super admin');
    $t->equals(['settings.manage' => true, 'roles.manage' => false, 'products.manage' => true, 'invoices.manage' => true, 'production.manage' => true, 'artwork.approve' => true], $check('admin2@example.ma', 'Password1'), 'administrator');
    $t->equals(['settings.manage' => false, 'roles.manage' => false, 'products.manage' => true, 'invoices.manage' => false, 'production.manage' => false, 'artwork.approve' => true], $check('designer@example.ma', 'Password1'), 'designer');
    $t->equals(['settings.manage' => false, 'roles.manage' => false, 'products.manage' => false, 'invoices.manage' => true, 'production.manage' => false, 'artwork.approve' => false], $check('compta@example.ma', 'Password1'), 'accountant');
    $t->assert(!Auth::can('settings.manage'), 'guest has nothing');
});
$t->test('password change invalidates other sessions', function (TestRunner $t) use ($request, $db, $designerId): void {
    Auth::attemptAdmin('designer@example.ma', 'Password1', $request);
    $t->assert(Auth::user() !== null);
    $db->update('users', ['password_changed_at' => date('Y-m-d H:i:s', time() + 5)], ['id' => $designerId]);
    Auth::refresh();
    $t->equals(null, Auth::user(), 'session dropped after password change elsewhere');
    $db->update('users', ['password_changed_at' => null], ['id' => $designerId]);
});
$t->test('customer register/login guard is separate from staff', function (TestRunner $t) use ($request, $db): void {
    $cid = $db->insert('customers', ['first_name' => 'Salma', 'last_name' => 'B', 'email' => 'salma@example.ma', 'phone' => '0612345678', 'password_hash' => Auth::hashPassword('Client123')]);
    $t->equals('invalid', Auth::attemptAdmin('salma@example.ma', 'Client123', $request)['error'] ?? null, 'customer cannot use admin login');
    $t->equals(['ok' => true], Auth::attemptCustomer('salma@example.ma', 'Client123', $request));
    $t->equals($cid, (int) Auth::customer()['id']);
    $t->equals(null, Auth::user());
    Auth::logoutCustomer();
    $guest = $db->insert('customers', ['first_name' => 'Guest', 'email' => 'guest@example.ma', 'is_guest' => 1, 'password_hash' => Auth::hashPassword('Guest1234')]);
    $t->equals('invalid', Auth::attemptCustomer('guest@example.ma', 'Guest1234', $request)['error'] ?? null, 'guest records cannot log in');
    $t->assert($guest > 0);
});
$t->test('password reset tokens: hashed, single use, expiring', function (TestRunner $t) use ($db, $designerId): void {
    $token = PasswordResetService::createToken('user', $designerId);
    $t->equals(64, strlen($token));
    $t->equals(0, (int) $db->value('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$token]), 'raw token never stored');
    $t->assert(PasswordResetService::find('customer', $token) === null, 'guard bound');
    $t->assert(PasswordResetService::reset('user', $token, 'NewPass99'));
    $t->assert(!PasswordResetService::reset('user', $token, 'Again999'), 'single use');
    $t->assert(password_verify('NewPass99', (string) $db->value('SELECT password_hash FROM users WHERE id = ?', [$designerId])));
    $expired = PasswordResetService::createToken('user', $designerId);
    $db->query('UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE token_hash = ?', [hash('sha256', $expired)]);
    $t->assert(PasswordResetService::find('user', $expired) === null, 'expired');
});
$t->test('reset request e-mails only existing accounts', function (TestRunner $t) use ($request): void {
    \App\Services\Mailer::$sent = [];
    PasswordResetService::request('user', 'unknown@example.ma', $request);
    $t->equals(0, count(\App\Services\Mailer::$sent));
    PasswordResetService::request('user', 'compta@example.ma', $request);
    $t->equals(1, count(\App\Services\Mailer::$sent));
    $t->contains('/admin/reset-password/', \App\Services\Mailer::$sent[0]['html']);
});

$t->section('Secure image uploads');
$makeFile = function (string $content, string $name): array {
    $tmp = tempnam(sys_get_temp_dir(), 'bwa');
    file_put_contents($tmp, $content);
    return ['name' => $name, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
};
$t->test('valid PNG is stored with a random name and re-encoded', function (TestRunner $t) use ($makeFile, $tmpPublic): void {
    $img = imagecreatetruecolor(1200, 800);
    imagefill($img, 0, 0, imagecolorallocate($img, 198, 166, 107));
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    $path = ImageUploadService::storePublic($makeFile($png, '../../evil name.png'), 'branding', 600);
    $t->assert(preg_match('#^branding/\d{4}/\d{2}/[a-f0-9]{32}\.png$#', $path) === 1, 'random path ' . $path);
    $size = getimagesize($tmpPublic . '/uploads/' . $path);
    $t->equals(600, $size[0], 'downscaled');
});
$t->test('PHP code disguised as an image is rejected', function (TestRunner $t) use ($makeFile): void {
    $t->throws(fn () => ImageUploadService::storePublic($makeFile('<?php system($_GET["c"]); ?>', 'shell.jpg'), 'products'), UploadException::class);
    $t->throws(fn () => ImageUploadService::storePublic($makeFile("GIF89a\x01\x00\x01\x00<?php ?>", 'a.gif'), 'products'), UploadException::class, 'gif not allowed');
    $t->throws(fn () => ImageUploadService::storePublic(['name' => 'x.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE], 'products'), UploadException::class);
});
$t->test('JPEG with PHP payload in metadata is neutralized by re-encoding', function (TestRunner $t) use ($makeFile, $tmpPublic): void {
    $img = imagecreatetruecolor(50, 50);
    ob_start();
    imagejpeg($img);
    $jpg = (string) ob_get_clean();
    $payload = substr($jpg, 0, 2) . "\xFF\xFE\x00\x1A<?php echo 'pwned'; ?>\x00\x00" . substr($jpg, 2);
    $path = ImageUploadService::storePublic($makeFile($payload, 'photo.jpg'), 'products');
    $t->assert(!str_contains((string) file_get_contents($tmpPublic . '/uploads/' . $path), '<?php'), 'payload stripped');
});


$t->section('Catalogue (Phase 2)');
$demo = new \App\Services\Demo\DemoCatalog($db, $tmpPublic, $base);
$t->test('demo catalogue installs with generated images and real-rate variant prices', function (TestRunner $t) use ($demo, $db, $tmpPublic): void {
    $counts = $demo->install();
    $t->equals(19, $counts['products']);
    $t->assert($counts['variants'] > 100, 'variants ' . $counts['variants']);
    $t->equals(42, count(glob($tmpPublic . '/uploads/products/demo/*.jpg')));
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM reviews"), 'no fake reviews');
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM orders"), 'no fake orders');
    // 30x40 canvas = max(minimum 199, 650/m2 * 0.12 m2 = 78) = 199 -> rounded to 199
    $t->equals('199.00', (string) $db->value("SELECT price FROM product_variants WHERE sku = (SELECT CONCAT(sku, '-30X40') FROM products WHERE slug = 'zellige-de-fes')"));
    $t->throws(fn () => $demo->install(), RuntimeException::class, 'second install refused');
});
$t->test('listing: filters, search (FR + AR), sorting and pagination', function (TestRunner $t): void {
    Translator::setLocale('fr');
    $all = CatalogService::listing([], 60, 1);
    $t->equals(19, $all->total);
    $t->equals(3, CatalogService::listing(['category' => 'nature-landscapes'], 60, 1)->total);
    $t->equals(2, CatalogService::listing(['q' => 'dunes'], 60, 1)->total);
    $t->equals(1, CatalogService::listing(['q' => 'شفشاون'], 60, 1)->total, 'arabic title search');
    $t->equals(1, CatalogService::listing(['type' => 'limited'], 60, 1)->total);
    $t->equals(3, CatalogService::listing(['flag' => 'customizable'], 60, 1)->total);
    $asc = CatalogService::listing(['sort' => 'price_asc'], 60, 1)->items;
    $prices = array_map(fn ($p) => Money::toCents($p['display_price']), $asc);
    $sorted = $prices;
    sort($sorted);
    $t->equals($sorted, $prices, 'price ascending');
    $cheap = CatalogService::listing(['max' => '100'], 60, 1)->items;
    $t->assert($cheap !== [] && max(array_map(fn ($p) => Money::toCents($p['display_price']), $cheap)) <= 10000, 'max price filter');
    $page2 = CatalogService::listing([], 8, 3);
    $t->equals(3, count($page2->items));
    $t->equals(0, CatalogService::listing(['category' => 'does-not-exist'], 60, 1)->total);
    $t->equals(19, CatalogService::listing(['min' => 'abc', 'type' => 'zzz', 'sort' => 'x; DROP'], 60, 1)->total, 'invalid filters ignored');
});
$t->test('promo prices respect their time window', function (TestRunner $t) use ($db): void {
    $promo = CatalogService::listing(['flag' => 'promo'], 60, 1);
    $t->equals(2, $promo->total);
    $p = $promo->items[0];
    $t->assert($p['on_sale'] && $p['discount_percent'] > 0, 'on sale');
    $t->assert(Money::compare($p['display_price'], $p['compare_price']) < 0);
    $db->query("UPDATE products SET promo_ends_at = NOW() - INTERVAL 1 DAY WHERE sku LIKE 'DEMO-%' AND promo_price IS NOT NULL");
    $t->equals(0, CatalogService::listing(['flag' => 'promo'], 60, 1)->total, 'expired promo not applied');
    $prod = CatalogService::product('aube-sur-atlas');
    $t->equals($prod['variants'][0]['regular_price'], $prod['variants'][0]['effective_price'], 'variant promo follows product window');
    $db->query("UPDATE products SET promo_ends_at = NOW() + INTERVAL 30 DAY WHERE sku LIKE 'DEMO-%' AND promo_price IS NOT NULL");
    $prod = CatalogService::product('aube-sur-atlas');
    $t->assert(Money::compare($prod['variants'][0]['effective_price'], $prod['variants'][0]['regular_price']) < 0, 'active again');
});
$t->test('product detail: images, variants, translations, related products', function (TestRunner $t): void {
    Translator::setLocale('ar');
    $p = CatalogService::product('zellige-de-fes');
    $t->equals('زليج فاس', $p['title']);
    $t->equals('الفن الإسلامي', $p['category']['name']);
    Translator::setLocale('fr');
    $p = CatalogService::product('zellige-de-fes');
    $t->equals('Zellige de Fès', $p['title']);
    $t->equals(2, count($p['images']));
    $t->equals(4, count($p['variants']));
    $t->equals('30 × 40 cm', $p['variants'][0]['size_label']);
    $t->equals(true, $p['from_price']);
    $rel = CatalogService::related($p, 4);
    $t->equals(3, count($rel), 'same category + same collection candidates');
    $t->assert(!in_array($p['id'], array_column($rel, 'id'), true), 'not related to itself');
    $t->equals(null, CatalogService::product('nope'));
});
$t->test('inactive or deleted products are never shown', function (TestRunner $t) use ($db): void {
    $db->query("UPDATE products SET is_active = 0 WHERE slug = 'orbites'");
    $t->equals(null, CatalogService::product('orbites'));
    $t->equals(18, CatalogService::listing([], 60, 1)->total);
    $db->query("UPDATE products SET is_active = 1, deleted_at = NOW() WHERE slug = 'orbites'");
    $t->equals(18, CatalogService::listing([], 60, 1)->total);
    $db->query("UPDATE products SET deleted_at = NULL WHERE slug = 'orbites'");
});
$t->test('collections, categories counts, FAQ translations', function (TestRunner $t): void {
    $t->equals(4, count(CatalogService::collections()));
    $c = CatalogService::collectionBySlug('noir-et-or');
    $t->equals(1, (int) $c['is_limited']);
    $cats = array_column(CatalogService::categories(), 'product_count', 'slug');
    $t->equals(3, (int) $cats['nature-landscapes']);
    Translator::setLocale('ar');
    $t->equals('هل يمكنني رؤية معاينة قبل الطباعة؟', CatalogService::faqs()[4]['question']);
    Translator::setLocale('fr');
});
$t->test('category tree includes sub-categories', function (TestRunner $t) use ($db): void {
    $parent = (int) $db->value("SELECT id FROM categories WHERE slug = 'nature-landscapes'");
    $child = $db->insert('categories', ['parent_id' => $parent, 'slug' => 'deserts-test', 'name' => 'Déserts']);
    $db->query("UPDATE products SET category_id = ? WHERE slug = 'dunes-de-merzouga'", [$child]);
    $t->equals(3, CatalogService::listing(['category' => 'nature-landscapes'], 60, 1)->total, 'child products included');
    $t->equals(1, CatalogService::listing(['category' => 'deserts-test'], 60, 1)->total);
    $db->query("UPDATE products SET category_id = ? WHERE slug = 'dunes-de-merzouga'", [$parent]);
    $db->delete('categories', ['id' => $child]);
});
$t->test('JSON-LD is valid and cannot break out of its script tag', function (TestRunner $t): void {
    $json = \App\Services\SeoService::encode(['name' => '</script><script>alert(1)</script>', 'q' => "l'été"]);
    $t->assert(!str_contains($json, '</script>') && !str_contains($json, '<'), 'escaped');
    $t->equals('</script><script>alert(1)</script>', json_decode($json, true)['name']);
    $ld = \App\Services\SeoService::product(CatalogService::product('dunes-de-merzouga'));
    $t->equals('AggregateOffer', $ld['offers']['@type']);
    $t->equals('MAD', $ld['offers']['priceCurrency']);
    $t->assert(!isset($ld['aggregateRating']), 'no rating without real reviews');
});
$t->test('demo catalogue removal deletes everything it created', function (TestRunner $t) use ($demo, $db, $tmpPublic): void {
    $out = $demo->remove();
    $t->equals(19, $out['products']);
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM products"));
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM collections"));
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM faqs"));
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM translations WHERE entity_type IN ('product','collection','faq')"));
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM categories WHERE image LIKE 'products/demo/%'"));
    $t->equals(13, (int) $db->value("SELECT COUNT(*) FROM translations WHERE entity_type = 'category'"), 'seeded category translations kept');
    $t->equals([], glob($tmpPublic . '/uploads/products/demo/*.jpg') ?: []);
    $t->equals(0, CatalogService::listing([], 60, 1)->total);
});


$t->section('Custom Wall Art Builder (Phase 3)');
$builderTmp = sys_get_temp_dir() . '/bwa_builder_' . bin2hex(random_bytes(4));
Config::set('uploads.private_dir', $builderTmp . '/private');
Config::set('uploads.preview_dir', $builderTmp . '/previews');
\App\Services\Builder\BuilderOptions::flush();
$migrator->seed(['07_builder_options']);
$mat = fn (string $code): int => (int) $db->value('SELECT id FROM product_materials WHERE code = ?', [$code]);
$frameId = fn (string $code): int => (int) $db->value('SELECT id FROM frames WHERE code = ?', [$code]);
$jpeg = function (int $w, int $h): string {
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 160));
    imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 3), intdiv($w, 3), imagecolorallocate($im, 240, 200, 120));
    ob_start();
    imagejpeg($im, null, 90);
    return (string) ob_get_clean();
};

$t->test('pricing: area/minimum, frame perimeter, multi-panel surcharge, quantity', function (TestRunner $t) use ($mat, $frameId): void {
    $q = \App\Services\Builder\PricingService::quote(['material_id' => $mat('canvas'), 'layout' => 'single', 'panel_width_cm' => 40, 'panel_height_cm' => 60, 'quantity' => 1]);
    $t->equals('199.00', $q['unit_price'], 'canvas 40x60 hits the 199 DH minimum');
    $q = \App\Services\Builder\PricingService::quote(['material_id' => $mat('canvas'), 'layout' => 'single', 'panel_width_cm' => 100, 'panel_height_cm' => 150, 'quantity' => 1]);
    $t->equals('975.00', $q['unit_price'], '1.5 m2 x 650');
    $q = \App\Services\Builder\PricingService::quote(['material_id' => $mat('photo_paper'), 'frame_id' => $frameId('gold'), 'layout' => 'single', 'panel_width_cm' => 90, 'panel_height_cm' => 60, 'quantity' => 1]);
    $t->equals('587.00', $q['unit_price'], '226.80 paper + 3 m x 120 frame (same as the browser showed)');
    $q = \App\Services\Builder\PricingService::quote(['material_id' => $mat('canvas'), 'layout' => 'triptych', 'panel_width_cm' => 40, 'panel_height_cm' => 30, 'border' => 'wrap', 'finish' => 'matte', 'quantity' => 3]);
    $t->equals('687.00', $q['unit_price'], '3 x 199 + 15 %');
    $t->equals('2061.00', $q['total']);
    $t->equals(126.0, $q['overall_width_cm']);
    $q = \App\Services\Builder\PricingService::quote(['material_id' => $mat('canvas'), 'layout' => 'single', 'panel_width_cm' => 100, 'panel_height_cm' => 100, 'finish' => 'gloss']);
    $t->equals('710.00', $q['unit_price'], '650 + gloss 60/m2');
});
$t->test('pricing rejects invalid configurations', function (TestRunner $t) use ($mat, $frameId): void {
    $P = fn (array $c) => \App\Services\Builder\PricingService::quote($c + ['material_id' => $mat('canvas'), 'layout' => 'single', 'panel_width_cm' => 40, 'panel_height_cm' => 60]);
    $t->assert(isset($P(['material_id' => 99999])['errors']['material_id']));
    $t->assert(isset($P(['panel_width_cm' => 5])['errors']['size']), 'too small');
    $t->assert(isset($P(['panel_width_cm' => 400])['errors']['size']), 'too big');
    $t->assert(isset($P(['panel_width_cm' => 'abc'])['errors']['size']));
    $t->assert(isset($P(['material_id' => $mat('bache'), 'frame_id' => $frameId('black')])['errors']['frame_id']), 'bache cannot be framed');
    $t->assert(isset($P(['material_id' => $mat('poster'), 'border' => 'wrap'])['errors']['border']), 'no canvas border on posters');
    $t->assert(isset($P(['quantity' => 0])['errors']['quantity']));
    $t->assert(isset($P(['layout' => 'hexa'])['errors']['layout']));
    $t->equals(true, $P([])['ok']);
});
$t->test('DPI and quality thresholds', function (TestRunner $t): void {
    $t->equals(300, \App\Services\Builder\PricingService::dpi(1182, 10));
    $t->equals(299, \App\Services\Builder\PricingService::dpi(1181, 10), 'never rounded up');
    $t->equals('ok', \App\Services\Builder\PricingService::quality(150));
    $t->equals('warning', \App\Services\Builder\PricingService::quality(80));
    $t->equals('low', \App\Services\Builder\PricingService::quality(40));
});

\App\Services\OwnerToken::set(str_repeat('a', 64));
$uploads = new \App\Services\Builder\CustomerUploadService();
$upload = function (string $bin, string $name, ?string $previewBin = null) use ($uploads): array {
    $tmp = tempnam(sys_get_temp_dir(), 'bwa');
    file_put_contents($tmp, $bin);
    $prev = null;
    if ($previewBin !== null) {
        $pt = tempnam(sys_get_temp_dir(), 'bwa');
        file_put_contents($pt, $previewBin);
        $prev = ['name' => 'preview.jpg', 'tmp_name' => $pt, 'error' => UPLOAD_ERR_OK];
    }
    return $uploads->store(['name' => $name, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bin)], $prev);
};
$photo = $jpeg(3000, 2000);
$info = null;
$t->test('upload: original kept byte-for-byte outside the web root, preview + thumb created', function (TestRunner $t) use ($upload, $photo, $db, $builderTmp, &$info): void {
    $info = $upload($photo, 'Mariage été.jpg');
    $row = $db->fetch('SELECT * FROM uploaded_images WHERE uuid = ?', [$info['uuid']]);
    $t->equals(3000, (int) $row['width_px']);
    $t->equals(hash('sha256', $photo), $row['checksum'], 'original untouched');
    $t->equals($photo, file_get_contents($builderTmp . '/private/' . $row['stored_path']));
    $t->assert(is_file($builderTmp . '/previews/' . $row['preview_path']) && getimagesize($builderTmp . '/previews/' . $row['preview_path'])[0] === 1600, 'server preview 1600px');
    $t->assert(is_file($builderTmp . '/previews/' . $row['thumb_path']), 'thumb');
    $t->equals('temporary', $row['status']);
    $t->assert(strtotime($row['retention_until']) > time() + 29 * 86400, 'retention date set');
    $t->assert(!str_contains($row['stored_path'], 'Mariage'), 'random file name');
});
$t->test('upload: browser preview is used when it matches the photo proportions', function (TestRunner $t) use ($upload, $photo, $jpeg, $db, $builderTmp): void {
    $i = $upload($photo, 'a.jpg', $jpeg(1600, 1067));
    $t->assert($i['has_preview']);
    $i2 = $upload($photo, 'b.jpg', $jpeg(500, 1000)); // wrong proportions: ignored, server fallback
    $t->assert($i2['has_preview'], 'fallback preview');
    $row = $db->fetch('SELECT preview_path FROM uploaded_images WHERE uuid = ?', [$i2['uuid']]);
    $t->equals(1600, getimagesize($builderTmp . '/previews/' . $row['preview_path'])[0]);
});
$t->test('upload: tiny, fake and oversized files are refused', function (TestRunner $t) use ($upload, $jpeg): void {
    $t->throws(fn () => $upload($jpeg(150, 100), 'tiny.jpg'), UploadException::class, 'too small');
    $t->throws(fn () => $upload('<?php echo 1;', 'x.jpg'), UploadException::class, 'php');
    $t->throws(fn () => $upload("GIF89a" . str_repeat("\0", 100), 'x.gif'), UploadException::class, 'gif');
    \App\Services\SettingsService::set('upload_max_mb', '1');
    $big = $jpeg(4000, 4000);
    $t->assert(strlen($big) < 1048576 || true);
    $noise = imagecreatetruecolor(2500, 2500);
    for ($i = 0; $i < 400000; $i++) { imagesetpixel($noise, random_int(0, 2499), random_int(0, 2499), random_int(0, 0xFFFFFF)); }
    ob_start(); imagejpeg($noise, null, 100); $heavy = (string) ob_get_clean();
    $t->assert(strlen($heavy) > 1048576, 'test file > 1 MB');
    $t->throws(fn () => $upload($heavy, 'heavy.jpg'), UploadException::class, 'over the admin limit');
    \App\Services\SettingsService::set('upload_max_mb', '25');
});
$t->test('photos are private: other visitors cannot access them', function (TestRunner $t) use (&$info): void {
    $t->assert(\App\Services\Builder\CustomerUploadService::findAccessible($info['uuid']) !== null, 'owner');
    \App\Services\OwnerToken::set(str_repeat('b', 64));
    $t->equals(null, \App\Services\Builder\CustomerUploadService::findAccessible($info['uuid']), 'other guest');
    $t->equals(null, \App\Services\Builder\CustomerUploadService::findAccessible('not-a-uuid'));
    \App\Services\OwnerToken::set(str_repeat('a', 64));
});

$designIn = function (array $over = []) use ($mat, &$info): array {
    return array_replace_recursive([
        'material_id' => $mat('canvas'), 'layout' => 'single', 'mode' => 'span', 'panel_width_cm' => 60, 'panel_height_cm' => 40,
        'border' => 'wrap', 'finish' => 'matte', 'quantity' => 2,
        'images' => [['uuid' => $info['uuid'], 'panel' => 0, 'crop' => ['x' => 0, 'y' => 0, 'width' => 3000, 'height' => 2000, 'rotate' => 0]]],
    ], $over);
};
$t->test('design save: server re-prices, checks crop & DPI, attaches the photo', function (TestRunner $t) use ($designIn, $db, &$info): void {
    $r = \App\Services\Builder\DesignService::save($designIn(['preview' => 'data:image/jpeg;base64,' . base64_encode((function () { $im = imagecreatetruecolor(600, 400); ob_start(); imagejpeg($im); return ob_get_clean(); })())]));
    $t->equals('199.00', $r['unit_price']);
    $t->equals('398.00', $r['total']);
    $t->equals(127, $r['min_dpi'], '3000 px over 60 cm');
    $t->equals('ok', $r['quality']);
    $d = $db->fetch('SELECT * FROM custom_designs WHERE uuid = ?', [$r['uuid']]);
    $t->assert($d['preview_path'] !== null, 'preview stored');
    $t->equals('attached', (string) $db->value('SELECT status FROM uploaded_images WHERE uuid = ?', [$info['uuid']]));
    $crop = json_decode((string) $db->value('SELECT crop FROM custom_design_images WHERE custom_design_id = ?', [(int) $d['id']]), true);
    $t->equals(['x' => 0, 'y' => 0, 'width' => 3000, 'height' => 2000, 'rotate' => 0], $crop);
    $t->assert(\App\Services\Builder\DesignService::findAccessible($r['uuid']) !== null);
    \App\Services\OwnerToken::set(str_repeat('c', 64));
    $t->equals(null, \App\Services\Builder\DesignService::findAccessible($r['uuid']), 'other visitor');
    \App\Services\OwnerToken::set(str_repeat('a', 64));
});
$t->test('design save: rotated crop uses swapped dimensions', function (TestRunner $t) use ($designIn): void {
    $r = \App\Services\Builder\DesignService::save($designIn(['panel_width_cm' => 40, 'panel_height_cm' => 60, 'images' => [['crop' => ['x' => 0, 'y' => 0, 'width' => 2000, 'height' => 3000, 'rotate' => 90]]]]));
    $t->equals('ok', $r['quality']);
    $t->equals(null, \App\Services\Builder\DesignService::validateCrop(['x' => 0, 'y' => 0, 'width' => 2000, 'height' => 3000, 'rotate' => 0], 3000, 2000, 2 / 3), 'without rotation it overflows');
    $t->equals(null, \App\Services\Builder\DesignService::validateCrop(['x' => 0, 'y' => 0, 'width' => 2000, 'height' => 2000, 'rotate' => 45], 3000, 2000, 1.0), '45 degrees refused');
});
$t->test('design save refuses tampered or low-quality configurations', function (TestRunner $t) use ($designIn, $upload, $jpeg): void {
    $E = function (array $over) use ($designIn): string {
        try {
            \App\Services\Builder\DesignService::save($designIn($over));
        } catch (\App\Core\ValidationException $e) {
            return (string) array_key_first($e->errors);
        }
        return 'saved';
    };
    $t->equals('images', $E(['images' => [['crop' => ['width' => 3000, 'height' => 1000]]]]), 'wrong ratio');
    $t->equals('images', $E(['images' => [['crop' => ['x' => 500, 'width' => 3000]]]]), 'outside image');
    $t->equals('images', $E(['panel_width_cm' => 300, 'panel_height_cm' => 200, 'border' => null, 'material_id' => (int) \App\Core\Database::instance()->value("SELECT id FROM product_materials WHERE code = 'bache'")]), 'DPI below minimum');
    $t->equals('images', $E(['layout' => 'triptych', 'mode' => 'per_panel']), 'per-panel needs 3 photos');
    $t->equals('images', $E(['images' => [['uuid' => '00000000-0000-4000-8000-000000000000']]]), 'unknown photo');
    $t->equals('frame_id', $E(['material_id' => (int) \App\Core\Database::instance()->value("SELECT id FROM product_materials WHERE code = 'bache'"), 'frame_id' => 1, 'border' => null, 'panel_width_cm' => 30, 'panel_height_cm' => 20]), 'invalid option');
    $t->equals('product', $E(['product' => 'not-customizable-product']));
    // a photo of another visitor cannot be used
    \App\Services\OwnerToken::set(str_repeat('d', 64));
    $foreign = $upload($jpeg(1200, 800), 'other.jpg');
    \App\Services\OwnerToken::set(str_repeat('a', 64));
    $t->equals('images', $E(['images' => [['uuid' => $foreign['uuid']]]]), 'foreign photo');
});
$t->test('guest photos and designs are claimed by the account on login', function (TestRunner $t) use ($db, &$info, $request): void {
    $cid = $db->insert('customers', ['first_name' => 'Nora', 'email' => 'nora@example.ma', 'password_hash' => Auth::hashPassword('Client123')]);
    Auth::loginCustomer($cid);
    $t->equals($cid, (int) $db->value('SELECT customer_id FROM uploaded_images WHERE uuid = ?', [$info['uuid']]));
    $t->assert((int) $db->value('SELECT COUNT(*) FROM custom_designs WHERE customer_id = ?', [$cid]) >= 2);
    Auth::logoutCustomer();
    // once claimed, the guest token alone no longer opens it
    $t->equals(null, \App\Services\Builder\CustomerUploadService::findAccessible($info['uuid'], false));
});
$t->test('retention: expired photos are purged and their designs expire', function (TestRunner $t) use ($db, $uploads, $builderTmp, &$info): void {
    $row = $db->fetch('SELECT * FROM uploaded_images WHERE uuid = ?', [$info['uuid']]);
    $db->query('UPDATE uploaded_images SET retention_until = NOW() - INTERVAL 1 DAY WHERE uuid = ?', [$info['uuid']]);
    $t->assert($uploads->purgeExpired() >= 1);
    $after = $db->fetch('SELECT * FROM uploaded_images WHERE uuid = ?', [$info['uuid']]);
    $t->equals('purged', $after['status']);
    $t->assert(!is_file($builderTmp . '/private/' . $row['stored_path']) && !is_file($builderTmp . '/previews/' . $row['preview_path']), 'files deleted');
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM custom_designs d JOIN custom_design_images di ON di.custom_design_id = d.id JOIN uploaded_images u ON u.id = di.uploaded_image_id WHERE u.uuid = ? AND d.status = 'draft'", [$info['uuid']]));
    $db->query("UPDATE uploaded_images SET status = 'ordered', retention_until = NOW() + INTERVAL 1 DAY WHERE status <> 'purged' LIMIT 1");
    $t->equals(0, $uploads->purgeExpired(), 'nothing else expired');
});


$t->section('Cart, checkout & orders (Phase 4)');
Auth::logoutCustomer();
\App\Services\OwnerToken::set(str_repeat('e', 64));
\App\Services\Sales\CartService::setToken(str_repeat('1', 64));
$demo->install();
$pid = fn (string $slug): int => (int) $db->value('SELECT id FROM products WHERE slug = ?', [$slug]);
$vid = fn (string $slug, int $n = 0): int => (int) $db->value('SELECT id FROM product_variants WHERE product_id = ? ORDER BY sort_order LIMIT 1 OFFSET ' . $n, [$pid($slug)]);
$place = function (array $over = []): array {
    return \App\Services\Sales\OrderService::place($over + [
        'full_name' => 'Yassine El Amrani', 'phone' => '06 61 23 45 67', 'email' => 'yassine@client.ma', 'country' => 'MA',
        'city' => 'Casablanca', 'address' => '12 Rue des Orangers, Maârif', 'postal_code' => '20330', 'delivery_instructions' => null,
        'payment_method' => 'cod',
    ], '10.0.0.5');
};

$t->test('shipping: city rates (accent-insensitive), fallback flat rate, free threshold, business days', function (TestRunner $t): void {
    $S = \App\Services\Sales\ShippingService::class;
    $q = $S::quote('casablanca', '100.00');
    $t->equals('25.00', $q['price']);
    $t->equals(true, $q['known_city']);
    $t->equals('40.00', $S::quote('  FES ', '100.00')['price'], 'Fès matched without accent');
    $t->equals('0.00', $S::quote('Casablanca', '800.00')['price'], 'zone free threshold 800');
    $u = $S::quote('Ifrane', '100.00');
    $t->equals(false, $u['known_city']);
    $t->equals('40.00', $u['price'], 'flat rate');
    $t->equals('0.00', $S::quote('Ifrane', '100.00', true)['price'], 'free-shipping coupon');
    $t->equals('2026-11-30', $S::addBusinessDays(1, '2026-11-28'), 'Saturday + 1 working day = Monday (Sunday skipped)');
    $t->assert(in_array('Marrakech', $S::cities(), true));
});
$t->test('coupons: percent, fixed + minimum, scope, dates, usage limits', function (TestRunner $t) use ($db, $pid): void {
    $cat = (int) $db->value("SELECT id FROM categories WHERE slug = 'nature-landscapes'");
    $db->query("INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount_amount, category_id, usage_limit, starts_at, ends_at, is_active) VALUES
        ('BIENVENUE10','percent',10,NULL,NULL,NULL,NULL,NULL,NULL,1),
        ('MOINS100','fixed',100,500,NULL,NULL,NULL,NULL,NULL,1),
        ('NATURE20','percent',20,NULL,50,?,NULL,NULL,NULL,1),
        ('OLD','percent',50,NULL,NULL,NULL,NULL,NULL,'2020-01-01',1),
        ('UNIQUE','fixed',30,NULL,NULL,NULL,1,NULL,NULL,1),
        ('LIVRAISON','free_shipping',0,NULL,NULL,NULL,NULL,NULL,NULL,1)", [$cat]);
    $C = \App\Services\Sales\CouponService::class;
    $lines = [['product_id' => $pid('aube-sur-atlas'), 'category_id' => $cat, 'line_total' => '300.00'], ['product_id' => $pid('orbites'), 'category_id' => 999, 'line_total' => '200.00']];
    $t->equals('50.00', $C::evaluate('bienvenue10', $lines, '500.00')['discount'], 'case-insensitive, 10 %');
    $t->equals('100.00', $C::evaluate('MOINS100', $lines, '500.00')['discount']);
    $t->equals(false, $C::evaluate('MOINS100', $lines, '499.99')['ok'], 'minimum order');
    $t->equals('50.00', $C::evaluate('NATURE20', $lines, '500.00')['discount'], '20 % of 300 = 60 capped at 50');
    $t->equals(false, $C::evaluate('NATURE20', [$lines[1]], '200.00')['ok'], 'category scope');
    $t->equals(false, $C::evaluate('OLD', $lines, '500.00')['ok'], 'expired');
    $t->equals(true, $C::evaluate('LIVRAISON', $lines, '500.00')['free_shipping']);
    $t->equals(false, $C::evaluate("x' OR 1=1 --", $lines, '500.00')['ok']);
});
$t->test('cart: variants required, customizable products go through the builder, stock limits, merge lines', function (TestRunner $t) use ($pid, $vid, $db): void {
    $Cart = \App\Services\Sales\CartService::class;
    $t->equals(false, $Cart::addProduct($pid('aube-sur-atlas'), null, 1)['ok'], 'a size must be chosen');
    $t->equals(false, $Cart::addProduct($pid('toile-photo-personnalisee'), $vid('toile-photo-personnalisee'), 1)['ok'], 'needs a photo');
    $t->equals(true, $Cart::addProduct($pid('aube-sur-atlas'), $vid('aube-sur-atlas'), 1)['ok']);
    $t->equals(true, $Cart::addProduct($pid('aube-sur-atlas'), $vid('aube-sur-atlas'), 2)['ok']);
    $t->equals(3, $Cart::count(), 'same variant merged');
    $t->equals(false, $Cart::addProduct($pid('eclipse-doree'), $vid('eclipse-doree'), 26)['ok'], 'limited edition: 25 in stock');
    $t->equals(true, $Cart::addProduct($pid('eclipse-doree'), $vid('eclipse-doree'), 2)['ok']);
    $t->equals(false, $Cart::addProduct($pid('orbites'), $vid('orbites'), 0)['ok'], 'quantity >= 1');
    $cart = $Cart::current(false);
    $lines = $Cart::lines($cart);
    $t->equals(2, count($lines));
    $t->equals(true, $lines[0]['available']);
    // a variant of another product is refused
    $t->equals(false, $Cart::addProduct($pid('orbites'), $vid('aube-sur-atlas'), 1)['ok']);
    $t->equals(true, $Cart::updateQuantity($lines[1]['id'], 1)['ok']);
    $foreign = $db->insert('carts', ['token' => hash('sha256', 'other'), 'status' => 'active']);
    $fitem = $db->insert('cart_items', ['cart_id' => $foreign, 'product_id' => $pid('orbites'), 'product_variant_id' => $vid('orbites'), 'quantity' => 1, 'unit_price' => '1.00']);
    $t->equals(false, $Cart::remove($fitem)['ok'], 'cannot touch another visitor cart');
});
$t->test('totals: prices TTC (TVA included) and prices HT (TVA added)', function (TestRunner $t): void {
    $Cart = \App\Services\Sales\CartService::class;
    $cart = $Cart::current(false);
    $lines = $Cart::lines($cart);
    $sub = \App\Helpers\Money::add('0', ...array_map(fn ($l) => $l['line_total'], $lines));
    $T = \App\Services\Sales\CheckoutCalculator::totals($cart, $lines, 'Rabat');
    $t->equals($sub, $T['subtotal']);
    $base = \App\Helpers\Money::add($sub, $T['shipping_total']);
    $t->equals($base, $T['grand_total'], 'TTC: total unchanged');
    $t->equals(\App\Helpers\Money::sub($base, \App\Helpers\Money::htFromTtc($base, '20')), $T['tax_total']);
    \App\Services\SettingsService::set('tax_prices_include_tax', false);
    $H = \App\Services\Sales\CheckoutCalculator::totals($cart, $lines, 'Rabat');
    $t->equals(\App\Helpers\Money::percent($base, '20'), $H['tax_total']);
    $t->equals(\App\Helpers\Money::add($base, $H['tax_total']), $H['grand_total']);
    \App\Services\SettingsService::set('tax_prices_include_tax', true);
    $t->equals(null, \App\Services\Sales\CheckoutCalculator::totals($cart, $lines, null)['shipping'], 'no city yet');
});
$orderA = null;
$t->test('place order: server prices (tampering ignored), snapshot, stock, coupon, history, tracking token', function (TestRunner $t) use ($db, $place, $pid, &$orderA): void {
    $Cart = \App\Services\Sales\CartService::class;
    $cart = $Cart::current(false);
    $db->query('UPDATE cart_items SET unit_price = 1.00 WHERE cart_id = ?', [(int) $cart['id']]); // tampered snapshot
    $t->equals(true, $Cart::applyCoupon('BIENVENUE10')['ok']);
    $expected = \App\Services\Sales\CheckoutCalculator::totals($Cart::current(false), $Cart::lines($Cart::current(false)), 'Casablanca');
    $stockBefore = (int) $db->value('SELECT stock_quantity FROM products WHERE id = ?', [$pid('eclipse-doree')]);
    $r = $place();
    $o = $r['order'];
    $orderA = $r;
    $t->assert(preg_match('/^BWA-\d{4}-\d{6}$/', $o['order_number']) === 1, 'order number ' . $o['order_number']);
    $t->equals($expected['grand_total'], (string) $o['grand_total']);
    $t->assert(\App\Helpers\Money::toCents((string) $o['subtotal']) > 300, 'not the tampered 1.00 prices');
    $t->equals('new', $o['status']);
    $t->equals('unpaid', $o['payment_status']);
    $t->equals(0, (int) $db->value('SELECT COUNT(*) FROM payments WHERE order_id = ?', [(int) $o['id']]), 'no payment recorded at checkout');
    $t->equals($stockBefore - 1, (int) $db->value('SELECT stock_quantity FROM products WHERE id = ?', [$pid('eclipse-doree')]));
    $t->equals(2, (int) $db->value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [(int) $o['id']]));
    $t->equals(1, (int) $db->value("SELECT used_count FROM coupons WHERE code = 'BIENVENUE10'"));
    $t->equals(1, (int) $db->value('SELECT COUNT(*) FROM order_status_history WHERE order_id = ?', [(int) $o['id']]));
    $t->equals('converted', (string) $db->value('SELECT status FROM carts WHERE id = ?', [(int) $cart['id']]));
    $t->equals(1, (int) $db->value('SELECT is_guest FROM customers WHERE id = ?', [(int) $o['customer_id']]), 'guest customer record');
    $t->equals(64, strlen((string) $o['access_token']));
    $t->assert($o['access_token'] !== $r['token'], 'only the hash is stored');
    $t->assert($o['estimated_delivery_min'] !== null && $o['estimated_delivery_max'] >= $o['estimated_delivery_min']);
    // the catalogue changing later does not alter the order
    $db->query("UPDATE products SET title = 'Renamed' WHERE id = ?", [$pid('aube-sur-atlas')]);
    $t->equals(1, (int) $db->value("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND title = 'Aube sur l’Atlas'", [(int) $o['id']]));
    $db->query("UPDATE products SET title = 'Aube sur l’Atlas' WHERE id = ?", [$pid('aube-sur-atlas')]);
    $mails = array_column(\App\Services\Mailer::$sent, 'subject');
    $t->assert(in_array('Confirmation de votre commande ' . $o['order_number'], $mails, true), 'customer e-mail');
});
$t->test('tracking: number + e-mail / phone (any format) / private token', function (TestRunner $t) use (&$orderA): void {
    $O = \App\Services\Sales\OrderService::class;
    $n = $orderA['order']['order_number'];
    $t->assert($O::findForTracking($n, 'YASSINE@client.ma') !== null);
    $t->assert($O::findForTracking(strtolower($n), '+212661234567') !== null, 'international phone format');
    $t->assert($O::findForTracking($n, '0661234567') !== null);
    $t->assert($O::findForTracking($n, null, $orderA['token']) !== null);
    $t->equals(null, $O::findForTracking($n, 'someone@else.ma'));
    $t->equals(null, $O::findForTracking($n, '0600000000'));
    $t->equals(null, $O::findForTracking($n, null, str_repeat('0', 48)));
    $t->equals(null, $O::findForTracking("' OR 1=1", 'x'));
});
$t->test('empty cart and invalid payment method are refused; online gateways are never offered', function (TestRunner $t) use ($place, $db): void {
    $e = null;
    try { $place(); } catch (\App\Core\ValidationException $ex) { $e = $ex; }
    $t->assert($e !== null && isset($e->errors['cart']), 'cart converted → empty');
    $db->query("UPDATE payment_methods SET is_active = 1 WHERE code = 'cmi'");
    $codes = array_column(\App\Services\Payments\PaymentRegistry::available(), 'code');
    $t->assert(!in_array('cmi', $codes, true), 'CMI active in DB but no gateway → not offered');
    $t->equals(['cod', 'bank_transfer', 'manual'], $codes);
    $db->query("UPDATE payment_methods SET is_active = 0 WHERE code = 'cmi'");
    \App\Services\Sales\CartService::addProduct((int) $db->value("SELECT id FROM products WHERE slug = 'orbites'"), (int) $db->value("SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.slug = 'orbites' LIMIT 1"), 1);
    $e = null;
    try { $place(['payment_method' => 'cmi']); } catch (\App\Core\ValidationException $ex) { $e = $ex; }
    $t->assert($e !== null && isset($e->errors['payment_method']));
});
$t->test('single-use coupon cannot be redeemed twice', function (TestRunner $t) use ($place, $db): void {
    $Cart = \App\Services\Sales\CartService::class;
    $t->equals(true, $Cart::applyCoupon('UNIQUE')['ok']);
    $place(['email' => 'first@client.ma']);
    $Cart::setToken(str_repeat('2', 64));
    $Cart::addProduct((int) $db->value("SELECT id FROM products WHERE slug = 'orbites'"), (int) $db->value("SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.slug = 'orbites' LIMIT 1"), 1);
    $t->equals(false, $Cart::applyCoupon('UNIQUE')['ok'], 'exhausted');
    $t->equals(1, (int) $db->value("SELECT used_count FROM coupons WHERE code = 'UNIQUE'"));
});
$t->test('design order: customizations, photo retention extended, design locked', function (TestRunner $t) use ($place, $db, $upload, $jpeg): void {
    $Cart = \App\Services\Sales\CartService::class;
    $info = $upload($jpeg(2400, 1600), 'famille.jpg');
    $d = \App\Services\Builder\DesignService::save([
        'material_id' => (int) $db->value("SELECT id FROM product_materials WHERE code = 'canvas'"), 'layout' => 'single', 'panel_width_cm' => 60, 'panel_height_cm' => 40, 'border' => 'wrap', 'quantity' => 1,
        'images' => [['uuid' => $info['uuid'], 'panel' => 0, 'crop' => ['x' => 0, 'y' => 0, 'width' => 2400, 'height' => 1600, 'rotate' => 0]]],
    ]);
    $t->equals(true, $Cart::addDesign($d['uuid'], 2)['ok']);
    \App\Services\OwnerToken::set(str_repeat('f', 64));
    $t->equals(false, $Cart::addDesign($d['uuid'], 1)['ok'], 'someone else cannot add my design');
    \App\Services\OwnerToken::set(str_repeat('e', 64));
    $r = $place(['email' => 'design@client.ma', 'payment_method' => 'bank_transfer']);
    $item = $db->fetch('SELECT * FROM order_items WHERE order_id = ? AND custom_design_id IS NOT NULL', [(int) $r['order']['id']]);
    $t->equals(2, (int) $item['quantity']);
    $t->equals('canvas', $item['product_type']);
    $t->equals(1, (int) $db->value('SELECT COUNT(*) FROM order_customizations WHERE order_item_id = ?', [(int) $item['id']]));
    $t->equals('ordered', (string) $db->value('SELECT status FROM custom_designs WHERE uuid = ?', [$d['uuid']]));
    $u = $db->fetch('SELECT status, retention_until FROM uploaded_images WHERE uuid = ?', [$info['uuid']]);
    $t->equals('ordered', $u['status']);
    $t->assert(strtotime((string) $u['retention_until']) > time() + 300 * 86400, 'kept for the ordered retention period');
    $t->equals(false, \App\Services\Builder\CustomerUploadService::isDeletable($db->fetch('SELECT * FROM uploaded_images WHERE uuid = ?', [$info['uuid']])));
    $t->equals(false, $Cart::addDesign($d['uuid'], 1)['ok'], 'an ordered design cannot be re-added');
});
$t->test('customer cancellation restocks, releases the coupon, frees designs; blocked once in production', function (TestRunner $t) use ($db, $pid, &$orderA): void {
    $O = \App\Services\Sales\OrderService::class;
    $o = $db->fetch('SELECT * FROM orders WHERE id = ?', [(int) $orderA['order']['id']]);
    $stock = (int) $db->value('SELECT stock_quantity FROM products WHERE id = ?', [$pid('eclipse-doree')]);
    $t->equals(true, $O::cancelByCustomer($o, 'Erreur de format'));
    $t->equals('cancelled', (string) $db->value('SELECT status FROM orders WHERE id = ?', [(int) $o['id']]));
    $t->equals($stock + 1, (int) $db->value('SELECT stock_quantity FROM products WHERE id = ?', [$pid('eclipse-doree')]));
    $t->equals(0, (int) $db->value("SELECT used_count FROM coupons WHERE code = 'BIENVENUE10'"));
    $t->equals(false, $O::cancelByCustomer($db->fetch('SELECT * FROM orders WHERE id = ?', [(int) $o['id']])), 'already cancelled');
    $other = $db->fetch("SELECT * FROM orders WHERE customer_email = 'design@client.ma'");
    $db->update('orders', ['status' => 'printing'], ['id' => (int) $other['id']]);
    $t->equals(false, $O::cancelByCustomer($db->fetch('SELECT * FROM orders WHERE id = ?', [(int) $other['id']])), 'production started');
});
$t->test('payments: partial then full, idempotent provider transaction, order balance in sync', function (TestRunner $t) use ($db): void {
    $o = $db->fetch("SELECT * FROM orders WHERE customer_email = 'design@client.ma'");
    $m = (int) $db->value("SELECT id FROM payment_methods WHERE code = 'bank_transfer'");
    \App\Services\Payments\PaymentService::recordConfirmed((int) $o['id'], '100.00', $m, null, 'VIR-1');
    $t->equals('partial', (string) $db->value('SELECT payment_status FROM orders WHERE id = ?', [(int) $o['id']]));
    $rest = \App\Helpers\Money::sub((string) $o['grand_total'], '100.00');
    $p1 = \App\Services\Payments\PaymentService::recordConfirmed((int) $o['id'], $rest, $m, 'TX-123');
    $p2 = \App\Services\Payments\PaymentService::recordConfirmed((int) $o['id'], $rest, $m, 'TX-123');
    $t->equals($p1, $p2, 'same provider transaction recorded once');
    $row = $db->fetch('SELECT payment_status, amount_paid, balance_due FROM orders WHERE id = ?', [(int) $o['id']]);
    $t->equals('paid', $row['payment_status']);
    $t->equals((string) $o['grand_total'], (string) $row['amount_paid']);
    $t->equals('0.00', (string) $row['balance_due']);
    $t->throws(fn () => \App\Services\Payments\PaymentService::recordConfirmed((int) $o['id'], '0', $m), InvalidArgumentException::class);
});
$t->test('checkout can create a customer account and log in', function (TestRunner $t) use ($place, $db): void {
    $Cart = \App\Services\Sales\CartService::class;
    $Cart::setToken(str_repeat('3', 64));
    $Cart::addProduct((int) $db->value("SELECT id FROM products WHERE slug = 'orbites'"), (int) $db->value("SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.slug = 'orbites' LIMIT 1"), 1);
    $r = $place(['email' => 'newacc@client.ma', 'create_account' => true, 'password' => 'Secret2026', 'save_address' => true]);
    $t->equals(true, $r['account_created']);
    $c = $db->fetch('SELECT * FROM customers WHERE email = ?', ['newacc@client.ma']);
    $t->equals(0, (int) $c['is_guest']);
    $t->assert(password_verify('Secret2026', (string) $c['password_hash']));
    $t->equals((int) $c['id'], (int) Auth::customer()['id'], 'logged in');
    $t->equals(1, (int) $db->value('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [(int) $c['id']]));
    Auth::logoutCustomer();
});

$t->section('Phase 5: admin dashboard figures & catalogue management');
$t->test('dashboard: profit = sales HT − order costs − shipping costs − expenses HT (cancelled excluded)', function (TestRunner $t) use ($db): void {
    $S = \App\Services\Admin\DashboardStats::class;
    $from = date('Y-m-01');
    $to = date('Y-m-01', strtotime($from . ' +1 month'));
    $expected = $db->fetch(
        "SELECT COALESCE(SUM(grand_total - tax_total), 0) AS ht, COALESCE(SUM(cost_total), 0) AS cost, COUNT(*) AS n
         FROM orders WHERE status NOT IN ('cancelled','returned') AND placed_at >= ? AND placed_at < ?",
        [$from, $to]
    );
    $t->assert((int) $expected['n'] > 0, 'fixtures placed orders this month');
    $before = $S::profit($from, $to);
    $t->equals(\App\Helpers\Money::normalize((string) $expected['ht']), $before['sales_ht']);
    $t->equals(\App\Helpers\Money::normalize((string) $expected['cost']), $before['production_cost']);
    $db->insert('expenses', ['category' => 'rent', 'description' => 'Loyer atelier (test)', 'amount' => '1200.00', 'tax_amount' => '200.00', 'expense_date' => date('Y-m-d')]);
    $after = $S::profit($from, $to);
    $t->equals('1000.00', \App\Helpers\Money::sub($after['expenses'], $before['expenses']), 'expense counted HT');
    $t->equals(\App\Helpers\Money::sub($before['profit'], '1000.00'), $after['profit']);
    $cancelled = (int) $db->value("SELECT COUNT(*) FROM orders WHERE status IN ('cancelled','returned')");
    $t->assert($cancelled > 0, 'fixtures contain a cancelled order');
    $t->equals((int) $expected['n'], $S::sales($from, $to)['orders'], 'cancelled orders not counted');
});
$t->test('dashboard: KPIs, 12-month series and status pipeline are consistent with the database', function (TestRunner $t) use ($db): void {
    $S = \App\Services\Admin\DashboardStats::class;
    $k = $S::kpis();
    $t->equals((int) $db->value('SELECT COUNT(*) FROM orders'), $k['orders_total']);
    $months = $S::monthly(12);
    $t->equals(12, count($months));
    $t->equals(date('Y-m'), $months[11]['month'], 'current month last');
    $t->equals($k['revenue_month'], $months[11]['sales']);
    $pipeline = $S::ordersByStatus();
    $t->equals($k['orders_total'], array_sum(array_column($pipeline, 'count')));
    $t->equals((string) $db->value("SELECT COALESCE(SUM(balance_due),0) FROM orders WHERE status NOT IN ('cancelled','returned') AND payment_status IN ('unpaid','partial')"), $k['outstanding']);
    $best = $S::bestSellers(date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('+1 day')));
    $t->assert($best !== [], 'best sellers from real order lines');
});
$t->test('catalogue: unique slugs/SKUs, save with Arabic translation and option maps', function (TestRunner $t) use ($db): void {
    $A = \App\Services\Admin\CatalogAdmin::class;
    $taken = (string) $db->value('SELECT slug FROM products LIMIT 1');
    $t->equals($taken . '-2', $A::uniqueSlug('products', $taken));
    $mat = (int) $db->value("SELECT id FROM product_materials WHERE is_active = 1 ORDER BY sort_order LIMIT 1");
    $id = $A::saveProduct(null, ['title' => 'Désert Rose', 'product_type' => 'canvas', 'selling_price' => '499.00', 'cost_price' => '120.00', 'is_active' => 1],
        ['title' => 'وردة الصحراء'], [$mat, $mat], [], [(int) $db->value('SELECT id FROM products WHERE deleted_at IS NULL LIMIT 1')]);
    $row = $db->fetch('SELECT * FROM products WHERE id = ?', [$id]);
    $t->equals('desert-rose', $row['slug']);
    $t->equals('BWA-DESERT-ROSE', $row['sku']);
    $t->equals('وردة الصحراء', \App\Services\TranslationService::get('product', $id, 'ar')['title'] ?? null);
    $t->equals(1, (int) $db->value('SELECT COUNT(*) FROM product_material_map WHERE product_id = ?', [$id]));
    $t->equals(1, (int) $db->value('SELECT COUNT(*) FROM related_products WHERE product_id = ?', [$id]));
    $id2 = $A::saveProduct(null, ['title' => 'Désert Rose', 'product_type' => 'canvas', 'selling_price' => '10.00', 'is_active' => 0], [], [], [], []);
    $t->equals('desert-rose-2', (string) $db->value('SELECT slug FROM products WHERE id = ?', [$id2]));
    $t->equals('BWA-DESERT-ROSE-2', (string) $db->value('SELECT sku FROM products WHERE id = ?', [$id2]));
});
$t->test('catalogue: variant generation uses the pricing engine, rounds to …9 and skips duplicates', function (TestRunner $t) use ($db): void {
    $A = \App\Services\Admin\CatalogAdmin::class;
    $id = (int) $db->value("SELECT id FROM products WHERE slug = 'desert-rose'");
    $canvas = (int) $db->value("SELECT id FROM product_materials WHERE code = 'canvas'");
    $frame = (int) $db->value('SELECT id FROM frames WHERE is_active = 1 ORDER BY sort_order LIMIT 1');
    $n = $A::generateVariants($id, [['w' => 40.0, 'h' => 60.0], ['w' => 60.0, 'h' => 90.0]], [$canvas], [null, $frame]);
    $t->equals(4, $n);
    $t->equals(0, $A::generateVariants($id, [['w' => 40.0, 'h' => 60.0]], [$canvas], [null]), 'existing combination skipped');
    $vs = $db->fetchAll('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order', [$id]);
    $t->equals(1, count(array_filter($vs, fn ($v) => (int) $v['is_default'] === 1)), 'exactly one default');
    foreach ($vs as $v) {
        $q = \App\Services\Builder\PricingService::quote(['material_id' => $canvas, 'frame_id' => $v['frame_id'] !== null ? (int) $v['frame_id'] : null, 'layout' => 'single', 'panel_width_cm' => (float) $v['width_cm'], 'panel_height_cm' => (float) $v['height_cm']]);
        $t->assert(\App\Helpers\Money::compare($v['price'], $q['unit_price']) >= 0, 'never below the engine price');
        $t->equals('9.00', substr(\App\Helpers\Money::normalize((string) $v['price']), -4), 'ends in 9 DH');
    }
    $t->equals((string) $db->value('SELECT MIN(price) FROM product_variants WHERE product_id = ?', [$id]), (string) $db->value('SELECT selling_price FROM products WHERE id = ?', [$id]), 'from-price synced');
});
$t->test('catalogue: duplicate creates an inactive copy with variants and translations', function (TestRunner $t) use ($db): void {
    $A = \App\Services\Admin\CatalogAdmin::class;
    $id = (int) $db->value("SELECT id FROM products WHERE slug = 'desert-rose'");
    $copy = $A::duplicate($id);
    $c = $db->fetch('SELECT * FROM products WHERE id = ?', [$copy]);
    $t->equals(0, (int) $c['is_active']);
    $t->equals('desert-rose-copie', $c['slug']);
    $t->equals((int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ?', [$id]), (int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ?', [$copy]));
    $t->equals('وردة الصحراء', \App\Services\TranslationService::get('product', $copy, 'ar')['title'] ?? null);
});
$t->test('catalogue: soft delete keeps order history, frees the slug and empties carts', function (TestRunner $t) use ($db): void {
    $A = \App\Services\Admin\CatalogAdmin::class;
    $ordered = $db->fetch('SELECT p.id, p.slug FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE p.deleted_at IS NULL LIMIT 1');
    $lines = (int) $db->value('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [(int) $ordered['id']]);
    $t->equals(true, $A::softDelete((int) $ordered['id']));
    $t->equals(false, $A::softDelete((int) $ordered['id']), 'second delete is a no-op');
    $row = $db->fetch('SELECT * FROM products WHERE id = ?', [(int) $ordered['id']]);
    $t->assert($row['deleted_at'] !== null && (int) $row['is_active'] === 0);
    $t->equals($ordered['slug'], $A::uniqueSlug('products', $ordered['slug']), 'slug reusable');
    $t->equals($lines, (int) $db->value('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [(int) $ordered['id']]), 'order lines intact');
    $t->equals(0, (int) $db->value('SELECT COUNT(*) FROM cart_items WHERE product_id = ? AND custom_design_id IS NULL', [(int) $ordered['id']]));
    $t->equals(null, \App\Services\CatalogService::product($ordered['slug']), 'hidden from the store');
});


$t->section('Phase 6: orders workflow, artwork, production, inventory, purchases');
$Inv = \App\Services\Operations\InventoryService::class;
$t->test('inventory: exact quantities, weighted average cost, exits at WAC, refusal below zero, physical count', function (TestRunner $t) use ($db, $Inv): void {
    $t->equals(1500, $Inv::toMilli('1,5'));
    $t->equals('-0.250', $Inv::fromMilli(-250));
    $id = $db->insert('materials', ['sku' => 'TOILE-TEST', 'name' => 'Toile coton 380g', 'category' => 'canvas', 'unit' => 'm2', 'min_stock' => '5.000']);
    $Inv::move($id, 'in', '10', '50.00');
    $Inv::move($id, 'in', '10', '70.00');
    $t->equals('60.0000', (string) $db->value('SELECT unit_cost FROM materials WHERE id = ?', [$id]), 'WAC');
    $r = $Inv::move($id, 'out', '-5', null);
    $t->equals('15.000', $r['balance']);
    $t->equals('300.00', $r['total_cost'], 'valued at WAC');
    $t->throws(fn () => $Inv::move($id, 'out', '-16'), \App\Services\Operations\InsufficientStockException::class);
    $t->throws(fn () => $Inv::move($id, 'in', '-1', '5'), InvalidArgumentException::class, 'sign must match type');
    $c = $Inv::count($id, '12', null, 'Inventaire');
    $t->equals('-3.000', (string) $db->value('SELECT quantity FROM stock_movements WHERE id = ?', [$c['id']]));
    $t->equals(null, $Inv::count($id, '12'), 'no-op count');
    $t->equals('12.000', (string) $db->value('SELECT stock_quantity FROM materials WHERE id = ?', [$id]));
    $t->equals('12.000', (string) $db->value('SELECT balance_after FROM stock_movements WHERE material_id = ? ORDER BY id DESC LIMIT 1', [$id]), 'ledger balance = stock');
    $t->equals(0, count(array_filter($Inv::lowStock(), fn ($m) => (int) $m['id'] === $id)), 'above threshold');
});
$t->test('purchases: totals HT/TVA, reception into stock updates WAC, payment limits, locked once received', function (TestRunner $t) use ($db): void {
    $P = \App\Services\Operations\PurchaseService::class;
    $sup = $db->insert('suppliers', ['name' => 'Papeterie Atlas', 'city' => 'Casablanca']);
    $mat = (int) $db->value("SELECT id FROM materials WHERE sku = 'TOILE-TEST'");
    $id = $P::save(null, ['supplier_id' => $sup, 'purchase_date' => date('Y-m-d'), 'status' => 'ordered'], [
        ['material_id' => $mat, 'description' => '', 'quantity' => '8', 'unit_cost' => '75', 'tax_rate' => '20'],
        ['material_id' => null, 'description' => 'Transport', 'quantity' => '1', 'unit_cost' => '100', 'tax_rate' => '0'],
    ], null);
    $p = $db->fetch('SELECT * FROM purchases WHERE id = ?', [$id]);
    $t->equals('700.00', (string) $p['subtotal']);
    $t->equals('120.00', (string) $p['tax_total']);
    $t->equals('820.00', (string) $p['total']);
    $t->assert(str_starts_with((string) $p['purchase_number'], 'ACH-' . date('Y') . '-'), 'sequential number');
    $t->equals(1, $P::receive($id, null), 'one stocked line');
    $t->equals('20.000', (string) $db->value('SELECT stock_quantity FROM materials WHERE id = ?', [$mat]));
    $t->equals('66.0000', (string) $db->value('SELECT unit_cost FROM materials WHERE id = ?', [$mat]), '(12×60 + 8×75) / 20');
    $t->throws(fn () => $P::receive($id, null), \App\Core\ValidationException::class, 'received once');
    $t->throws(fn () => $P::pay($id, '900', 'cash', date('Y-m-d'), null, null), \App\Core\ValidationException::class);
    $P::pay($id, '500', 'bank_transfer', date('Y-m-d'), 'VIR-77', null);
    $t->equals('500.00', (string) $db->value('SELECT amount_paid FROM purchases WHERE id = ?', [$id]));
    $t->throws(fn () => $P::cancel($id), \App\Core\ValidationException::class, 'received purchase cannot be cancelled');
});
$t->test('manual order: catalogue + custom lines, negotiated price, stock reserved, customer matched by phone', function (TestRunner $t) use ($db): void {
    $prod = $db->fetch("SELECT p.id, v.id AS vid, v.price FROM products p JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
                        WHERE p.slug = 'desert-rose' AND p.deleted_at IS NULL ORDER BY v.sort_order LIMIT 1");
    $db->update('products', ['track_stock' => 1, 'stock_quantity' => 10], ['id' => (int) $prod['id']]);
    $db->update('product_variants', ['stock_quantity' => 10], ['id' => (int) $prod['vid']]);
    $known = $db->fetch("SELECT id, phone FROM customers WHERE phone IS NOT NULL AND deleted_at IS NULL LIMIT 1");
    $o = \App\Services\Sales\AdminOrderService::create(
        ['source' => 'whatsapp', 'full_name' => 'Client WhatsApp', 'phone' => $known['phone'], 'email' => null, 'city' => 'Casablanca', 'address' => '12 rue Atlas', 'shipping' => '0', 'locale' => 'fr'],
        [
            ['kind' => 'catalog', 'product_id' => (int) $prod['id'], 'variant_id' => (int) $prod['vid'], 'quantity' => 2, 'unit_price' => ''],
            ['kind' => 'custom', 'title' => 'Toile famille 100×70', 'width_cm' => '100', 'height_cm' => '70', 'quantity' => 1, 'unit_price' => '1 200,00', 'unit_cost' => '300'],
        ],
        1
    );
    $t->equals('confirmed', $o['status']);
    $t->equals('whatsapp', $o['source']);
    $t->equals((int) $db->value('SELECT id FROM customers WHERE phone = ? AND deleted_at IS NULL ORDER BY is_guest, id LIMIT 1', [$known['phone']]), (int) $o['customer_id'], 'matched by phone (account first)');
    $expected = \App\Helpers\Money::add(\App\Helpers\Money::mul((string) $prod['price'], 2), '1200.00');
    $t->equals($expected, (string) $o['subtotal']);
    $t->equals($expected, (string) $o['grand_total'], 'TTC prices, free shipping given');
    $t->equals(8, (int) $db->value('SELECT stock_quantity FROM product_variants WHERE id = ?', [(int) $prod['vid']]));
    $t->equals(2, (int) $db->value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [(int) $o['id']]));
    $t->throws(fn () => \App\Services\Sales\AdminOrderService::create(['full_name' => 'X', 'phone' => '0600000000', 'city' => 'Rabat', 'address' => 'x', 'shipping' => '', 'locale' => 'fr'],
        [['kind' => 'catalog', 'product_id' => (int) $prod['id'], 'variant_id' => (int) $prod['vid'], 'quantity' => 9]], 1), \App\Core\ValidationException::class, 'stock limit');
});
$t->test('workflow: only allowed transitions, timestamps + history, cancellation releases stock and stops production', function (TestRunner $t) use ($db): void {
    $W = \App\Services\Sales\OrderWorkflow::class;
    $o = $db->fetch("SELECT * FROM orders WHERE source = 'whatsapp' ORDER BY id DESC LIMIT 1");
    $t->throws(fn () => $W::change((int) $o['id'], 'shipped'), \App\Services\Sales\WorkflowException::class, 'confirmed → shipped refused');
    $t->throws(fn () => $W::change((int) $o['id'], 'confirmed'), \App\Services\Sales\WorkflowException::class, 'same status');
    $W::change((int) $o['id'], 'approved_for_production', ['comment' => 'OK client', 'user_id' => 1]);
    $t->equals(2, (int) $db->value('SELECT COUNT(*) FROM production_jobs WHERE order_id = ?', [(int) $o['id']]), 'one job per line');
    $t->equals('OK client', (string) $db->value('SELECT comment FROM order_status_history WHERE order_id = ? ORDER BY id DESC LIMIT 1', [(int) $o['id']]));
    // a copy of this order to test cancellation
    $vid = (int) $db->value('SELECT product_variant_id FROM order_items WHERE order_id = ? AND product_variant_id IS NOT NULL', [(int) $o['id']]);
    $c = \App\Services\Sales\AdminOrderService::create(['source' => 'phone', 'full_name' => 'Annulation Test', 'phone' => '0611223344', 'city' => 'Rabat', 'address' => '1 av. Hassan II', 'shipping' => '30', 'locale' => 'fr'],
        [['kind' => 'catalog', 'product_id' => (int) $db->value('SELECT product_id FROM product_variants WHERE id = ?', [$vid]), 'variant_id' => $vid, 'quantity' => 3]], 1);
    $t->equals(5, (int) $db->value('SELECT stock_quantity FROM product_variants WHERE id = ?', [$vid]));
    $W::change((int) $c['id'], 'approved_for_production', ['user_id' => 1]);
    $W::change((int) $c['id'], 'cancelled', ['comment' => 'Client injoignable', 'user_id' => 1]);
    $row = $db->fetch('SELECT status, cancelled_at, balance_due FROM orders WHERE id = ?', [(int) $c['id']]);
    $t->equals('cancelled', $row['status']);
    $t->assert($row['cancelled_at'] !== null, 'cancelled_at set');
    $t->equals('0.00', (string) $row['balance_due']);
    $t->equals(8, (int) $db->value('SELECT stock_quantity FROM product_variants WHERE id = ?', [$vid]), 'stock released');
    $t->equals('cancelled', (string) $db->value('SELECT status FROM production_jobs WHERE order_id = ?', [(int) $c['id']]));
    $t->equals([], $W::allowed('cancelled'), 'final state');
});
$t->test('production: stages in order, print consumes linked stock at WAC, order follows the workshop, shipment closes it', function (TestRunner $t) use ($db): void {
    $Pr = \App\Services\Operations\ProductionService::class;
    $mat = (int) $db->value("SELECT id FROM materials WHERE sku = 'TOILE-TEST'");
    $db->update('product_materials', ['inventory_material_id' => $mat], ['code' => 'canvas']);
    $o = $db->fetch("SELECT * FROM orders WHERE source = 'whatsapp' ORDER BY id DESC LIMIT 1");
    $job = $db->fetch('SELECT j.* FROM production_jobs j JOIN order_items oi ON oi.id = j.order_item_id WHERE j.order_id = ? AND oi.product_variant_id IS NOT NULL', [(int) $o['id']]);
    $other = $db->fetch('SELECT j.* FROM production_jobs j JOIN order_items oi ON oi.id = j.order_item_id WHERE j.order_id = ? AND oi.product_variant_id IS NULL', [(int) $o['id']]);
    $stages = array_column($db->fetchAll('SELECT stage FROM production_tasks WHERE production_job_id = ? ORDER BY sort_order', [(int) $job['id']]), 'stage');
    $t->assert(!in_array('approve_artwork', $stages, true), 'no artwork stage for a catalogue print');
    $t->equals('receive', $stages[0]);
    $task = fn (int $jobId, string $stage) => (int) $db->value('SELECT id FROM production_tasks WHERE production_job_id = ? AND stage = ?', [$jobId, $stage]);
    $t->throws(fn () => $Pr::act($task((int) $job['id'], 'print'), 'start', 1), \App\Services\Operations\ProductionException::class, 'cannot skip ahead');
    $Pr::act($task((int) $job['id'], 'prepare_file'), 'done', 1);
    $Pr::act($task((int) $job['id'], 'print'), 'start', 1);
    $t->equals('printing', (string) $db->value('SELECT status FROM orders WHERE id = ?', [(int) $o['id']]), 'order follows the workshop');
    $stock = (string) $db->value('SELECT stock_quantity FROM materials WHERE id = ?', [$mat]);
    $Pr::act($task((int) $job['id'], 'print'), 'done', 1);
    $item = $db->fetch('SELECT * FROM order_items WHERE id = ?', [(int) $job['order_item_id']]);
    $need = \App\Services\Operations\InventoryService::requirementsFor($item)['lines'][0]['quantity'];
    $t->equals(number_format((float) $stock - (float) $need, 3, '.', ''), (string) $db->value('SELECT stock_quantity FROM materials WHERE id = ?', [$mat]), 'consumed ' . $need . ' m²');
    $cost = (string) $db->value('SELECT material_cost FROM production_jobs WHERE id = ?', [(int) $job['id']]);
    $t->equals(number_format(round((float) $need * 66, 2), 2, '.', ''), $cost, 'valued at WAC 66.00');
    \App\Services\Operations\InventoryService::consumeForJob((int) $job['id']);
    $t->equals($cost, (string) $db->value('SELECT material_cost FROM production_jobs WHERE id = ?', [(int) $job['id']]), 'consumption booked once');
    foreach ([$job, $other] as $j) {
        foreach ($db->fetchAll("SELECT id, stage FROM production_tasks WHERE production_job_id = ? AND status = 'pending' AND stage <> 'ship' ORDER BY sort_order", [(int) $j['id']]) as $tk) {
            $Pr::act((int) $tk['id'], 'done', 1);
        }
    }
    $t->equals('ready_for_shipping', (string) $db->value('SELECT status FROM orders WHERE id = ?', [(int) $o['id']]));
    $t->throws(fn () => $Pr::act($task((int) $job['id'], 'ship'), 'done', 1), \App\Services\Operations\ProductionException::class, 'ship closed by the shipment');
    $t->throws(fn () => \App\Services\Sales\ShipmentService::create((int) $o['id'], ['courier' => 'Amana', 'cod_amount' => '999999'], 1), \App\Services\Sales\WorkflowException::class, 'COD above balance');
    $sid = \App\Services\Sales\ShipmentService::create((int) $o['id'], ['courier' => 'Amana', 'tracking_number' => 'AM123456789MA', 'shipping_cost' => '45', 'cod_amount' => (string) $o['balance_due']], 1, true, false);
    $t->equals('shipped', (string) $db->value('SELECT status FROM orders WHERE id = ?', [(int) $o['id']]));
    $t->equals(2, (int) $db->value("SELECT COUNT(*) FROM production_jobs WHERE order_id = ? AND status = 'completed'", [(int) $o['id']]), 'jobs closed by shipment');
    \App\Services\Sales\ShipmentService::updateStatus($sid, 'delivered', 1, false);
    $row = $db->fetch('SELECT status, delivered_at FROM orders WHERE id = ?', [(int) $o['id']]);
    $t->equals('delivered', $row['status']);
    $t->assert($row['delivered_at'] !== null);
    $from = date('Y-m-01');
    $to = date('Y-m-01', strtotime('+1 month'));
    $t->assert(\App\Helpers\Money::compare(\App\Services\Admin\DashboardStats::profit($from, $to)['shipping_cost'], '45.00') >= 0, 'real courier cost reaches profit');
});
$t->test('artwork: proof link (hash stored, single use), customer approval releases the order to production', function (TestRunner $t) use ($db): void {
    $A = \App\Services\Operations\ArtworkService::class;
    $o = $db->fetch("SELECT * FROM orders WHERE customer_email = 'design@client.ma'");
    $db->update('orders', ['status' => 'confirmed'], ['id' => (int) $o['id']]);
    $item = (int) $db->value('SELECT id FROM order_items WHERE order_id = ? AND custom_design_id IS NOT NULL', [(int) $o['id']]);
    $t->assert($item > 0, 'design line');
    $url = $A::sendProof($item, 1, 'Merci de vérifier le cadrage');
    $raw = substr($url, -64);
    $t->equals('awaiting_artwork_approval', (string) $db->value('SELECT status FROM orders WHERE id = ?', [(int) $o['id']]));
    $t->equals(hash('sha256', $raw), (string) $db->value('SELECT approval_token FROM order_customizations WHERE order_item_id = ? LIMIT 1', [$item]), 'only the hash is stored');
    $t->equals(null, $A::findByToken(str_repeat('a', 64)));
    $t->assert($A::findByToken($raw) !== null, 'valid link');
    $t->equals(true, $A::customerRespond($raw, false, 'Le visage est coupé en haut'));
    $t->equals('changes_requested', (string) $db->value('SELECT approval_status FROM order_customizations WHERE order_item_id = ? LIMIT 1', [$item]));
    $t->equals(false, $A::customerRespond($raw, true), 'link used once');
    $raw2 = substr($A::sendProof($item, 1), -64);
    $t->equals(true, $A::customerRespond($raw2, true));
    $t->equals('approved_for_production', (string) $db->value('SELECT status FROM orders WHERE id = ?', [(int) $o['id']]), 'released to production');
    $job = (int) $db->value('SELECT id FROM production_jobs WHERE order_item_id = ?', [$item]);
    $t->equals('done', (string) $db->value("SELECT status FROM production_tasks WHERE production_job_id = ? AND stage = 'approve_artwork'", [$job]), 'artwork stage already done');
    $t->equals('prepare_file', (string) $db->value('SELECT current_stage FROM production_jobs WHERE id = ?', [$job]));
    $t->assert((int) $db->value("SELECT COUNT(*) FROM notifications WHERE event = 'artwork_proof' AND related_id = ?", [(int) $o['id']]) >= 2, 'proof e-mails recorded');
});
$t->test('print-ready files: type checked by content, stored privately', function (TestRunner $t) use ($db): void {
    $cid = (int) $db->value('SELECT oc.id FROM order_customizations oc LIMIT 1');
    $tmp = tempnam(sys_get_temp_dir(), 'pf');
    file_put_contents($tmp, '%PDF-1.4' . "\n" . str_repeat('x', 200) . "\n%%EOF");
    $rel = \App\Services\Operations\ArtworkService::storePrintFile($cid, ['name' => 'tirage.pdf', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)], true);
    $t->assert(is_file(storage_path('print_files/' . $rel)) && str_ends_with($rel, '.pdf'), 'stored outside public/');
    file_put_contents($tmp, '<?php echo 1;');
    $t->throws(fn () => \App\Services\Operations\ArtworkService::storePrintFile($cid, ['name' => 'evil.pdf', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)], true), \App\Services\UploadException::class);
    @unlink(storage_path('print_files/' . $rel));
    @unlink($tmp);
});


$t->section('Phase 7: quotations, invoices, credit notes, payments, PDF, finance');
$scratch = getenv('BWA_PDF_OUT') ?: '';
$t->test('document lines: decimal quantities, line discounts, VAT per line, validation', function (TestRunner $t): void {
    $c = \App\Services\Finance\DocumentLines::compute([
        ['description' => 'Toile 120×80', 'quantity' => '2', 'unit_price' => '1 250,00', 'discount' => '100', 'tax_rate' => '20'],
        ['description' => 'Pose sur site', 'quantity' => '2,5', 'unit_price' => '199.99', 'tax_rate' => '20'],
        ['description' => '', 'unit_price' => ''],
    ]);
    $t->equals(2, count($c['lines']), 'empty row ignored');
    $t->equals('2400.00', $c['lines'][0]['ht']);
    $t->equals('480.00', $c['lines'][0]['tax']);
    $t->equals('499.98', $c['lines'][1]['ht'], '2.5 × 199.99 rounded half up');
    $t->equals('2899.98', $c['ht']);
    $t->equals('580.00', $c['tax']);
    $t->equals('3479.98', $c['total']);
    $t->throws(fn () => \App\Services\Finance\DocumentLines::compute([['description' => 'X', 'quantity' => '1', 'unit_price' => '10', 'discount' => '11', 'tax_rate' => '20']]), \App\Core\ValidationException::class);
    $t->throws(fn () => \App\Services\Finance\DocumentLines::compute([['description' => 'X', 'quantity' => '0', 'unit_price' => '10', 'tax_rate' => '20']]), \App\Core\ValidationException::class);
    $t->equals('mille-deux-cent-quatre-vingts dirhams et cinquante centimes', \App\Services\Finance\AmountInWords::fr('1280.50'));
    $t->equals("\u{FEE1}\u{FEFC}\u{FEB3}", \App\Services\Finance\ArabicShaper::visualLine('سلام'), 'Arabic joined + RTL');
});
$t->test('quotation: numbered draft, single-use customer link, acceptance, conversion into an order with the same total', function (TestRunner $t) use ($db): void {
    $Q = \App\Services\Finance\QuotationService::class;
    $id = $Q::save(null, ['customer_name' => 'Riad Atlas', 'customer_company' => 'Riad Atlas SARL', 'customer_ice' => '001122334000055', 'customer_email' => 'contact@riad.test',
        'customer_phone' => '0524000000', 'customer_address' => 'Derb Sidi Bouloukat, Marrakech'], [
        ['description' => 'Triptyque Atlas 3×(60×90) toile', 'quantity' => '2', 'unit_price' => '2500', 'tax_rate' => '20'],
        ['description' => 'Installation', 'quantity' => '1', 'unit_price' => '400', 'discount' => '50', 'tax_rate' => '20'],
    ], 1);
    $q = $Q::find($id);
    $t->assert(str_starts_with((string) $q['quotation_number'], 'DEV-' . date('Y') . '-'), 'number');
    $t->equals('6420.00', (string) $q['total'], '(5000 + 350) × 1.2');
    $t->equals(date('Y-m-d', strtotime('+15 days')), (string) $q['valid_until'], 'validity from settings');
    $raw = substr($Q::send($id, 1), -64);
    $t->equals(hash('sha256', $raw), (string) $db->value('SELECT access_token FROM quotations WHERE id = ?', [$id]));
    $t->equals(1, (int) $db->value("SELECT COUNT(*) FROM notifications WHERE event = 'quotation_sent' AND related_type = 'quotation' AND related_id = ?", [$id]));
    $t->equals(true, $Q::respond($raw, true, '127.0.0.1'));
    $t->equals(false, $Q::respond($raw, false, '127.0.0.1'), 'answered once');
    $t->equals('accepted', (string) $db->value('SELECT status FROM quotations WHERE id = ?', [$id]));
    $t->throws(fn () => $Q::save($id, ['customer_name' => 'X'], [['description' => 'Y', 'quantity' => '1', 'unit_price' => '1', 'tax_rate' => '20']], 1), \App\Core\ValidationException::class, 'accepted quote is locked');
    $order = $Q::convert($id, ['city' => 'Marrakech', 'address' => 'Derb Sidi Bouloukat', 'phone' => '0524000000'], 1);
    $t->equals('6420.00', (string) $order['grand_total']);
    $t->equals('quotation', $order['source']);
    $t->equals('converted', (string) $db->value('SELECT status FROM quotations WHERE id = ?', [$id]));
    $t->equals((int) $order['id'], (int) $db->value('SELECT converted_order_id FROM quotations WHERE id = ?', [$id]));
    $dup = $Q::duplicate($id, 1);
    $t->equals('draft', (string) $db->value('SELECT status FROM quotations WHERE id = ?', [$dup]));
    $t->equals('6420.00', (string) $db->value('SELECT total FROM quotations WHERE id = ?', [$dup]));
    $db->update('quotations', ['status' => 'sent', 'valid_until' => date('Y-m-d', strtotime('-1 day'))], ['id' => $dup]);
    $t->assert($Q::expireDue() >= 1, 'cron expires');
    $t->equals('expired', (string) $db->value('SELECT status FROM quotations WHERE id = ?', [$dup]));
});
$t->test('invoice from an order: HT/VAT split equals the amount owed, sequential numbers at issue, integrity hash', function (TestRunner $t) use ($db): void {
    $I = \App\Services\Finance\InvoiceService::class;
    $o = $db->fetch("SELECT * FROM orders WHERE source = 'quotation' ORDER BY id DESC LIMIT 1");
    $id = $I::fromOrder((int) $o['id'], 1);
    $inv = $I::find($id);
    $t->equals('draft', $inv['status']);
    $t->equals(null, $inv['invoice_number'], 'drafts have no number');
    $t->equals((string) $o['grand_total'], (string) $inv['total_ttc']);
    $ht = \App\Helpers\Money::add(\App\Helpers\Money::sub((string) $inv['subtotal_ht'], (string) $inv['discount_total']), (string) $inv['shipping_ht']);
    $t->equals((string) $inv['total_ttc'], \App\Helpers\Money::add($ht, (string) $inv['tax_total']), 'HT + VAT = TTC');
    $t->equals('5350.00', $ht);
    $t->throws(fn () => $I::fromOrder((int) $o['id'], 1), \App\Core\ValidationException::class, 'one invoice per order');
    $before = (int) $db->value("SELECT COALESCE(MAX(last_number), 0) FROM document_sequences WHERE sequence_type = 'invoice' AND period_year = ?", [(int) date('Y')]);
    $issued = $I::issue($id, 1);
    $t->equals(sprintf('FAC-%s-%06d', date('Y'), $before + 1), $issued['invoice_number']);
    $t->equals(date('Y-m-d'), (string) $issued['issue_date']);
    $t->assert(json_decode((string) $issued['company_snapshot'], true)['company_name'] !== null, 'company frozen');
    $t->equals(true, $I::verify($issued));
    $line = $db->fetch('SELECT id, total_ttc FROM invoice_items WHERE invoice_id = ? LIMIT 1', [$id]);
    $db->update('invoice_items', ['total_ttc' => '1.00'], ['id' => (int) $line['id']]);
    $t->equals(false, $I::verify($I::find($id)), 'tampering detected');
    $db->update('invoice_items', ['total_ttc' => $line['total_ttc']], ['id' => (int) $line['id']]);
    $t->equals(true, $I::verify($I::find($id)));
    $t->throws(fn () => $I::deleteDraft($id), \App\Core\ValidationException::class, 'issued invoice cannot be deleted');
    $t->throws(fn () => $I::issue($id, 1), \App\Core\ValidationException::class, 'issued once');
    \App\Services\Payments\PaymentService::recordConfirmed((int) $o['id'], '1000.00', (int) $db->value("SELECT id FROM payment_methods WHERE code = 'bank_transfer'"), null, 'VIR-INV', null, 1);
    $t->equals('partially_paid', (string) $db->value('SELECT status FROM invoices WHERE id = ?', [$id]));
    $t->equals('1000.00', (string) $db->value('SELECT amount_paid FROM invoices WHERE id = ?', [$id]));
});
$t->test('credit notes: partial then the rest cancels the invoice (kept, never deleted); refunds capped at what was paid', function (TestRunner $t) use ($db): void {
    $I = \App\Services\Finance\InvoiceService::class;
    $inv = $db->fetch("SELECT i.* FROM invoices i JOIN orders o ON o.id = i.order_id WHERE o.source = 'quotation' AND i.invoice_type = 'invoice' ORDER BY i.id DESC LIMIT 1");
    $t->throws(fn () => $I::creditNote((int) $inv['id'], 'x', 1), \App\Core\ValidationException::class, 'reason required');
    $t->throws(fn () => $I::creditNote((int) $inv['id'], 'Geste commercial', 1, '99999'), \App\Core\ValidationException::class, 'above invoice');
    $cn = $I::creditNote((int) $inv['id'], 'Geste commercial (retard)', 1, '420');
    $t->assert(str_starts_with((string) $cn['invoice_number'], 'AV-' . date('Y') . '-'), 'credit note number');
    $t->equals('420.00', (string) $cn['total_ttc']);
    $t->equals('350.00', (string) $cn['subtotal_ht'], '420 TTC at 20 %');
    $t->equals('partially_paid', (string) $db->value('SELECT status FROM invoices WHERE id = ?', [(int) $inv['id']]));
    $t->equals('5000.00', $I::balance($I::find((int) $inv['id'])), '6420 − 420 − 1000');
    $t->throws(fn () => $I::creditNote((int) $inv['id'], 'Annulation', 1), \App\Core\ValidationException::class, 'full credit after a partial one must state the amount');
    $I::creditNote((int) $inv['id'], 'Annulation de la commande', 1, '6000');
    $row = $db->fetch('SELECT status, cancel_reason, invoice_number FROM invoices WHERE id = ?', [(int) $inv['id']]);
    $t->equals('cancelled', $row['status']);
    $t->equals('Annulation de la commande', $row['cancel_reason']);
    $t->assert($row['invoice_number'] !== null, 'number kept');
    $t->equals(2, (int) $db->value("SELECT COUNT(*) FROM invoices WHERE original_invoice_id = ? AND invoice_type = 'credit_note'", [(int) $inv['id']]));
    $P = \App\Services\Payments\PaymentService::class;
    $m = (int) $db->value("SELECT id FROM payment_methods WHERE code = 'bank_transfer'");
    $t->throws(fn () => $P::refund((int) $inv['order_id'], '1000.01', $m, 'Remboursement', 1), InvalidArgumentException::class);
    $P::refund((int) $inv['order_id'], '400.00', $m, 'Remboursement partiel', 1);
    $o = $db->fetch('SELECT amount_paid, payment_status FROM orders WHERE id = ?', [(int) $inv['order_id']]);
    $t->equals('600.00', (string) $o['amount_paid']);
    $t->equals('partially_refunded', $o['payment_status']);
    $t->assert($I::activeForOrder((int) $inv['order_id']) === null, 'fully credited: the order may be invoiced again');
});
$t->test('standalone invoice: typed HT lines, payments on the invoice, overpayment refused', function (TestRunner $t) use ($db): void {
    $I = \App\Services\Finance\InvoiceService::class;
    $id = $I::saveManual(null, ['customer_name' => 'Agence Lumière', 'customer_ice' => '002233445000011', 'locale' => 'fr'], [
        ['description' => 'Décoration bureaux — 12 toiles 50×70', 'quantity' => '12', 'unit_price' => '350', 'tax_rate' => '20'],
    ], 1);
    $t->equals('5040.00', (string) $I::find($id)['total_ttc']);
    $id = $I::saveManual($id, ['customer_name' => 'Agence Lumière', 'customer_ice' => '002233445000011', 'locale' => 'fr'], [
        ['description' => 'Décoration bureaux — 12 toiles 50×70', 'quantity' => '12', 'unit_price' => '350', 'discount' => '200', 'tax_rate' => '20'],
    ], 1);
    $t->equals('4800.00', (string) $I::find($id)['total_ttc'], 'draft edited');
    $I::issue($id, 1);
    $P = \App\Services\Payments\PaymentService::class;
    $m = (int) $db->value("SELECT id FROM payment_methods WHERE code = 'bank_transfer'");
    $t->throws(fn () => $P::recordForInvoice($id, '4800.01', $m, null, 1), InvalidArgumentException::class);
    $P::recordForInvoice($id, '4800', $m, 'VIR-LUM', 1);
    $t->equals('paid', (string) $db->value('SELECT status FROM invoices WHERE id = ?', [$id]));
    $t->throws(fn () => $I::saveManual($id, ['customer_name' => 'X'], [['description' => 'Y', 'quantity' => '1', 'unit_price' => '1', 'tax_rate' => '20']], 1), \App\Core\ValidationException::class, 'issued invoice locked');
});
$t->test('PDF: invoice (FR + AR), credit note and quotation render with Dompdf; issued invoices are archived', function (TestRunner $t) use ($db, $scratch): void {
    if (!\App\Services\Finance\PdfService::available()) {
        $t->assert(false, 'Dompdf not installed (composer install)');
        return;
    }
    $inv = $db->fetch("SELECT * FROM invoices WHERE invoice_type = 'invoice' AND order_id IS NULL AND status = 'paid' ORDER BY id DESC LIMIT 1");
    $pdf = \App\Services\Finance\PdfService::invoice($inv);
    $t->assert(str_starts_with($pdf, '%PDF-'), 'PDF bytes');
    $fresh = $db->fetch('SELECT pdf_path FROM invoices WHERE id = ?', [(int) $inv['id']]);
    $t->assert($fresh['pdf_path'] !== null && is_file(storage_path('invoices/' . $fresh['pdf_path'])), 'archived in storage/invoices');
    $ar = $inv;
    $ar['locale'] = 'ar';
    $ar['pdf_path'] = null;
    $ar['status'] = 'draft';
    $ar['customer_name'] = 'شركة الأطلس للديكور';
    $pdfAr = \App\Services\Finance\PdfService::invoice($ar);
    $t->assert(str_starts_with($pdfAr, '%PDF-'), 'Arabic PDF');
    $cn = $db->fetch("SELECT * FROM invoices WHERE invoice_type = 'credit_note' ORDER BY id DESC LIMIT 1");
    $q = $db->fetch('SELECT * FROM quotations ORDER BY id LIMIT 1');
    $pdfCn = \App\Services\Finance\PdfService::invoice($cn);
    $pdfQ = \App\Services\Finance\PdfService::quotation($q);
    $t->assert(str_starts_with($pdfCn, '%PDF-') && str_starts_with($pdfQ, '%PDF-'));
    if ($scratch !== '' && is_dir($scratch)) {
        file_put_contents($scratch . '/invoice-fr.pdf', $pdf);
        file_put_contents($scratch . '/invoice-ar.pdf', $pdfAr);
        file_put_contents($scratch . '/credit-note.pdf', $pdfCn);
        file_put_contents($scratch . '/quotation.pdf', $pdfQ);
    }
    @unlink(storage_path('invoices/' . $fresh['pdf_path']));
});
$t->test('finance summary: cash in/out, VAT collected − deductible, receivables and payables from recorded documents', function (TestRunner $t) use ($db): void {
    $F = \App\Services\Finance\FinanceStats::class;
    $p = $F::period('month');
    $s = $F::summary($p['from'], $p['to']);
    $t->equals(\App\Helpers\Money::normalize((string) $db->value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='confirmed' AND payment_type='payment' AND paid_at >= ? AND paid_at < ?", [$p['from'], $p['to']])), $s['cash_in']);
    $t->equals(\App\Helpers\Money::sub($s['vat_collected'], $s['vat_deductible']), $s['vat_due']);
    $t->equals(\App\Helpers\Money::sub($s['cash_in'], $s['cash_out']), $s['net_cash']);
    $t->assert(\App\Helpers\Money::toCents($s['refunds']) >= 40000, 'refund counted as cash out');
    $t->equals(12, count($F::cashflow(12)));
    $r = $F::receivables();
    $t->equals(\App\Helpers\Money::add($r['orders'], $r['invoices']), $r['total']);
    $t->assert(\App\Helpers\Money::toCents($F::payables()) >= 0);
});


$t->section('Phase 8: SEO, reports, notifications, retention, backups, security');
$t->test('SEO: language alternates, canonical, sitemap with FR/AR URLs and images, robots.txt', function (TestRunner $t) use ($db): void {
    $alt = \App\Services\SeoService::alternates('/product/orbites');
    $t->assert(str_ends_with($alt['fr'], '/product/orbites'), 'default language without parameter');
    $t->assert(str_ends_with($alt['ar'], '/product/orbites?lang=ar'), 'Arabic URL');
    $t->equals($alt['fr'], $alt['x-default']);
    $t->assert(str_ends_with(\App\Services\SeoService::alternates('/shop', 3)['ar'], '/shop?lang=ar&page=3'), 'pagination kept');
    $xml = \App\Services\Seo\SitemapService::xml(true);
    $doc = simplexml_load_string($xml);
    $t->assert($doc !== false, 'valid XML');
    $locs = array_map('strval', $doc->xpath('//*[local-name()="loc"]') ?: []);
    $active = (int) $db->value('SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL');
    $t->equals($active * 2, count(array_filter($locs, fn ($l) => str_contains($l, '/product/'))), 'every active product in both languages');
    $deleted = (string) $db->value('SELECT slug FROM products WHERE deleted_at IS NOT NULL LIMIT 1');
    $t->assert($deleted === '' || !str_contains($xml, '/product/' . $deleted . '<'), 'deleted products excluded');
    $t->assert(str_contains($xml, 'hreflang="ar"') && str_contains($xml, '<image:loc>'), 'alternates and images');
    $robots = \App\Services\Seo\SitemapService::robots();
    $t->assert(str_contains($robots, 'Sitemap: ') && str_contains($robots, 'Disallow: /checkout'), 'robots rules');
    $t->assert(!str_contains($robots, '/' . trim((string) config('app.admin_path', 'admin'), '/') . "\n"), 'admin path not advertised');
});
$t->test('reports: every report runs; sales match the dashboard figures; CSV is Excel-safe', function (TestRunner $t) use ($db): void {
    $R = \App\Services\Reports\ReportService::class;
    $from = date('Y-01-01');
    $to = date('Y-m-d', strtotime('+1 day'));
    foreach ($R::REPORTS as $r) {
        $d = $R::run($r, $from, $to, ['group' => 'month']);
        $t->assert(isset($d['columns'], $d['rows']) && $d['columns'] !== [], $r);
    }
    $sales = $R::run('sales', $from, $to, ['group' => 'day']);
    $t->equals(\App\Services\Admin\DashboardStats::sales($from, $to)['sales'], (string) $sales['totals']['sales']);
    $t->equals(\App\Services\Admin\DashboardStats::sales($from, $to)['orders'], (int) $sales['totals']['orders']);
    $cat = $R::run('categories', $from, $to);
    $lines = \App\Helpers\Money::normalize((string) $db->value("SELECT COALESCE(SUM(oi.line_total),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status NOT IN ('cancelled','returned') AND o.placed_at >= ? AND o.placed_at < ?", [$from, $to]));
    $t->equals($lines, (string) $cat['totals']['revenue'], 'category split covers every line');
    $csv = $R::csv(['columns' => [['key' => 'a', 'label' => 'Nom', 'type' => 'text'], ['key' => 'b', 'label' => 'Montant', 'type' => 'money']], 'rows' => [['a' => '=HYPERLINK("http://x")', 'b' => '1250.50']]]);
    $t->assert(str_starts_with($csv, "\xEF\xBB\xBF"), 'UTF-8 BOM');
    $t->assert(str_contains($csv, "'=HYPERLINK"), 'formula neutralised');
    $t->assert(str_contains($csv, '1250,50'), 'decimal comma');
    $q = $R::run('catalogue', $from, $to);
    $t->equals((int) $q['totals']['active'], (int) $q['totals']['complete'] + count($q['rows']));
});
$t->test('team notifications: recipients by permission, dedupe, read state, daily checks', function (TestRunner $t) use ($db): void {
    $N = \App\Services\Notifications\StaffNotifier::class;
    $expected = (int) $db->value("SELECT COUNT(DISTINCT u.id) FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN role_permissions rp ON rp.role_id = r.id LEFT JOIN permissions p ON p.id = rp.permission_id WHERE u.is_active = 1 AND (r.slug = 'super_admin' OR p.slug = 'inventory.view')");
    $t->assert($expected > 0, 'some staff can see stock');
    $mat = (int) $db->value("SELECT id FROM materials WHERE sku = 'TOILE-TEST'");
    $db->update('materials', ['min_stock' => '999.000'], ['id' => $mat]);
    $db->query("DELETE FROM notifications WHERE event = 'low_stock'");
    $N::dailyChecks();
    $t->equals($expected, (int) $db->value("SELECT COUNT(*) FROM notifications WHERE event = 'low_stock' AND related_id = ?", [$mat]));
    $N::dailyChecks();
    $t->equals($expected, (int) $db->value("SELECT COUNT(*) FROM notifications WHERE event = 'low_stock' AND related_id = ?", [$mat]), 'once per 24 h');
    $owner = (int) $db->value("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'super_admin' LIMIT 1");
    $before = $N::unreadCount($owner);
    $t->assert($before >= 1);
    $t->assert($N::markRead($owner) >= 1);
    $t->equals(0, $N::unreadCount($owner));
    $db->update('materials', ['min_stock' => '5.000'], ['id' => $mat]);
});
$t->test('backups: pure-PHP gzip dump restores into an empty database with identical row counts', function (TestRunner $t) use ($db, $cfg): void {
    $B = \App\Services\System\BackupService::class;
    $name = $B::create('test');
    $path = $B::path($name);
    $t->assert($path !== null && filesize($path) > 1000, 'file written');
    $t->equals(null, $B::path('../../.env'), 'no traversal');
    $sql = gzdecode((string) file_get_contents($path));
    $t->assert(str_contains($sql, 'CREATE TABLE `orders`') && str_contains($sql, 'INSERT INTO `orders`'), 'schema + data');
    try {
        $pdo = new PDO('mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';charset=utf8mb4', $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS bwa_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE bwa_restore');
        $pdo->exec($sql);
        foreach (['orders', 'order_items', 'invoices', 'payments', 'products', 'settings', 'translations'] as $tb) {
            $t->equals((int) $db->value("SELECT COUNT(*) FROM `{$tb}`"), (int) (new PDO('mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=bwa_restore;charset=utf8mb4', $cfg['username'], $cfg['password']))->query("SELECT COUNT(*) FROM `{$tb}`")->fetchColumn(), 'restored ' . $tb);
        }
        $pdo->exec('DROP DATABASE bwa_restore');
    } catch (PDOException $e) {
        $t->assert(false, 'restore check needs CREATE DATABASE rights: ' . $e->getMessage());
    }
    $B::create('test');
    $t->assert(count($B::list()) >= 2);
    $B::rotate(1);
    $t->equals(1, count($B::list()), 'rotation keeps the newest');
    foreach ($B::list() as $b) {
        $B::delete($b['name']);
    }
});
$t->test('retention: old logs, read alerts and e-mail bodies are purged; financial records never', function (TestRunner $t) use ($db): void {
    $orders = (int) $db->value('SELECT COUNT(*) FROM orders');
    $invoices = (int) $db->value('SELECT COUNT(*) FROM invoices');
    $db->insert('activity_logs', ['action' => 'test.old', 'created_at' => date('Y-m-d H:i:s', strtotime('-400 days'))]);
    $old = $db->insert('notifications', ['channel' => 'email', 'event' => 'test_old', 'recipient' => 'x@test.ma', 'subject' => 'Vieux', 'body' => 'Données personnelles', 'status' => 'sent', 'created_at' => date('Y-m-d H:i:s', strtotime('-200 days'))]);
    $r = \App\Services\System\RetentionService::run();
    $t->assert($r['logs'] >= 1, 'old log removed');
    $t->equals(0, (int) $db->value("SELECT COUNT(*) FROM activity_logs WHERE action = 'test.old'"));
    $t->equals(null, $db->value('SELECT body FROM notifications WHERE id = ?', [$old]), 'body erased');
    $t->equals('Vieux', (string) $db->value('SELECT subject FROM notifications WHERE id = ?', [$old]), 'metadata kept');
    $t->equals($orders, (int) $db->value('SELECT COUNT(*) FROM orders'));
    $t->equals($invoices, (int) $db->value('SELECT COUNT(*) FROM invoices'));
});
$t->test('customer data export and security self-check', function (TestRunner $t) use ($db): void {
    $cid = (int) $db->value('SELECT customer_id FROM orders WHERE customer_id IS NOT NULL LIMIT 1');
    $x = \App\Services\System\RetentionService::exportCustomer($cid);
    $t->equals($cid, (int) $x['customer']['id']);
    $t->equals((int) $db->value('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$cid]), count($x['orders']));
    $t->assert(!isset($x['customer']['password_hash']), 'no password hash in the export');
    $checks = array_column(\App\Services\System\SecurityCheck::run(), 'state', 'key');
    $t->assert(isset($checks['https'], $checks['debug'], $checks['installer'], $checks['backup']), 'checks listed');
    $t->assert(in_array($checks['debug'], ['ok', 'fail'], true));
});


$t->section('Phase 9: performance refactors keep identical figures');
$t->test('grouped monthly dashboard and cash-flow queries equal the per-month calculations', function (TestRunner $t): void {
    $S = \App\Services\Admin\DashboardStats::class;
    foreach ($S::monthly(12) as $row) {
        $from = $row['month'] . '-01';
        $to = date('Y-m-01', strtotime($from . ' +1 month'));
        $s = $S::sales($from, $to);
        $t->equals($s['sales'], $row['sales'], 'sales ' . $row['month']);
        $t->equals($s['orders'], $row['orders'], 'orders ' . $row['month']);
        $t->equals($S::profit($from, $to)['profit'], $row['profit'], 'profit ' . $row['month']);
    }
    $F = \App\Services\Finance\FinanceStats::class;
    foreach ($F::cashflow(12) as $row) {
        $from = $row['month'] . '-01';
        $sum = $F::summary($from, date('Y-m-01', strtotime($from . ' +1 month')));
        $t->equals($sum['cash_in'], $row['in'], 'cash in ' . $row['month']);
        $t->equals($sum['cash_out'], $row['out'], 'cash out ' . $row['month']);
    }
    $p = \App\Services\Reports\ReportService::run('products', date('Y-01-01'), date('Y-m-d', strtotime('+1 day')));
    $lines = \App\Helpers\Money::normalize((string) \App\Core\Database::instance()->value("SELECT COALESCE(SUM(oi.line_total),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status NOT IN ('cancelled','returned') AND o.placed_at >= ?", [date('Y-01-01')]));
    $t->equals($lines, (string) $p['totals']['revenue'], 'products report covers every line');
});

$demo->remove();

exit($t->finish());
