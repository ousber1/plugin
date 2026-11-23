# Mises à jour, sauvegardes et restauration

## Sauvegarde automatique
`php artisan hocreative:backup` (lancé chaque nuit à 03:15 par le Cron) crée
`storage/app/backups/backup-AAAAMMJJ-HHMMSS.zip` contenant :
* `database.sql` – export complet en PHP pur (pas besoin de `mysqldump`) ;
* `files/private/…` – fichiers clients, BAT, designs ; `files/public/…` – images produits, logo.

Les 14 dernières sont conservées (`--keep=N`). Ce dossier est hors du web. **Copiez régulièrement une sauvegarde hors du serveur** (téléchargement via le Gestionnaire de fichiers, ou cPanel → *Sauvegarde* / JetBackup). Sauvegardez aussi le fichier `.env` séparément et en lieu sûr (il contient `APP_KEY` : sans lui, les sessions sont invalidées, les données restent lisibles).

## Restauration
1. Passez le site en maintenance : `php artisan down` (ou créez `storage/framework/down` via le Gestionnaire de fichiers avec le contenu `{}`).
2. Décompressez la sauvegarde sur votre poste.
3. phpMyAdmin → sélectionnez la base → **Importer** `database.sql` (le fichier supprime puis recrée chaque table). Pour un gros fichier, utilisez *Assistant* ou SSH : `mysql -u USER -p BASE < database.sql`.
4. Replacez `files/private/*` dans `storage/app/private/` et `files/public/*` dans `storage/app/public/`.
5. `php artisan optimize:clear` puis `php artisan up`.

Testez une restauration sur une base vide au moins une fois après la mise en ligne.

## Mise à jour de l'application
1. Sauvegarde : `php artisan hocreative:backup`.
2. `php artisan down`.
3. Téléversez la nouvelle archive et remplacez **tout sauf** `.env` et `storage/` (ne supprimez pas `storage/app/installed.lock`).
4. `php artisan migrate --force` (Cron unique ou SSH). Les migrations sont additives ; aucune ne modifie les montants des documents émis.
5. `php artisan db:seed --class=Database\\Seeders\\RolesSeeder --force` si de nouvelles permissions ont été ajoutées.
6. `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. `php artisan up` et vérifiez une commande test.

Avec Git + SSH : `git pull && composer install --no-dev -o && php artisan migrate --force && php artisan optimize`.

## Construire l'archive de déploiement (poste développeur)
```bash
composer install --no-dev --optimize-autoloader
php artisan test        # avec les dépendances de dev
bash build/package.sh   # produit dist/ho-creative-cpanel.zip
```
Aucun build Node.js : les fichiers CSS/JS sont livrés tels quels dans `public/assets` et `public/vendor`.
