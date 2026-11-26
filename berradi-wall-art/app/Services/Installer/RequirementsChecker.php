<?php

declare(strict_types=1);

namespace App\Services\Installer;

/**
 * Checks the real server environment (PHP version, extensions, permissions, ini limits).
 */
final class RequirementsChecker
{
    public function __construct(private string $basePath, private string $publicPath)
    {
    }

    /** @return list<array{label:string,required:string,current:string,ok:bool,critical:bool}> */
    public function server(): array
    {
        $rows = [];
        $rows[] = [
            'label' => 'PHP',
            'required' => '>= 8.2',
            'current' => PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'critical' => true,
        ];
        $rows[] = [
            'label' => 'Server API',
            'required' => 'Apache / LiteSpeed / FPM',
            'current' => PHP_SAPI . (isset($_SERVER['SERVER_SOFTWARE']) ? ' — ' . mb_substr((string) $_SERVER['SERVER_SOFTWARE'], 0, 60) : ''),
            'ok' => true,
            'critical' => false,
        ];
        if (function_exists('apache_get_modules')) {
            $rows[] = [
                'label' => 'mod_rewrite',
                'required' => 'enabled',
                'current' => in_array('mod_rewrite', apache_get_modules(), true) ? 'enabled' : 'not detected',
                'ok' => in_array('mod_rewrite', apache_get_modules(), true),
                'critical' => false,
            ];
        }
        $ini = [
            ['upload_max_filesize', '>= 25M', 25],
            ['post_max_size', '>= 30M', 30],
            ['memory_limit', '>= 256M', 256],
        ];
        foreach ($ini as [$key, $label, $minMb]) {
            $val = (string) ini_get($key);
            $mb = self::toMb($val);
            $rows[] = ['label' => $key, 'required' => $label, 'current' => $val === '' ? '-' : $val, 'ok' => $mb < 0 || $mb >= $minMb, 'critical' => false];
        }
        $met = (int) ini_get('max_execution_time');
        $rows[] = ['label' => 'max_execution_time', 'required' => '>= 60', 'current' => (string) $met, 'ok' => $met === 0 || $met >= 60, 'critical' => false];
        $rows[] = [
            'label' => 'HTTPS',
            'required' => 'recommended',
            'current' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'yes' : 'no',
            'ok' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
            'critical' => false,
        ];
        $rows[] = [
            'label' => 'Composer vendor (Dompdf, PHPMailer)',
            'required' => 'uploaded',
            'current' => is_file($this->basePath . '/vendor/autoload.php') ? 'present' : 'missing',
            'ok' => is_file($this->basePath . '/vendor/autoload.php'),
            'critical' => false,
        ];
        return $rows;
    }

    /** @return list<array{label:string,required:string,current:string,ok:bool,critical:bool}> */
    public function extensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo', 'session', 'ctype', 'filter', 'dom', 'libxml'];
        $recommended = ['gd' => 'image previews', 'curl' => 'payment providers', 'intl' => 'slugs & formatting', 'zip' => 'backups', 'exif' => 'photo orientation', 'iconv' => 'PDF fonts'];
        $rows = [];
        foreach ($required as $ext) {
            $rows[] = ['label' => $ext, 'required' => 'required', 'current' => extension_loaded($ext) ? (phpversion($ext) ?: 'loaded') : 'missing', 'ok' => extension_loaded($ext), 'critical' => true];
        }
        foreach ($recommended as $ext => $why) {
            $rows[] = ['label' => $ext, 'required' => 'recommended (' . $why . ')', 'current' => extension_loaded($ext) ? (phpversion($ext) ?: 'loaded') : 'missing', 'ok' => extension_loaded($ext), 'critical' => false];
        }
        return $rows;
    }

    /** @return list<array{label:string,required:string,current:string,ok:bool,critical:bool}> */
    public function permissions(): array
    {
        $dirs = [
            'storage' => $this->basePath . '/storage',
            'storage/logs' => $this->basePath . '/storage/logs',
            'storage/cache' => $this->basePath . '/storage/cache',
            'storage/sessions' => $this->basePath . '/storage/sessions',
            'storage/private_uploads' => $this->basePath . '/storage/private_uploads',
            'storage/previews' => $this->basePath . '/storage/previews',
            'storage/invoices' => $this->basePath . '/storage/invoices',
            'storage/backups' => $this->basePath . '/storage/backups',
            'public/uploads' => $this->publicPath . '/uploads',
        ];
        $rows = [];
        foreach ($dirs as $label => $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $ok = is_dir($dir) && is_writable($dir);
            $rows[] = ['label' => $label, 'required' => 'writable', 'current' => $ok ? 'writable' : 'not writable', 'ok' => $ok, 'critical' => true];
        }
        $env = $this->basePath . '/.env';
        $envOk = is_file($env) ? is_writable($env) : is_writable($this->basePath);
        $rows[] = ['label' => '.env', 'required' => 'writable (or create manually)', 'current' => $envOk ? 'writable' : 'not writable', 'ok' => $envOk, 'critical' => false];
        return $rows;
    }

    /** @param list<array{critical:bool,ok:bool}> $rows */
    public static function passes(array $rows): bool
    {
        foreach ($rows as $r) {
            if ($r['critical'] && !$r['ok']) {
                return false;
            }
        }
        return true;
    }

    /** Convert ini shorthand (128M, 1G) to MB. -1 => unlimited. */
    public static function toMb(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;
        return match ($unit) {
            'g' => $num * 1024,
            'm' => $num,
            'k' => intdiv($num, 1024),
            default => intdiv($num, 1024 * 1024),
        };
    }
}
