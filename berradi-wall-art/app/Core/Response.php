<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }
    }

    public static function html(string $body, int $status = 200): Response
    {
        return new Response($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): Response
    {
        return new Response(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function redirect(string $url, int $status = 302): Response
    {
        // prevent header injection & open redirects to foreign hosts with protocol-relative URLs
        $url = str_replace(["\r", "\n"], '', $url);
        if (str_starts_with($url, '//')) {
            $url = '/';
        }
        return new Response('', $status, ['Location' => $url]);
    }

    public static function file(string $path, string $mime, ?string $downloadName = null, bool $inline = true): Response
    {
        $r = new Response('', 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
        if ($downloadName !== null) {
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
            $r->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"');
        }
        $r->filePath = $path;
        return $r;
    }

    public ?string $filePath = null;

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = str_replace(["\r", "\n"], '', $value);
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }
        echo $this->body;
    }
}
