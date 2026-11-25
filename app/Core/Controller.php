<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Contrôleur de base : rendu, redirections, autorisations.
 */
abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    protected function view(string $view, array $data = [], ?string $layout = 'app'): void
    {
        echo View::render($view, $data, $layout);
    }

    protected function redirect(string $route, array $query = []): never
    {
        Response::redirect(url($route, $query));
    }

    protected function back(string $fallback = ''): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $base = (string) Config::get('app.url', '');
        // Redirection uniquement vers notre propre application (anti open-redirect)
        if ($referer !== '' && $base !== '' && str_starts_with($referer, $base)) {
            Response::redirect($referer);
        }
        $this->redirect($fallback);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    /** Refuse l'accès (403) si la permission manque. */
    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            AuditLog::log('access_denied', 'security', null, null, 'Permission refusée : ' . $permission);
            if (Request::isAjax()) {
                $this->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
            }
            http_response_code(403);
            $this->view('errors/403', ['permission' => $permission], Auth::check() ? 'app' : 'auth');
            exit;
        }
    }
}
