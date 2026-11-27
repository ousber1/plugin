# Security — BERRADI WALL ART

This document lists the protections built into the application and the checks done in the Phase 8 review.
The **Maintenance → Security check** page verifies the configuration of a live installation.

## Application

| Area | Protection |
|---|---|
| SQL injection | PDO prepared statements everywhere; the query builder validates identifiers; dynamic `ORDER BY` / columns only from whitelists. Reviewed: no request value is concatenated into SQL. |
| XSS | Every output goes through `e()` (htmlspecialchars, UTF-8); JSON in pages uses `JSON_HEX_*`; strict CSP (`script-src 'self'`, no inline scripts, `object-src 'none'`, `frame-ancestors 'self'`). Stored e-mail HTML is shown as text in the back office. |
| CSRF | Token on every state-changing request (middleware, `hash_equals`). Exempt: payment webhooks (authenticated by the provider's signature) and the bank's return URL (read-only, secret order token in the path). |
| Sessions | HttpOnly, SameSite=Lax, Secure on HTTPS, ID regenerated at login/logout and privilege changes, fingerprint check, admin idle timeout. |
| Passwords | `password_hash()` (bcrypt/argon), strength rules, rate-limited logins (per account + IP), generic error messages, single-use hashed reset tokens with expiry. |
| Authorisation | RBAC with 40 permissions checked by route middleware and in views; customers only reach their own orders, designs, photos and invoices (others get 404). |
| Secret links | Order tracking, artwork proofs, quotations and invoices use 64-hex random tokens; only their SHA-256 is stored; pages send `noindex` and `no-referrer`. |
| Uploads | Size limits, MIME detected with finfo, images re-encoded with GD (strips payloads/EXIF), random names, customer photos and print files outside the web root; `public/uploads` cannot execute scripts. |
| Redirects | Only same-origin referrers / internal paths are followed (look-alike hosts such as `site.ma.evil.example` are refused — fixed in Phase 8). |
| Host header | E-mail and canonical links use `APP_URL` written by the installer, never the request Host. |
| Money | Integer cents, server-side prices only, idempotent payment recording, issued invoices immutable (SHA-256 integrity hash, corrections by credit note). |
| CSV exports | Cells starting with `= + - @` are neutralised (formula injection). |
| Errors | Generic error pages in production; details only in `storage/logs` (deny-all). Old input never keeps passwords or tokens. |
| Card payments (CMI, v1.1) | Card data never reaches the site (hosted 3-D Secure page). Request and callback signed with the store key (hash v3, SHA-512, constant-time compare); merchant id and currency (504 = MAD) checked; **only the server-to-server callback records a payment**, never the browser return; retries idempotent on the transaction id; amount different from the balance → staff alert. Callback and return URLs run without a session, so a cross-site POST can never replace a visitor's session cookie. Store key encrypted at rest, never displayed, never logged. |
| Content-Security-Policy (v1.1) | Built per response (`App\Services\Csp`): the default allows this site only; a page adds exactly the hosts it needs (the CMI gateway in `form-action` on the payment page, the hosts of the tracking tags the owner enabled). Only `https://host` sources are accepted — keywords such as `'unsafe-inline'` cannot be added. `Permissions-Policy: camera=(self)` only on product pages (“Sur mon mur”), `camera=()` everywhere else. |
| Tracking & consent (v1.1) | No inline script: tags are loaded by `tracking.js` after consent (analytics vs. advertising, cookie `bwa_consent`, 180 days). IDs validated against each provider's format. Meta Conversions API only with advertising consent; e-mail, phone, city and customer id SHA-256 hashed; access token encrypted and masked in error logs. |
| SEO tools (v1.1) | Redirects only apply to URLs that would be 404 (a live page is never hijacked); targets limited to site paths or `https://`, no protocol-relative URLs, no self-redirects or chains; robots.txt additions validated line by line; verification codes restricted to `[A-Za-z0-9_-=.]`. The 404 monitor ignores admin, assets and common bot probes (`.php`, `wp-…`, dotfiles). |
| Headers | CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, COOP, HSTS on HTTPS, `X-Robots-Tag: noindex` on admin, account, cart, checkout and secret-link pages. |
| Installer | Locks itself (`storage/installed.lock`); all installer routes return 404 afterwards. |

## Data protection (Moroccan law 09-08 / GDPR principles)

* Customer photos are private; unordered photos are deleted after `retention_unordered_days`, ordered photos and print
  files after `retention_ordered_days` once the order is finished.
* Activity logs, e-mail bodies and abandoned carts are purged after the delays set in **Backups & retention**.
* Orders, invoices, credit notes and payments are never deleted (accounting obligations); customers can be
  anonymised and their data exported as JSON from their admin file.

## Backups

* Daily automatic database backup by cron (gzip SQL, pure PHP, restorable with phpMyAdmin), rotation by count.
* Backups contain password hashes and encrypted settings: download them only to a safe place.
* Files (`public/uploads`, `storage/`) are backed up with the cPanel backup tool.

## Operator checklist

1. HTTPS active and forced; `APP_URL` in https.
2. `APP_DEBUG=false`; `display_errors` off.
3. Project outside `public_html`; `.env` with permissions 640.
4. Private `ADMIN_PATH`; 1–3 super-admins, other staff with limited roles; deactivate unused accounts.
5. Cron every 15 minutes (mails, retention, backups, alerts).
6. SMTP configured (deliverability, SPF/DKIM on the domain).
7. Remove the demo catalogue before going live.

## Known limits

* No two-factor authentication yet for staff accounts (strong passwords + rate limiting + private admin path).
* Team alert texts are stored in the language active when the event happened.
