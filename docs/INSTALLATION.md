# HO CREATIVE — Installation

> Guide de base (Phase 1). Le guide complet de déploiement, sauvegarde et dépannage sera livré en Phase 10.

## Prérequis

- PHP **8.2+** avec les extensions `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`
  (recommandées : `gd`, `zip`, `intl`, `curl`)
- MySQL **5.7+** ou MariaDB **10.3+**
- Apache avec `mod_rewrite` (standard sur cPanel). Sans mod_rewrite : décochez « Adresses simplifiées » à l'installation.

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
