# TVPlus Panel API — mapping table

Source: <https://tvpluspanel.ru/api/panel_api.html> ("TVPLUS PANEL API", Ver 1.81, last update 13/08/2024), read on 2026-11-24.
The documentation URL (`tvpluspanel.ru`) is separate from the API endpoint it documents (`tvpluspanel.net`). Both are configurable in **Relay Stream → API Settings & Job Queue**.

> **Status:** mapped from the documentation and exercised against a **fixture** (`tests/fixtures/class-fake-panel.php`). It has **not** been verified against a real, authorized panel. Live mode stays off until an administrator ticks **Mapping verified**, saves an API key, and sets the mode to **Live**.

## Transport and authentication

| Item | Documented | Implementation |
|---|---|---|
| Base URL | `https://tvpluspanel.net/api/api.php` | Setting `api_base_url`; must be HTTPS, no credentials/query/fragment, host must be in **Allowed API hosts** |
| Method | `GET` for every operation | `wp_safe_remote_get()` with `redirection => 0`, `sslverify => true`, `reject_unsafe_urls => true`, 1 MB response cap |
| Auth | `api_key` query parameter ("Reseller API KEY from panel") | Key stored encrypted (libsodium secretbox); added to the URL at send time only. Request URLs are never logged or stored |
| Response format | JSON array wrapping one object; `status: "true"` on success | Parsed defensively; first object is used, `bouquet` is read as a list |
| Error responses | **Not documented** | Explicit non-`"true"` status ⇒ `failed`. Timeout, 5xx, redirect, non-JSON or missing status on a **write** ⇒ `ambiguous` (reconcile, never blind-retry). Connection/DNS failure before sending ⇒ `failed` (safe) |
| Rate limits / idempotency | **Not documented** | Local idempotency keys per order item, per-account locks, reconciliation (below) |

## Operations

| Portal capability | HTTP | Action / params (besides `api_key`) | Success response (documented fields) | Identifiers stored | Used for |
|---|---|---|---|---|---|
| Test connection (read-only) | GET | `action=reseller_info` | `status`, `username`, `enabled`, `credits` | — (username shown masked) | **Test connection** button, `wp rsp test-connection` |
| List packages (read-only) | GET | `action=bouquet` | list of `{id, name}` (no status field) | `rsp_packages_cache` option (`id ⇒ name`) | Product “Provider package” field |
| Create M3U line | GET | `action=new&type=m3u&sub=1\|3\|6\|12&pack=<id>\|all&note=<text>` (`sub=99` = demo ticket, not used) | `status`, `user_id`, `message`, `url` (M3U `get.php?username=…&password=…`) | `provider_user_id` = `user_id`; username, password, server, url parsed from `url` and stored **encrypted**; `note` = `rsp-<local account uuid>` | New subscriptions, gift redemptions for a new account |
| Create MAG device | GET | `action=new&type=mag&mac=<MAC>&sub=…&pack=…&note=…` | `status`, `mac`, `user_id`, `message`, `url` (portal) | `provider_user_id`, `mac` (encrypted) | Products with type “MAG” (MAC required) |
| Create protocol code | GET | `action=new&type=protocol&sub=…&pack=…` | `status`, `user_id`, `message`, `code` | — | **Not used** (no matching product type) |
| Renew M3U | GET | `action=renew&type=m3u&username=…&password=…&sub=1\|3\|6\|12` | `status`, `messasge` (sic) | — | Paid renewals, gift extensions, staff renewals, reward month conversion |
| Renew MAG | GET | `action=renew&type=mag&mac=…&sub=…` | `status`, `messasge` (sic) | — | Same, for MAG accounts |
| Device info (lookup) | GET | `action=device_info&username=…&password=…` or `&mac=…` | `status`, `username`, `password`, `expire` (YYYY-MM-DD), `country`, `user_id`, `note`, `url`, `enabled` | `expires_at` = `expire 23:59:59` | Verify after create, before/after renew, reconciliation, **Refresh status from panel** |

## Capabilities exposed in the dashboard

| Capability | Supported? | Behaviour |
|---|---|---|
| Account creation | Yes | Job type `create`, then `device_info` verification |
| Renewal | Yes (1/3/6/12 months) | Job type `renew` with before/after expiry comparison |
| Status lookup / expiry | Yes | `device_info` |
| Packages / bouquets | Yes | `pack` on create; list via `bouquet` |
| Device / connection limits | **No** (not documented) | Recorded manually per account (`max_connections`) |
| Suspend / enable / disable / delete | **No** (not documented) | Staff record a manual status change; refunds after activation go to review |
| Extend by arbitrary days (referral rewards) | **No** | Reward days stay in the ledger and go to staff review; optional “month conversion” applies 30 days as a confirmed 1-month renewal |
| Idempotency / lookup by note | **No** | An ambiguous create goes to staff review; retry requires explicit confirmation that no line was created |

## Fulfillment state machine

```
paid order item ──► job(create|renew) pending ──claim (atomic UPDATE)──► working
create: start ─► step=send (persisted) ─► new ─► success ─► store creds ─► verify (device_info) ─► succeeded
                                              └► ambiguous ─► needs_review (no automatic replay)
                                              └► failed ─► retry with backoff (max 5) ─► needs_review
renew:  start ─► device_info (pre_expiry) ─► step=send ─► renew ─► success ─► verify: expire > pre ─► succeeded
                                                              └► ambiguous ─► reconcile: device_info
                                                                     expire > pre ─► succeeded (reconciled)
                                                                     unchanged    ─► needs_review (staff may retry)
worker crash while working: create@send ─► needs_review ; renew@send ─► reconcile
```

