<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Import / export CSV compatible Excel (version française) :
 * séparateur « ; », encodage UTF-8 avec BOM, décimales à virgule.
 * L'import accepte « ; » ou « , », avec ou sans BOM, et l'encodage Windows-1252.
 */
final class Csv
{
    /**
     * Envoie un fichier CSV au navigateur.
     * @param array<string,string> $columns clé de ligne => intitulé de colonne
     * @param iterable<array> $rows
     */
    public static function download(string $filename, array $columns, iterable $rows): never
    {
        $filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename) ?: 'export.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_values($columns), ';', '"', '');
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = self::cell($row[$key] ?? '');
            }
            fputcsv($out, $line, ';', '"', '');
        }
        fclose($out);
        exit;
    }

    private static function cell(mixed $value): string
    {
        if (is_float($value)) {
            return str_replace('.', ',', rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'));
        }
        $value = (string) $value;
        // Neutralise l'injection de formules dans Excel (=, +, -, @ en début de cellule)
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)) {
            $value = "'" . $value;
        }
        return $value;
    }

    /**
     * Lit un CSV envoyé. Retourne la liste des lignes indexées par en-tête normalisé
     * (minuscules, sans accents, espaces → _). Ex. « Prix de vente » → prix_de_vente.
     *
     * @return array{0: array<int,string>, 1: array<int,array<string,string>>}
     */
    public static function parseUpload(string $field, int $maxRows = 5000): array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Veuillez choisir un fichier CSV valide.');
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            throw new \RuntimeException('Le fichier dépasse 5 Mo.');
        }
        $content = (string) file_get_contents($file['tmp_name']);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $firstLine = strtok($content, "\n") ?: '';
        $sep = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+b');
        fwrite($handle, $content);
        rewind($handle);
        $header = fgetcsv($handle, 0, $sep, '"', '');
        if (!$header) {
            throw new \RuntimeException('Fichier vide.');
        }
        $keys = array_map([self::class, 'normalize'], $header);
        $rows = [];
        while (($data = fgetcsv($handle, 0, $sep, '"', '')) !== false) {
            if ($data === [null] || implode('', $data) === '') {
                continue;
            }
            if (count($rows) >= $maxRows) {
                throw new \RuntimeException("Le fichier dépasse {$maxRows} lignes.");
            }
            $row = [];
            foreach ($keys as $i => $k) {
                $row[$k] = trim((string) ($data[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);
        return [$keys, $rows];
    }

    public static function normalize(?string $label): string
    {
        $label = mb_strtolower(trim((string) $label));
        $label = strtr($label, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '°' => '']);
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $label), '_');
    }

    /** Nombre saisi à la française (« 1 250,50 ») → float, null si vide/invalide. */
    public static function number(?string $value): ?float
    {
        $v = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim((string) $value));
        return $v !== '' && is_numeric($v) ? (float) $v : null;
    }

    /** Booléen saisi (oui/non, 1/0, vrai/faux, x). */
    public static function bool(?string $value, bool $default = true): bool
    {
        $v = self::normalize($value);
        if ($v === '') {
            return $default;
        }
        return in_array($v, ['1', 'oui', 'o', 'yes', 'y', 'vrai', 'true', 'x', 'actif'], true);
    }
}
