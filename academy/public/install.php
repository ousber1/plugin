<?php

/*
|--------------------------------------------------------------------------
| One-page browser installer
|--------------------------------------------------------------------------
| Runs ONLY while no .env file exists. It checks the server, writes .env,
| creates the database tables, adds base content and the first admin, then
| deletes itself. Once .env exists this page refuses to do anything.
*/

declare(strict_types=1);

$base = dirname(__DIR__);
$envPath = $base.'/.env';

session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function envValue(string $v): string
{
    // Quote values so spaces and # are safe in .env.
    return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $v).'"';
}

// ---------------------------------------------------------------------------
// Already installed: do nothing.
// ---------------------------------------------------------------------------
if (file_exists($envPath)) {
    http_response_code(403);
    page('الموقع مثبّت مسبقًا', '<div class="alert alert--info">الموقع مثبّت بالفعل. لأسباب أمنية لا يعمل المثبّت بعد التثبيت. احذف الملف <code>public/install.php</code> إن كان لا يزال موجودًا.</div><p><a class="btn btn--primary" href="./">الذهاب إلى الموقع</a></p>');
    exit;
}

// ---------------------------------------------------------------------------
// Server checks
// ---------------------------------------------------------------------------
$checks = [
    'PHP 8.3 أو أحدث (الحالي: '.PHP_VERSION.')' => version_compare(PHP_VERSION, '8.3.0', '>='),
];
foreach (['pdo', 'mbstring', 'openssl', 'fileinfo', 'tokenizer', 'xml', 'ctype', 'curl'] as $ext) {
    $checks['امتداد PHP: '.$ext] = extension_loaded($ext);
}
$checks['امتداد قاعدة البيانات (pdo_mysql أو pdo_sqlite)'] = extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite');
$checks['مكتبات التطبيق (مجلد vendor)'] = is_file($base.'/vendor/autoload.php');
foreach (['' => 'المجلد الرئيسي (لإنشاء ملف .env)', 'storage' => 'المجلد storage', 'bootstrap/cache' => 'المجلد bootstrap/cache'] as $dir => $label) {
    $path = $base.($dir ? '/'.$dir : '');
    $checks[$label.' قابل للكتابة'] = is_dir($path) && is_writable($path);
}
$ready = ! in_array(false, $checks, true);

if (empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(16));
}

$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
// Installed from the hosting root (root .htaccess): the public URL has no /public.
$path = preg_replace('#/public$#', '', $path);
$guessUrl = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').$path;

$old = $_POST + [
    'app_name' => 'أكاديمية نبراس', 'app_url' => $guessUrl, 'db_type' => extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite',
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'admin_name' => '', 'admin_email' => '', 'admin_password' => '', 'whatsapp' => '', 'demo' => '',
];
$errors = [];

