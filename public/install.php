<?php
/**
 * OmniChannel Business Management System - Installer
 *
 * Upload all files to your server and visit: https://yourdomain.com/install.php
 * Compatible with shared hosting (cPanel, LiteSpeed, Apache)
 */

// Laravel root is one level up from public/
$basePath = realpath(__DIR__ . '/..');

$step = $_GET['step'] ?? '1';
$errors = [];
$success = [];

// Check if already installed
if (file_exists($basePath . '/storage/installed.lock') && $step !== 'done') {
    die('<div style="font-family:sans-serif;max-width:500px;margin:100px auto;text-align:center;padding:40px;"><h2>Already Installed</h2><p>The application is already installed.</p><p style="margin-top:16px;"><a href="/" style="color:#4f46e5;font-weight:bold;">Go to Dashboard</a></p><p style="margin-top:8px;font-size:12px;color:#999;">Delete <code>storage/installed.lock</code> to reinstall.</p></div>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $appUrl = rtrim(trim($_POST['app_url'] ?? 'http://localhost'), '/');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@omnichannel.com');
    $adminPass = $_POST['admin_pass'] ?? 'password';

    // Validate inputs
    if (empty($dbName)) $errors[] = 'Database name is required.';
    if (empty($dbUser)) $errors[] = 'Database username is required.';
    if (empty($adminEmail)) $errors[] = 'Admin email is required.';
    if (strlen($adminPass) < 6) $errors[] = 'Admin password must be at least 6 characters.';

    // Test database connection
    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // Generate .env file in Laravel root
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        $envContent = "APP_NAME=OmniChannel
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL={$appUrl}

DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPass}

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=database

# WhatsApp Cloud API (configure later in Settings)
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_VERIFY_TOKEN=omnichannel_verify

# OpenAI API (for AI ad copy suggestions)
OPENAI_API_KEY=

# Meta Ads API
META_ADS_ACCESS_TOKEN=
META_ADS_ACCOUNT_ID=

# Google Ads API
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_DEVELOPER_TOKEN=
";
        if (!file_put_contents($basePath . '/.env', $envContent)) {
            $errors[] = 'Could not write .env file. Check folder permissions (chmod 755).';
        }

        if (empty($errors)) {
            // Find PHP binary
            $phpBin = PHP_BINARY ?: 'php';

            // Run migrations
            $migrationOutput = [];
            $migrationCode = 0;
            exec("cd " . escapeshellarg($basePath) . " && {$phpBin} artisan migrate --force 2>&1", $migrationOutput, $migrationCode);

            if ($migrationCode !== 0) {
                $errors[] = 'Migration failed: ' . implode("\n", array_slice($migrationOutput, -5));
            } else {
                $success[] = 'Database tables created (' . count($migrationOutput) . ' migrations).';
            }
        }

        if (empty($errors)) {
            // Seed default data
            $seedOutput = [];
            exec("cd " . escapeshellarg($basePath) . " && {$phpBin} artisan db:seed --force 2>&1", $seedOutput);
            $success[] = 'Default data seeded (admin user, sample products, settings).';

            // Update admin credentials with user's chosen email/password
            try {
                $pdo->exec("USE `{$dbName}`");
                $hashedPass = password_hash($adminPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE role = 'admin' LIMIT 1");
                $stmt->execute([$adminEmail, $hashedPass]);
                $success[] = 'Admin account: ' . htmlspecialchars($adminEmail);
            } catch (Exception $e) {
                $errors[] = 'Could not update admin credentials: ' . $e->getMessage();
            }

            // Create storage symlink
            exec("cd " . escapeshellarg($basePath) . " && {$phpBin} artisan storage:link 2>&1");

            // Cache optimization
            exec("cd " . escapeshellarg($basePath) . " && {$phpBin} artisan config:cache 2>&1");
            exec("cd " . escapeshellarg($basePath) . " && {$phpBin} artisan route:cache 2>&1");
            exec("cd " . escapeshellarg($basePath) . " && {$phpBin} artisan view:cache 2>&1");

            // Mark as installed
            file_put_contents($basePath . '/storage/installed.lock', date('Y-m-d H:i:s'));
            $success[] = 'Installation complete!';
            $step = 'done';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install OmniChannel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { primary: { 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b' } } } }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-primary-900 via-primary-800 to-primary-950 flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 backdrop-blur-sm rounded-2xl mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h1 class="text-3xl font-bold text-white">OmniChannel</h1>
            <p class="text-white/60 mt-1">Business Management System — Installer</p>
        </div>

        <!-- Step Indicator -->
        <div class="flex items-center justify-center gap-2 mb-6">
            <?php for ($i = 1; $i <= 3; $i++):
                $stepNum = $step === 'done' ? 3 : (int)$step;
                $isActive = $i <= $stepNum;
            ?>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?= $isActive ? 'bg-white text-primary-700' : 'bg-white/20 text-white/50' ?>">
                    <?php if ($i < $stepNum): ?>✓<?php else: ?><?= $i ?><?php endif; ?>
                </div>
                <?php if ($i < 3): ?>
                <div class="w-8 h-0.5 <?= $isActive && $i < $stepNum ? 'bg-white' : 'bg-white/20' ?>"></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <?php if ($step === '1'): ?>
        <!-- Step 1: Requirements Check -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold mb-1">System Requirements</h2>
            <p class="text-sm text-gray-500 mb-5">Checking your server configuration</p>
            <?php
            $checks = [
                ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), 'Current: PHP ' . PHP_VERSION],
                ['PDO MySQL Extension', extension_loaded('pdo_mysql'), 'Required for database'],
                ['Mbstring Extension', extension_loaded('mbstring'), 'String handling'],
                ['OpenSSL Extension', extension_loaded('openssl'), 'Encryption support'],
                ['Tokenizer Extension', extension_loaded('tokenizer'), 'Template engine'],
                ['JSON Extension', extension_loaded('json'), 'API communication'],
                ['cURL Extension', extension_loaded('curl'), 'WhatsApp/Ads API'],
                ['Fileinfo Extension', extension_loaded('fileinfo'), 'File uploads'],
                ['storage/ Writable', is_writable($basePath . '/storage'), 'chmod 775 storage/'],
                ['bootstrap/cache/ Writable', is_writable($basePath . '/bootstrap/cache'), 'chmod 775 bootstrap/cache/'],
                ['Composer Installed', is_dir($basePath . '/vendor'), 'Run: composer install'],
            ];
            $allPassed = true;
            ?>
            <div class="space-y-2 mb-6">
                <?php foreach ($checks as [$label, $ok, $hint]): ?>
                <div class="flex items-center justify-between py-2.5 px-4 rounded-lg <?= $ok ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100' ?>">
                    <div>
                        <span class="text-sm font-medium"><?= $label ?></span>
                        <span class="text-xs text-gray-400 ml-2"><?= $hint ?></span>
                    </div>
                    <span class="text-sm font-bold <?= $ok ? 'text-emerald-600' : 'text-red-600' ?>"><?= $ok ? '✓ PASS' : '✗ FAIL' ?></span>
                </div>
                <?php $allPassed = $allPassed && $ok; endforeach; ?>
            </div>
            <?php if ($allPassed): ?>
            <a href="?step=2" class="block w-full py-3 bg-primary-600 text-white text-center rounded-xl font-semibold hover:bg-primary-700 transition shadow-lg shadow-primary-600/25">
                Continue to Setup →
            </a>
            <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                <p class="text-red-700 text-sm font-medium">Please fix the failed requirements before continuing.</p>
                <a href="?" class="inline-block mt-2 text-sm text-primary-600 hover:underline">Re-check</a>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($step === '2'): ?>
        <!-- Step 2: Database & Admin Setup -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold mb-1">Database & Admin Setup</h2>
            <p class="text-sm text-gray-500 mb-5">Configure your MySQL database and admin account</p>

            <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
                <p class="text-sm font-medium text-red-700 mb-1">Installation Error:</p>
                <?php foreach ($errors as $e): ?>
                <p class="text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <!-- Database Section -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-6 h-6 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                        MySQL Database
                    </h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">Host</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Port</label><input type="text" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none"></div>
                    </div>
                    <div class="mt-3"><label class="block text-xs font-medium text-gray-600 mb-1">Database Name</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none" placeholder="omnichannel_db"></div>
                    <div class="grid grid-cols-2 gap-3 mt-3">
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Username</label><input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Password</label><input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none"></div>
                    </div>
                </div>

                <!-- App URL -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-6 h-6 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                        Application URL
                    </h3>
                    <input type="url" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                    <p class="text-xs text-gray-400 mt-1">Your domain without trailing slash</p>
                </div>

                <!-- Admin Account -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-6 h-6 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                        Admin Account
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Email</label><input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? 'admin@omnichannel.com') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Password</label><input type="password" name="admin_pass" value="<?= htmlspecialchars($_POST['admin_pass'] ?? '') ?>" required minlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none" placeholder="Min 6 characters"></div>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-primary-600 text-white rounded-xl font-semibold hover:bg-primary-700 transition shadow-lg shadow-primary-600/25">
                    Install Now
                </button>
                <p class="text-xs text-gray-400 text-center">This will create database tables and configure the application</p>
            </form>
        </div>

        <?php elseif ($step === 'done'): ?>
        <!-- Step 3: Complete -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
                <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-2xl font-bold mb-2">Installation Complete!</h2>
            <p class="text-gray-500 mb-5">Your OmniChannel system is ready to use.</p>

            <div class="text-left bg-gray-50 rounded-xl p-4 space-y-2 mb-5">
                <?php foreach ($success as $s): ?>
                <p class="text-sm text-emerald-600 flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <?= htmlspecialchars($s) ?>
                </p>
                <?php endforeach; ?>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5">
                <p class="text-sm text-amber-700 font-semibold">Security Notice</p>
                <p class="text-xs text-amber-600 mt-1">Delete <code class="bg-amber-100 px-1 py-0.5 rounded">public/install.php</code> after setup for security.</p>
            </div>

            <a href="/" class="block w-full py-3 bg-primary-600 text-white rounded-xl font-semibold hover:bg-primary-700 transition shadow-lg shadow-primary-600/25">
                Go to Dashboard →
            </a>
        </div>
        <?php endif; ?>

        <p class="text-center text-white/30 text-xs mt-6">OmniChannel Business Management System v1.0</p>
    </div>
</body>
</html>
