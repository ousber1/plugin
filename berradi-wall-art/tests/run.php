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

exit($t->finish());
