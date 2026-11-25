# HO CREATIVE — Installation

> Guide de base (Phase 1). Le guide complet de déploiement, sauvegarde et dépannage sera livré en Phase 10.

## Prérequis

- PHP **8.2+** avec les extensions `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`
  (recommandées : `gd`, `zip`, `intl`, `curl`)
- MySQL **5.7+** ou MariaDB **10.3+**
- Apache ou LiteSpeed avec prise en charge de `.htaccess` (standard sur cPanel). La réécriture d'URL est facultative : elle est détectée automatiquement.

## Installation sur cPanel

1. **Base de données** — cPanel › *Bases de données MySQL* :
   créez une base (ex. `moncompte_erp`), un utilisateur avec un mot de passe fort,
   puis *Ajouter l'utilisateur à la base* avec **Tous les privilèges**.
2. **Fichiers** — téléversez le contenu du projet (ZIP puis *Extraire*) dans
   `public_html/` ou un sous-dossier (ex. `public_html/erp/`).
3. **Droits** — les dossiers `config/` et `storage/` (et ses sous-dossiers) doivent être
   accessibles en écriture (755, ou 775 selon l'hébergeur).
4. **PHP** — cPanel › *Sélecteur de version PHP* : choisissez 8.2 ou plus et activez les extensions ci-dessus.
5. **Assistant** — ouvrez `https://votre-domaine.ma/erp/` : vous êtes redirigé vers `/install/`.
   Suivez les 6 étapes : prérequis → base de données → entreprise → paramètres → administrateur → installation.
6. **Après l'installation** :
   - supprimez le dossier `install/` (il est de toute façon verrouillé par `storage/installed.lock`) ;
   - activez HTTPS (cPanel › *SSL/TLS Status* › AutoSSL) ;
   - vérifiez que `https://votre-domaine.ma/erp/config/config.php` renvoie **403**.

## Installation manuelle (sans assistant)

```bash
mysql -u UTILISATEUR -p NOM_BASE < database/schema.sql
```

```bash
mysql -u UTILISATEUR -p NOM_BASE < database/seed.sql
```

Puis copiez `config/config.sample.php` en `config/config.php`, renseignez-le, créez le premier
administrateur dans la table `users` (rôle 1, mot de passe généré avec `password_hash()`) et créez
le fichier vide `storage/installed.lock`.

## Serveur Nginx

Les fichiers `.htaccess` ne s'appliquent pas. Équivalent minimal :

```nginx
location ~ ^/(app|config|database|storage|docs)/ { deny all; }
location ~ \.(sql|lock|log|env|bak)$ { deny all; }
location / { try_files $uri $uri/ /index.php?route=$uri&$args; }
```

## Réinstaller

Supprimez `storage/installed.lock` et `config/config.php`, videz la base, puis rouvrez `/install/`.
**Attention : toutes les données seront perdues** — faites une sauvegarde avant.

## Dépannage

### « 404 Not Found — The resource requested could not be found on this server! » après l'installation

Ce message vient du **serveur web**, pas du logiciel : l'adresse simplifiée (ex. `…/login`) n'a pas été
transmise à `index.php` parce que le serveur n'applique pas le fichier `.htaccess`.

Depuis la version corrigée, le logiciel **détecte automatiquement** si la réécriture d'URL fonctionne et
utilise sinon des adresses `index.php?route=…`, valables partout. Ouvrez simplement :

```
https://votre-domaine.ma/erp/index.php?route=login
```

Corrigez ensuite la cause, car sans `.htaccess` **les dossiers privés (`config/`, `storage/`) ne sont plus protégés**
(un bandeau rouge l'indique aux administrateurs) :

1. **Fichier `.htaccess` absent** — c'est un fichier caché que beaucoup de logiciels FTP n'envoient pas.
   Dans cPanel › Gestionnaire de fichiers › *Paramètres* › cochez **Afficher les fichiers cachés (dotfiles)**,
   puis vérifiez la présence de `.htaccess` à la racine de l'application et dans `app/ config/ database/ storage/ docs/ install/ assets/uploads/`.
   Le plus sûr : envoyer le ZIP puis utiliser *Extraire* dans le gestionnaire de fichiers.
2. **`.htaccess` ignoré par l'hébergeur** (AllowOverride désactivé) — demandez au support d'activer la prise en charge
   de `.htaccess` et de `mod_rewrite` pour votre compte.
3. **Serveur Nginx** — appliquez la configuration de la section « Serveur Nginx » ci-dessus.

Une fois la réécriture fonctionnelle, les adresses simplifiées s'activent d'elles-mêmes à la visite suivante.
Pour forcer les adresses `index.php?route=…`, mettez `'pretty_urls' => false` dans `config/config.php`.
Si vous changez d'hébergement, supprimez `storage/cache/rewrite.ok` pour relancer la détection.

### Mettre à jour une installation existante

Envoyez le nouveau ZIP et **extrayez-le par-dessus** le dossier existant (écraser les fichiers).
Le ZIP ne contient ni `config/config.php` ni `storage/installed.lock` : votre configuration, votre base et
vos fichiers sont conservés. Supprimez ensuite le dossier `install/`. Aucune réinstallation n'est nécessaire.
