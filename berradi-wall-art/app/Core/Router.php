<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];
    /** @var array<string,Route> */
    private array $named = [];
    /** @var list<array{prefix:string,middleware:list<string>,name:string}> */
    private array $groupStack = [];

    public function get(string $path, callable|array $action): Route
    {
        return $this->add(['GET', 'HEAD'], $path, $action);
    }

    public function post(string $path, callable|array $action): Route
    {
        return $this->add(['POST'], $path, $action);
    }

    public function put(string $path, callable|array $action): Route
    {
        return $this->add(['PUT', 'PATCH'], $path, $action);
    }

    public function delete(string $path, callable|array $action): Route
    {
        return $this->add(['DELETE'], $path, $action);
    }

    /** @param list<string> $methods */
    public function match(array $methods, string $path, callable|array $action): Route
    {
        return $this->add(array_map('strtoupper', $methods), $path, $action);
    }

    /**
     * @param array{prefix?:string,middleware?:list<string>,name?:string} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $parent = end($this->groupStack) ?: ['prefix' => '', 'middleware' => [], 'name' => ''];
        $this->groupStack[] = [
            'prefix' => rtrim($parent['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge($parent['middleware'], $attributes['middleware'] ?? []),
            'name' => $parent['name'] . ($attributes['name'] ?? ''),
        ];
        $callback($this);
        array_pop($this->groupStack);
    }

    /** @param list<string> $methods */
    private function add(array $methods, string $path, callable|array $action): Route
    {
        $group = end($this->groupStack) ?: ['prefix' => '', 'middleware' => [], 'name' => ''];
        $full = '/' . trim($group['prefix'] . '/' . trim($path, '/'), '/');
        $route = new Route($methods, $full, $action, $this, $group['name']);
        $route->middleware($group['middleware']);
        $this->routes[] = $route;
        return $route;
    }

    /** @internal */
    public function registerName(string $name, Route $route): void
    {
        $this->named[$name] = $route;
    }

    /**
     * @return array{0:Route,1:array<string,string>}|null  null = not found; [route, params]
     * @throws HttpException 405
     */
    public function dispatch(string $method, string $path): ?array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $route->matches($path);
            if ($params === null) {
                continue;
            }
            if (in_array($method, $route->methods, true)) {
                return [$route, $params];
            }
            $allowed = array_merge($allowed, $route->methods);
        }
        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed');
        }
        return null;
    }

    /** @param array<string,string|int> $params */
    public function url(string $name, array $params = []): string
    {
        $route = $this->named[$name] ?? null;
        if ($route === null) {
            throw new \InvalidArgumentException("Route [{$name}] not defined");
        }
        $query = [];
        $path = $route->path;
        foreach ($params as $key => $value) {
            if (str_contains($path, '{' . $key . '}') || str_contains($path, '{' . $key . '?}')) {
                $path = str_replace(['{' . $key . '}', '{' . $key . '?}'], rawurlencode((string) $value), $path);
            } else {
                $query[$key] = $value;
            }
        }
        $path = preg_replace('#/\{[^}]+\?\}#', '', $path) ?? $path;
        if (preg_match('/\{[^}]+\}/', $path)) {
            throw new \InvalidArgumentException("Missing parameter for route [{$name}]");
        }
        return $path . ($query ? '?' . http_build_query($query) : '');
    }

    public function has(string $name): bool
    {
        return isset($this->named[$name]);
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }
}
