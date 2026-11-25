# HO CREATIVE — Architecture technique

## 1. Choix techniques

| Élément | Choix | Raison |
|---|---|---|
| Langage | PHP 8.2+ (`declare(strict_types=1)`) | Disponible sur tous les hébergements cPanel |
| Base | MySQL 5.7+ / MariaDB 10.3+, InnoDB, `utf8mb4` | Clés étrangères, transactions, verrous de ligne |
| Accès données | PDO, requêtes préparées uniquement (`ATTR_EMULATE_PREPARES = false`) | Protection injection SQL |
| Architecture | MVC léger maison, autoloader PSR-4 ; bibliothèques tierces (dompdf, php-qrcode) **livrées dans `vendor/`** : aucune commande Composer sur l'hébergement | Déploiement par simple copie FTP / gestionnaire de fichiers |
| Interface | Bootstrap 5.3, Bootstrap Icons, police Inter, JS natif + AJAX (`fetch`) | Pas d'étape de build |
| Langue | Français (interface, messages, documents) | Exigence métier |
| Fuseau | `Africa/Casablanca` (PHP et session MySQL alignés) | Dates cohérentes |

## 2. Arborescence

```
/                         ← racine web (public_html/ ou sous-dossier)
├── index.php             Contrôleur frontal unique
├── .htaccess             Réécriture d'URL, blocage des dossiers privés, en-têtes de sécurité
├── app/                  [PRIVÉ] Code applicatif
│   ├── bootstrap.php     Amorçage : autoload, config, erreurs, session
│   ├── helpers.php       e(), url(), asset(), money(), format_date(), whatsapp_link()…
│   ├── routes.php        Table des routes
│   ├── Core/             Noyau du framework
│   │   ├── Config.php          Configuration (config/config.php)
│   │   ├── Database.php        PDO, helpers insert/update, transactions imbriquées
│   │   ├── Router.php          Routes, middlewares auth/guest/can:, CSRF automatique
│   │   ├── Request.php         Entrées nettoyées, décimaux à la française, IP
│   │   ├── Response.php        JSON, redirection, en-têtes de sécurité (CSP…)
│   │   ├── View.php            Rendu des vues avec gabarit
│   │   ├── Controller.php      Contrôleur de base (authorize, json, redirect)
│   │   ├── Session.php         Session sécurisée, flash, anciennes saisies
│   │   ├── Csrf.php            Jetons CSRF
│   │   ├── Auth.php            Connexion, verrouillage, permissions
│   │   ├── Validator.php       Validation (messages FR, ICE, téléphone…)
│   │   ├── Settings.php        Paramètres métier en base
│   │   ├── DocumentNumber.php  Numérotation unique et sans trou
│   │   ├── AuditLog.php        Journal d'audit
│   │   └── Logger.php          Journal technique (storage/logs)
│   ├── Controllers/      Un contrôleur par module
│   ├── Models/           (Phase 2+) Accès aux données par entité
│   ├── Services/         (Phase 3+) Logique métier transversale : StockService,
│   │                     PricingService, PaymentService, CostCalculator, PdfService…
│   └── Views/            layouts/, partials/, un dossier par module, errors/
├── assets/               css/, js/, img/ (public)
├── config/               [PRIVÉ] config.php (généré), config.sample.php
├── database/             [PRIVÉ] schema.sql, seed.sql, migrations/ (futures)
├── docs/                 [PRIVÉ] Documentation
├── install/              Assistant d'installation (verrouillé après usage, à supprimer)
└── storage/              [PRIVÉ] logs/, backups/, uploads/, cache/, sessions/, installed.lock
```

Chaque dossier privé contient un `.htaccess` `Require all denied`, en plus de la règle globale.
Les fichiers envoyés (maquettes, justificatifs) sont stockés dans `storage/uploads` et servis
uniquement via un contrôleur qui vérifie les droits — jamais par URL directe.

## 3. Cycle d'une requête

```
navigateur → .htaccess → index.php → bootstrap.php
   ├─ app non installée ? → redirection /install/
   ├─ Config::load, fuseau, gestion d'erreurs, en-têtes de sécurité
   ├─ Session::start (expiration, empreinte, régénération)
   └─ Router::dispatch
        ├─ middlewares : auth → guest → can:permission
        ├─ POST : vérification CSRF obligatoire
        └─ Contrôleur → (Service → Database) → View (layout app/auth) ou JSON
```

## 4. Conventions

- **Montants** : `DECIMAL(15,2)` ; prix unitaires et coûts : `DECIMAL(15,4)` ; quantités : `DECIMAL(15,3)`.
- **Totaux de documents** calculés côté serveur (jamais confiance aux totaux envoyés par le navigateur).
- **Documents financiers** : brouillon modifiable → validation (numéro attribué, `is_locked = 1`, instantané client/entreprise figé). Une facture validée ne se modifie plus : annulation tracée ou avoir.
- **Stock** : `stock_movements` est un journal immuable ; `stock_levels` (par emplacement) est la source de vérité ; `products.stock_quantity` / `materials.stock_quantity` sont des caches maintenus dans la même transaction.
- **Suppression logique** (`deleted_at`) pour clients, fournisseurs, produits, matières, machines, devis, dépenses, ordres de production.
- **Toute écriture multi-tables** passe par `Database::transaction()`.
- **Toute action sensible** appelle `AuditLog::log()`.

## 5. Phases de développement

Voir [PROGRESS.md](PROGRESS.md).
