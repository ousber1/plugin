<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP template engine with layouts and sections.
 *
 * In a view:
 *   <?php $this->layout('layouts/admin', ['title' => 'Users']) ?>
 *   <?php $this->start('scripts') ?> ... <?php $this->stop() ?>
 * In a layout:
 *   <?= $this->section('content') ?>
 */
final class View
{
    private static string $basePath = '';
    /** @var array<string,mixed> */
    private static array $shared = [];

    /** @var array<string,string> */
    private array $sections = [];
    /** @var list<string> */
    private array $sectionStack = [];
    private ?string $layoutName = null;
    /** @var array<string,mixed> */
    private array $layoutData = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public static function make(string $view, array $data = []): string
    {
        return (new View())->render($view, $data);
    }

    public static function exists(string $view): bool
    {
        return is_file(self::resolve($view));
    }

    private static function resolve(string $view): string
    {
        if (!preg_match('#^[A-Za-z0-9_/\-.]+$#', $view) || str_contains($view, '..')) {
            throw new \InvalidArgumentException('Invalid view name');
        }
        return self::$basePath . '/' . str_replace('.', '/', $view) . '.php';
    }

    /** @param array<string,mixed> $data */
    public function render(string $view, array $data = []): string
    {
        $content = $this->renderFile(self::resolve($view), array_merge(self::$shared, $data));
        while ($this->layoutName !== null) {
            $layout = $this->layoutName;
            $layoutData = array_merge(self::$shared, $data, $this->layoutData);
            $this->layoutName = null;
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $content;
            }
            $content = $this->renderFile(self::resolve($layout), $layoutData);
            $this->sections['content'] = $content;
        }
        return $content;
    }

    /** @param array<string,mixed> $__data */
    private function renderFile(string $__file, array $__data): string
    {
        if (!is_file($__file)) {
            throw new \RuntimeException('View not found: ' . basename($__file));
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public function layout(string $name, array $data = []): void
    {
        $this->layoutName = $name;
        $this->layoutData = $data;
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new \LogicException('stop() without start()');
        }
        $this->sections[$name] = ($this->sections[$name] ?? '') . ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /** Render a partial (shares no section state). @param array<string,mixed> $data */
    public function insert(string $view, array $data = []): string
    {
        return (new View())->render($view, $data);
    }
}
