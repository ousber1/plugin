# Relay Stream Portal — setup and operations guide

A WordPress plugin with three connected parts: a customer portal (shortcodes + Elementor Free widgets), a management dashboard in `/wp-admin`, and server-side integrations with the documented **TVPlus Panel API** and **MegaOTT API v1** (choose one in API Settings).

## Portal look and behaviour

* **Full-page app (default):** portal pages render without the theme header/footer — a left sidebar (Dashboard, My Accounts, Apps & Guides, Plans & Billing, Transactions, Referrals & Gifts, Support), a top bar (theme toggle, notifications, account menu), promo cards for referrals and gifts, and a floating support button. On phones the sidebar becomes a drawer.
* **After sign-in or registration** (portal forms, `wp-login.php`, WooCommerce My Account) customers land on the portal dashboard. Signed-in customers opening the login page go to the dashboard; guests opening a portal page are sent to sign in and back.
* Switch to the older top-navigation layout, or turn app mode off to embed the portal inside your theme, in **Branding**. Brand name, logo, colors (accent, gold promo color, dark/light backgrounds), texts and navigation are all configurable. Use it only for streaming services you are authorized to distribute.

## Requirements

* WordPress 6.4+ (tested 7.1.5), PHP 7.4+ with `sodium` (tested 8.3), MySQL/MariaDB (tested MariaDB 10.11).
* WooCommerce 8+ for billing (tested 11.3.1, HPOS **and** legacy order storage). Without WooCommerce, billing modules are disabled; accounts, guides, referrals display and support keep working.
* Elementor Free optional (tested 4.4.1). Without it, everything works through shortcodes.

## Install

1. **Plugins → Add New → Upload Plugin** → `relay-stream-portal.zip` → Activate.
2. Recommended: add a dedicated encryption key to `wp-config.php` **before** saving any API key:
   ```php
   define( 'RSP_ENCRYPTION_KEY', 'base64-of-32-random-bytes' ); // php -r "echo base64_encode(random_bytes(32));"
   ```
   Without it, a key is derived from your WordPress salts. Changing the key later makes stored secrets unreadable.
3. **Relay Stream → Elementor & Pages**: create or map the portal pages (see `ELEMENTOR-WALKTHROUGH.md`).
4. **Relay Stream → Plans**: create plan products (or the demo plans 1/3/6/12 months — demo prices are placeholders) and review them.
5. Enable at least one WooCommerce payment gateway.
6. **Relay Stream → Branding**: name, logo, colors, layout, app mode, theme, navigation, language, toggles.
7. **Relay Stream → API Settings & Job Queue**: pick **TVPlus** or **MegaOTT**, save the key/token. Provisioning stays **Manual** until you complete the provider checklist in `API-MAPPING.md`.
8. Make sure WP-Cron runs (or call `wp rsp queue` every minute from a system cron).

## How fulfillment works

* Buying a plan from the portal posts to the server, which validates the plan, the purpose (new / renew / gift) and **ownership of the target account**, then adds it to the WooCommerce cart and redirects to checkout. Plan products cannot be added to the cart any other way.
* At order creation each plan line gets an **immutable snapshot** (plan, months, package, type, price, purpose, target account). Later plan edits never change past orders.
* Only WooCommerce’s confirmed-paid state (`payment_complete`, `processing`, `completed` with `$order->is_paid()`) creates jobs. Thank-you pages and gateway redirects never activate anything. Bitcoin/Lightning gateways work the same way — the gateway must mark the order paid.
* One job per order item (`UNIQUE` idempotency key + `GET_LOCK`), so duplicate payment webhooks are harmless. Payment status and provisioning status are shown separately to customers and staff.
* **Manual mode**: jobs wait in *Needs review*; staff do the work in the panel, then **Complete manually** with the credentials and confirmed expiry (audited).
* **Live mode**: jobs call the panel, verify with `device_info`, and reconcile timeouts (see `API-MAPPING.md`).

## Cancellations and refunds

