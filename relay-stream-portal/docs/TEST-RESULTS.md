# Test results (v1.1.0)

Run on 2026-11-24 in a clean sandbox: **WordPress 7.1.5**, **WooCommerce 11.3.1** (HPOS), **Elementor 4.4.1 (Free)**, PHP 8.3.6, MariaDB 10.11, theme Twenty Twenty-Five, Chromium via Playwright.

## What was and was not verified

| Area | Verified against | Result |
|---|---|---|
| TVPlus adapter, fulfillment, reconciliation | **Fixture** fake panel (`tests/fixtures/class-fake-panel.php`) | Passed |
| MegaOTT adapter, fulfillment, create reconciliation by username, extend, suspend | **Fixture** fake API (`tests/fixtures/class-fake-megaott.php`) | Passed |
| Real TVPlus panel | **Not tested** — no key; the sandbox could not resolve `tvpluspanel.net` | **Not verified** |
| Real MegaOTT API | One read-only call only: `GET /api/v1/user` with an invalid token → `401 {"message":"Unauthenticated."}` (confirms base URL, Bearer auth, error shape). No line was created, extended or suspended | **Not verified beyond auth** |
| WooCommerce checkout | Real block checkout in Chromium with the offline test gateway; payment confirmed by staff | Passed |
| HPOS and legacy order storage | Integration + concurrency suites on both (legacy runs from v1.0.0; v1.1.0 runs on HPOS) | Passed |
| Elementor Free | Editor in Chromium (panel lists 18 widgets, synthetic preview), front-end render, style override | Passed |
| Full-page app, sign-in redirect, sidebar drawer, Arabic RTL, mobile | Chromium desktop + iPhone 13 profile; screenshots reviewed | Passed |

Live integration must be verified on your own panel with the checklist in `API-MAPPING.md` before enabling live mode.

## How to run

```
wp eval-file wp-content/plugins/relay-stream-portal/tests/run-tests.php        # integration (fixtures)
WP="wp --path=/path/to/wp" tests/concurrency/run.sh                           # multi-process races
BASE=http://your-test-site OUT=/tmp/shots node tests/e2e/portal.e2e.js        # browser (Playwright)
```
Run these on a disposable test site only (they create users and orders). Clear `_transient_rsp_rl_*` first (rate limits).

## Integration suite — 99 passed, 0 failed

