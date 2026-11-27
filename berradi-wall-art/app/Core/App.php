<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\CsrfMiddleware;
use App\Middleware\InstalledMiddleware;
use App\Middleware\LocaleMiddleware;
use App\Middleware\MiddlewareInterface;
use App\Middleware\SecurityHeadersMiddleware;
use Throwable;

/**
 * Application kernel: boots config, routes the request through middleware to a controller.
 */
final class App
{
    private static ?App $instance = null;
    private Router $router;
    private string $publicPath;

    /** @var array<string,class-string<MiddlewareInterface>> */
    private array $middlewareAliases = [
        'auth.admin' => \App\Middleware\AdminAuthMiddleware::class,
        'auth.customer' => \App\Middleware\CustomerAuthMiddleware::class,
        'guest' => \App\Middleware\GuestMiddleware::class,
        'can' => \App\Middleware\PermissionMiddleware::class,
        'installer' => \App\Middleware\InstallerMiddleware::class,
        'throttle' => \App\Middleware\ThrottleMiddleware::class,
    ];

    /** @var list<class-string<MiddlewareInterface>> */
    private array $globalMiddleware = [
        SecurityHeadersMiddleware::class,
        InstalledMiddleware::class,
        LocaleMiddleware::class,
        \App\Middleware\MaintenanceMiddleware::class,
        CsrfMiddleware::class,
    ];

