<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;

/**
 * Liens de partage sécurisés des documents (WhatsApp, e-mail, QR code).
 * Jeton aléatoire de 160 bits, expiration configurable, révocable.
 * Le lien ne donne accès qu'à CE document (consultation et PDF).
 */
final class ShareService
{
    public const TYPES = ['quotation', 'invoice', 'credit_note', 'payment', 'sales_order'];

    /** Lien existant valide ou nouveau lien. */
    public static function token(string $docType, int $docId): string
    {
        if (!in_array($docType, self::TYPES, true)) {
            throw new \InvalidArgumentException('Type de document non partageable.');
        }
        $db = Database::instance();
        $row = $db->fetch(
            'SELECT token FROM document_shares WHERE doc_type = ? AND doc_id = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > (NOW() + INTERVAL 7 DAY))
              ORDER BY id DESC LIMIT 1',
            [$docType, $docId]
        );
        if ($row) {
            return $row['token'];
        }
        $days = max(1, (int) Settings::get('share_link_days', 60));
        $token = bin2hex(random_bytes(20));
        $db->insert('document_shares', [
            'doc_type' => $docType, 'doc_id' => $docId, 'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400), 'created_by' => Auth::id(),
        ]);
        return $token;
    }

    public static function url(string $docType, int $docId): string
    {
        return url('d/' . self::token($docType, $docId));
    }

    /** Document correspondant à un jeton valide (ou null). Compte les consultations. */
    public static function resolve(string $token, bool $count = true): ?array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }
        $db = Database::instance();
        $row = $db->fetch('SELECT * FROM document_shares WHERE token = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())', [$token]);
        if ($row && $count) {
            $db->query('UPDATE document_shares SET views = views + 1, last_viewed_at = NOW() WHERE id = ?', [$row['id']]);
        }
        return $row ?: null;
    }

    public static function revoke(string $docType, int $docId): int
    {
        return Database::instance()->query('UPDATE document_shares SET revoked_at = NOW() WHERE doc_type = ? AND doc_id = ? AND revoked_at IS NULL', [$docType, $docId])->rowCount();
    }
}
