# Elementor Free walkthrough

Relay Stream Portal works with **Elementor Free** (tested with Elementor 4.4.1) or with no page builder at all. Elementor Pro and any specific theme are not required.

## 1. Create the pages

**Relay Stream → Elementor & Pages → Step 1**

* *One portal page* creates Sign In, Create Account, Reset Password, Portal and Redeem a Gift. The portal page switches modules with `?rsp_view=accounts` etc.
* *A separate page for every module* creates all 12 pages (Login, Register, Forgot/Reset Password, Dashboard, My Accounts, Apps & Guides, Plans & Billing, Transactions, Referrals & Gifts, Gift Redemption, Support, Profile).
* Tick **Open new pages with Elementor** to build them with widgets instead of shortcodes.
* Or map existing pages in the table (useful when you already designed pages).

WP-CLI equivalent: `wp rsp setup-pages --all [--elementor]`.

## 2. Build with widgets

1. Open a page with **Edit with Elementor**.
2. In the widget panel search **Relay** (category *Relay Stream Portal*).
3. Full portal: drag **Portal Shell** into a full-width container and choose its *Default module*.
   Module-only: drop **Portal: Plans & Billing**, **Plan Cards**, **Subscription Summary**, etc. into your own layout.
4. Content tab → titles, button labels and menu labels. Layout tab → *Stretch to full browser width*, *Content max width*, *Color theme*.
5. Style tab → colors (accent, background, cards, text, borders), typography (body and headings), radius, spacing, padding.
6. The editor shows **sample data** (“Sample Customer”, “Living room”…). Publish, then open the page while signed in as a customer to see live data.

## How style overrides work

1. **Branding** settings (WordPress admin) define CSS variables on every `.rsp-root` (`--rsp-accent`, `--rsp-accent-2`, `--rsp-radius`, dark/light `--rsp-bg` and `--rsp-surface`).
2. Each widget Style control re-defines those variables **only inside that widget** (`{{WRAPPER}} .rsp-root`). Empty controls inherit the Branding value.
3. Choosing a fixed *Color theme* on a widget disables the customer theme toggle for that widget.
4. All portal CSS is scoped under `.rsp-root`; the theme and other Elementor sections are not affected. The “Full-width layout on portal pages” Branding option only applies on pages assigned in Elementor & Pages.

## What is (not) stored in Elementor

Widgets render on the server at request time. Elementor’s `_elementor_data` stores only widget settings (labels, colors, layout). Plans, prices, accounts, orders, credentials and API settings always come from WordPress/WooCommerce at runtime, so nothing customer-specific is saved in templates or exported kits. Portal pages send `no-cache` headers and define `DONOTCACHEPAGE`.

## Without Elementor

Every widget has a shortcode. They work in Elementor’s free **Shortcode** widget, in the block editor (Shortcode block) and in the classic editor. If Elementor is deactivated, pages built with shortcodes keep working; pages whose content exists only as Elementor data need a shortcode added.

## Page → widget / shortcode reference

| Page | Elementor widget | Shortcode |
|---|---|---|
| Full portal (any module) | Portal Shell | `[relay_portal module="dashboard"]` |
| Dashboard | Portal: Overview | `[relay_dashboard]` |
| My Accounts | Portal: My Accounts | `[relay_accounts]` |
| Apps & Guides | Portal: Apps & Guides | `[relay_apps]` |
| Plans & Billing | Portal: Plans & Billing | `[relay_plans]` |
| Transactions | Portal: Transactions | `[relay_transactions]` |
| Referrals & Gifts | Portal: Referrals & Gifts | `[relay_referrals]` / `[relay_gifts]` |
| Gift Redemption | Portal: Gift Redemption | `[relay_gift_redeem]` |
| Support | Portal: Support | `[relay_support]` |
| Profile | Portal: Profile | `[relay_profile]` |
| Login | Portal: Login | `[relay_login]` |
| Register | Portal: Register | `[relay_register]` |
| Forgot / Reset password | Portal: Forgot / Reset Password | `[relay_password_reset]` |
| — reusable — | Plan Cards | `[relay_plan_cards]` |
| — reusable — | Subscription Summary | `[relay_subscription_summary]` |
| — reusable — | Announcements | `[relay_announcements]` |
| — reusable — | Device Guides | `[relay_device_guides device="firetv"]` |
| — reusable — | Support Button | `[relay_support_button label="Get help"]` |

Common shortcode attributes: `title`, `label`, `class`, `tab` (`gifts`), `device`, `module` (Portal Shell only).