| Group | Test | Result |
|---|---|---|
| adapter | reseller_info test connection succeeds and masks username | PASS |
| adapter | redacted log never contains the API key | PASS |
| adapter | bouquet list parsed (no status field in docs) | PASS |
| adapter | create parses user_id + username/password from returned URL | PASS |
| adapter | create sends documented params only | PASS |
| adapter | renew handles documented "messasge" key and expiry advances | PASS |
| adapter | durations outside 1/3/6/12 are refused locally | PASS |
| adapter | non-HTTPS endpoint refused before sending | PASS |
| adapter | host outside allowlist refused before sending | PASS |
| adapter | write timeout => ambiguous | PASS |
| adapter | connect failure => failed (not sent) | PASS |
| adapter | redirect on write => ambiguous, not followed | PASS |
| adapter | non-JSON on write => ambiguous | PASS |
| adapter | explicit status false => failed | PASS |
| adapter | URL secrets redacted in strings | PASS |
| crypto | round trip | PASS |
| crypto | tampered ciphertext rejected | PASS |
| crypto | mask keeps last 4 | PASS |
| auth | registration creates customer | PASS |
| auth | duplicate email rejected | PASS |
| auth | short password rejected | PASS |
| auth | wrong password rejected | PASS |
| auth | login by email works | PASS |
| auth | open redirect blocked | PASS |
| auth | same-site redirect allowed | PASS |
| auth | unverified user cannot log in; verification email sent | PASS |
| auth | verification token valid | PASS |
| auth | verified user can log in | PASS |
| billing | thank-you page on unpaid order creates no job | PASS |
| billing | immutable snapshot stored on the order item | PASS |
| billing | duplicate payment events create exactly one job | PASS |
| billing | manual mode: job waits in staff review, account pending activation | PASS |
| billing | payment and provisioning status separate | PASS |
| billing | manual completion activates account with confirmed expiry | PASS |
| billing | credentials stored encrypted | PASS |
| billing | manual override audited without secrets | PASS |
| billing | activation notification created | PASS |
| billing | no email contains player password | PASS |
| live | paid order -> create -> verified by lookup -> active | PASS |
| live | local expiry equals panel-confirmed expiry | PASS |
| live | panel note carries stable local id | PASS |
| live | replayed payment event does not create a second remote account | PASS |
| live | renewal applies to the selected account only | PASS |
| live | timeout after success is reconciled (succeeded, not replayed) | PASS |
| live | timeout without change -> needs review, not replayed automatically | PASS |
| live | staff retry after reconciliation succeeds | PASS |
| live | ambiguous create goes to staff review and is not replayed | PASS |
| live | retrying an ambiguous create requires explicit confirmation | PASS |
| live | renewal of an account not owned by the payer -> review, account untouched | PASS |
| live | checkout refuses to renew another customer's account | PASS |
| refunds | full refund before activation cancels the job and account | PASS |
| refunds | partial refund flags review without changing the account | PASS |
| gifts | paid gift issues exactly one encrypted code | PASS |
| gifts | non-owner cannot read code | PASS |
| referrals | self-referral rejected | PASS |
| referrals | friend binds once | PASS |
| referrals | no reward during review period | PASS |
| referrals | reward credited exactly once (30 days) across repeated runs and later orders | PASS |
| referrals | stats reflect real data | PASS |
| referrals | history hides invitee email | PASS |
| referrals | refund after reward reverses unused days (policy reverse_unapplied) | PASS |
| referrals | applying days without provider support -> staff review, days reserved | PASS |
| referrals | cancelled reward job returns the days | PASS |
| support | ticket with validated attachment stored privately | PASS |
| support | private dir has deny rules | PASS |
| support | PHP upload rejected | PASS |
| isolation | other customer cannot view ticket | PASS |
| isolation | other customer cannot download attachment | PASS |
| support | internal notes hidden from customer; staff reply sets awaiting customer | PASS |
| isolation | reply to someone else's ticket refused | PASS |
| isolation | get_owned refuses other customer | PASS |
| isolation | REST credential reveal for non-owner -> 404 | PASS |
| isolation | REST account list only returns own accounts | PASS |
| isolation | REST list never includes credentials | PASS |
| isolation | owner can reveal credentials (no-store) | PASS |
| isolation | anonymous REST -> 401 | PASS |
| isolation | customer order list scoped to customer | PASS |
| render | all shortcodes render for a customer without leaking credentials | PASS |
| render | protected module shows sign-in to guests | PASS |
| render | editor preview uses synthetic data only | PASS |
| elementor | all 18 widgets registered in the Relay Stream Portal category | PASS |
| elementor | Portal Shell widget renders (editor preview) | PASS |
| elementor | Elementor page JSON holds no customer data | PASS |
| i18n | Arabic portal renders right-to-left | PASS |
| megaott | GET /user connection test with Bearer token, username masked | PASS |
| megaott | token never appears in redacted logs | PASS |
| megaott | paginated /packages fully read with period metadata | PASS |
| megaott | 401 is a definitive failure | PASS |
| megaott | 422 on create (bad package) => failed, not ambiguous | PASS |
| megaott | unmapped package refused locally | PASS |
| megaott | paid order -> POST /subscriptions -> verified by GET -> active | PASS |
| megaott | username chosen by the portal; password, server and M3U stored encrypted | PASS |
| megaott | expiry and connection limit taken from the panel | PASS |
| megaott | create timed out after applying -> scheduled for reconciliation (not review, not resent) | PASS |
| megaott | reconciled via by-username: adopted the existing line, no duplicate create | PASS |
| megaott | create lost before applying -> by-username 404 -> safely re-created once | PASS |
| megaott | renewal uses /extend with the purchased plan package on the right subscription | PASS |
| megaott | extend timeout after success reconciled by expiry comparison | PASS |
| megaott | deactivate / activate supported | PASS |

