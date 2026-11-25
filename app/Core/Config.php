<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuration de l'application (fichier config/config.php généré par l'installateur).
 * Accès en notation pointée : Config::get('db.host').
 */
final class Config
{
    private static array $items = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Fichier de configuration introuvable : ' . basename($file));
        }
        $config = require $file;
        if (!is_array($config)) {
            throw new \RuntimeException('Fichier de configuration invalide.');
        }
        self::$items = $config;
        self::$loaded = true;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $ref = &self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    /**
     * Écrit un fichier de configuration PHP de manière atomique.
     */
    public static function write(string $file, array $config): void
    {
        $content = "<?php\n"
            . "// HO CREATIVE — configuration générée le " . date('Y-m-d H:i:s') . "\n"
            . "// Ne jamais publier ce fichier : il contient les identifiants de la base de données.\n"
            . "return " . var_export($config, true) . ";\n";
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d\'écrire le fichier de configuration.');
        }
        @chmod($tmp, 0640);
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Impossible de finaliser le fichier de configuration.');
        }
    }
}
