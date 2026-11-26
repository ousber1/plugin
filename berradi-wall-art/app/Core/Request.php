<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,string> */
    private array $routeParams = [];
    private static ?Request $current = null;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     * @param array<string,mixed> $files
     * @param array<string,mixed> $cookies
     */
    public function __construct(
        private array $query,
        private array $post,
        private array $server,
        private array $files = [],
        private array $cookies = [],
        private ?string $rawBody = null,
    ) {
    }

    public static function capture(): Request
    {
        $post = $_POST;
        $raw = null;
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $raw = (string) file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $post = $json;
            }
        }
        self::$current = new Request($_GET, $post, $_SERVER, $_FILES, $_COOKIE, $raw);
        return self::$current;
    }

    public static function current(): ?Request
    {
        return self::$current;
    }

    public static function setCurrent(?Request $request): void
    {
        self::$current = $request;
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $override = strtoupper((string) ($this->post['_method'] ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public function isPost(): bool
    {
        return $this->method() !== 'GET' && $this->method() !== 'HEAD';
    }

    /**
     * Base path when the app lives in a sub-folder (e.g. /shop). Empty string at web root.
     */
    public function basePath(): string
    {
        $script = str_replace('\\', '/', (string) ($this->server['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        return $dir === '.' ? '' : $dir;
    }

    /** Path relative to the application, always starting with "/". */
    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $base = $this->basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        // front controller explicitly in URL: /index.php/foo
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, 10);
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Accept French-formatted amounts ("1 234,50") in the given POST fields by rewriting them to "1234.50"
     * before validation. Only unambiguous values are touched.
     *
     * @param list<string> $keys
     */
    public function normalizeDecimals(array $keys): void
    {
        foreach ($keys as $k) {
            $v = $this->post[$k] ?? null;
            if (is_string($v)) {
                $clean = str_replace(["\u{00A0}", "\u{202F}", ' '], '', trim($v));
                if (preg_match('/^-?\d+,\d{1,3}$/', $clean)) {
                    $clean = str_replace(',', '.', $clean);
                }
                $this->post[$k] = $clean;
            }
        }
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), [1, '1', 'on', 'true', 'yes', true], true);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /** @return array<string,mixed> */
    public function post(): array
    {
        return $this->post;
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $all[$k] ?? null;
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return is_array($f) ? $f : null;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $v = $this->cookies[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v = $this->server[$key] ?? null;
        if ($v === null && strtolower($name) === 'content-type') {
            $v = $this->server['CONTENT_TYPE'] ?? null;
        }
        return is_string($v) ? $v : $default;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest'
            || str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((int) ($this->server['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function host(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');
        return preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host) ? $host : 'localhost';
    }

    /** Detected root URL (scheme + host + base path). */
    public function rootUrl(): string
    {
        return ($this->isSecure() ? 'https' : 'http') . '://' . $this->host() . $this->basePath();
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /** @param array<string,string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }
}
