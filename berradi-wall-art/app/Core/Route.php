<?php

declare(strict_types=1);

namespace App\Core;

final class Route
{
    /** @var list<string> */
    public array $middleware = [];
    public ?string $name = null;
    private ?string $regex = null;

    /**
     * @param list<string> $methods
     * @param callable|array{0:class-string,1:string} $action
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $path,
        public readonly mixed $action,
        private Router $router,
        private string $namePrefix = '',
    ) {
    }

    /** @param string|list<string> $middleware */
    public function middleware(string|array $middleware): self
    {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = $m;
        }
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $this->namePrefix . $name;
        $this->router->registerName($this->name, $this);
        return $this;
    }

    private function compile(): string
    {
        if ($this->regex === null) {
            $pattern = preg_replace_callback('#/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}#', function ($m) {
                $optional = ($m[2] ?? '') === '?';
                $seg = '/(?P<' . $m[1] . '>[^/]+)';
                return $optional ? '(?:' . $seg . ')?' : $seg;
            }, $this->path);
            $this->regex = '#^' . $pattern . '$#u';
        }
        return $this->regex;
    }

    /** @return array<string,string>|null */
    public function matches(string $path): ?array
    {
        if (!preg_match($this->compile(), $path, $m)) {
            return null;
        }
        $params = [];
        foreach ($m as $k => $v) {
            if (is_string($k) && $v !== '') {
                $params[$k] = $v;
            }
        }
        return $params;
    }
}
