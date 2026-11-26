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

## Phase 2: Premium storefront & catalogue ✅

| Item | Status | Verified by |
|---|---|---|
| Homepage, 15 sections (hero/banner carousel, custom photo art, collections, best sellers, offers, new arrivals, categories, how it works, reviews, installations gallery, FAQ, newsletter, WhatsApp, footer). Data sections hidden when empty: no fake reviews or stats | ✅ | HTTP (sections present; reviews/gallery absent without real data) |
| Navigation & footer fill in automatically as routes exist; header search | ✅ | HTTP |
| `CatalogService`: listing with category tree, collection, type, price range, flags, FR/AR search, 6 sorts, pagination, one query per page (no N+1) | ✅ | integration (filters, sorting, pagination, invalid input) |
| Promotional pricing with time window (product + variants), "from" price for variants, % off badge | ✅ | integration (window expiry/reactivation) |
| Shop, category, best sellers, new arrivals, offers, collections index/detail pages | ✅ | HTTP (200, content, breadcrumbs, 404s) |
| Product page: gallery, variant selectors (size × material × frame) with live price/SKU, impossible-combination handling, "on my wall" view at real scale, specs, delivery, real reviews, related products, WhatsApp order with chosen variant | ✅ | HTTP + Playwright (price 229 → 709 DH, SKU, room scale) |
| FR/AR content translations (`translations` table) incl. Arabic search | ✅ | integration + HTTP |
| SEO: per-page title/description, canonical, Open Graph, `noindex` on filtered listings, JSON-LD (Organization, WebSite+SearchAction, Product/AggregateOffer, BreadcrumbList, FAQPage), injection-safe encoding | ✅ | integration + HTTP |
| About, Contact (validation, honeypot, throttling, stored + e-mailed), FAQ | ✅ | HTTP |
| Newsletter: explicit consent, AJAX/JSON + no-JS fallback, no e-mail enumeration, one-click unsubscribe | ✅ | HTTP + Playwright |
| Optional demo catalogue: 19 products, 123 variants priced from the real substrate/frame rates, 4 collections, 6 FAQ, 42 generated images; installer checkbox or `demo:install`; `demo:remove` deletes everything | ✅ | integration (install → remove → nothing left) |
| Responsive & RTL | ✅ | Playwright: 14 pages × FR/AR at 390px, no overflow, no JS errors |
| Contact e-mail delivery on the real host | 🟡 | message stored when `mail()` is unavailable; real SMTP to verify on the host |

## Phase 3: Custom Wall Art Builder ⬜
Private photo upload (multi-panel), browser canvas editor (crop/zoom/move/rotate/ratios), sizes/materials/frames/
borders/panels, server-side live pricing API, mockup preview, DPI quality warnings, saved designs.

## Phase 4: Cart, checkout, customer area, shipping, payments ⬜
## Phase 5: Admin dashboard KPIs & charts, products, categories, collections, customers ⬜
## Phase 6: Orders, status workflow, production, artwork management, inventory ⬜
## Phase 7: Invoices, quotations, PDF (FR/AR), payments, finance ⬜
## Phase 8: Reports, SEO (sitemap, schema.org, OG), notifications, retention purge, backups, security review ⬜
## Phase 9: Optimization, documentation, final ZIP package ⬜
