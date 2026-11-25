<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Réception sécurisée des fichiers envoyés.
 *
 * - Contrôle du type MIME réel (finfo), jamais de l'extension déclarée.
 * - Liste blanche d'extensions par usage.
 * - Nom de fichier aléatoire (aucun nom fourni par l'utilisateur sur le disque).
 * - Images publiques (logo, cachet, signature) : ré-encodées avec GD quand
 *   c'est possible, ce qui élimine tout contenu caché (polyglottes).
 * - Fichiers privés (maquettes, justificatifs) : stockés dans storage/uploads,
 *   servis uniquement par un contrôleur qui vérifie les droits.
 */
final class Uploader
{
    public const IMAGE_TYPES = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public const DOCUMENT_TYPES = [
        'application/pdf'          => 'pdf',
        'image/png'                => 'png',
        'image/jpeg'               => 'jpg',
        'image/webp'               => 'webp',
        'image/svg+xml'            => 'svg',
        'application/postscript'   => 'eps',
        'application/illustrator'  => 'ai',
        'application/zip'          => 'zip',
    ];

    /** Message d'erreur lisible pour un code d'erreur PHP d'envoi. */
    public static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement envoyé.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier envoyé.',
            default => 'Erreur lors de l\'envoi du fichier.',
        };
    }

    /** true si un fichier a été choisi pour ce champ. */
    public static function has(string $field): bool
    {
        return isset($_FILES[$field]) && is_array($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Enregistre une image publique dans assets/uploads/{$folder}.
     * Retourne le chemin relatif à assets/ (ex. "uploads/branding/ab12….png").
     */
    public static function publicImage(string $field, string $folder, int $maxKb = 2048, int $maxSide = 1600): string
    {
        $file = self::check($field, self::IMAGE_TYPES, $maxKb);
        $folder = self::safeFolder($folder);
        $dir = BASE_PATH . '/assets/uploads/' . $folder;
        self::ensureDir($dir);

        $ext = self::IMAGE_TYPES[$file['mime']];
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $dir . '/' . $name;

        if (!self::reencode($file['tmp_name'], $file['mime'], $target, $maxSide)) {
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new \RuntimeException('Impossible d\'enregistrer le fichier.');
            }
        }
        @chmod($target, 0644);
        return 'uploads/' . $folder . '/' . $name;
    }

    /**
     * Enregistre un fichier privé dans storage/uploads/{$folder}.
     * Retourne [stored_name relatif, mime, taille, nom d'origine nettoyé].
     */
    public static function privateFile(string $field, string $folder, array $types = self::DOCUMENT_TYPES, ?int $maxKb = null): array
    {
        $maxKb ??= (int) Config::get('uploads.max_size_mb', 50) * 1024;
        $file = self::check($field, $types, $maxKb);
        $folder = self::safeFolder($folder);
        $dir = BASE_PATH . '/storage/uploads/' . $folder;
        self::ensureDir($dir);
        $name = bin2hex(random_bytes(16)) . '.' . $types[$file['mime']];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Impossible d\'enregistrer le fichier.');
        }
        @chmod($dir . '/' . $name, 0640);
        $original = preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', basename((string) $file['name'])) ?: 'fichier';
        return [$folder . '/' . $name, $file['mime'], (int) $file['size'], mb_substr($original, 0, 200)];
    }

    /** Supprime un fichier public précédemment enregistré (chemin relatif à assets/). */
    public static function deletePublic(?string $relative): void
    {
        if ($relative === null || $relative === '' || !preg_match('#^uploads/[a-z0-9_\-]+/[a-f0-9]{24}\.(png|jpg|webp)$#', $relative)) {
            return;
        }
        $path = BASE_PATH . '/assets/' . $relative;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function check(string $field, array $types, int $maxKb): array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || is_array($file['error'] ?? null)) {
            throw new \RuntimeException('Aucun fichier envoyé.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::errorMessage((int) $file['error']));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Fichier invalide.');
        }
        if ((int) $file['size'] > $maxKb * 1024) {
            throw new \RuntimeException('Le fichier dépasse ' . ($maxKb >= 1024 ? round($maxKb / 1024, 1) . ' Mo' : $maxKb . ' Ko') . '.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        // Fichiers Illustrator : reconnus comme PDF ou PostScript selon la version
        if ($mime === 'application/pdf' && str_ends_with(strtolower((string) $file['name']), '.ai') && isset($types['application/illustrator'])) {
            $mime = 'application/illustrator';
        }
        if (!isset($types[$mime])) {
            throw new \RuntimeException('Type de fichier non autorisé. Formats acceptés : ' . implode(', ', array_unique(array_map('strtoupper', $types))) . '.');
        }
        $file['mime'] = $mime;
        return $file;
    }

    private static function reencode(string $source, string $mime, string $target, int $maxSide): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $img = match ($mime) {
            'image/png'  => @imagecreatefrompng($source),
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default      => false,
        };
        if (!$img) {
            throw new \RuntimeException('Image illisible ou corrompue.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxSide / max($w, $h));
        if ($scale < 1) {
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $resized = imagecreatetruecolor($nw, $nh);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        } else {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
        $ok = match ($mime) {
            'image/png'  => imagepng($img, $target, 6),
            'image/jpeg' => imagejpeg($img, $target, 88),
            'image/webp' => function_exists('imagewebp') && imagewebp($img, $target, 88),
            default      => false,
        };
        imagedestroy($img);
        return (bool) $ok;
    }

    private static function safeFolder(string $folder): string
    {
        if (!preg_match('/^[a-z0-9_\-]+(\/[a-z0-9_\-]+)*$/', $folder)) {
            throw new \InvalidArgumentException('Dossier de destination invalide.');
        }
        return $folder;
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier de destination.');
        }
    }
}
