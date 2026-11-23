# Déploiement sur hébergement cPanel – HO CREATIVE

Ce guide installe l'application sur **https://hocreative.shop/** à partir de `ho-creative-cpanel.zip` (dépendances PHP et fichiers front-end déjà inclus : **ni Composer, ni Node.js, ni SSH ne sont nécessaires**).

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

## 3. Téléverser les fichiers – site directement sur https://hocreative.shop/
L'archive contient deux dossiers : `hocreative/` (l'application) et `public_html/` (les fichiers web). Aucune modification de la racine du document n'est nécessaire et l'adresse ne contient jamais `/public`.

1. cPanel → **Gestionnaire de fichiers** → ouvrez votre **dossier personnel** (`/home/VOTRE_UTILISATEUR/`, le dossier qui *contient* `public_html`, pas `public_html` lui-même).
2. Si `public_html` contient la page par défaut de l'hébergeur (`index.html`, `default.html`), supprimez-la ou renommez-la (gardez `cgi-bin` et `.well-known`).
3. Téléversez `ho-creative-cpanel.zip` dans ce dossier personnel, puis clic droit → **Extraire** (vers `/home/VOTRE_UTILISATEUR`). Acceptez la fusion avec le dossier `public_html` existant.
4. Résultat attendu :
```
/home/VOTRE_UTILISATEUR/
├── hocreative/      ← application, .env, fichiers clients, sauvegardes (NON accessible depuis le web)
└── public_html/     ← index.php, .htaccess, assets/, images/, docs/ (servi sur https://hocreative.shop/)
```
5. Pensez à afficher les fichiers cachés (Paramètres du Gestionnaire de fichiers → « Afficher les fichiers cachés ») pour vérifier que `public_html/.htaccess` est présent.

> Le code, le fichier `.env`, les fichiers clients (`hocreative/storage/app/private`) et les sauvegardes (`hocreative/storage/app/backups`) restent **en dehors** de `public_html`. Ne déplacez pas le dossier `hocreative` dans `public_html` (s'il y était placé par erreur, un `.htaccess` « Require all denied » bloque l'accès, mais ce n'est qu'un filet de sécurité).

## 4. Domaine
Le domaine principal `hocreative.shop` pointe déjà sur `public_html` : rien à changer. Pour un sous-domaine ou un domaine supplémentaire, choisissez `public_html` (ou extrayez le contenu de `public_html/` de l'archive dans la racine de ce domaine, en gardant `hocreative/` à côté de cette racine).

## 5. Permissions
Les dossiers `hocreative/storage/` et `hocreative/bootstrap/cache/` doivent être inscriptibles par PHP (755 suffit sur la plupart des cPanel en suPHP/LSAPI ; sinon 775). Le dossier `hocreative/` doit être inscriptible le temps de l'installation (création de `.env`).

## 6. Installation (assistant web à usage unique)
1. Ouvrez `https://hocreative.shop/` : vous êtes redirigé vers `https://hocreative.shop/install`.
2. La page affiche les prérequis et crée `storage/app/install-token.txt`.
3. Ouvrez `hocreative/storage/app/install-token.txt` dans le Gestionnaire de fichiers, copiez son contenu (jeton) dans le formulaire. *Ce jeton prouve que vous contrôlez le serveur ; il n'est jamais affiché par le site.*
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
Activez AutoSSL, puis vérifiez que `APP_URL` commence par `https://` et que `SESSION_SECURE_COOKIE=true`. Optionnel : forcer HTTPS en ajoutant en tête de `public_html/.htaccess` :
```
RewriteEngine On
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
| Images téléversées absentes | Le lien `public_html/storage` est créé par l'installateur ; si l'hébergeur interdit les liens symboliques, l'application sert ces images elle-même (route de secours). |
| La page par défaut de l'hébergeur s'affiche | Supprimez `public_html/index.html` / `default.html`. |
| E-mails non reçus | Vérifiez `MAIL_*`, puis `php artisan tinker` → `Mail::raw('test', fn($m)=>$m->to('vous@…'))`. |
| Le Cron ne tourne pas | Vérifiez le chemin de PHP ; testez `php artisan schedule:list`. |