// ---------------------------------------------------------------------------
// Install
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (! hash_equals($_SESSION['install_token'], (string) ($_POST['token'] ?? ''))) {
        $errors[] = 'انتهت صلاحية الصفحة. أعد تحميلها وحاول مجددًا.';
    }
    $appUrl = rtrim(trim((string) $old['app_url']), '/');
    if (! filter_var($appUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'رابط الموقع غير صالح.';
    }
    if (trim((string) $old['app_name']) === '') {
        $errors[] = 'اكتب اسم الأكاديمية.';
    }
    if (! filter_var($old['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني للمدير غير صالح.';
    }
    if (mb_strlen((string) $old['admin_password']) < 10 || ! preg_match('/[A-Za-z]/', $old['admin_password']) || ! preg_match('/\d/', $old['admin_password'])) {
        $errors[] = 'كلمة مرور المدير: 10 أحرف على الأقل وتحتوي على حروف وأرقام.';
    }
    if (trim((string) $old['admin_name']) === '') {
        $errors[] = 'اكتب اسم المدير.';
    }

    // Test the database connection before writing anything.
    if (! $errors) {
        try {
            if ($old['db_type'] === 'sqlite') {
                $sqlite = $base.'/database/database.sqlite';
                if (! file_exists($sqlite)) {
                    touch($sqlite);
                }
                new PDO('sqlite:'.$sqlite);
            } else {
                new PDO(
                    'mysql:host='.$old['db_host'].';port='.((int) $old['db_port'] ?: 3306).';dbname='.$old['db_name'].';charset=utf8mb4',
                    (string) $old['db_user'], (string) $old['db_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
                );
            }
        } catch (Throwable $ex) {
            $errors[] = 'تعذّر الاتصال بقاعدة البيانات. تحقّق من الاسم والمستخدم وكلمة المرور (في cPanel تبدأ الأسماء ببادئة حسابك، مثل cpuser_academy). التفاصيل: '.$ex->getMessage();
        }
    }

    if (! $errors) {
        // Write .env from .env.example
        $env = file_get_contents($base.'/.env.example');
        $values = [
            'APP_NAME' => envValue(trim($old['app_name'])),
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'SESSION_SECURE_COOKIE' => str_starts_with($appUrl, 'https://') ? 'true' : 'false',
            'MAIL_MAILER' => 'log',
        ];
        if ($old['db_type'] === 'sqlite') {
            $values['DB_CONNECTION'] = 'sqlite';
            $env = preg_replace('/^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=.*$/m', '# $0', $env);
        } else {
            $values += [
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => envValue($old['db_host']), 'DB_PORT' => (string) ((int) $old['db_port'] ?: 3306),
                'DB_DATABASE' => envValue($old['db_name']), 'DB_USERNAME' => envValue($old['db_user']), 'DB_PASSWORD' => envValue($old['db_pass']),
            ];
        }
        foreach ($values as $key => $value) {
            $env = preg_match('/^'.$key.'=.*$/m', $env)
                ? preg_replace('/^'.$key.'=.*$/m', $key.'='.str_replace(['\\', '$'], ['\\\\', '\\$'], $value), $env)
                : $env."\n".$key.'='.$value;
        }
        if (file_put_contents($envPath, $env) === false) {
            $errors[] = 'تعذّر إنشاء ملف .env. اجعل المجلد الرئيسي قابلًا للكتابة.';
        } else {
            @chmod($envPath, 0640);
            @unlink($base.'/bootstrap/cache/config.php');
            $log = [];
            try {
                require $base.'/vendor/autoload.php';
                $app = require $base.'/bootstrap/app.php';
                $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
                $kernel->bootstrap();

                $kernel->call('migrate', ['--force' => true]);
                $log[] = 'تم إنشاء جداول قاعدة البيانات.';
                $kernel->call('db:seed', ['--class' => 'Database\\Seeders\\BaseSeeder', '--force' => true]);
                $log[] = 'تمت إضافة الصفحات والأدوات والتصنيفات الأساسية.';
                if (! empty($old['demo'])) {
                    $kernel->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
                    $log[] = 'تمت إضافة دورات تجريبية (احذفها قبل الإطلاق).';
                }
                try {
                    $kernel->call('storage:link');
                    $log[] = is_link(__DIR__.'/storage') || is_dir(__DIR__.'/storage') ? 'تم ربط مجلد الصور العامة.' : 'تعذّر ربط مجلد الصور العامة (اطلب من الاستضافة تفعيل symlinks).';
                } catch (Throwable) {
                    $log[] = 'تعذّر ربط مجلد الصور العامة (اطلب من الاستضافة تفعيل symlinks). الفيديوهات والملفات تعمل عاديًا.';
                }

                $user = App\Models\User::firstOrNew(['email' => mb_strtolower(trim($old['admin_email']))]);
                $user->fill(['name' => trim($old['admin_name']), 'password' => $old['admin_password'], 'locale' => 'ar']);
                $user->role = 'admin';
                $user->is_active = true;
                $user->email_verified_at = now();
                $user->save();
                $log[] = 'تم إنشاء حساب المدير.';

                $settings = ['site_name' => ['ar' => trim($old['app_name']), 'fr' => trim($old['app_name']), 'en' => trim($old['app_name'])]];
                $wa = preg_replace('/\D+/', '', (string) $old['whatsapp']);
                if ($wa !== '') {
                    $settings['whatsapp_number'] = $wa;
                }
                App\Support\Settings::set($settings);
            } catch (Throwable $ex) {
                // Roll back the .env so the installer can be run again.
                @unlink($envPath);
                $errors[] = 'فشل التثبيت: '.$ex->getMessage();
            }

            if (! $errors) {
                $deleted = @unlink(__FILE__);
                $_SESSION = [];
                page('تم التثبيت بنجاح 🎉',
                    '<div class="alert alert--success">'.implode('<br>', array_map('e', $log)).'</div>'
                    .'<ol class="small"><li>سجّل الدخول بحساب المدير.</li><li>افتح <strong>الإعدادات</strong> وأضف الشعار ومعلومات التواصل ورقم واتساب.</li><li>أنشئ أول دورة وارفع فيديوهاتها.</li>'
                    .'<li>لإرسال رسائل تأكيد البريد، أضف إعدادات البريد (MAIL_*) في ملف .env لاحقًا.</li></ol>'
                    .($deleted ? '' : '<div class="alert alert--warning">احذف الملف <code>public/install.php</code> من مدير الملفات لأسباب أمنية.</div>')
                    .'<p><a class="btn btn--amber btn--lg" href="login">تسجيل الدخول</a></p>');
                exit;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Form
// ---------------------------------------------------------------------------
ob_start();
?>
<ul class="list-plain small" style="margin-bottom:18px">
    <?php foreach ($checks as $label => $ok): ?>
        <li><?= $ok ? '✅' : '❌' ?> <?= e($label) ?></li>
    <?php endforeach ?>
</ul>
<?php if (! $ready): ?>
    <div class="alert alert--error">أصلح العناصر المعلّمة بـ ❌ أولًا (من cPanel ← Select PHP Version، أو صلاحيات المجلدات 755)، ثم أعد تحميل الصفحة.</div>
<?php else: ?>
    <?php if ($errors): ?>
        <div class="alert alert--error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="token" value="<?= e($_SESSION['install_token']) ?>">
        <h2 style="font-size:1.1rem">1. الموقع</h2>
        <div class="field"><label for="app_name">اسم الأكاديمية</label><input class="input" id="app_name" name="app_name" value="<?= e($old['app_name']) ?>" required></div>
        <div class="field"><label for="app_url">رابط الموقع</label><input class="input ltr" id="app_url" name="app_url" value="<?= e($old['app_url']) ?>" required></div>
        <div class="field"><label for="whatsapp">رقم واتساب الأكاديمية (اختياري، مثل 212600000000)</label><input class="input ltr" id="whatsapp" name="whatsapp" value="<?= e($old['whatsapp']) ?>"></div>

        <h2 style="font-size:1.1rem">2. قاعدة البيانات</h2>
        <p class="small muted">أنشئها من cPanel ← MySQL Databases، وأضف المستخدم إليها بكل الصلاحيات.</p>
        <div class="field"><label for="db_type">النوع</label>
            <select class="select" id="db_type" name="db_type" onchange="document.getElementById('mysql-fields').hidden = this.value !== 'mysql'">
                <option value="mysql" <?= $old['db_type'] === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB (موصى به)</option>
                <option value="sqlite" <?= $old['db_type'] === 'sqlite' ? 'selected' : '' ?>>SQLite (ملف، للمواقع الصغيرة أو التجربة)</option>
            </select></div>
        <div id="mysql-fields" <?= $old['db_type'] === 'sqlite' ? 'hidden' : '' ?>>
            <div class="form-grid">
                <div class="field"><label for="db_name">اسم قاعدة البيانات</label><input class="input ltr" id="db_name" name="db_name" value="<?= e($old['db_name']) ?>" placeholder="cpuser_academy"></div>
                <div class="field"><label for="db_user">اسم المستخدم</label><input class="input ltr" id="db_user" name="db_user" value="<?= e($old['db_user']) ?>" placeholder="cpuser_academy"></div>
                <div class="field"><label for="db_pass">كلمة المرور</label><input class="input ltr" type="password" id="db_pass" name="db_pass" value="<?= e($old['db_pass']) ?>"></div>
                <div class="field"><label for="db_host">الخادم</label><input class="input ltr" id="db_host" name="db_host" value="<?= e($old['db_host']) ?>"></div>
            </div>
            <input type="hidden" name="db_port" value="<?= e($old['db_port']) ?>">
        </div>

        <h2 style="font-size:1.1rem">3. حساب المدير</h2>
        <div class="form-grid">
            <div class="field"><label for="admin_name">الاسم</label><input class="input" id="admin_name" name="admin_name" value="<?= e($old['admin_name']) ?>" required></div>
            <div class="field"><label for="admin_email">البريد الإلكتروني</label><input class="input ltr" type="email" id="admin_email" name="admin_email" value="<?= e($old['admin_email']) ?>" required></div>
        </div>
        <div class="field"><label for="admin_password">كلمة المرور (10 أحرف على الأقل، حروف وأرقام)</label><input class="input ltr" type="password" id="admin_password" name="admin_password" minlength="10" required></div>
        <label class="check field"><input type="checkbox" name="demo" value="1" <?= $old['demo'] ? 'checked' : '' ?>> إضافة دورات تجريبية للاستكشاف (يمكن حذفها لاحقًا)</label>

        <button class="btn btn--amber btn--lg btn--block" type="submit">تثبيت الآن</button>
    </form>
<?php endif;
page('تثبيت الأكاديمية', (string) ob_get_clean());

function page(string $title, string $body): void
{
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        .'<title>'.e($title).'</title><link rel="stylesheet" href="css/app.css"></head><body>'
        .'<main class="container"><div class="card form-card" style="max-width:640px"><h1>'.e($title).'</h1>'.$body.'</div></main></body></html>';
}
