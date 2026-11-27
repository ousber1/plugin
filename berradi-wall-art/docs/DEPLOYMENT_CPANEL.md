# Deploying on cPanel shared hosting (no SSH needed)

## 1. The package
Use the release ZIP (`berradi-wall-art-<version>-cpanel.zip`): it already contains `vendor/` (Dompdf,
PHPMailer) with an optimized autoloader, so Composer is **not** needed on the server.

Building it yourself from the Git repository (on any computer with PHP 8.2+, Composer, git and zip):
```bash
bin/build-release.sh            # → dist/berradi-wall-art-<version>-cpanel.zip
```

## 2. Create the database
cPanel → **MySQL® Databases**: create a database (e.g. `cpuser_bwa`), a user with a strong password,
and add the user to the database with **ALL PRIVILEGES**.

## 3. Upload — option A (recommended): document root = `public`
1. File Manager → upload the ZIP to your home folder (`/home/cpuser/`) and extract → `/home/cpuser/berradi-wall-art`.
2. cPanel → **Domains** → *Manage* your domain → set **Document Root** to `berradi-wall-art/public`.
3. Only `public/` is reachable from the web; `.env`, `app/`, `storage/`… are outside it.

## 3. Upload — option B: the document root cannot be changed (`public_html` fixed)
1. Extract the project to `/home/cpuser/berradi-wall-art` (**outside** `public_html`).
2. Copy the **content** of `berradi-wall-art/public/` into `public_html/` (index.php, .htaccess, assets, uploads, favicon).
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

What each run does (every task is idempotent: running it twice does no harm):

| Task | When |
|---|---|
| Send queued e-mails (up to 5 attempts, then marked failed in the e-mail log) | every run |
| Clean up: expired login attempts and reset tokens, stale sessions; mark carts idle for 24 h as abandoned | every run |
| Team alerts: low stock, overdue invoices, late production jobs | at most once per 24 h each |
| Expire quotations past their validity date | every run |
| Retention purge: expired customer photos, finished orders' print files, old logs, read alerts, e-mail bodies, old carts (delays set on the Backups page) | every run |
| Automatic database backup + rotation (`backup_auto`, `backup_keep`) | once a day |
| Send queued server conversions (Meta Conversions API, 5 attempts) and forget 404s not seen for 180 days | every run |

The Maintenance page shows the last run; the security check turns red after 2 hours without one.

## 8. Permissions
Folders `storage/*` and `public/uploads` must be writable by PHP (755 on suPHP/LSAPI hosts; 775 otherwise). `.env` → 640.

## 9. Optional hardening
* Change `ADMIN_PATH=admin` in `.env` to a private word (e.g. `ADMIN_PATH=atelier-bwa`).
* Keep `APP_DEBUG=false` in production.
* Back up the database and `storage/` + `public/uploads` regularly (cPanel → Backup, or JetBackup).

## 10. Card payments (CMI), camera and tracking: what the host must allow
* **HTTPS is required** for card payments (the bank refuses plain http), for the camera of “Sur mon mur” (browsers
  only open it on https) and recommended for tracking cookies. Enable AutoSSL first (§ 6).
* **CMI**: give the bank the notification (callback) URL shown in *Finance → Payment methods*:
  `https://your-domain/webhooks/payments/cmi`. It must be reachable from the internet (no password protection, no
  maintenance mode on that path). Test with *Test mode* on, then switch it off. Nothing else to install.
* **Meta Conversions API**: the cron sends events to `https://graph.facebook.com` with cURL. On most shared hosts
  outgoing HTTPS is open; if *Suivi & pixels* shows “Network error”, ask the host to allow outgoing connections on port 443.
* Firewalls such as ModSecurity sometimes block POSTs that contain `|` (the CMI signature). If CMI callbacks get a 403,
  ask the host to whitelist `/webhooks/payments/cmi` and `/payment-return/`.

## 11. Performance (OPcache)
* cPanel → **Select PHP Version → Options**: keep `opcache.enable = On` (default on most hosts).
  `opcache.memory_consumption = 128` and `opcache.max_accelerated_files = 10000` are comfortable for this application.
* *Maintenance → Update* resets OPcache after migrating, so new code is used at once. If you replace files without
  running it, changes can take up to `opcache.revalidate_freq` seconds (often 2–60) to appear.
