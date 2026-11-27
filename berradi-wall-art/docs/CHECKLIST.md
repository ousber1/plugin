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

## Phase 5: Admin dashboard KPIs & charts, products, categories, collections, customers ✅

| Item | Status | Verified by |
|---|---|---|
| Dashboard KPIs from real data only (revenue today / month / total, trend vs previous month, outstanding balance, collected, orders by stage, customers, expenses, estimated profit with its formula shown) | ✅ | integration (figures recomputed in SQL; cancelled/returned orders excluded) + HTTP |
| Estimated profit = sales HT − order cost − real shipping cost − expenses HT | ✅ | integration (adding a 1 200 TTC / 200 TVA expense lowers profit by exactly 1 000) |
| Charts (Chart.js, self-hosted): 12-month sales, 12-month profit, orders by status, revenue by category, best sellers; one brand mark colour, tooltips, integer ticks for counts, data table under each chart, empty states, RTL axes | ✅ | HTTP (JSON matches DB) + Playwright screenshots |
| Finance tiles/charts only with `finance.view` / `reports.view` | ✅ | HTTP (designer sees no profit data) |
| Setup checklist, latest orders, low stock, recent activity, quick actions limited to existing routes + permissions, pending-migration alert | ✅ | HTTP |
| Products: list with search, category/collection/type/status/flag filters, sorting, pagination, bulk activate/deactivate/feature/category/delete | ✅ | HTTP + Playwright (bulk bar) |
| Product editor: tabs (general, price & stock, images, variants, options & related, Arabic & SEO), French decimal commas accepted, promo/date/panel validations, unique slug & SKU | ✅ | integration + HTTP |
| Images: multi-upload (re-encoded, thumbnails), main image, reorder, delete only when no longer referenced | ✅ | HTTP (upload → main image) |
| Variants: generation size × support × frame priced by the builder engine (rounded up to …9 DH), skip existing, edit/delete/default, product from-price synced | ✅ | integration + HTTP |
| Duplicate (inactive copy with variants, maps, translations); soft delete keeps order history, frees slug/SKU, empties carts | ✅ | integration + HTTP |
| Categories (tree with cycle protection) and collections (limited edition, date window): Arabic translation, image, SEO, drag-and-drop ordering, delete requires explicit detach when products exist | ✅ | HTTP |
| Print options (supports, frames, sizes, borders, layouts, finishes) editable with prices; a change is reflected immediately in the builder price API | ✅ | HTTP |
| Customers: list (spend, orders, guest/registered), profile (stats, orders, addresses, designs/photos count), create/edit B2B data (ICE), GDPR anonymisation (purges unordered photos, keeps financial records) | ✅ | HTTP |
| Maintenance page: pending migrations + one-click update, demo install/remove, system info, cron status | ✅ | HTTP |
| RBAC on every new route (designer: catalogue yes, customers/maintenance 403) | ✅ | HTTP |
| Mobile 390 px and Arabic RTL: no horizontal overflow on all new admin pages, no JS errors | ✅ | Playwright |

## Phase 6: Orders, status workflow, production, artwork management, inventory ✅

