# BERRADI WALL ART

E-commerce & business management system for a Moroccan wall-art printing company: personalised canvas
prints, framed photos, posters, multi-panel art, production, stock, invoices/quotations and finance.

Built for **ordinary cPanel shared hosting**: PHP 8.2–8.4, MySQL/MariaDB, Apache. No Node, Docker,
Redis, workers or SSH required.

* Stack: PHP 8.2+ (custom lightweight MVC), PDO, Bootstrap 5 (LTR + RTL), vanilla ES6, Chart.js, Dompdf, PHPMailer
* Languages: French (LTR) & Arabic (RTL) · Currency: MAD / DH
* Brand palette: `#171717` · `#C6A66B` · `#F8F7F4` · `#FFFFFF` · `#292929`

## Quick start (local)
```bash
composer install
php -S 127.0.0.1:8000 -t public tests/server-router.php
# open http://127.0.0.1:8000/install
```

## Deployment
See **[docs/DEPLOYMENT_CPANEL.md](docs/DEPLOYMENT_CPANEL.md)** (document root on `public/`, or the
`app_path.php` alternative when `public_html` is fixed).

## Documentation
* [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): request lifecycle, folders, design decisions
* [docs/DATABASE.md](docs/DATABASE.md): 63-table schema, statuses, seed data
* [docs/CHECKLIST.md](docs/CHECKLIST.md): development checklist by phase, with test status

## Command line (optional)
```bash
php bin/console migrate | migrate:status | migrate:rollback | seed
php bin/console make:admin email@example.ma "Full Name" 'StrongPass1'
php bin/console schema:dump      # regenerates database/schema.sql
php bin/console cron:run         # add to cPanel cron every 15 min
php bin/console demo:install     # optional demo catalogue (also offered by the installer)
php bin/console demo:remove      # removes everything the demo created
php bin/console lint             # php -l on every file
```

## Tests
```bash
# unit + database integration (the test DB is WIPED)
BWA_TEST_DB_DATABASE=bwa_test BWA_TEST_DB_USERNAME=user BWA_TEST_DB_PASSWORD=pass php tests/run.php

# HTTP end-to-end: installs a throw-away copy on a fresh DB and drives it over HTTP
MYSQL_ADMIN_CMD="mysql -uroot" DB_NAME=bwa_install DB_USER=user DB_PASS=pass tests/e2e.sh
```

## Default access after installation
* Storefront: `/`, `/custom-wall-art`, `/cart`, `/checkout`, `/track`, `/shop`, `/collections`, `/best-sellers`, `/new-arrivals`, `/offers`, `/product/{slug}`, `/about`, `/contact`, `/faq` · Customer area: `/account`
* Administration: `/admin` (change with `ADMIN_PATH` in `.env`); Super Admin created by the installer