* Static files (`/assets`, images) are sent with long cache headers and gzip by `public/.htaccess`
  (`mod_expires`, `mod_deflate`, `mod_headers`). CSS/JS URLs carry a version, so browsers fetch new files after an update.
* Measured on the reference data set (20 000 orders): every back-office page under ~200 ms of PHP time and at most
  ~35 SQL queries. To check on your host, set `APP_DEBUG=true` **temporarily** and read the
  `X-Debug-Time` / `X-Debug-Queries` / `X-Debug-Slow` response headers in the browser's developer tools.

## 12. Updating to a new version
1. **Back up**: *Maintenance → Backups → Back up now* and download the file; cPanel → Backup for the files.
2. Optionally put the storefront in maintenance mode (*Settings → SEO & maintenance*): visitors get a 503 page, the back office keeps working.
3. Extract the new ZIP **next to** the current folder (e.g. `berradi-wall-art-new`), then copy **from the old installation** into it:
   * `.env`
   * `storage/` (whole folder: lock file, private uploads, print files, invoices, backups, logs)
   * `public/uploads/` (option B: it already lives in `public_html/uploads`, keep it)
   * `public_html/app_path.php` (option B only — it is not in the ZIP)
4. Swap the folders (rename the old one to `berradi-wall-art-old`, the new one to `berradi-wall-art`).
   Option B: copy the new `public/` content into `public_html/` **except** `uploads/`.
5. Log in → *Maintenance → Update*: applies new database migrations and default data, clears caches and OPcache.
6. Check the **security check** at the bottom of the Maintenance page. Coming from a version before 1.0.0:
   delete `public/robots.txt` (or `public_html/robots.txt`) — it is now generated.
7. Keep `berradi-wall-art-old` a few days, then delete it.

Rolling back: swap the folders back and restore the database backup in phpMyAdmin (migrations only add
tables/columns, but restoring keeps both sides consistent).

## 13. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Blank page or "500 Internal Server Error" | Read `storage/logs/app-YYYY-MM-DD.log` (and cPanel → *Errors*). Usually a missing PHP extension or PHP < 8.2. |
| "403 Forbidden" on every page | Document root not set to `public/` (option A) or `app_path.php` missing (option B). |
| CSS missing / every URL returns 404 except the home page | `mod_rewrite` / `.htaccess` ignored: ask the host to allow `AllowOverride All`. |
| Redirected to `/install` again | `storage/installed.lock` was not copied during an update — copy it back (never re-run the installer on a live database). |
| "419 Page expired" on forms | Session expired or cookies blocked; if constant, check `APP_URL` matches the domain (http/https, www). |
| E-mails not received | *Settings → E-mail*: use SMTP with an address of your domain; check *E-mail log* for the error, then SPF/DKIM in cPanel → *Email Deliverability*. |
| Uploads refused | Raise `upload_max_filesize` / `post_max_size`; folders `storage/` and `public/uploads` must be writable. |
| PDF invoices in Arabic show boxes | The bundled DejaVu fonts in `vendor/dompdf` were removed: re-upload `vendor/` from the ZIP. |
| Cron shows "never" | Wrong PHP path in the cron line: copy the path from cPanel → *Select PHP Version*, test with "Run" in the cron interface or look at the cron e-mail. |
| Card payment stays “unpaid” although the customer paid | The bank could not reach the callback: check the URL given to CMI, HTTPS, and the firewall (§ 10). *Activity log* shows `payment.redirected`; a refused signature is written to `storage/logs`. |
| “Sur mon mur” camera does not open | The site must be on https; the visitor must allow the camera; “Ma photo” always works. |
| Pixels do not fire | Accept the cookie banner first (they only load with consent); check the IDs in *Suivi & pixels*; ad blockers also block them. |
| Slow pages | Make sure OPcache is on; check `X-Debug-*` headers with debug temporarily on; a slow shared server may need a higher plan. |

## Without the installer (manual)
Import `database/schema.sql` in phpMyAdmin, create `.env` from `.env.example` (generate `APP_KEY` with
`php bin/console key:generate` or any 64-hex-char random string), then still run `/install`: it skips
the applied migrations and only seeds the default data, company and admin.
