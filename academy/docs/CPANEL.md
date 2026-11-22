# Installing on cPanel shared hosting

The application runs on shared hosting that offers **PHP 8.3+**, **MySQL/MariaDB** and lets you choose the domain's document root. Node.js is not needed.

## 1. Check the host

In cPanel › *Select PHP Version* (or *MultiPHP Manager*):

- PHP **8.3** or newer
- Extensions enabled: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`, `intl` (recommended), `gd` (recommended)

In *MultiPHP INI Editor* set (see `deploy/php.ini.example`):

```
upload_max_filesize = 32M
post_max_size = 40M
memory_limit = 256M
max_execution_time = 120
```

Videos are sent in chunks of 5 MB, so you can upload large videos even when the host does not allow big single uploads. If the host only allows smaller uploads, lower `VIDEO_CHUNK_MB` in `.env` (e.g. `2`).

## 2. Create the database

cPanel › *MySQL Databases*: create a database and a user, grant **ALL PRIVILEGES**, and note the names (cPanel prefixes them, e.g. `cpuser_academy`).

## 3. Upload the files

1. Upload `academy-deploy.zip` (it already contains `vendor/`) to your home folder, **outside** `public_html`, e.g. `/home/cpuser/academy`.
2. Extract it with *File Manager › Extract*.

## 4. Point the domain to `/public`

cPanel › *Domains* (or *Addon Domains/Subdomains*) › edit the domain › set **Document Root** to:

```
/home/cpuser/academy/public
```

This is the recommended and secure setup: `.env`, `storage/` and source files stay outside the web root.

If your host does not let you change the document root of the primary domain, use a subdomain (e.g. `learn.example.ma`) pointed at `academy/public`, or ask the host to change it. Do **not** move the whole project into `public_html`.

## 5. Configure `.env`

In File Manager, copy `.env.example` to `.env` and edit it:

```
APP_URL=https://example.ma
APP_ENV=production
APP_DEBUG=false
DB_HOST=localhost
DB_DATABASE=cpuser_academy
DB_USERNAME=cpuser_academy
DB_PASSWORD=...
MAIL_HOST=mail.example.ma
MAIL_USERNAME=no-reply@example.ma
MAIL_PASSWORD=...
```

## 6. Run the installer

**With Terminal access** (cPanel › *Terminal* or SSH):

```bash
cd ~/academy
```

```bash
php artisan key:generate
```

```bash
php artisan academy:install
```

If `php` points to an older version, use the full path given by your host, e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.

**Without Terminal access:** ask your host to run the three commands above once, or use cPanel › *Cron Jobs* to schedule each command one time, removing it after it has run:

```
cd ~/academy && php artisan key:generate --force
cd ~/academy && php artisan migrate --force && php artisan db:seed --class=Database\\Seeders\\BaseSeeder --force && php artisan storage:link
cd ~/academy && php artisan academy:create-admin --name="Admin" --email=you@example.ma --password="Change-Me-123"
```

Change that password immediately after logging in (Profile › Change password).

If `storage:link` is not allowed (symlinks disabled), ask the host to enable `FollowSymLinks`. Otherwise uploaded thumbnails will not display, though videos and files still work because they are served by the application.

## 7. Cron job

cPanel › *Cron Jobs* › every minute:

```
cd /home/cpuser/academy && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Permissions

`storage/` and `bootstrap/cache/` must be writable (755 for folders is usually enough on cPanel because PHP runs as your user).

## 9. Finish in the dashboard

Log in at `/login`, open `/admin/settings`, and set the academy name, logo, contact details, **WhatsApp number** (international format, e.g. `212600000000`) and social links.
