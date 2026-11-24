# Test results

Run on 2026-11-24 in a clean sandbox: **WordPress 7.1.5**, **WooCommerce 11.3.1**, **Elementor 4.4.1 (Free)**, PHP 8.3.6, MariaDB 10.11, theme Twenty Twenty-Five, Chromium via Playwright.

## What was and was not verified

| Area | Verified against | Result |
|---|---|---|
| TVPlus adapter, live fulfillment, reconciliation | **Fixture** fake panel built from the documented response shapes (`tests/fixtures/class-fake-panel.php`) | Passed |
| Real TVPlus panel | **Not tested.** No API key was available and the sandbox cannot resolve `tvpluspanel.net`. The *Test connection* button was exercised and failed safely before sending (“A valid URL was not provided” — DNS) | **Not verified** |
| WooCommerce checkout | Real block checkout in Chromium with the offline “cheque” test gateway; payment confirmed by setting the order to Processing | Passed |
| HPOS and legacy order storage | Full integration + concurrency suites run on both | Passed on both |
| Elementor Free | Editor loaded in Chromium; widget panel; editor preview; front-end render; style override | Passed |
| Arabic / RTL, mobile | Chromium (iPhone 13 profile), screenshots reviewed | Passed |

Live integration must still be verified on your panel with the checklist in `API-MAPPING.md` before enabling live mode.

## How to run

```
wp eval-file wp-content/plugins/relay-stream-portal/tests/run-tests.php        # integration (fixture panel)
WP="wp --path=/path/to/wp" tests/concurrency/run.sh                           # multi-process races
BASE=http://your-test-site OUT=/tmp/shots node tests/e2e/portal.e2e.js        # browser (Playwright)
```
Run these on a disposable test site only (they create users and orders).

## Integration suite — 84 passed, 0 failed (also re-run with HPOS enabled: 84/84)

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

## Concurrency (8 parallel PHP processes each, legacy storage and HPOS)

| Test | Result |
|---|---|
| Same gift code redeemed by 8 processes at once → exactly 1 succeeds, 1 job, 1 account | PASS |
| 8 concurrent payment events for one order → 1 job, 1 account | PASS |
| 8 concurrent reward runs → one 30-day ledger credit | PASS |

## Browser end-to-end — 27 passed, 0 failed

| Test | Result |
|---|---|
| guest sees sign-in form on portal | PASS |
| registration signs in and opens the portal | PASS |
| plans page lists 4 plans | PASS |
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
| All 15 admin screens + customer detail + filtered queue load as administrator without PHP notices | PASS |
| Support Agent role: blocked from API settings, can open Tickets | PASS |
| Jobs CSV export contains no credentials | PASS |
| Elementor editor: 18 widgets listed under “Relay Stream Portal”; Portal Shell preview shows synthetic data only | PASS |
| Elementor deactivated: shortcode pages still render | PASS |
| WooCommerce deactivated: billing modules show a notice, other modules work, no errors | PASS |
| Paying a browser-placed order (staff marks Processing) creates exactly one job | PASS |
| Plugin activated on a clean install; versioned migration v3→v4 altered a column in place | PASS |
| `debug.log` clean (WP_DEBUG on) after all runs | PASS |
| Built `relay-stream-portal.zip` installed via `wp plugin install` on a second, fresh WordPress database; activated, 10 tables, pages created, demo plans; data kept after deactivation | PASS |

## Bugs found and fixed during testing

* `tickets.status` was `varchar(16)`; `awaiting_customer` (17 chars) silently failed → widened in DB migration v4.
* Shortcode registration and role names triggered WordPress 6.7 “translations loaded too early” notices → deferred to `init`.
* Portal-language translations were not applied on WordPress 6.5+ (locale-keyed translation registry) → loaded under the request locale.
* WooCommerce blocked wp-admin for portal staff roles → allowed for `rsp_view_admin`.
* Orders filter used `meta_query`, unsupported on legacy order storage → mapped via the documented CPT query filter; HPOS uses `meta_query`.
* Pre-send DNS/URL validation errors were classified as ambiguous → now “not sent”.
* Block checkout sets the currency after line items → snapshot falls back to the store currency.
