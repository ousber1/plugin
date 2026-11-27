# BERRADI WALL ART

E-commerce and business management system for a Moroccan wall-art printing company: personalised canvas
prints, framed photos, posters and multi-panel art — from the online shop and the photo builder to
artwork proofs, production, stock, invoices, payments and reports.

Built for **ordinary cPanel shared hosting**: PHP 8.2–8.4, MySQL 5.7+/MariaDB 10.4+, Apache.
No Node, Docker, Redis, queue workers or SSH required — a cron job every 15 minutes is enough.

* **Stack**: PHP (custom lightweight MVC), PDO, Bootstrap 5 (LTR + RTL), vanilla ES6, Chart.js, Cropper.js, Dompdf, PHPMailer
* **Languages**: French (LTR) and Arabic (RTL), storefront, back office, e-mails and PDFs · **Currency**: MAD / DH
* **Brand palette**: `#171717` · `#C6A66B` · `#F8F7F4` · `#FFFFFF` · `#292929`

## Features

| Area | What it does |
|---|---|
| Storefront | Home, shop with filters/sorting/search, categories, collections, best sellers, new arrivals, offers, product pages with variants, FAQ, contact, newsletter, order tracking — SEO (hreflang, sitemap, schema.org, Open Graph) |
| Photo builder | Private photo upload, cropping per panel (1–5 panels), supports, frames, borders, finishes, live server-side pricing, print-quality (DPI) warnings, saved designs |
| Checkout | Cart (guest or account), coupons, shipping by city/zone with free-shipping thresholds, VAT included or added, cash on delivery / bank transfer, account creation, confirmation and tracking e-mails |
| Customer area | Orders, invoices (PDF), designs, photos (delete now), addresses, profile |
| Orders | Status workflow with history and customer e-mails, phone/WhatsApp orders, shipments with tracking and cash on delivery, offline payments and refunds, printable work order |
| Artwork | Review of customer photos (DPI, crop, original), single-use proof links for the customer, print-ready files |
| Production | One job per order line, ordered stages, kanban board, operators, machines, real costs; stock consumed at printing |
| Stock & purchasing | Materials with weighted average cost, movements, physical counts, low-stock alerts, suppliers, purchases and supplier payments |
| Documents & finance | Quotations (customer accepts online, converted to order), invoices and credit notes (numbered at issue, integrity hash, FR/AR PDF), expenses with receipts, finance overview, VAT position |
| Marketing & content | Shipping zones and city rates, coupons, review moderation (verified purchases), banners, customer gallery (with consent), FAQ, newsletter list and export — all in French and Arabic |
| Management | Dashboard KPIs and charts, 10 reports with CSV export, team notifications, e-mail log, roles & permissions, activity log |
| Operations | Web installer, one-click database updates, daily backups, data retention, security self-check, demo catalogue |

## Installation (cPanel)

Download the release ZIP (it already contains the `vendor/` libraries) and follow
**[docs/DEPLOYMENT_CPANEL.md](docs/DEPLOYMENT_CPANEL.md)**: create a MySQL database, extract outside `public_html`,
point the document root to `public/` (or use `app_path.php`), open `https://your-domain/install`, add the cron job.

Updating an existing installation is described in the same guide (replace code, keep `.env`, `storage/`,
`public/uploads/`, then *Maintenance → Update*).

## Documentation

| Document | For |
|---|---|
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | The team (French): daily work in the back office |
| [docs/DEPLOYMENT_CPANEL.md](docs/DEPLOYMENT_CPANEL.md) | Installing, updating, cron, backups, troubleshooting |
| [docs/SECURITY.md](docs/SECURITY.md) | Protections, data retention, operator checklist |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Developers: request lifecycle, folders, design decisions |
| [docs/DATABASE.md](docs/DATABASE.md) | Schema (63 tables), statuses, seed data |
| [docs/CHECKLIST.md](docs/CHECKLIST.md) | What was built and how each item was verified |
| [CHANGELOG.md](CHANGELOG.md) | Versions |

## Command line (optional — everything is also available in the back office)

```bash
php bin/console migrate | migrate:status | migrate:rollback | seed
php bin/console make:admin email@example.ma "Full Name" 'StrongPass1'
php bin/console cron:run         # every 15 min: e-mails, alerts, retention, backups, quotation expiry
php bin/console schema:dump      # regenerates database/schema.sql (phpMyAdmin import)
php bin/console demo:install     # optional demo catalogue (also offered by the installer)
php bin/console demo:remove      # removes everything the demo created
php bin/console lint             # php -l on every file
```

## Development

```bash
composer install
php -S 127.0.0.1:8000 -t public tests/server-router.php     # then open http://127.0.0.1:8000/install

# unit + database integration tests (the test database is WIPED)
BWA_TEST_DB_DATABASE=bwa_test BWA_TEST_DB_USERNAME=user BWA_TEST_DB_PASSWORD=pass php tests/run.php

# HTTP end-to-end: installs a throw-away copy on a fresh database and drives it over HTTP
MYSQL_ADMIN_CMD="mysql -uroot" DB_NAME=bwa_install DB_USER=user DB_PASS=pass tests/e2e.sh

# release ZIP for cPanel (git archive + composer --no-dev + trimmed vendor)
bin/build-release.sh             # → dist/berradi-wall-art-<version>-cpanel.zip (committed code only)
```

With `APP_DEBUG=true`, every response carries `X-Debug-Time`, `X-Debug-Queries`, `X-Debug-Repeated` and `X-Debug-Slow`
headers (see [docs/ARCHITECTURE.md → Performance](docs/ARCHITECTURE.md)). Never leave debug on in production.

## Default addresses after installation

* Storefront: `/`, `/shop`, `/custom-wall-art`, `/collections`, `/cart`, `/track`, `/account`
* Back office: `/admin` — change it with `ADMIN_PATH` in `.env` (recommended); the Super Admin is created by the installer

## License

Proprietary — BERRADI WALL ART. All rights reserved.
