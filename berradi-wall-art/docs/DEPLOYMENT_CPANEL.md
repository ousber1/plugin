# Deploying on cPanel shared hosting (no SSH needed)

## 1. Prepare the package (on your computer, once)
```bash
composer install --no-dev --optimize-autoloader
```
Zip the whole `berradi-wall-art` folder **including `vendor/`**. (The Phase 9 ZIP will already contain it.)

## 2. Create the database
cPanel → **MySQL® Databases**: create a database (e.g. `cpuser_bwa`), a user with a strong password,
and add the user to the database with **ALL PRIVILEGES**.

## 3. Upload — option A (recommended): document root = `public`
1. File Manager → upload the ZIP to your home folder (`/home/cpuser/`) and extract → `/home/cpuser/berradi-wall-art`.
2. cPanel → **Domains** → *Manage* your domain → set **Document Root** to `berradi-wall-art/public`.
3. Only `public/` is reachable from the web; `.env`, `app/`, `storage/`… are outside it.

## 3. Upload — option B: the document root cannot be changed (`public_html` fixed)
1. Extract the project to `/home/cpuser/berradi-wall-art` (**outside** `public_html`).
2. Copy the **content** of `berradi-wall-art/public/` into `public_html/` (index.php, .htaccess, assets, uploads, favicon, robots.txt).
3. Create `public_html/app_path.php`:
   ```php
   <?php return '/home/cpuser/berradi-wall-art';
   ```
   (`public/.htaccess` blocks direct access to this file.)
4. Note: product images uploaded from the admin are written to `public_html/uploads` automatically
   (the app uses the folder that contains the running `index.php`).

Never extract the whole project directly inside `public_html`. If it ever happens, the root `.htaccess`
denies `app/`, `config/`, `storage/`, `vendor/`, `.env`… and forwards everything to `public/`, but option A or B is required for production.

## 4. PHP settings
cPanel → **Select PHP Version**: PHP 8.2, 8.3 or 8.4 with `pdo_mysql, mbstring, openssl, fileinfo, gd, dom, curl, intl, zip, exif`.
Options: `upload_max_filesize = 32M`, `post_max_size = 40M`, `memory_limit = 256M`, `max_execution_time = 120`.

## 5. Run the installer
Open `https://your-domain/install` and follow the 10 steps. It checks the real PHP version and extensions,
tests the DB, writes `.env` (or shows its content to paste if the folder is read-only), creates tables and
default data, saves the company profile, creates the Super Admin and **locks itself** (`storage/installed.lock`).

## 6. SSL & HTTPS
cPanel → **SSL/TLS Status** → run AutoSSL. Then uncomment the 3 "force HTTPS" lines in `public/.htaccess`.
Session cookies automatically become `Secure` on HTTPS.

## 7. Cron job
cPanel → **Cron Jobs** → every 15 minutes:
```
*/15 * * * * /usr/local/bin/php /home/cpuser/berradi-wall-art/bin/console cron:run >/dev/null 2>&1
```
(Use the PHP path shown by your host, e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.)

## 8. Permissions
Folders `storage/*` and `public/uploads` must be writable by PHP (755 on suPHP/LSAPI hosts; 775 otherwise). `.env` → 640.

## 9. Optional hardening
* Change `ADMIN_PATH=admin` in `.env` to a private word (e.g. `ADMIN_PATH=atelier-bwa`).
* Keep `APP_DEBUG=false` in production.
* Back up the database and `storage/` + `public/uploads` regularly (cPanel → Backup, or JetBackup).

## Without the installer (manual)
Import `database/schema.sql` in phpMyAdmin, create `.env` from `.env.example` (generate `APP_KEY` with
`php bin/console key:generate` or any 64-hex-char random string), then still run `/install`: it skips
the applied migrations and only seeds the default data, company and admin.