| Event | Before activation | After activation |
|---|---|---|
| Order cancelled / failed | Open jobs cancelled; new local account cancelled | Flagged for review |
| Full refund | Open jobs cancelled; account cancelled | Account flagged for review (the API has no suspend). Staff may shorten/suspend in the panel and record it |
| Partial refund | Order flagged for review, nothing automatic | Same |
| Gift order refunded | Unredeemed code revoked | Redeemed code ⇒ review |
| Referral order refunded | During review window ⇒ reward cancelled | Per policy: *remove unused days* (default), *always review*, or *keep* |

Ambiguous cases (a create that may have happened, a job running during the refund) always go to staff review.

## Referrals and gifts

* Each customer gets a unique code (`R` + 9 chars) and link (`?rsp_ref=` cookie, or the code at registration/on Plans before the first purchase).
* Self-referrals are blocked (same user, same normalized email, or billing email equal to the referrer’s).
* After the friend’s first eligible confirmed payment, the reward (default 30 days) is credited once after the review period (default 7 days) to a **ledger**. Customers apply days to a chosen account; because the provider only renews whole months, this goes to staff review unless *Reward month conversion* is enabled.
* Gift plans are bought through WooCommerce. After payment a random 12-character, expiring, single-use code is issued (stored encrypted + keyed hash). Redemption is one atomic `UPDATE`; concurrent attempts cannot both succeed.

## Staff roles and capabilities

| Capability | Administrator | Support Agent | Fulfillment Staff | Billing Manager |
|---|---|---|---|---|
| `rsp_view_admin` | ✓ | ✓ | ✓ | ✓ |
| `rsp_manage_settings` (branding, pages, API settings, guides, notifications, audit) | ✓ | | | |
| `rsp_manage_billing` (plans, orders, referrals, gifts, ledger) | ✓ | | | ✓ |
| `rsp_manage_provisioning` (accounts, job queue, manual activation) | ✓ | | ✓ | |
| `rsp_manage_support` (tickets, announcements) | ✓ | ✓ | | |
| `rsp_view_credentials` (reveal player credentials, audited) | ✓ | | ✓ | |
| `rsp_export_data` (CSV exports, no secrets) | ✓ | | | ✓ |

Sensitive actions require a reason and are written to **Audit Log** with actor, time, target and reason; secrets are scrubbed.

## Security notes

* Nonces **and** capability/ownership checks on every form, admin action and REST route; REST responses are `no-store`.
* Prepared SQL throughout; output escaped in templates; safe redirects (`wp_validate_redirect`).
* Rate limits: login, registration, password reset, promo/referral codes, checkout, redemption, tickets, credential reveal.
* Provider: HTTPS only, host allowlist, no redirects, response size cap; the API key never appears in logs, audit, emails, REST or page output.
* Player credentials: encrypted at rest, masked, revealed only through an authenticated owner-only REST call (audited). Never emailed, never in QR codes.
* Attachments: type/size validated (content sniffed), stored with random `.bin` names in `uploads/rsp-private/` with deny rules, served only through an authenticated, nonce-protected download that checks ticket ownership. On Nginx, also deny `/wp-content/uploads/rsp-private/` in the server config, or define `RSP_PRIVATE_DIR` outside the web root.
* Portal pages send `no-cache`, `X-Robots-Tag: noindex` and set `DONOTCACHEPAGE`.

## Data retention

Deactivation only unschedules cron. Updates run versioned migrations (`rsp_db_version`). Data is removed only when the plugin is **deleted** and *Delete all plugin data* is enabled in Branding.

## WP-CLI

```
wp rsp status                 # mode + job counts
wp rsp queue [--limit=25]     # process pending jobs
wp rsp test-connection        # read-only reseller_info, redacted output
wp rsp setup-pages [--all] [--elementor]
```

## Translations

English source strings and a complete Arabic translation of the customer portal (`languages/relay-stream-portal-ar.*`, 384 strings, RTL layout via CSS logical properties). Portal language follows Branding → *Portal language*, else the customer’s profile language, else the site language. Admin screens follow WordPress’ own language and are currently English-only in Arabic installs except shared strings.
