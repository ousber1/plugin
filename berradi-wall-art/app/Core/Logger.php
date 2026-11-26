<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File logger writing daily files into storage/logs.
 */
final class Logger
{
    private static string $dir = '';

    public static function setDirectory(string $dir): void
    {
        self::$dir = rtrim($dir, '/');
    }

    /** @param array<string,mixed> $context */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$dir === '' || !is_dir(self::$dir) || !is_writable(self::$dir)) {
            error_log('[' . $level . '] ' . $message);
            return;
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            str_replace(["\r", "\n"], ' ', $message),
            $context ? ' ' . json_encode(self::scrub($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : ''
        );
        @file_put_contents(self::$dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** Never write secrets to logs. @param array<string,mixed> $context @return array<string,mixed> */
    private static function scrub(array $context): array
    {
        foreach ($context as $k => $v) {
            if (is_string($k) && preg_match('/pass|token|secret|key/i', $k)) {
                $context[$k] = '***';
            } elseif (is_array($v)) {
                $context[$k] = self::scrub($v);
            }
        }
        return $context;
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }
}
