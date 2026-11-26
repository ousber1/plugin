# BERRADI WALL ART — Architecture

## Constraints that shaped the design

| Constraint (cPanel shared hosting) | Decision |
|---|---|
| No SSH, no Node, no Redis, no workers | Plain PHP 8.2+ MVC, no build step. `vendor/` is shipped in the ZIP. Web installer runs migrations. |
| One PHP process per request | Everything is request/response. Periodic work = one cPanel cron (`php bin/console cron:run`). |
| Limited CPU/RAM | Image editing happens **in the browser** (canvas). Server only validates, re-encodes once and makes one preview (GD). |
| Shared server, other users | Secrets in `.env` outside the web root, sessions in `storage/sessions`, private uploads in `storage/private_uploads`. |

## Request lifecycle

```
public/index.php
  └─ app/bootstrap.php            Composer autoload (+ built-in PSR-4 fallback)
      └─ App::boot()              .env → Config → timezone → Logger → View → Translator → routes/*.php
          └─ App::handle(Request)
               global middleware: SecurityHeaders → Installed → Locale → Maintenance → Csrf
               Router::dispatch → route middleware (auth.admin, can:perm, guest, throttle, installer)
               Controller::method(...route params) → Response (HTML view / JSON / redirect / file)
          exceptions → HttpException (403/404/405/419/429/503 views), ValidationException
                       (flash errors + old input, redirect back / JSON 422), others → logged 500
```

## Folder map

```
app/Core/          Framework: App, Router/Route, Request/Response, Database (PDO), QueryBuilder,
                   Paginator, Session, Csrf, Validator, View, Translator, Config, Env, Logger, Migrator
app/Controllers/   Admin/*, Customer/*, Storefront/*, Installer/InstallController
app/Middleware/    Auth (admin/customer), Guest, Permission (can:), Csrf, Throttle, Locale,
                   Installed, Installer (lock), Maintenance, SecurityHeaders
app/Services/      Auth, RateLimiter, SettingsService, Crypt, Mailer, PasswordResetService,
                   SequenceService, ImageUploadService, ActivityLogger, Installer/*
app/Helpers/       functions.php (e(), url(), route(), __(), money()…), Money (integer cents), Str
config/            app, database, uploads, permissions (RBAC matrix)
database/          migrations/*.php (SQL per module), seeders/*.php (idempotent), schema.sql (generated)
public/            ONLY web-accessible folder: index.php, .htaccess, assets/, uploads/ (catalogue images)
resources/         views/ (layouts, partials, storefront, customer, admin, installer, errors, emails), lang/fr|ar
routes/            installer.php, web.php, admin.php
storage/           logs, sessions, cache, private_uploads, previews, invoices, backups (all deny-all)
tests/             run.php (unit + DB integration), http.php + e2e.sh (HTTP end-to-end)
```

## Key design decisions

* **Two auth guards.** Staff (`users`, RBAC) and customers (`customers`) never share a session key or a
  login form. Sessions are bound to a user-agent fingerprint, regenerated on login, invalidated when the
  password changes elsewhere or the account is deactivated.
* **RBAC.** `config/permissions.php` → seeded into `permissions`, `roles`, `role_permissions`.
  `super_admin` bypasses checks and cannot be edited; the last active super admin cannot be removed.
  Routes use `can:perm`; menus only show entries whose route exists and is permitted, so modules appear
  automatically as phases are delivered.
* **Money.** DB columns are `DECIMAL(12,2)`; PHP uses `App\Helpers\Money` on integer centimes
  (half-up rounding, TVA %/HT↔TTC). No `float` column exists (asserted by a test).
* **Document numbers.** `SequenceService` uses `document_sequences` + `SELECT … FOR UPDATE` inside a
  transaction → gap-free `PREFIX-YYYY-000001` per type and year.
* **Financial immutability.** Invoices get a number only when issued, store a company snapshot and an
  integrity hash; corrections are credit notes (`invoice_type = credit_note`), cancellation is a status
  change. FKs use `ON DELETE RESTRICT` on financial rows.
* **Accounting separation.** Material purchases → `purchases` (become stock, valued in `stock_movements`);
  consumption → `stock_movements` (production/waste) linked to `production_jobs`; `expenses` holds only
  operating costs (it has no "materials" category) → no double counting.
* **Translations.** UI strings in `resources/lang/{fr,ar}`; catalogue content in main columns (French) +
  `translations` table (Arabic). `<html dir>` switches and Bootstrap RTL is loaded for Arabic.
* **Private customer photos.** `uploaded_images` stores files under `storage/private_uploads` (never under
  `public/`), owner token / customer id, checksum and `retention_until` for automatic purge (Phase 3/8).
* **Security.** Prepared statements everywhere; identifiers validated; CSRF on every POST (form field or
  `X-CSRF-Token`); `e()` escaping in views; CSP without inline scripts; DB rate limiting; uploads checked
  by finfo + getimagesize and re-encoded with GD; upload folder has execution disabled; audit log.