| Item | Status | Verified by |
|---|---|---|
| Order list: status tabs with counts, search (number/name/phone/e-mail), payment/source/date filters, artwork-to-check flag | ✅ | HTTP |
| Order detail: lines with private mock-ups, totals, payments, shipments, production jobs, full history with author and "customer notified" | ✅ | HTTP + Playwright |
| Status workflow with an explicit transition table (impossible jumps refused, only valid next statuses offered), timestamps, comment, optional customer e-mail; cancel/return need `orders.manage` | ✅ | integration + HTTP |
| Cancellation by staff releases catalogue stock and coupon, returns photos to normal retention, cancels production jobs; paid amounts stay recorded | ✅ | integration |
| Editable contact/delivery details until shipment; internal notes always | ✅ | HTTP |
| Phone / WhatsApp / showroom orders: catalogue lines at shop price or negotiated price, free custom lines, shipping by city or manual, same tax rules, stock reserved, customer matched by e-mail/phone | ✅ | integration + HTTP |
| Printable work order / packing slip (HTML, print button without inline JS) | ✅ | HTTP |
| Artwork queue + review page: final mock-up, original photo (download logged), crop, effective DPI with warning colours | ✅ | HTTP + Playwright |
| Proof to customer: single-use link (only SHA-256 hash stored), approve or request changes with feedback, internal alert, order → awaiting approval → approved for production | ✅ | integration + HTTP |
| Studio approval, change requests, print-ready file upload (PDF/TIFF/JPG/PNG checked by content, private storage), early purge of the original | ✅ | integration + HTTP |
| Production jobs (one per line) with stage tasks adapted to the line (no artwork stages for catalogue prints, no framing without frame), worked strictly in order | ✅ | integration + HTTP |
| Board by stage with priority, deadline (late in red), operator, machine; job sheet with planning, hold/resume, real costs (materials at WAC, machine time × hourly rate, labour, other) | ✅ | HTTP + Playwright |
| Workshop progress pushes the order forward only (printing → framing → quality control → ready to ship); shipping closes the jobs | ✅ | integration + HTTP |
| Print stage consumes linked stock once (m² × consumption factor for supports, perimeter + 10 % for mouldings) | ✅ | integration + HTTP |
| Shipments: courier, tracking number/https link, real cost (feeds profit), COD limited to balance, multi-parcel; shipped/delivered/returned update the order and e-mail tracking | ✅ | integration + HTTP |
| Offline payments recorded on the order (never above the balance) | ✅ | HTTP |
| Inventory: materials with units, thresholds, locations; signed movement ledger with balance after each move; WAC on valued entries; exits refused below zero (production may go negative); physical count = adjustment | ✅ | integration + HTTP |
| Suppliers (purchased / still owed), purchases draft → ordered → received (stock in at purchase cost) or cancelled, supplier payments capped at the remaining amount | ✅ | integration + HTTP |
| Machines with hourly cost and status | ✅ | HTTP |
| RBAC on every new route (designer: orders read-only, artwork yes, stock/purchases/machines 403, no status change or payment) | ✅ | HTTP |
| Mobile 390 px and Arabic RTL on all new pages: no horizontal overflow, no JS errors | ✅ | Playwright |

## Phase 7: Invoices, quotations, PDF (FR/AR), payments, finance ✅

| Item | Status | Verified by |
|---|---|---|
| Document lines priced HT: decimal quantities, line discounts, VAT per line, cent rounding (integer maths), totals = sum of rounded lines | ✅ | integration |
| Quotations: numbered (DEV-YYYY-NNNNNN), validity from settings, editable while draft/sent (editing a sent quote returns it to draft and kills the old link), duplicate, cancel | ✅ | integration + HTTP |
| Customer quotation page from a secret link (SHA-256 stored, noindex, no-referrer): view, PDF, accept / decline once, internal alert; cron expires overdue quotes | ✅ | integration + HTTP |
| Conversion of an accepted quotation into a confirmed order with exactly the same total | ✅ | integration + HTTP |
| Invoice drafts from an order: HT/VAT split derived from the order (TTC or HT pricing), total = amount owed; one active invoice per order | ✅ | integration + HTTP |
| Standalone invoices (B2B/services) with typed lines; drafts editable/deletable, never numbered | ✅ | integration + HTTP |
| Issuing: next number of the year inside the issuing transaction (no gaps), company identity frozen, SHA-256 integrity hash, tampering detected | ✅ | integration (line altered in DB → "modified" badge) |
| Issued documents never edited or deleted: credit notes (AV-) full or partial; a fully credited invoice is marked cancelled with its reason and kept | ✅ | integration + HTTP |
| Payments: order payments update the invoice status; payments on standalone invoices; refunds (never above what was received); ledger page with filters | ✅ | integration + HTTP |
| PDF (Dompdf, no remote resources): FR and AR (Arabic shaping + RTL column order), VAT breakdown, legal identifiers, amount in words (FR), draft/cancelled watermark, signature box on quotes; issued PDFs archived in `storage/invoices` | ✅ | integration + rendered pages inspected |
| Customer access: invoice link from the e-mail (hashed token) and invoices of their own orders in the account (others: 404) | ✅ | HTTP |
| Expenses: categories, TTC with recoverable VAT, supplier, private receipt (finfo-checked), monthly view; deletions recorded in the activity log | ✅ | HTTP |
| Finance overview by period: sales, net invoiced, cash in/out, net cash chart, VAT collected − deductible, receivables (orders + invoices, overdue list), payables, cash by method, expenses by category, estimated profit | ✅ | integration + HTTP + Playwright |
| RBAC: quotations/invoices/payments/expenses/finance permissions enforced (designer 403) | ✅ | HTTP |
| Mobile 390 px and Arabic RTL on all new pages: no overflow, no JS errors | ✅ | Playwright |

## Phase 8: Reports, SEO (sitemap, schema.org, OG), notifications, retention purge, backups, security review ✅

