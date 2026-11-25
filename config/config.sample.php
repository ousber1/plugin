<?php
/**
 * HO CREATIVE — modèle de configuration.
 *
 * L'assistant d'installation (install/) génère automatiquement config/config.php
 * à partir de ce modèle. Pour une installation manuelle : copiez ce fichier en
 * config/config.php, renseignez les valeurs, importez database/schema.sql puis
 * database/seed.sql, et créez le fichier storage/installed.lock.
 */
return [
    'app' => [
        'name'        => 'HO CREATIVE',
        'url'         => 'https://www.example.ma/erp',   // URL publique, sans barre finale
        'pretty_urls' => true,                           // false si mod_rewrite indisponible
        'timezone'    => 'Africa/Casablanca',
        'locale'      => 'fr_MA',
        'debug'       => false,                          // ne jamais activer en production
        'key'         => 'CHANGER_PAR_64_CARACTERES_HEXADECIMAUX_ALEATOIRES',
        'installed_at'=> null,
        'version'     => '1.0.0',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'ho_creative',
        'user' => 'utilisateur_mysql',
        'pass' => 'mot_de_passe_mysql',
    ],
    'uploads' => [
        'max_size_mb' => 50,
    ],
];