    public function __construct(private string $basePath, ?string $publicPath = null)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->publicPath = rtrim($publicPath ?? $this->basePath . '/public', '/');
        self::$instance = $this;
        $this->router = new Router();
    }

    public static function instance(): App
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application not booted');
        }
        return self::$instance;
    }

    public static function hasInstance(): bool
    {
        return self::$instance !== null;
    }

    public function boot(): self
    {
        Env::load($this->basePath . '/.env');
        Config::init($this->basePath . '/config');
        date_default_timezone_set((string) Config::get('app.timezone', 'Africa/Casablanca'));
        mb_internal_encoding('UTF-8');

        Logger::setDirectory($this->storagePath('logs'));
        View::setBasePath($this->basePath . '/resources/views');
        Translator::init($this->basePath . '/resources/lang', (string) Config::get('app.locale', 'fr'));

        $debug = (bool) Config::get('app.debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug && PHP_SAPI !== 'cli' ? '1' : '0');
        ini_set('log_errors', '1');
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                Logger::warning("Deprecated: {$message} in {$file}:{$line}");
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        $this->loadRoutes();
        return $this;
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        foreach (['installer', 'web', 'admin'] as $file) {
            $path = $this->basePath . '/routes/' . $file . '.php';
            if (is_file($path)) {
                (static function (Router $router, string $path): void {
                    require $path;
                })($router, $path);
            }
        }
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(string $sub = ''): string
    {
        return $this->basePath . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    public function storagePath(string $sub = ''): string
    {
        return $this->basePath('storage' . ($sub !== '' ? '/' . ltrim($sub, '/') : ''));
    }

    public function publicPath(string $sub = ''): string
    {
        return $this->publicPath . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    private ?bool $installedOverride = null;

    public function isInstalled(): bool
    {
        if ($this->installedOverride !== null) {
            return $this->installedOverride;
        }
        return is_file($this->storagePath('installed.lock')) && is_file($this->basePath('.env'));
    }

    /** Integration tests only: run against a test database without writing installed.lock into the project. */
    public function markInstalledForTesting(bool $installed = true): void
    {
        $this->installedOverride = $installed;
    }

    public function handle(Request $request): Response
    {
        Request::setCurrent($request);
        try {
            Session::start($request);
            $pipeline = $this->globalMiddleware;
            $core = function (Request $request): Response {
                $match = $this->router->dispatch($request->method() === 'HEAD' ? 'GET' : $request->method(), $request->path());
                if ($match === null) {
                    throw new HttpException(404, 'Page not found');
                }
                [$route, $params] = $match;
                $request->setRouteParams($params);
                return $this->runRoute($route, $request, $params);
            };
            $response = $this->pipe($request, $pipeline, $core);
        } catch (Throwable $e) {
            $response = $this->renderException($e, $request);
        }
        if (Config::get('app.debug')) {
            $db = Database::hasInstance() ? Database::instance() : null;
            $response->header('X-Debug-Time', number_format((microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 1) . 'ms');
            if ($db !== null) {
                $response->header('X-Debug-Queries', $db->queryCount . ' (' . number_format($db->queryMs, 1) . 'ms)');
                if ($db->slow !== []) {
                    usort($db->slow, fn ($a, $b) => $b[0] <=> $a[0]);
                    $response->header('X-Debug-Slow', substr((string) json_encode(array_slice($db->slow, 0, 3), JSON_UNESCAPED_SLASHES), 0, 900));
                }
                $dup = array_filter($db->queryLog, fn ($n) => $n > 3);
                if ($dup !== []) {
                    arsort($dup);
                    $response->header('X-Debug-Repeated', substr((string) json_encode(array_slice($dup, 0, 3, true), JSON_UNESCAPED_SLASHES), 0, 900));
                }
            }
        }
        return $response;
    }

    /**
     * @param list<string> $middleware
     * @param callable(Request):Response $core
     */
    private function pipe(Request $request, array $middleware, callable $core): Response
    {
        $next = $core;
        foreach (array_reverse($middleware) as $definition) {
            [$class, $params] = $this->resolveMiddleware($definition);
            $current = $next;
            $next = static function (Request $req) use ($class, $params, $current): Response {
                /** @var MiddlewareInterface $mw */
                $mw = new $class();
                return $mw->handle($req, $current, ...$params);
            };
        }
        return $next($request);
    }

    /** @return array{0:class-string<MiddlewareInterface>,1:list<string>} */
    private function resolveMiddleware(string $definition): array
    {
        $params = [];
        $name = $definition;
        if (str_contains($definition, ':')) {
            [$name, $p] = explode(':', $definition, 2);
            $params = explode(',', $p);
        }
        $class = $this->middlewareAliases[$name] ?? $name;
        if (!class_exists($class)) {
            throw new \RuntimeException('Unknown middleware ' . $name);
        }
        return [$class, $params];
    }

    /** @param array<string,string> $params */
    private function runRoute(Route $route, Request $request, array $params): Response
    {
        return $this->pipe($request, $route->middleware, function (Request $request) use ($route, $params): Response {
            $action = $route->action;
            if (is_array($action)) {
                [$class, $method] = $action;
                $controller = new $class($request);
                $result = $controller->{$method}(...array_values($params));
            } else {
                $result = $action($request, ...array_values($params));
            }
            if ($result instanceof Response) {
                return $result;
            }
            if (is_array($result)) {
                return Response::json($result);
            }
            return Response::html((string) $result);
        });
    }

    public function renderException(Throwable $e, Request $request): Response
    {
        if ($e instanceof ValidationException) {
            if ($request->isAjax()) {
                return Response::json(['ok' => false, 'message' => __('validation.failed'), 'errors' => $e->errors], 422);
            }
            $old = $e->old;
            foreach (array_keys($old) as $k) {
                if (preg_match('/pass|token|_token|card/i', (string) $k)) {
                    unset($old[$k]);
                }
            }
            Session::flash('errors', $e->errors);
            Session::flash('old', $old);
            $ref = (string) $request->header('Referer', '');
            $root = rtrim($request->rootUrl(), '/');
            $target = ($ref !== '' && ($ref === $root || str_starts_with($ref, $root . '/'))) ? $ref : url($request->path());
            return Response::redirect($target);
        }

        $status = $e instanceof HttpException ? $e->status : 500;
        if ($status >= 500) {
            Logger::error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'url' => $request->path(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 15),
            ]);
        }
        if ($request->isAjax()) {
            $msg = $status >= 500 && !Config::get('app.debug') ? 'Server error' : $e->getMessage();
            return Response::json(['ok' => false, 'message' => $msg], $status);
        }
        $view = in_array($status, [403, 404, 405, 419, 429, 500, 503], true) ? 'errors/' . $status : 'errors/500';
        try {
            $html = View::make($view, [
                'exception' => Config::get('app.debug') ? $e : null,
                'message' => $e instanceof HttpException ? $e->getMessage() : '',
            ]);
        } catch (Throwable $inner) {
            $html = '<!doctype html><meta charset="utf-8"><title>Error ' . $status . '</title><h1>Error ' . $status . '</h1>';
            if (Config::get('app.debug')) {
                $html .= '<pre>' . htmlspecialchars((string) $e) . "\n\n" . htmlspecialchars((string) $inner) . '</pre>';
            }
        }
        return Response::html($html, $status);
    }
}
