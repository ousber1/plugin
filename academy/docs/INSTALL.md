# Production installation (Apache or Nginx)

This guide installs the academy on a VPS or dedicated server. For shared hosting, see [CPANEL.md](CPANEL.md).

## 1. Requirements

| Component | Version / notes |
|---|---|
| PHP | **8.3 or newer** (CLI and web) |
| Database | MySQL 8.0+ or MariaDB 10.6+, `utf8mb4` |
| Web server | Apache 2.4 with `mod_rewrite`, or Nginx with PHP-FPM |
| Composer | 2.x (only needed if you install from source rather than the ready package) |
| Node.js | **Not required.** CSS and JS are plain static files in `public/`. |

Required PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip` (for Composer).
Recommended: `intl`, `gd`, `opcache`.

`fileinfo` is mandatory. It is used to check that uploaded videos and files really have the type they claim.

## 2. Get the code

Option A uses the ready-made package (it includes `vendor/`):

```bash
unzip academy-deploy.zip -d /var/www/academy
```

Option B installs from source:

```bash
cd /var/www/academy
```

```bash
composer install --no-dev --optimize-autoloader
```

## 3. Configure

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Edit `.env` and set at least: `APP_URL`, `DB_*`, `MAIL_*`, and keep `APP_ENV=production` and `APP_DEBUG=false`.
Keep secrets in `.env` only. Never commit or upload `.env` publicly.

Create the database:

```sql
CREATE DATABASE academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'academy'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON academy.* TO 'academy'@'localhost';
```

## 4. Install

The interactive installer checks extensions and writable folders, runs migrations, links storage, seeds the base data (pages, tools, categories) and creates the first administrator:

```bash
php artisan academy:install
```

Add `--demo` to load clearly marked demo courses, articles and resources. You can also create or promote an administrator later:

```bash
php artisan academy:create-admin
```

## 5. Writable directories and permissions

The web server user (e.g. `www-data`) must be able to write to:

- `storage/` (including `storage/app/private` for lesson videos and `storage/app/chunks` for in-progress uploads)
- `bootstrap/cache/`

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

```bash
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

`php artisan storage:link` creates `public/storage` for **public** images only (course thumbnails, logos, blog covers). Lesson videos, attachments, library files and store downloads stay in `storage/app/private` and are **never** reachable through `public/`.

## 6. Web server: the document root must be `/public`

- Nginx: see [`deploy/nginx.conf`](../deploy/nginx.conf)
- Apache: see [`deploy/apache-vhost.conf`](../deploy/apache-vhost.conf). `public/.htaccess` handles URL rewriting.

Pointing the web root at the project folder instead of `public/` would expose `.env`. Always use `/public`.

## 7. Large video uploads

Videos are uploaded from the dashboard in **resumable chunks** (default 5 MB each). PHP and the web server therefore only need to accept one chunk per request, not the whole file:

| Setting | Where | Recommended |
|---|---|---|
| `upload_max_filesize` | php.ini | ≥ `VIDEO_CHUNK_MB` + margin (e.g. 32M) |
| `post_max_size` | php.ini | slightly larger than `upload_max_filesize` |
| `client_max_body_size` | Nginx | ≥ chunk size (e.g. 64M) |
| `LimitRequestBody` | Apache | ≥ chunk size |
| `VIDEO_MAX_MB` | .env | total size allowed per video (default 2048) |
| `VIDEO_CHUNK_MB` | .env | chunk size sent by the browser (default 5) |

See [`deploy/php.ini.example`](../deploy/php.ini.example) and [VIDEO.md](VIDEO.md) for encoding advice.

## 8. Private object storage (optional)

Local private storage is the default. To keep lesson media in a private S3-compatible bucket (AWS S3, Wasabi, Scaleway, DigitalOcean Spaces…):

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

Then in `.env`: set `MEDIA_DISK=s3_private` and fill in `AWS_*` and `AWS_PRIVATE_BUCKET`. Keep the bucket **private**. The app authorizes each request and redirects to a signed URL valid for `MEDIA_SIGNED_URL_MINUTES`. Files uploaded before the switch keep working from their original disk.

## 9. Cron and caches

```cron
* * * * * cd /var/www/academy && php artisan schedule:run >> /dev/null 2>&1
```

This runs `academy:prune-uploads` daily to delete abandoned partial uploads.

After every deployment:

```bash
php artisan migrate --force
```

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 10. Mail

Email verification and password reset need working SMTP settings (`MAIL_*`). By default students must confirm their email before requesting a printed certificate (Admin › Settings › WhatsApp & certificates).

## 11. HTTPS and proxies

Serve the site over HTTPS (`SESSION_SECURE_COOKIE=true`). Behind a load balancer or Cloudflare, set `TRUSTED_PROXIES`.

## 12. Backups

Back up the database **and** `storage/app/private` (videos and files) plus `storage/app/public` (images).

## 13. Updating

```bash
php artisan down
```

Replace the code (keep `.env` and `storage/`), then:

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan migrate --force && php artisan optimize && php artisan up
```