## Concurrency (8 parallel PHP processes each)

| Test | Result |
|---|---|
| Same gift code redeemed by 8 processes at once → exactly 1 succeeds, 1 job, 1 account | PASS |
| 8 concurrent payment events for one order → 1 job, 1 account | PASS |
| 8 concurrent reward runs → one 30-day ledger credit | PASS |

## Browser end-to-end — 29 passed, 0 failed

| Test | Result |
|---|---|
| guest opening the portal is sent to the full-page sign-in | PASS |
| registration signs in and opens the portal (full-page app, no theme header) | PASS |
| plans page lists 4 plans | PASS |
| signed-in customer opening the login page goes to the dashboard | PASS |
| Select plan hands off to WooCommerce checkout | PASS |
| order placed with test gateway (thank-you page) | PASS |
| thank-you page did not activate: account shows awaiting payment | PASS |
| transactions show payment and activation status | PASS |
| device tabs switch panels | PASS |
| referral code shown | PASS |
| ticket created with attachment | PASS |
| owner can download own attachment | PASS |
| theme toggle persists across reloads | PASS |
| no JavaScript errors on portal pages | PASS |
| other customer cannot download the attachment (even with the URL) | PASS |
| other customer cannot open someone else's ticket thread | PASS |
| REST accounts for a new customer is empty | PASS |
| anonymous REST denied | PASS |
| login form works on mobile | PASS |
| mobile menu opens as a drawer | PASS |
| mobile portal: no horizontal scroll | PASS |
| mobile portal-accounts: no horizontal scroll | PASS |
| mobile portal-plans: no horizontal scroll | PASS |
| mobile portal-apps: no horizontal scroll | PASS |
| mobile portal-transactions: no horizontal scroll | PASS |
| mobile portal-referrals: no horizontal scroll | PASS |
| mobile portal-support: no horizontal scroll | PASS |
| Elementor page renders Portal Shell + Plan Cards widgets with live data | PASS |
| Elementor style control overrides branding color for that widget | PASS |

## Other checks

| Check | Result |
|---|---|
| All 15 admin screens + customer detail + filtered queue load without PHP notices | PASS |
| API settings: switch provider to MegaOTT, save, test connection (graceful errors) | PASS |
| Support Agent role: blocked from API settings, can open Tickets | PASS |
| Jobs CSV export contains no credentials | PASS |
| Elementor editor: 18 widgets listed; Portal Shell preview shows synthetic data only | PASS |
| Elementor deactivated: shortcode pages still render (v1.0.0 run) | PASS |
| WooCommerce deactivated: billing modules show a notice, others work (v1.0.0 run) | PASS |
| Upgrade 1.0.0 → 1.1.0 by installing the new ZIP over an active 1.0.0 on a second site: db v4→v5, new visual defaults applied, plans and all 10 tables kept, clean log | PASS |
| `debug.log` clean (WP_DEBUG on) after all runs | PASS |

## Bugs found and fixed during testing

* `tickets.status` was `varchar(16)`; `awaiting_customer` (17 chars) silently failed → widened in DB migration v4.
* Shortcode registration and role names triggered WordPress 6.7 “translations loaded too early” notices → deferred to `init`.
* Portal-language translations were not applied on WordPress 6.5+ (locale-keyed translation registry) → loaded under the request locale.
* WooCommerce blocked wp-admin for portal staff roles → allowed for `rsp_view_admin`.
* Orders filter used `meta_query`, unsupported on legacy order storage → mapped via the documented CPT query filter; HPOS uses `meta_query`.
* Pre-send DNS/URL validation errors were classified as ambiguous → now “not sent”.
* Block checkout sets the currency after line items → snapshot falls back to the store currency.
* v1.1.0: the test suite left a fake MegaOTT token in settings → teardown now clears provider keys.
* v1.1.0: upgrade routine ran at `plugins_loaded` and translated module names too early → runs on `init`.
