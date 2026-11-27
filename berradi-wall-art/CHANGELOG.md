# Changelog

All notable changes to BERRADI WALL ART. Versions follow [semantic versioning](https://semver.org/).
Database changes are applied by *Maintenance → Update* (or `php bin/console migrate`); they only add tables,
columns and indexes.

## [1.0.0] — 2026-11-27

First production release. Built in nine phases; each phase below was tested end to end before the next one.

### Phase 9: optimization, back-office completion, release
- **Performance**: dashboard monthly figures and finance cash flow computed with grouped queries
  (dashboard 90 → 33 SQL queries, finance 189 → 25); products report aggregated before joining.
  All back-office pages under ~200 ms on 20 000 orders.
- Debug profiling headers (`X-Debug-Time`, `X-Debug-Queries`, `X-Debug-Repeated`, `X-Debug-Slow`) when `APP_DEBUG=true`.
- Long-lived cache headers for versioned assets, 30 days for images, gzip for text, WOFF2/WebP types.
- New back-office screens: **Shipping** zones and city rates (accent-insensitive duplicate check),
  **Coupons** (percent/fixed/free shipping, conditions, used codes deactivated instead of deleted),
  **Reviews** moderation (publish, feature, remove), **Store content** (banners, customer gallery with
  consent, FAQ — French and Arabic), **Newsletter** (subscribers, CSV export, unsubscribe).
- Customers can review products of delivered orders (verified purchase, moderated before publication).
- *Maintenance → Update* resets OPcache; security check warns about a leftover static `robots.txt`.
- Fixed: a validation rule containing `|` inside a regular expression was split into two rules (banner links).
- Fixed: newsletter toolbar and shipping rate form overflowing at phone width; user text in the other
  language now uses `dir="auto"` (punctuation on the correct side in RTL).
- Documentation: user guide (French), cPanel deployment with update procedure and troubleshooting,
  architecture notes on performance, `bin/build-release.sh`.

### Phase 8: reports, SEO, notifications, retention, backups, security
- `hreflang` alternates, localized canonical, dynamic `sitemap.xml` and `robots.txt`, `X-Robots-Tag` on private areas.
- Ten reports with Excel-safe CSV export; team notifications (bell); e-mail log with retry.
- Data retention purges, customer data export, pure-PHP database backups (manual + daily), security self-check.
- Fixed an open redirect through look-alike referrers.

### Phase 7: quotations, invoices, PDF, finance
- Quotations accepted online and converted to orders; invoices numbered at issue with frozen company identity and
  integrity hash; credit notes; French/Arabic PDF (Arabic shaping); payments, refunds, expenses, finance overview.

### Phase 6: orders, artwork, production, stock
- Order workflow with history, phone/WhatsApp orders, shipments; single-use artwork proof links; production jobs
  with ordered stages; materials with weighted average cost, purchases and suppliers.

### Phase 5: back office
- Dashboard, products and variants, categories, collections, customers, print options, team and roles, maintenance.

### Phase 4: cart and checkout
- Guest or account checkout, coupons, shipping by city, cash on delivery / bank transfer, customer area.

### Phase 3: custom wall-art builder
- Private photo upload, per-panel cropping (1–5 panels), supports, frames, live server-side pricing, DPI warnings.

### Phase 2: storefront
- Home, shop with filters, categories, collections, product pages, FAQ, contact, newsletter, schema.org.

### Phase 1: foundation
- MVC core, database schema and migrations, web installer, authentication, roles and permissions, settings, FR/AR.

## Updating from a pre-release build
Follow *docs/DEPLOYMENT_CPANEL.md → Updating*. Delete `public/robots.txt` (it is generated since Phase 8).
