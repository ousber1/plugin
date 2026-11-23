# Déploiement sur hébergement cPanel – HO CREATIVE

Ce guide installe l'application à partir de `ho-creative-cpanel.zip` (dépendances PHP et fichiers front-end déjà inclus : **ni Composer, ni Node.js, ni SSH ne sont nécessaires**).

## 1. Prérequis serveur

| Élément | Requis |
|---|---|
| PHP | **8.3 ou plus** (cPanel → *Select PHP Version* / *MultiPHP Manager*) |
| Extensions PHP | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` *ou* `gmp` (recommandé), `fileinfo`, `gd`, `intl`, `zip`, `curl`, `dom` |
| Base de données | MySQL ≥ 8.0 ou MariaDB ≥ 10.6 (testé sur MariaDB 10.11), interclassement `utf8mb4_unicode_ci` |
| HTTPS | Certificat SSL actif (AutoSSL / Let's Encrypt dans cPanel) |
| Cron | Accès « Tâches Cron » cPanel |
| Espace disque | ~120 Mo pour l'application + vos fichiers clients |

Réglages PHP conseillés (cPanel → *MultiPHP INI Editor*) :

```
upload_max_filesize = 100M
post_max_size       = 110M     (≥ upload_max_filesize ; l'éditeur en ligne envoie jusqu'à ~40 Mo)
memory_limit        = 512M     (génération PDF / export d'images)
max_execution_time  = 120
```
La taille maximale acceptée par l'application se règle ensuite dans *Admin → Paramètres → Délais & fichiers* et doit rester ≤ `upload_max_filesize`.

## 2. Créer la base de données
cPanel → **Bases de données MySQL** :
1. Créez une base (ex. `cpuser_hocreative`).
2. Créez un utilisateur avec un mot de passe fort.
3. Ajoutez l'utilisateur à la base avec **TOUS LES PRIVILÈGES**.

## 3. Téléverser les fichiers (hors du dossier public)
1. cPanel → **Gestionnaire de fichiers** → dans votre dossier personnel (ex. `/home/cpuser/`), créez `hocreative`.
2. Téléversez `ho-creative-cpanel.zip` dans `/home/cpuser/hocreative/` puis **Extraire**.
3. Vous obtenez `/home/cpuser/hocreative/app`, `…/public`, `…/storage`, `…/vendor`, etc.

> Le code, le fichier `.env`, les fichiers clients (`storage/app/private`) et les sauvegardes (`storage/app/backups`) restent **en dehors** du dossier web. Seul `public/` est exposé.

## 4. Pointer le domaine vers `/public`
- **Domaine supplémentaire ou sous-domaine** (recommandé) : cPanel → *Domaines* → *Gérer* → **Racine du document** = `/home/cpuser/hocreative/public`.
- **Domaine principal** dont la racine est imposée à `public_html` : renommez `public_html` en `public_html_old`, puis créez un lien symbolique via *Tâches Cron* (exécution unique) ou *Terminal* :
  `ln -s /home/cpuser/hocreative/public /home/cpuser/public_html`
  Si votre hébergeur interdit les liens symboliques, utilisez un sous-domaine (`boutique.votredomaine.ma`) ou demandez au support de changer la racine du document.

Ne copiez **jamais** l'ensemble de l'application dans `public_html`.

## 5. Permissions
Les dossiers `storage/` et `bootstrap/cache/` doivent être inscriptibles par PHP (755 suffit sur la plupart des cPanel en suPHP/LSAPI ; sinon 775). Le dossier racine doit être inscriptible le temps de l'installation (création de `.env`).

## 6. Installation (assistant web à usage unique)
1. Ouvrez `https://votredomaine.ma/install`.
2. La page affiche les prérequis et crée `storage/app/install-token.txt`.
3. Ouvrez ce fichier dans le Gestionnaire de fichiers, copiez son contenu (jeton) dans le formulaire. *Ce jeton prouve que vous contrôlez le serveur ; il n'est jamais affiché par le site.*
4. Renseignez l'URL du site (https), les accès MySQL, le compte **propriétaire** (mot de passe ≥ 10 caractères) et, si possible, le SMTP.
5. Cochez « données de démonstration » uniquement pour un essai.
6. Validez. L'assistant : écrit `.env` (clé `APP_KEY` aléatoire, `APP_ENV=production`, `APP_DEBUG=false`), exécute les migrations, crée les rôles, le contenu de base et le catalogue d'exemple, crée le propriétaire, puis écrit `storage/app/installed.lock`.
7. À partir de là, `/install` répond **404** définitivement. Le jeton est supprimé.

**Alternative avec SSH :**
```bash
cd ~/hocreative && cp .env.example .env && php artisan key:generate
# éditez .env (DB_*, APP_URL, MAIL_*)
php artisan hocreative:install --email=vous@domaine.ma --name="Votre nom"
```

## 7. Tâche Cron (obligatoire)
cPanel → **Tâches Cron** → « Une fois par minute » :
```
* * * * * /usr/local/bin/php /home/cpuser/hocreative/artisan schedule:run >> /dev/null 2>&1
```
(Vérifiez le chemin de PHP 8.3 avec votre hébergeur, ex. `/opt/cpanel/ea-php83/root/usr/bin/php`.)

Le planificateur exécute : expiration des devis, purge des paniers abandonnés, rétention des fichiers, alerte de stock bas (02:30), **sauvegarde quotidienne** (03:15), et — seulement si `QUEUE_CONNECTION=database` — un traitement borné de la file d'attente (`queue:work --stop-when-empty --max-time=50`). Aucun processus permanent n'est requis.

## 8. E-mail
Dans `.env` (Gestionnaire de fichiers → *Modifier*) :
```
MAIL_MAILER=smtp
MAIL_HOST=mail.votredomaine.ma
MAIL_PORT=587
MAIL_USERNAME=commandes@votredomaine.ma
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS=commandes@votredomaine.ma
```
Créez la boîte dans cPanel → *Comptes de messagerie*. Configurez SPF/DKIM (cPanel → *Délivrabilité des e-mails*).

## 9. HTTPS
Activez AutoSSL, puis vérifiez que `APP_URL` commence par `https://` et que `SESSION_SECURE_COOKIE=true`. Optionnel : forcer HTTPS dans `public/.htaccess` :
```
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## 10. Avant la mise en ligne – à saisir par le propriétaire
Voir la liste complète dans `README.md` (section « Checklist de mise en production ») : informations légales (ICE, RC, IF, patente, CNSS, RIB), taux de TVA validé par votre comptable, prix réels de chaque produit, zones et tarifs de livraison, jours fériés religieux de l'année, moyens de paiement, textes CGV/confidentialité, purge des données démo.

## Dépannage
| Symptôme | Solution |
|---|---|
| Page blanche / erreur 500 | Consultez `storage/logs/laravel-AAAA-MM-JJ.log`. Vérifiez la version PHP et les extensions. |
| « Fichier trop volumineux » | Augmentez `upload_max_filesize` / `post_max_size`. |
| Images produits absentes | Le lien `public/storage` doit exister ; sinon exécutez `php artisan storage:link` (Cron unique) ou copiez `storage/app/public` vers `public/storage`. |
| E-mails non reçus | Vérifiez `MAIL_*`, puis `php artisan tinker` → `Mail::raw('test', fn($m)=>$m->to('vous@…'))`. |
| Le Cron ne tourne pas | Vérifiez le chemin de PHP ; testez `php artisan schedule:list`. |
