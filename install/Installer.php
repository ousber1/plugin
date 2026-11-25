<?php
declare(strict_types=1);

/**
 * HO CREATIVE — moteur d'installation.
 * Vérifie l'environnement, prépare la base, importe le schéma et les données
 * de référence, enregistre l'entreprise et l'administrateur, écrit la
 * configuration et verrouille l'installation.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\DocumentNumber;

final class Installer
{
    public const LOCK_FILE = BASE_PATH . '/storage/installed.lock';
    public const CONFIG_FILE = BASE_PATH . '/config/config.php';

    /** Documents dont le préfixe et le numéro de départ sont configurables à l'installation. */
    public const NUMBERED_DOCS = [
        'quotation'   => ['Devis', 'DEV'],
        'invoice'     => ['Facture', 'FAC'],
        'credit_note' => ['Avoir', 'AV'],
        'receipt'     => ['Reçu de paiement', 'REC'],
        'sale'        => ['Ticket de vente', 'TK'],
        'sales_order' => ['Bon de commande', 'BC'],
        'delivery'    => ['Bon de livraison', 'BL'],
        'production'  => ['Bon de production', 'BP'],
    ];

    public const NUMBER_FORMATS = [
        '{PREFIX}-{YYYY}-{SEQ}' => 'FAC-2026-0001',
        '{PREFIX}{YYYY}-{SEQ}'  => 'FAC2026-0001',
        '{PREFIX}-{YY}{MM}-{SEQ}' => 'FAC-2601-0001',
        '{PREFIX}/{YYYY}/{SEQ}' => 'FAC/2026/0001',
        '{PREFIX}-{SEQ}'        => 'FAC-0001',
    ];

    public static function isInstalled(): bool
    {
        return is_file(self::LOCK_FILE);
    }

    /* =====================================================================
       Étape 1 : prérequis
       ===================================================================== */

    public static function requirements(): array
    {
        $checks = [];
        $add = static function (string $label, bool $ok, string $detail, bool $required = true) use (&$checks): void {
            $checks[] = compact('label', 'ok', 'detail', 'required');
        };

        $add('PHP 8.2 ou supérieur', version_compare(PHP_VERSION, '8.2.0', '>='), 'Version détectée : ' . PHP_VERSION);
        foreach ([
            'pdo'       => 'PDO',
            'pdo_mysql' => 'PDO MySQL',
            'mbstring'  => 'Mbstring (texte multi-octets)',
            'json'      => 'JSON',
            'openssl'   => 'OpenSSL (jetons sécurisés)',
            'fileinfo'  => 'Fileinfo (contrôle des fichiers envoyés)',
            'session'   => 'Sessions',
            'dom'       => 'DOM (génération des PDF)',
        ] as $ext => $label) {
            $add('Extension ' . $label, extension_loaded($ext), extension_loaded($ext) ? 'Chargée' : 'Manquante — à activer dans cPanel > Sélecteur de PHP');
        }
        foreach ([
            'gd'   => 'GD (images, logos, QR codes)',
            'zip'  => 'Zip (sauvegardes compressées)',
            'intl' => 'Intl (formats régionaux)',
            'curl' => 'cURL (envoi d\'e-mails / API)',
        ] as $ext => $label) {
            $add('Extension ' . $label, extension_loaded($ext), extension_loaded($ext) ? 'Chargée' : 'Recommandée', false);
        }

        foreach (['config', 'storage', 'storage/logs', 'storage/backups', 'storage/uploads', 'storage/cache', 'storage/sessions'] as $dir) {
            $path = BASE_PATH . '/' . $dir;
            $ok = is_dir($path) && is_writable($path);
            $add('Dossier « ' . $dir . ' » accessible en écriture', $ok, $ok ? 'OK' : 'Donnez les droits 755 (ou 775) à ce dossier');
        }

        $add('Fichiers d\'installation SQL présents',
            is_file(BASE_PATH . '/database/schema.sql') && is_file(BASE_PATH . '/database/seed.sql'),
            'database/schema.sql et database/seed.sql');

        $upload = self::iniBytes((string) ini_get('upload_max_filesize'));
        $add('Taille max. des fichiers envoyés', $upload >= 8 * 1024 * 1024, 'Actuelle : ' . ini_get('upload_max_filesize') . ' (16M ou plus recommandé)', false);
        $add('Connexion HTTPS', self::isHttps(), self::isHttps() ? 'Active' : 'Fortement recommandée en production (certificat SSL gratuit dans cPanel)', false);

        return $checks;
    }

    public static function requirementsMet(array $checks): bool
    {
        foreach ($checks as $c) {
            if ($c['required'] && !$c['ok']) {
                return false;
            }
        }
        return true;
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $num = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $num * 1024 ** 3,
            'm' => $num * 1024 ** 2,
            'k' => $num * 1024,
            default => $num,
        };
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** URL publique de l'application détectée à partir de la requête courante. */
    public static function detectAppUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!preg_match('/^[a-z0-9.\-:\[\]]+$/i', $host)) {
            $host = 'localhost';
        }
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php', 2)), '/');
        return (self::isHttps() ? 'https' : 'http') . '://' . $host . $dir;
    }

    /* =====================================================================
       Étape 2 : base de données
       ===================================================================== */

    /**
     * Teste la connexion, crée la base si demandé et vérifie qu'elle ne contient
     * pas déjà une installation. Retourne [bool, message].
     */
    public static function testDatabase(array $db, bool $create): array
    {
        if (!preg_match('/^[A-Za-z0-9_\-$]{1,64}$/', $db['name'])) {
            return [false, 'Nom de base invalide (lettres, chiffres, _ et - uniquement).'];
        }
        try {
            $pdo = Database::connect($db, false);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            if (!self::versionSupported($version)) {
                return [false, 'Version de MySQL/MariaDB non supportée (' . $version . '). Requis : MySQL 5.7+ ou MariaDB 10.3+.'];
            }
            if ($create) {
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
            $pdo = Database::connect($db, true);
        } catch (\PDOException $e) {
            return [false, self::explainPdoError($e)];
        }

        $existing = self::existingTables($pdo);
        $conflicts = array_intersect($existing, self::schemaTables());
        if ($conflicts) {
            return [false, 'Cette base contient déjà ' . count($conflicts) . ' table(s) de HO CREATIVE (ex. « '
                . implode(', ', array_slice($conflicts, 0, 3)) . ' »). Utilisez une base vide ou supprimez ces tables.'];
        }
        return [true, 'Connexion réussie à ' . $version . '. Base « ' . $db['name'] . ' » prête.'];
    }

    private static function versionSupported(string $version): bool
    {
        if (stripos($version, 'mariadb') !== false) {
            return version_compare(preg_replace('/[^0-9.].*$/', '', $version), '10.3.0', '>=');
        }
        return version_compare(preg_replace('/[^0-9.].*$/', '', $version), '5.7.0', '>=');
    }

    private static function explainPdoError(\PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        return match ($code) {
            1045 => 'Accès refusé : nom d\'utilisateur ou mot de passe MySQL incorrect.',
            1044 => 'Cet utilisateur n\'a pas les droits sur la base. Dans cPanel > Bases de données MySQL, associez l\'utilisateur à la base avec « Tous les privilèges ».',
            1049 => 'Base de données inconnue. Créez-la dans cPanel ou cochez « Créer la base si elle n\'existe pas ».',
            2002, 2005 => 'Serveur MySQL injoignable. Vérifiez l\'hôte (souvent « localhost ») et le port.',
            1007 => 'La base existe déjà.',
            default => 'Erreur de connexion : ' . $e->getMessage(),
        };
    }

    private static function existingTables(\PDO $pdo): array
    {
        return array_map('strtolower', $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Liste des tables définies dans schema.sql. */
    public static function schemaTables(): array
    {
        $sql = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
        foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
            $sql .= "\n" . file_get_contents($file);
        }
        preg_match_all('/^CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?([a-z0-9_]+)`?/mi', $sql, $m);
        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /**
     * Découpe un script SQL en instructions, en respectant les chaînes entre
     * apostrophes (avec '' échappés) et en ignorant les commentaires « -- ».
     */
    public static function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if (!$inString && $ch === '-' && ($sql[$i + 1] ?? '') === '-') {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl;
                $buffer .= "\n";
                continue;
            }
            if ($ch === "'") {
                if ($inString && ($sql[$i + 1] ?? '') === "'") {
                    $buffer .= "''";
                    $i++;
                    continue;
                }
                $inString = !$inString;
            }
            if ($ch === '\\' && $inString) {
                $buffer .= $ch . ($sql[$i + 1] ?? '');
                $i++;
                continue;
            }
            if ($ch === ';' && !$inString) {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }

    /* =====================================================================
       Validation des étapes
       ===================================================================== */

    public static function validateBusiness(array $in): array
    {
        $errors = [];
        if (trim($in['business_name'] ?? '') === '') {
            $errors[] = 'Le nom de l\'entreprise est obligatoire.';
        }
        if (($in['business_email'] ?? '') !== '' && !filter_var($in['business_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse e-mail de l\'entreprise est invalide.';
        }
        if (($in['business_ice'] ?? '') !== '' && !preg_match('/^\d{15}$/', $in['business_ice'])) {
            $errors[] = 'L\'ICE doit contenir exactement 15 chiffres.';
        }
        if (($in['business_website'] ?? '') !== '' && !filter_var($in['business_website'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Le site web doit être une URL complète (ex. https://www.hocreative.ma).';
        }
        foreach (['business_phone' => 'téléphone', 'business_whatsapp' => 'WhatsApp'] as $k => $label) {
            if (($in[$k] ?? '') !== '' && !preg_match('/^\+?[0-9 ().\-]{6,20}$/', $in[$k])) {
                $errors[] = "Le numéro de {$label} est invalide.";
            }
        }
        if (($in['bank_rib'] ?? '') !== '' && !preg_match('/^[0-9 ]{24,30}$/', $in['bank_rib'])) {
            $errors[] = 'Le RIB doit comporter 24 chiffres.';
        }
        return $errors;
    }

    public static function validateSettings(array $in): array
    {
        $errors = [];
        if (!preg_match('/^[A-Z]{3}$/', $in['currency_code'] ?? '')) {
            $errors[] = 'Le code devise doit comporter 3 lettres majuscules (ex. MAD).';
        }
        if (trim($in['currency_symbol'] ?? '') === '' || mb_strlen($in['currency_symbol']) > 5) {
            $errors[] = 'Le symbole monétaire est obligatoire (5 caractères max).';
        }
        if (!in_array((int) ($in['default_tax_id'] ?? 0), [1, 2, 3, 4, 5], true)) {
            $errors[] = 'Taux de TVA par défaut invalide.';
        }
        if (!array_key_exists($in['number_format'] ?? '', self::NUMBER_FORMATS)) {
            $errors[] = 'Format de numérotation invalide.';
        }
        $padding = (int) ($in['number_padding'] ?? 0);
        if ($padding < 3 || $padding > 8) {
            $errors[] = 'Le nombre de chiffres doit être compris entre 3 et 8.';
        }
        if (!in_array($in['reset_policy'] ?? '', ['never', 'yearly', 'monthly'], true)) {
            $errors[] = 'Politique de remise à zéro invalide.';
        }
        $prefixes = [];
        foreach (self::NUMBERED_DOCS as $type => [$label]) {
            $prefix = strtoupper(trim($in['prefix_' . $type] ?? ''));
            if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
                $errors[] = "Préfixe « {$label} » invalide (1 à 10 lettres ou chiffres).";
            } elseif (isset($prefixes[$prefix])) {
                $errors[] = "Le préfixe « {$prefix} » est utilisé pour plusieurs documents.";
            }
            $prefixes[$prefix] = true;
            $start = (int) ($in['start_' . $type] ?? 0);
            if ($start < 1 || $start > 99999999) {
                $errors[] = "Numéro de départ « {$label} » invalide.";
            }
        }
        foreach (['quotation_validity_days' => 'Validité des devis', 'invoice_due_days' => 'Échéance des factures'] as $k => $label) {
            $v = (int) ($in[$k] ?? -1);
            if ($v < 0 || $v > 365) {
                $errors[] = "{$label} : valeur entre 0 et 365 jours.";
            }
        }
        return $errors;
    }

    public static function validateAdmin(array $in): array
    {
        $errors = [];
        if (mb_strlen(trim($in['full_name'] ?? '')) < 2) {
            $errors[] = 'Le nom complet est obligatoire.';
        }
        if (!preg_match('/^[a-zA-Z0-9_.\-]{3,60}$/', $in['username'] ?? '')) {
            $errors[] = 'Le nom d\'utilisateur doit contenir 3 à 60 caractères (lettres, chiffres, . _ -).';
        }
        if (!filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        }
        $pwd = (string) ($in['password'] ?? '');
        if (mb_strlen($pwd) < 10) {
            $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
        }
        if (!preg_match('/[a-z]/', $pwd) || !preg_match('/[A-Z]/', $pwd) || !preg_match('/\d/', $pwd)) {
            $errors[] = 'Le mot de passe doit contenir des minuscules, des majuscules et au moins un chiffre.';
        }
        if ($pwd !== (string) ($in['password_confirm'] ?? '')) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }
        return $errors;
    }

    /* =====================================================================
       Étape finale : installation
       ===================================================================== */

    /**
     * Exécute l'installation complète. En cas d'échec, les tables créées sont
     * supprimées pour permettre une nouvelle tentative propre.
     *
     * @return array{0: bool, 1: string}
     */
    public static function run(array $data): array
    {
        if (self::isInstalled()) {
            return [false, 'L\'application est déjà installée.'];
        }
        $db = $data['database'];
        $createdTables = false;

        try {
            [$ok, $message] = self::testDatabase($db, (bool) ($db['create'] ?? false));
            if (!$ok) {
                return [false, $message];
            }
            $pdo = Database::connect($db, true);

            // 1. Schéma (DDL : non transactionnel en MySQL)
            $createdTables = true;
            foreach (self::splitSql((string) file_get_contents(BASE_PATH . '/database/schema.sql')) as $stmt) {
                $pdo->exec($stmt);
            }

            // 2. Données de référence + configuration métier (transaction)
            $pdo->beginTransaction();
            foreach (self::splitSql((string) file_get_contents(BASE_PATH . '/database/seed.sql')) as $stmt) {
                $pdo->exec($stmt);
            }
            self::saveBusiness($pdo, $data['business']);
            self::saveSettings($pdo, $data['settings']);
            $adminId = self::createAdmin($pdo, $data['admin']);
            $pdo->prepare('INSERT INTO activity_logs (user_id, action, module, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$adminId, 'install', 'system', 'Installation de HO CREATIVE POS & ERP v' . APP_VERSION,
                    $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
            $pdo->commit();

            // 2 bis. Migrations postérieures au schéma initial
            \App\Core\Migrator::migrate($pdo);

            // 3. Fichier de configuration
            Config::write(self::CONFIG_FILE, [
                'app' => [
                    'name'         => $data['business']['business_name'],
                    'url'          => rtrim($db['app_url'], '/'),
                    'pretty_urls'  => (bool) $db['pretty_urls'],
                    'timezone'     => 'Africa/Casablanca',
                    'locale'       => 'fr_MA',
                    'debug'        => false,
                    'key'          => bin2hex(random_bytes(32)),
                    'installed_at' => date('Y-m-d H:i:s'),
                    'version'      => APP_VERSION,
                ],
                'db' => [
                    'host' => $db['host'],
                    'port' => (int) $db['port'],
                    'name' => $db['name'],
                    'user' => $db['user'],
                    'pass' => $db['pass'],
                ],
                'uploads' => ['max_size_mb' => 50],
            ]);

            // 4. Verrou d'installation
            $lock = json_encode([
                'installed_at' => date('c'),
                'version'      => APP_VERSION,
                'admin'        => $data['admin']['username'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents(self::LOCK_FILE, (string) $lock, LOCK_EX) === false) {
                throw new \RuntimeException('Impossible de créer le fichier de verrouillage storage/installed.lock.');
            }
            @chmod(self::LOCK_FILE, 0640);

            return [true, 'Installation terminée avec succès.'];
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($createdTables && isset($pdo)) {
                self::dropSchema($pdo);
            }
            if (is_file(self::CONFIG_FILE) && !self::isInstalled()) {
                @unlink(self::CONFIG_FILE);
            }
            return [false, 'Échec de l\'installation : ' . $e->getMessage()];
        }
    }

    private static function dropSchema(\PDO $pdo): void
    {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (self::schemaTables() as $table) {
                $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Throwable) {
            // Nettoyage au mieux : l'utilisateur sera invité à vider la base manuellement
        }
    }

    private static function setSetting(\PDO $pdo, string $key, string $value): void
    {
        $stmt = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
        $stmt->execute([$value, $key]);
        if ($stmt->rowCount() === 0) {
            // Clé non présente dans les données initiales : on la crée
            $pdo->prepare('INSERT IGNORE INTO settings (setting_group, setting_key, setting_value, value_type) VALUES (?, ?, ?, ?)')
                ->execute(['business', $key, $value, 'string']);
        }
    }

    private static function saveBusiness(\PDO $pdo, array $b): void
    {
        foreach ([
            'business_name', 'business_activity', 'business_address', 'business_city', 'business_phone',
            'business_whatsapp', 'business_email', 'business_website', 'business_ice', 'business_if',
            'business_rc', 'business_patente', 'business_cnss', 'business_capital', 'bank_name', 'bank_rib', 'bank_swift',
        ] as $key) {
            self::setSetting($pdo, $key, trim((string) ($b[$key] ?? '')));
        }
        self::setSetting($pdo, 'mail_from_name', trim((string) $b['business_name']));
        self::setSetting($pdo, 'mail_from_address', trim((string) ($b['business_email'] ?? '')));
    }

    private static function saveSettings(\PDO $pdo, array $s): void
    {
        self::setSetting($pdo, 'currency_code', $s['currency_code']);
        self::setSetting($pdo, 'currency_symbol', $s['currency_symbol']);
        self::setSetting($pdo, 'currency_position', $s['currency_position'] === 'before' ? 'before' : 'after');
        self::setSetting($pdo, 'currency_decimals', (string) max(0, min(3, (int) $s['currency_decimals'])));
        self::setSetting($pdo, 'tax_enabled', !empty($s['tax_enabled']) ? '1' : '0');
        self::setSetting($pdo, 'prices_include_tax', !empty($s['prices_include_tax']) ? '1' : '0');
        self::setSetting($pdo, 'default_tax_id', (string) (int) $s['default_tax_id']);
        self::setSetting($pdo, 'quotation_validity_days', (string) (int) $s['quotation_validity_days']);
        self::setSetting($pdo, 'invoice_due_days', (string) (int) $s['invoice_due_days']);
        if (trim((string) ($s['default_payment_terms'] ?? '')) !== '') {
            self::setSetting($pdo, 'default_payment_terms', trim((string) $s['default_payment_terms']));
        }

        $pdo->exec('UPDATE taxes SET is_default = 0');
        $pdo->prepare('UPDATE taxes SET is_default = 1 WHERE id = ?')->execute([(int) $s['default_tax_id']]);

        // Numérotation : format commun aux documents commerciaux
        $update = $pdo->prepare('UPDATE document_sequences SET prefix = ?, format = ?, padding = ?, next_number = ?, reset_policy = ? WHERE doc_type = ?');
        foreach (array_keys(self::NUMBERED_DOCS) as $type) {
            $update->execute([
                strtoupper(trim($s['prefix_' . $type])),
                $s['number_format'],
                (int) $s['number_padding'],
                (int) $s['start_' . $type],
                $s['reset_policy'],
                $type,
            ]);
        }
    }

    private static function createAdmin(\PDO $pdo, array $a): int
    {
        $pdo->prepare('INSERT INTO users (role_id, full_name, username, email, phone, password_hash, password_changed_at)
                       VALUES (1, ?, ?, ?, ?, ?, NOW())')
            ->execute([
                trim($a['full_name']),
                mb_strtolower(trim($a['username'])),
                mb_strtolower(trim($a['email'])),
                trim((string) ($a['phone'] ?? '')) ?: null,
                password_hash((string) $a['password'], PASSWORD_DEFAULT),
            ]);
        return (int) $pdo->lastInsertId();
    }

    /** Aperçu d'un numéro pour l'écran de récapitulatif. */
    public static function previewNumber(string $format, string $prefix, int $start, int $padding): string
    {
        return DocumentNumber::format($format, $prefix, $start, $padding, new \DateTimeImmutable('now'));
    }
}
