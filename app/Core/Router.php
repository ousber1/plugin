<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimaliste compatible hébergement mutualisé.
 * - Motifs : "customers", "customers/{id}", "customers/{id}/files/{id2}"
 *   ({id}, {id2} = entiers, {slug} = [a-z0-9-_]).
 * - Middlewares : "auth", "guest", "can:permission.code".
 * - Toute requête POST est soumise à la vérification CSRF.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        $pattern = trim($pattern, '/');
        // preg_quote échappe les accolades : on remplace les jetons sur la version échappée
        $regex = str_replace(
            ['\{id\}', '\{id2\}', '\{slug\}'],
            ['(?P<id>\d+)', '(?P<id2>\d+)', '(?P<slug>[a-z0-9_\-]+)'],
            preg_quote($pattern, '#')
        );
        $this->routes[] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#',
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $route): void
    {
        $allowed = [];
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $route, $matches)) {
                continue;
            }
            if ($r['method'] !== $method) {
                $allowed[] = $r['method'];
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->runMiddleware($r['middleware']);
            if ($method === 'POST' && !Csrf::verifyRequest()) {
                $this->csrfFailure();
            }
            [$class, $action] = $r['handler'];
            $controller = new $class();
            $controller->{$action}(...array_values(array_map(
                static fn($v) => ctype_digit($v) ? (int) $v : $v,
                $params
            )));
            return;
        }

        if ($allowed !== []) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_unique($allowed)));
            echo View::render('errors/404', ['title' => 'Méthode non autorisée'], Auth::check() ? 'app' : 'auth');
            return;
        }

        http_response_code(404);
        echo View::render('errors/404', [], Auth::check() ? 'app' : 'auth');
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $m) {
            if ($m === 'auth' && !Auth::check()) {
                if (Request::isAjax()) {
                    Response::json(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.'], 401);
                }
                Session::set('_intended', Request::route());
                Response::redirect(url('login'));
            }
            if ($m === 'guest' && Auth::check()) {
                Response::redirect(url(''));
            }
            if (str_starts_with($m, 'can:') && !Auth::can(substr($m, 4))) {
                AuditLog::log('access_denied', 'security', null, null, 'Permission refusée : ' . substr($m, 4));
                if (Request::isAjax()) {
                    Response::json(['success' => false, 'message' => 'Action non autorisée.'], 403);
                }
                http_response_code(403);
                echo View::render('errors/403', ['permission' => substr($m, 4)], Auth::check() ? 'app' : 'auth');
                exit;
            }
        }
    }

    private function csrfFailure(): never
    {
        Logger::info('Jeton CSRF invalide', ['route' => Request::route(), 'ip' => Request::ip()]);
        if (Request::isAjax()) {
            Response::json(['success' => false, 'message' => 'Jeton de sécurité invalide. Rechargez la page.'], 419);
        }
        http_response_code(419);
        Session::flash('danger', 'Le formulaire a expiré. Veuillez réessayer.');
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $base = (string) Config::get('app.url', '');
        Response::redirect($referer !== '' && str_starts_with($referer, $base) ? $referer : url(''));
    }
}
