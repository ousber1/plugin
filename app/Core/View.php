<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rendu des vues PHP (app/Views) avec gabarit (layout) optionnel.
 * Toute donnée affichée doit passer par e() pour éviter le XSS.
 */
final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::capture($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture('layouts/' . $layout, array_merge($data, ['content' => $content]));
    }

    private static function capture(string $view, array $data): string
    {
        if (!preg_match('#^[a-z0-9_\-/]+$#i', $view) || str_contains($view, '..')) {
            throw new \InvalidArgumentException('Nom de vue invalide.');
        }
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vue introuvable : ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Inclut un fragment (partial) depuis une vue. */
    public static function partial(string $view, array $data = []): void
    {
        echo self::capture('partials/' . $view, $data);
    }
}
