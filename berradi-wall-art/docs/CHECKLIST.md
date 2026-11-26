# Development checklist

Legend: ✅ done and verified by an executed test · 🟡 done, verified manually or partially · ⬜ planned

## Phase 1: Architecture, database, installer, authentication, settings ✅

| Item | Status | Verified by |
|---|---|---|
| MVC framework (router, middleware, request/response, views+layouts, validator, query builder, paginator) | ✅ | `tests/run.php` (router, request, validator, query builder) |
| Complete normalized schema: 63 tables, 109 FKs, indexes, DECIMAL money, timestamps | ✅ | migrate / rollback / re-migrate test; no float columns; InnoDB+utf8mb4 test |
| Migrations runner (web + CLI) and idempotent seeders | ✅ | seeders run twice → identical counts |
| `database/schema.sql` for phpMyAdmin import | ✅ | imported into an empty MariaDB 10.11 → 63 tables |
| 10-step installation wizard (real PHP/extension/permission checks, DB test, .env writing or manual fallback, migrations, company + logo, admin, lock) | ✅ | `tests/http.php` (full flow, wrong credentials, weak password, skipped steps, lock → 404) |
| Admin authentication (login, logout, intended URL, forgot/reset password) | ✅ | unit + HTTP |
| Customer accounts (register, login, logout, profile, password change, forgot/reset) | ✅ | unit + HTTP |
| Login rate limiting (5 failures / 15 min, DB-based), throttled POST routes | ✅ | unit |
| RBAC: 7 roles, 40 permissions, editable permission matrix, last super-admin protection | ✅ | unit (4 roles × 6 permissions) + HTTP (designer → 403) |
| Staff user management | ✅ | HTTP (create, duplicate e-mail) |
| Settings (8 tabs: company/legal IDs/logo, language & currency, TVA, document prefixes, shipping, orders & privacy, SMTP with encrypted password, SEO & maintenance) | ✅ | HTTP (all tabs render, save, validation, password not re-displayed) |
| Gap-free document numbering service | ✅ | unit |
| Money helper (integer cents, TVA, HT/TTC) | ✅ | unit |
| French + Arabic (RTL) translations, language switch | ✅ | key-coverage test (both languages) + HTTP |
| Security: CSRF, XSS escaping, CSP and security headers, secure session cookie, prepared statements, identifier validation, upload validation & re-encoding, upload folder no-exec, audit log | ✅ | unit + HTTP |
| Branded error pages (403/404/405/419/429/500/503), maintenance mode | 🟡 | 404/419/403/405 by HTTP test; 503 manual |
| Responsive layout (storefront, account, admin) | 🟡 | Playwright at 1440px and 390px: no horizontal overflow on 13 pages |
| E-mail sending (PHPMailer SMTP / mail() fallback) | 🟡 | log driver tested; real SMTP must be tested on the host |
| Apache `.htaccess` rules | 🟡 | written; built-in PHP server emulation tested, real Apache to verify at deployment |

## Phase 2: Premium storefront & catalogue ⬜
Homepage sections (collections, best sellers, new arrivals, reviews, gallery, offers, FAQ, newsletter), shop
listing with filters/sorting/search, category & collection pages, product detail, related products, demo catalogue seeder.

## Phase 3: Custom Wall Art Builder ⬜
Private photo upload (multi-panel), browser canvas editor (crop/zoom/move/rotate/ratios), sizes/materials/frames/
borders/panels, server-side live pricing API, mockup preview, DPI quality warnings, saved designs.

## Phase 4: Cart, checkout, customer area, shipping, payments ⬜
## Phase 5: Admin dashboard KPIs & charts, products, categories, collections, customers ⬜
## Phase 6: Orders, status workflow, production, artwork management, inventory ⬜
## Phase 7: Invoices, quotations, PDF (FR/AR), payments, finance ⬜
## Phase 8: Reports, SEO (sitemap, schema.org, OG), notifications, retention purge, backups, security review ⬜
## Phase 9: Optimization, documentation, final ZIP package ⬜