| Item | Status | Verified by |
|---|---|---|
| SEO: one URL per language (`?lang=ar`), localized canonical, `hreflang` fr/ar/x-default, `og:locale:alternate` | ✅ | integration + HTTP |
| `sitemap.xml`: public pages, active categories/collections/products in both languages with alternates, lastmod and images; cached 1 h, refreshed on catalogue changes | ✅ | integration (valid XML, deleted products excluded) + HTTP |
| Dynamic `robots.txt` (private areas disallowed, sitemap advertised, admin path not revealed, maintenance = disallow all) and `X-Robots-Tag: noindex` on private areas | ✅ | HTTP |
| Existing schema.org: Organization, WebSite, Product (offers/aggregate offers, real ratings only), BreadcrumbList, FAQPage | ✅ | Phase 2 tests |
| Reports: sales (day/week/month), products & margins, categories, customers, cities & courier cost, channels & cancellation rate, production lead time & lateness, stock movements & valuation, expenses, catalogue quality (SEO/AR completeness) | ✅ | integration (sales = dashboard figures, categories cover every line) + HTTP |
| CSV exports for Excel (BOM, `;`, decimal comma, formula injection neutralised), export logged | ✅ | integration + HTTP |
| Team notifications by permission: new orders, staff orders, artwork answers, quote answers, contact messages; daily low stock / overdue invoices / late jobs (once per 24 h); bell with unread count, list, mark read | ✅ | integration + HTTP + Playwright |
| E-mail log with status, attempts, error, text view (HTML never rendered) and manual retry | ✅ | HTTP |
| Retention settings + cron purges (logs, read alerts, e-mail bodies, old carts, print files of finished orders, backup rotation); financial records untouched | ✅ | integration |
| Customer data export (JSON, no password hash) | ✅ | integration + HTTP |
| Database backups in pure PHP (gzip SQL), manual + daily automatic, download, delete, rotation, strict file-name validation | ✅ | integration (restored into an empty database: identical row counts) + HTTP |
| Security review: SQL/XSS/CSRF/session/upload/redirect/host-header audit; **fixed** look-alike-referrer open redirect; `docs/SECURITY.md`; live security self-check on the Maintenance page | ✅ | HTTP (redirect test) + review |
| RBAC on reports, backups and e-mail log (designer 403); mobile 390 px and RTL without overflow or JS errors | ✅ | HTTP + Playwright |

## Phase 9: Optimization, back-office completion, documentation, final ZIP package ✅

| Item | Status | Verified by |
|---|---|---|
| Debug profiling headers (time, query count, repeated SQL, slow queries) only when `APP_DEBUG=true` | ✅ | measured on 20 000 synthetic orders |
| Dashboard monthly series and finance cash flow as grouped queries (90 → 33 and 189 → 25 queries), products report aggregated before join | ✅ | integration (grouped results = per-month sums; report total = line sum) |
| Every back-office page under ~200 ms PHP time on 20 000 orders; list/report filters use indexes | ✅ | debug headers + `EXPLAIN` |
| Static assets: 1-year immutable cache for versioned files, 30 days for images, gzip, WOFF2/WebP types | ✅ | `public/.htaccess` (Apache modules guarded by `IfModule`) |
| Shipping zones and city rates CRUD; accent/case-insensitive duplicate refused; new city rate used by the cart estimate | ✅ | HTTP |
| Coupons CRUD with validation (percent ≤ 100, unique code case-insensitive); customer applies it; used coupons deactivated, not deleted | ✅ | HTTP |
| Customer reviews: delivered orders only, one per product and order, verified purchase, hidden until approved, then shown on the product page; staff alert | ✅ | HTTP |
| Store content: banners (safe links only — `javascript:` refused), customer gallery published only with consent, FAQ in FR + AR on the public FAQ page | ✅ | HTTP |
| Newsletter: subscribers / unsubscribed tabs, CSV export, unsubscribe | ✅ | HTTP |
| **Fixed**: a regex validation rule containing `|` was split into two rules (500 on banner save) — array rule syntax | ✅ | HTTP (test failed before the fix) |
| RBAC on the five new pages (designer 403); Arabic RTL rendering | ✅ | HTTP |
| Mobile 390 px and desktop, FR and AR, new pages: no overflow, no JS errors (**fixed** newsletter toolbar and shipping rate form overflow; `dir="auto"` on user text) | ✅ | Playwright |
| *Maintenance → Update* resets OPcache; security check warns about a leftover static `robots.txt` | ✅ | lint + HTTP (maintenance page) |
| Documentation: README, CHANGELOG, user guide (FR) incl. new modules, cPanel deployment (update procedure, cron duties, OPcache, troubleshooting), architecture performance notes | ✅ | review against the code (each documented behaviour checked in the source) |
| `bin/build-release.sh`: git archive → no-dev Composer → trimmed vendor → lint → ZIP with installation notes | ✅ | see release verification below |
| Version 1.0.0 | ✅ | `config/app.php` |
