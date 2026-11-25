<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Settings;

/**
 * Fonctions utilitaires globales utilisées par les vues et contrôleurs.
 */

/** Échappement HTML (protection XSS) — à utiliser pour TOUTE donnée affichée. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * true si les adresses simplifiées (/login) sont activées ET que le serveur
 * applique réellement la réécriture (vérifié par la sonde /__hoc-rewrite-test).
 * Tant que ce n'est pas prouvé, les liens utilisent index.php?route=… qui
 * fonctionne sur tout hébergement : l'application ne renvoie jamais vers une 404.
 */
function pretty_urls_active(): bool
{
    static $active = null;
    return $active ??= (bool) Config::get('app.pretty_urls', true) && is_file(BASE_PATH . '/storage/cache/rewrite.ok');
}

/** URL interne de l'application. url('customers', ['page' => 2]) */
function url(string $route = '', array $query = []): string
{
    $base = rtrim((string) Config::get('app.url', ''), '/');
    $route = trim($route, '/');
    if (pretty_urls_active()) {
        $u = $base . '/' . $route;
        return $query ? $u . '?' . http_build_query($query) : $u;
    }
    $params = $route !== '' ? array_merge(['route' => $route], $query) : $query;
    return $base . '/index.php' . ($params ? '?' . http_build_query($params) : '');
}

/** URL d'un fichier statique avec version anti-cache. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/assets/' . $path;
    $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return rtrim((string) Config::get('app.url', ''), '/') . '/assets/' . $path . '?v=' . $version;
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function auth_user(): ?array
{
    return Auth::user();
}

function old(string $key, mixed $default = ''): mixed
{
    return Session::old($key, $default);
}

/**
 * Formate un montant selon les paramètres de devise (par défaut : 1 234,50 DH).
 */
function money(float|int|string|null $amount, bool $withSymbol = true): string
{
    $decimals = (int) Settings::get('currency_decimals', 2);
    $formatted = number_format(
        (float) ($amount ?? 0),
        $decimals,
        (string) Settings::get('decimal_separator', ','),
        (string) Settings::get('thousands_separator', ' ')
    );
    if (!$withSymbol) {
        return $formatted;
    }
    $symbol = (string) Settings::get('currency_symbol', 'DH');
    return Settings::get('currency_position', 'after') === 'before'
        ? $symbol . ' ' . $formatted
        : $formatted . ' ' . $symbol;
}

/** Nombre au format français : 1 234,5 */
function number_fr(float|int|string|null $value, int $decimals = 2): string
{
    return number_format((float) ($value ?? 0), $decimals, ',', ' ');
}

/** Date au format configuré (d/m/Y par défaut). */
function format_date(?string $date, bool $withTime = false): string
{
    if ($date === null || $date === '' || str_starts_with($date, '0000')) {
        return '—';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '—';
    }
    $format = (string) Settings::get('date_format', 'd/m/Y');
    return date($withTime ? $format . ' H:i' : $format, $ts);
}

/** Arrondi monétaire (évite les erreurs de flottants). */
function round_money(float $value): float
{
    return round($value + 0.0, 2, PHP_ROUND_HALF_UP);
}

/** Lien WhatsApp (format international marocain). */
function whatsapp_link(?string $phone, string $message = ''): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    } elseif (str_starts_with($digits, '0')) {
        $digits = '212' . substr($digits, 1);
    }
    return 'https://wa.me/' . $digits . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

/**
 * Libellés et couleurs des statuts de documents (partagés par tous les modules).
 * @return array<string, array{0: string, 1: string}> statut => [libellé, couleur Bootstrap]
 */
function status_map(string $domain): array
{
    return match ($domain) {
        'quotation' => ['draft' => ['Brouillon', 'secondary'], 'sent' => ['Envoyé', 'info'], 'pending' => ['En attente', 'warning'],
            'accepted' => ['Accepté', 'success'], 'refused' => ['Refusé', 'danger'], 'expired' => ['Expiré', 'dark'], 'converted' => ['Converti', 'primary']],
        'invoice' => ['draft' => ['Brouillon', 'secondary'], 'validated' => ['Validée', 'info'], 'paid' => ['Payée', 'success'],
            'partial' => ['Partiellement payée', 'warning'], 'unpaid' => ['Impayée', 'danger'], 'overdue' => ['En retard', 'danger'], 'cancelled' => ['Annulée', 'dark']],
        'sale' => ['draft' => ['Brouillon', 'secondary'], 'suspended' => ['En attente', 'warning'], 'completed' => ['Terminée', 'success'],
            'cancelled' => ['Annulée', 'dark'], 'refunded' => ['Remboursée', 'danger'], 'partially_refunded' => ['Remb. partiel', 'warning']],
        'production' => ['order' => ['Commande', 'secondary'], 'design' => ['Design', 'info'], 'design_validation' => ['Validation design', 'info'],
            'production' => ['Production', 'primary'], 'finishing' => ['Finition', 'primary'], 'quality_control' => ['Contrôle qualité', 'warning'],
            'ready' => ['Prêt', 'success'], 'delivery' => ['Livraison', 'success'], 'completed' => ['Terminé', 'dark'], 'cancelled' => ['Annulé', 'danger']],
        'credit_note' => ['draft' => ['Brouillon', 'secondary'], 'validated' => ['Validé', 'info'], 'applied' => ['Imputé', 'success'],
            'refunded' => ['Remboursé', 'success'], 'cancelled' => ['Annulé', 'dark']],
        'payment' => ['valid' => ['Valide', 'success'], 'cancelled' => ['Annulé', 'dark']],
        'inventory' => ['draft' => ['Brouillon', 'secondary'], 'validated' => ['Validé', 'success'], 'cancelled' => ['Annulé', 'dark']],
        default => [],
    };
}

/** Badge HTML d'un statut (libellé + couleur, jamais la couleur seule). */
function status_badge(string $domain, ?string $status): string
{
    [$label, $color] = status_map($domain)[(string) $status] ?? [(string) $status, 'secondary'];
    $text = in_array($color, ['warning', 'info', 'light'], true) ? ' text-dark' : '';
    return '<span class="badge text-bg-' . e($color) . $text . '">' . e($label) . '</span>';
}

/** Lien de tri pour les en-têtes de tableaux (conserve les filtres). */
function sort_link(string $label, string $key, array $f): string
{
    $query = $_GET;
    unset($query['route'], $query['page']);
    $active = ($f['sort'] ?? '') === $key;
    $query['sort'] = $key;
    $query['dir'] = $active && ($f['dir'] ?? 'ASC') === 'ASC' ? 'desc' : 'asc';
    $icon = $active ? (($f['dir'] ?? 'ASC') === 'ASC' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(url(\App\Core\Request::route(), $query)) . '">' . e($label) . $icon . '</a>';
}

/** Chaîne de requête des filtres courants (pour les liens d'export). */
function current_query(array $extra = []): array
{
    $q = $_GET;
    unset($q['route'], $q['page']);
    return array_merge($q, $extra);
}

/**
 * Champ caché « route » pour les formulaires GET : en mode index.php?route=…,
 * le navigateur remplace la chaîne de requête de l'attribut action par les champs
 * du formulaire ; la route doit donc être transmise comme champ.
 */
function route_field(string $route): string
{
    return pretty_urls_active() ? '' : '<input type="hidden" name="route" value="' . e(trim($route, '/')) . '">';
}
