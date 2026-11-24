=== Relay Stream Portal ===
Contributors: relaystreamportal
Tags: woocommerce, elementor, subscriptions, customer portal, iptv
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Customer portal, management dashboard and TVPlus / MegaOTT API integrations for authorized streaming subscriptions.

== Description ==

* Customer portal: dashboard, multiple streaming accounts, plans & billing (WooCommerce), transactions, apps & guides with QR codes, referrals with a reward ledger, gift codes, support tickets with private attachments, notifications.
* Admin: Overview, Customers, Streaming Accounts, Plans, Orders & Transactions, API Settings & Job Queue, Referrals, Gifts, Apps & Guides, Tickets, Announcements, Notifications, Elementor & Pages, Branding, Audit Log.
* Provisioning queue with idempotency, per-account locks, timeout reconciliation and manual fulfillment.
* Elementor Free widgets (18) and equivalent shortcodes. Dark/light themes, English + Arabic (RTL).

Use only for streaming services you are authorized to distribute.

Documentation: docs/README.md, docs/API-MAPPING.md, docs/ELEMENTOR-WALKTHROUGH.md, docs/TEST-RESULTS.md.

== Installation ==

1. Upload the ZIP in Plugins → Add New → Upload Plugin and activate.
2. Optional but recommended: define RSP_ENCRYPTION_KEY in wp-config.php.
3. Relay Stream → Elementor & Pages → Create missing pages.
4. Relay Stream → Plans → create plan products.
5. Keep provisioning in Manual mode until the API mapping is verified against your panel.

== Third-party code ==

assets/vendor/qrcode-generator.js — qrcode-generator 1.4.4, (c) Kazuhiko Arase, MIT License.

== Changelog ==

= 1.1.0 =
* Full-page app layout with left sidebar, redesigned dashboard, plans, accounts, apps and referrals screens.
* Customers land on the portal after sign-in/registration.
* MegaOTT API v1 provider (create, extend, lookup, reconcile by username, suspend/resume).
* 81 more Arabic strings.

= 1.0.0 =
* Initial release.