Guarantees: `UNIQUE(idempotency_key)` (`item:<order_item_id>`, `gift:<id>`, `reward:<uuid>`), MySQL `GET_LOCK` per order item during job creation, per-account lock column during execution, payment status kept separate from provisioning status.

## Before enabling live mode — verification checklist

1. Save the API key, keep mode **Manual**, click **Test connection** — expect `outcome: success` and a credits value.
2. Click **Load packages** and map each plan product to a package.
3. On a panel test line: create one 1-month line via a test order, confirm the panel shows the note `rsp-<uuid>`, and that the portal expiry matches the panel.
4. Renew that line via a test renewal order; confirm the expiry moved by one month in both places.
5. Confirm the panel’s error body for an invalid key (the docs do not specify it) is a JSON object with a non-`"true"` `status`. If not, report it before going live.
6. Tick **Mapping verified**, set **Live**, save.

---

# MegaOTT API (v1) — mapping table

Source: <https://megaott.net/docs/> (pages: quickstart, authentication, errors, subscriptions, packages, templates, reports, user), read on 2026-11-24.
Select it in **API Settings & Job Queue → Provider panel → MegaOTT API (v1)**.

> **Status:** mapped from the documentation and exercised against a **fixture** (`tests/fixtures/class-fake-megaott.php`). Against the real server, only one call was observed: `GET /api/v1/user` with an invalid token returned `401 {"message":"Unauthenticated."}`, which confirms the base URL, Bearer auth and the documented error shape. Creating, extending and suspending lines on a real account has **not** been tested.

## Transport and authentication

| Item | Documented | Implementation |
|---|---|---|
| Base URL | `https://megaott.net/api/v1` | Setting `megaott_base_url`; HTTPS + host allowlist (`megaott.net`), no redirects |
| Auth | `Authorization: Bearer {token}`; send `Accept: application/json` | Token stored encrypted; header only (never in URLs, logs or audit) |
| Token abilities needed | `user_read`, `package_read`, `template_read`, `subscription_read`, `subscription_create`, `subscription_extend`, `subscription_activate`, `subscription_deactivate` | Listed in the settings screen |
| Errors | `{"message": …}` (+ `errors` on 422); 401 / 403 / 404 / 409 / 422 per endpoint | 400/401/403/404/409/422 ⇒ `failed` (not applied). 5xx, timeouts, non-JSON on writes ⇒ `ambiguous` ⇒ reconcile |
| Pagination | `page`, `per_page` (≤100) on packages/templates | All package pages are read (up to 10 × 100) |
| Idempotency / rate limits / webhooks | **Not documented** | Username chosen by the portal + `by-username` reconciliation; per-account locks |

## Operations

| Portal capability | HTTP | Path / params | Success fields used | Stored |
|---|---|---|---|---|
| Test connection (read-only) | GET | `/user` | `id`, `username` (shown masked), `credit` | — |
| List packages | GET | `/packages?page&per_page=100` | `id`, `name`, `period`, `period_type`, `max_connections`, `is_trial` | `rsp_packages_cache`, `rsp_packages_meta` |
| Bouquet templates | GET | `/templates?package_id` | `id`, `name` | Per-plan “Bouquet template ID” field |
| Create line | POST (form) | `/subscriptions` — `type=m3u\|mag`, `username` (generated by the portal and saved **before** sending), `mac_address` (MAG), `package_id`, `max_connections`, `forced_country`, `adult`, `whatsapp_telegram`, `enable_vpn`, `paid=1`, `template_id?`, `note=rsp-<uuid>` | `id`, `username`, `password`, `expiring_at`, `max_connections`, `dns_link`, `portal_link` | `provider_user_id`=`id`; username/password/server(`dns_link`)/M3U URL **encrypted**; `max_connections` |
| Reconcile a lost create | GET | `/subscriptions/by-username?username=` | 200 ⇒ adopt the existing line; 404 ⇒ not created, safe to send again; other ⇒ staff review | — |
| Lookup / verify | GET | `/subscriptions/{id}` | `expiring_at` (`YYYY-MM-DD HH:MM:SS GMT+0000`) | `expires_at` |
| Renew | POST (form) | `/subscriptions/{id}/extend` — `package_id` (from the order snapshot), `paid=1` | `new_expiration_date`; then verified with GET | — |
| Suspend / resume | POST | `/subscriptions/{id}/deactivate`, `/activate` | `status: true` | Account status `suspended` / `active` (admin buttons, audited) |
| Renew (`/renew`), delete, change connections, list lines | — | Not documented with a body or not documented at all ⇒ **not used** | | |

## MegaOTT-specific behaviour

* Plans map to a **package ID** (“Provider package” on the product). The package defines the period; keep the product’s months equal to it.
* Optional per-plan **template ID** and **connections**; defaults (country, adult, VPN, contact shown on expiry, connections) are in API Settings.
* Reward days: with *Reward month conversion* and a **Reward package ID** (1-month package), 30 days are applied as an `/extend`; otherwise they go to staff review.
* Refunds after activation are flagged for review; staff can **Suspend in panel** from the account screen.

## Before enabling live mode with MegaOTT

1. Create a token with the abilities above; paste it, keep **Manual** mode, click **Test connection** (expect your credit balance).
2. **Load packages**, set each plan product’s package ID (and template ID if you use templates).
3. Place a test order for the cheapest package; confirm in the panel that the line has the `rsp-<uuid>` note and that the portal expiry and credentials match.
4. Renew it with a test renewal order; confirm the panel expiry moved.
5. Tick **Mapping verified**, set **Live**, save.
