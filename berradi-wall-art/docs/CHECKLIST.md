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

## Phase 3: Custom Wall Art Builder ✅

| Item | Status | Verified by |
|---|---|---|
| Photo upload (JPG/PNG/WEBP, several photos, drag & drop, progress), type/size/dimension validation, per-visitor quota | ✅ | integration + HTTP (fake PHP file, tiny image, admin size limit) + Playwright |
| Originals stored byte-for-byte in `storage/private_uploads` (outside web root), randomized names, SHA-256 checksum | ✅ | integration (checksum identical) |
| Previews/thumbnails generated in the browser (canvas) and only re-encoded by the server; GD fallback only when memory allows | ✅ | integration (browser preview used / mismatched preview rejected → fallback) |
| Private serving with ownership checks (guest cookie token stored hashed, customer account, staff `artwork.view`); originals only for staff | ✅ | HTTP (other visitor 404, customer 403 on original, admin 200) |
| Guest photos & designs claimed by the account on login/registration | ✅ | integration + HTTP |
| Editor (Cropper.js 1.6.2, self-hosted): fixed print-ratio frame, move, zoom (buttons/slider/wheel, never below "cover"), rotate ±90°, center, reset | ✅ | Playwright |
| Portrait / landscape / square, standard sizes (filtered per support), custom size within support limits | ✅ | Playwright + integration |
| Supports, canvas borders, finishes, frames (only when the support can be framed), 1–5 panels, one photo across panels or one photo per panel, quantity | ✅ | Playwright (single, triptych span, diptych per-panel) |
| Live price from the server (`/builder/price`), breakdown; same formula enforced again on save | ✅ | integration (199 / 587 / 687 / 710 DH cases) + HTTP + Playwright |
| Wall mockup at real scale (3 m wall, 2 m sofa) with frames, gaps and canvas depth | ✅ | Playwright screenshots |
| Print-quality check (effective DPI) with warning and hard block under the admin minimum, re-checked on the server | ✅ | integration + Playwright (25 dpi blocked, 55 dpi warning) |
| Save design: config, crops in original pixels (rotation aware), server price, quality, browser-rendered preview stored privately | ✅ | integration + HTTP (tampered price ignored, tampered crop 422) |
| Design page (private), WhatsApp order with design reference, "Mes créations" in the account, delete design / delete photo now | ✅ | HTTP |
| Retention: cron purges expired photos (files deleted, row kept for audit) and expires designs that used them | ✅ | integration |
| FR/AR (RTL) + mobile | ✅ | Playwright 390px FR/AR |
| Fixes found by tests: request throttle shared by IP across routes (could lock out "forgot password"), readonly exception property crash, "126 × 3 cm" formatting | ✅ | regression tests added |
| "Add to cart" from a design | ⬜ | Phase 4 (button appears automatically when the cart exists) |

## Phase 4: Cart, checkout, customer area, shipping, payments ✅

| Item | Status | Verified by |
|---|---|---|
| Server-side cart (hashed cookie token for guests, linked to the account, merged on login, revived after "abandoned"), products/variants and saved designs, quantity merge, stock limits, ownership checks | ✅ | integration + HTTP |
| Every cart view re-prices every line (catalogue promo windows, current builder pricing); tampered stored prices are ignored | ✅ | integration (unit price forced to 1.00 → order at real price) |
| Customizable products must go through the builder (no "add to cart" without a photo) | ✅ | integration + HTTP |
| Coupons: percent / fixed / free shipping, minimum order, max discount, product or category scope, dates, global & per-customer limits, atomic redemption, released on cancellation | ✅ | integration (single-use coupon cannot be used twice) |
| Shipping: city rates (accent-insensitive) → zone thresholds, unknown city → flat rate + global free threshold, delivery estimate in working days (Sunday skipped), live estimate on cart & checkout | ✅ | integration + HTTP + Playwright |
| Taxes: TVA included (TTC) or added (HT), computed on goods − discount + shipping | ✅ | integration (both modes) |
| Checkout (guest or customer): validation, saved addresses, optional account creation, account e-mail cannot be overridden, abandoned-cart contact capture | ✅ | HTTP + Playwright |
| Payment methods: cash on delivery, bank transfer (RIB on confirmation/e-mail), manual; online methods only if a gateway is implemented & configured; nothing is marked paid at checkout; verified-webhook extension point | ✅ | integration + HTTP (webhook 404, CMI never offered) |
| Order creation in one transaction: sequential number, line snapshots, design customizations (crop, DPI, preview), photo retention extended, stock locked & decremented, coupon redemption, status history, cart converted | ✅ | integration |
| Confirmation page (private to the buyer), e-mails to customer (in their language) & admin, recorded in `notifications`, retried by cron when SMTP is down | ✅ | HTTP (2 notifications recorded) |
| Order tracking: number + e-mail/phone (any format) or private token link; shows status & shipment only | ✅ | integration + HTTP |
| Customer area: order history, detail, cancellation of eligible orders (restock, coupon & design release), addresses CRUD, contact support prefilled, invoices listed when issued (Phase 7) | ✅ | HTTP |
| Payment recording service (partial/paid/refund status sync, idempotent provider transactions) | ✅ | integration |
| Terms of sale page (FR/AR, editable via settings `terms_body_fr/ar`) | ✅ | HTTP |
| Responsive & RTL (cart, checkout, tracking), header fits at ≥1200px, burger below | ✅ | Playwright 390/1100/1280/1440 px |
| Real e-mail delivery | 🟡 | sandbox has no mail server: messages are queued and retried by cron; verify SMTP on the host |

## Phase 5: Admin dashboard KPIs & charts, products, categories, collections, customers ⬜
## Phase 6: Orders, status workflow, production, artwork management, inventory ⬜
## Phase 7: Invoices, quotations, PDF (FR/AR), payments, finance ⬜
## Phase 8: Reports, SEO (sitemap, schema.org, OG), notifications, retention purge, backups, security review ⬜
## Phase 9: Optimization, documentation, final ZIP package ⬜
