<?php
/**
 * BERRADI PRINT - Configuration Base de Données
 * Système de Gestion de Services d'Impression
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'lobefuthkh_print');
define('DB_USER', 'lobefuthkh_print');
define('DB_PASS', '1993AZaz@az');
define('DB_CHARSET', 'utf8mb4');

// Connexion PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    return $pdo;
}
