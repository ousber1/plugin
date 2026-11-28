# Changelog

All notable changes to BERRADI WALL ART. Versions follow [semantic versioning](https://semver.org/).
Database changes are applied by *Maintenance → Update* (or `php bin/console migrate`); they only add tables,
columns and indexes.

## [1.2.0] — 2026-11-28

From a wall-art shop to a complete print shop. Database update: migration `000011` + seed data (run
*Maintenance → Update*). Existing products, orders and the photo builder are unchanged.

### Personalised gifts & CO2 laser
- New product families: printed gifts (sublimation / DTF), textiles, stationery, other gifts, laser engraving,
  laser cutting — next to the six wall-art types. Storefront universes `/wall-art`, `/personalized-gifts`,
  `/laser-engraving` (menu, home page, sitemap, SEO pages).
- **Option variants** (up to two dimensions, e.g. Colour × Size) with colour swatches, Arabic values and a generator.
- **Personalisation fields** per product: short text, message, photo, font, choice list, date — required or not,
  length limits, extra price, printed area for photos (resolution check); FR/AR labels.
- Private photo upload on the product page (same protected storage as the photo builder); someone else's photo is refused.
- **Quantity price breaks** (corporate gifts, weddings), minimum / maximum quantity, lead time, **proof (BAT)** before production.
- Personalised lines are never merged in the cart; the personalisation follows the order everywhere: cart,
  confirmation, e-mails, customer area, admin order, work order, production job, artwork review, invoice line.
- **Template library** (19 products): photo mug, magic mug, steel bottle, t-shirt, cushion, puzzle, A5 notebook,
  wooden keychain, engraved cutting board, wooden box, acrylic LED lamp, engraved photo frame, cut-out name sign,
  wall decoration, coasters, leather keychain, bookmark, acrylic office sign — created hidden with indicative prices.

### Production & stock
- Production route per family: sublimation `print → press`, laser `engrave → assemble`, stationery
  `print → cut → assemble`, wall art unchanged; the laser (or press / printer) is pre-selected on the stage.
- **Bill of materials** per product or variant (blank mug, plywood sheet fraction, LED base…), consumed when the
  object is made (print for printed goods, engraving for laser).
- Machines record their work area: the **Laser CO2 4040** (40 × 40 cm) is seeded; laser variants larger than the
  bed show a warning. Common blanks and laser sheets are seeded as stock items at zero quantity and zero cost.

## [1.1.0] — 2026-11-27

Database update: migration `000010` (run *Maintenance → Update*). Nothing changes on the storefront until you
configure it: no tracking ID is set and card payment stays off until CMI credentials are entered.

### Payment methods (back office)
- New page *Finance → Payment methods*: names, descriptions and instructions in French and Arabic, order, active
  state, minimum / maximum order amount per method (e.g. cash on delivery up to 3 000 DH), new offline methods
  (Cash Plus, cheque…); methods already used are deactivated, never deleted; one offline method always stays active.
- **Card payment with CMI** (3-D Secure hosted page, hash v3): credentials encrypted, test / live mode, notification
  URL shown; the order is marked paid only by CMI's signed server callback (tampering, other merchant, other currency
  and replays refused); team alert; "Pay by card" from the order tracking page if the payment failed.
- PayPal explained as unavailable (it does not accept MAD). Arabic texts for the default payment methods.

### "Sur mon mur" studio (product pages)
- Full-screen studio with three modes: **Creative** (5 rooms drawn to real scale, wall colours incl. a custom one,
  floors, day / evening light), **My photo** and **Camera (pro)** (live rear camera, level indicator, capture).
- Real size everywhere: sizes switch live (in sync with the product form), multi-panel artworks, frames; on photos,
  visible-wall-width slider or **calibration with a reference object** (A4 sheet, door).
- Drag with finger, mouse or keyboard; download or share the composed image with the size caption.
- Everything stays on the visitor's device; `camera=(self)` only on product pages.

### SEO tools
- *Marketing → SEO tools*: default title/description with live Google preview and length counters; verification
  codes (Google, Bing, Yandex, Pinterest, Facebook domain), X/Twitter account; local-business schema (type,
  opening hours, GPS, price range); extra robots.txt rules.
- Per-page titles, descriptions and noindex in French and Arabic for the fixed pages (noindex pages leave the sitemap).
- 301/302 redirects (only for URLs that would be 404, query string kept, no chains) and a **404 monitor** with
  one-click redirect.
- Catalogue **SEO audit** with score and fixes per product / category / collection; Google preview on the product form.

### Tracking & pixels
- *Marketing → Tracking & pixels*: Google Analytics 4, Google Tag Manager, Google Ads (purchase conversion),
  Meta Pixel, TikTok, Snapchat, Pinterest, Microsoft Clarity — IDs validated.
- **Consent banner** (analytics / advertising, law 09-08 / CNDP); nothing loads before consent; no inline script.
- E-commerce events for every provider: view_item, add_to_cart, begin_checkout, purchase (once per order),
  search, generate_lead, sign_up, WhatsApp/phone contact.
- **Meta Conversions API** (server side, consent required, hashed customer data, same event id as the pixel),
  sent by the cron with retries; test button.
- Content-Security-Policy now built per response: only the hosts of the enabled tags are allowed.

### Fixes
- Validation rules may contain `|` inside a regular expression when written as an array (used by the new forms).

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
