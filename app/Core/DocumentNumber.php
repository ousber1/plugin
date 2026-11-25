<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Numérotation unique et sans trou des documents (devis, factures, avoirs…).
 *
 * La ligne de séquence est verrouillée (SELECT … FOR UPDATE) : deux caisses
 * validant une facture au même moment obtiennent deux numéros différents.
 * DOIT être appelé à l'intérieur de la transaction qui crée le document,
 * afin qu'un rollback libère aussi le numéro (pas de trou de numérotation).
 *
 * Jetons de format : {PREFIX} {YYYY} {YY} {MM} {SEQ}
 */
final class DocumentNumber
{
    public static function next(string $docType, ?\DateTimeInterface $date = null): string
    {
        $db = Database::instance();
        if (!$db->inTransaction()) {
            throw new \LogicException('DocumentNumber::next() doit être appelé dans une transaction.');
        }
        $date ??= new \DateTimeImmutable('now');

        $seq = $db->fetch('SELECT * FROM document_sequences WHERE doc_type = ? FOR UPDATE', [$docType]);
        if (!$seq) {
            throw new \RuntimeException('Séquence de numérotation inconnue : ' . $docType);
        }

        $period = match ($seq['reset_policy']) {
            'yearly'  => $date->format('Y'),
            'monthly' => $date->format('Y-m'),
            default   => null,
        };
        $number = (int) $seq['next_number'];
        if ($period !== null && $seq['current_period'] !== $period) {
            // Nouvelle période : on repart à 1 (sauf premier usage avec un numéro de départ configuré)
            $number = $seq['current_period'] === null ? max(1, $number) : 1;
        }

        $formatted = self::format($seq['format'], $seq['prefix'], $number, (int) $seq['padding'], $date);

        $db->update('document_sequences', [
            'next_number'    => $number + 1,
            'current_period' => $period,
        ], 'id = :id', ['id' => $seq['id']]);

        return $formatted;
    }

    /** Aperçu du prochain numéro, sans le consommer. */
    public static function preview(string $docType): ?string
    {
        $seq = Database::instance()->fetch('SELECT * FROM document_sequences WHERE doc_type = ?', [$docType]);
        if (!$seq) {
            return null;
        }
        $date = new \DateTimeImmutable('now');
        $period = match ($seq['reset_policy']) {
            'yearly'  => $date->format('Y'),
            'monthly' => $date->format('Y-m'),
            default   => null,
        };
        $number = ($period !== null && $seq['current_period'] !== null && $seq['current_period'] !== $period)
            ? 1 : (int) $seq['next_number'];
        return self::format($seq['format'], $seq['prefix'], $number, (int) $seq['padding'], $date);
    }

    public static function format(string $format, string $prefix, int $number, int $padding, \DateTimeInterface $date): string
    {
        return strtr($format, [
            '{PREFIX}' => $prefix,
            '{YYYY}'   => $date->format('Y'),
            '{YY}'     => $date->format('y'),
            '{MM}'     => $date->format('m'),
            '{SEQ}'    => str_pad((string) $number, max(1, $padding), '0', STR_PAD_LEFT),
        ]);
    }

    /** Valide un format saisi par l'utilisateur. */
    public static function isValidFormat(string $format): bool
    {
        if (!str_contains($format, '{SEQ}') || strlen($format) > 60) {
            return false;
        }
        $rest = preg_replace('/\{(PREFIX|YYYY|YY|MM|SEQ)\}/', '', $format);
        return $rest !== null && preg_match('#^[A-Za-z0-9\-/_.]*$#', $rest) === 1;
    }
}
