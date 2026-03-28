<?php
/**
 * OmniChannel Business Management System - Installer
 *
 * This script helps set up the application on shared hosting (cPanel) or any server.
 * Upload all files to your server and visit: https://yourdomain.com/install.php
 */

$step = $_GET['step'] ?? '1';
$errors = [];
$success = [];

// Check if already installed
if (file_exists(__DIR__ . '/storage/installed.lock') && $step !== 'done') {
    die('<div style="font-family:sans-serif;max-width:500px;margin:100px auto;text-align:center;"><h2>Already Installed</h2><p>The application is already installed. Delete <code>storage/installed.lock</code> to reinstall.</p></div>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    $dbHost = $_POST['db_host'] ?? '127.0.0.1';
    $dbPort = $_POST['db_port'] ?? '3306';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $appUrl = $_POST['app_url'] ?? 'http://localhost';
    $adminEmail = $_POST['admin_email'] ?? 'admin@omnichannel.com';
    $adminPass = $_POST['admin_pass'] ?? 'password';

    // Test database connection
    try {
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $errors[] = 'Database connection failed: ' . $e->getMessage();
    }

    if (empty($errors)) {
        // Generate .env
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        $envContent = "APP_NAME=OmniChannel
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appUrl}

DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPass}

SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=database

# WhatsApp Cloud API
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_VERIFY_TOKEN=omnichannel_verify

# OpenAI (for AI ad suggestions)
OPENAI_API_KEY=

# Meta Ads API
META_ADS_ACCESS_TOKEN=
META_ADS_ACCOUNT_ID=

# Google Ads API
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_DEVELOPER_TOKEN=
";
        file_put_contents(__DIR__ . '/.env', $envContent);

        // Run migrations and seed
        $output = [];
        exec('cd ' . escapeshellarg(__DIR__) . ' && php artisan migrate --force 2>&1', $output);
        $success[] = 'Database tables created.';

        exec('cd ' . escapeshellarg(__DIR__) . ' && php artisan db:seed --force 2>&1', $output);
        $success[] = 'Default data seeded.';

        // Update admin credentials
        try {
            $pdo->exec("UPDATE users SET email = " . $pdo->quote($adminEmail) . ", password = '" . password_hash($adminPass, PASSWORD_BCRYPT) . "' WHERE role = 'admin' LIMIT 1");
            $success[] = 'Admin account configured.';
        } catch (Exception $e) {
            $errors[] = 'Could not update admin: ' . $e->getMessage();
        }

        // Create storage link
        exec('cd ' . escapeshellarg(__DIR__) . ' && php artisan storage:link 2>&1', $output);

        // Optimize
        exec('cd ' . escapeshellarg(__DIR__) . ' && php artisan config:cache 2>&1', $output);
        exec('cd ' . escapeshellarg(__DIR__) . ' && php artisan route:cache 2>&1', $output);
        exec('cd ' . escapeshellarg(__DIR__) . ' && php artisan view:cache 2>&1', $output);

        // Mark as installed
        file_put_contents(__DIR__ . '/storage/installed.lock', date('Y-m-d H:i:s'));
        $success[] = 'Installation complete!';
        $step = 'done';
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
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-900 via-indigo-800 to-indigo-950 flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">OmniChannel Installer</h1>
            <p class="text-indigo-300 mt-2">Business Management System</p>
        </div>

        <?php if ($step === '1'): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold mb-4">System Requirements</h2>
            <?php
            $checks = [
                ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>=')],
                ['PDO MySQL Extension', extension_loaded('pdo_mysql')],
                ['Mbstring Extension', extension_loaded('mbstring')],
                ['OpenSSL Extension', extension_loaded('openssl')],
                ['Tokenizer Extension', extension_loaded('tokenizer')],
                ['JSON Extension', extension_loaded('json')],
                ['cURL Extension', extension_loaded('curl')],
                ['Fileinfo Extension', extension_loaded('fileinfo')],
                ['storage/ Writable', is_writable(__DIR__ . '/storage')],
                ['bootstrap/cache/ Writable', is_writable(__DIR__ . '/bootstrap/cache')],
            ];
            $allPassed = true;
            ?>
            <div class="space-y-2 mb-6">
                <?php foreach ($checks as [$label, $ok]): ?>
                <div class="flex items-center justify-between py-2 px-3 rounded-lg <?= $ok ? 'bg-emerald-50' : 'bg-red-50' ?>">
                    <span class="text-sm"><?= $label ?></span>
                    <span class="text-sm font-bold <?= $ok ? 'text-emerald-600' : 'text-red-600' ?>"><?= $ok ? 'PASS' : 'FAIL' ?></span>
                </div>
                <?php $allPassed = $allPassed && $ok; endforeach; ?>
            </div>
            <?php if ($allPassed): ?>
            <a href="?step=2" class="block w-full py-2.5 bg-indigo-600 text-white text-center rounded-lg font-medium hover:bg-indigo-700 transition">Continue Setup</a>
            <?php else: ?>
            <p class="text-red-600 text-sm text-center">Please fix the failed requirements before continuing.</p>
            <?php endif; ?>
        </div>

        <?php elseif ($step === '2'): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold mb-4">Database & Admin Setup</h2>
            <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <?php foreach ($errors as $e): ?><p class="text-sm text-red-600"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium mb-1">DB Host</label><input type="text" name="db_host" value="127.0.0.1" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium mb-1">DB Port</label><input type="text" name="db_port" value="3306" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                </div>
                <div><label class="block text-sm font-medium mb-1">Database Name</label><input type="text" name="db_name" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="omnichannel_db"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium mb-1">DB Username</label><input type="text" name="db_user" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium mb-1">DB Password</label><input type="password" name="db_pass" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                </div>
                <div><label class="block text-sm font-medium mb-1">Application URL</label><input type="url" name="app_url" value="http://localhost" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                <hr>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium mb-1">Admin Email</label><input type="email" name="admin_email" value="admin@omnichannel.com" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium mb-1">Admin Password</label><input type="password" name="admin_pass" value="password" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                </div>
                <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition">Install Now</button>
            </form>
        </div>

        <?php elseif ($step === 'done'): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-xl font-bold mb-2">Installation Complete!</h2>
            <div class="text-left space-y-1 mb-6">
                <?php foreach ($success as $s): ?><p class="text-sm text-emerald-600">&#10003; <?= htmlspecialchars($s) ?></p><?php endforeach; ?>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-amber-700 font-medium">Important: Delete this install.php file for security!</p>
            </div>
            <a href="/" class="block w-full py-2.5 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition">Go to Dashboard</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
