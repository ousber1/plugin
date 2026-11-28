# Database schema

MySQL ≥ 5.7 / MariaDB ≥ 10.3 · InnoDB · utf8mb4_unicode_ci · 70 tables (incl. `migrations`) · 116 foreign keys.
Source of truth: `database/migrations/*.php`. A single importable file is generated with
`php bin/console schema:dump` → `database/schema.sql`.

Money: `DECIMAL(12,2)` (unit costs `DECIMAL(12,4)`, quantities `DECIMAL(14,3)`, tax rates `DECIMAL(6,4)`).

| Migration | Tables |
|---|---|
| 000001 core_auth | `roles`, `permissions`, `role_permissions`, `users` (staff), `customers` (incl. guests), `customer_addresses`, `password_resets` (hashed tokens), `login_attempts` (rate limiting), `settings`, `translations`, `activity_logs`, `notifications` (queue for cron) |
| 000002 inventory_base | `suppliers`, `machines`, `materials` (canvas rolls, bâche, vinyl, papers, ink, frames, MDF, accessories, packaging) |
| 000003 catalog | `categories` (tree, ordering), `collections`, `product_materials` (print substrates with price/cost per m², linked stock item & consumption factor), `frames` (price per metre of perimeter), `customization_options` (sizes, borders, panel layouts, finishes), `products`, `product_images`, `product_variants`, `product_material_map`, `product_frame_map`, `product_option_map`, `related_products` |
| 000004 customization | `uploaded_images` (private photos, retention), `custom_designs` (builder state, server price, DPI quality), `custom_design_images` (photo per panel + crop) |
| 000005 sales | `payment_methods`, `shipping_zones`, `shipping_rates` (per city), `coupons`, `carts`, `cart_items`, `orders`, `order_items` (snapshots), `order_customizations` (crop, print file, artwork approval), `order_status_history`, `coupon_redemptions`, `shipments` |
| 000006 documents_payments | `document_sequences`, `quotations`, `quotation_items`, `invoices` (+ credit notes), `invoice_items`, `payments` (payments & refunds, provider tx id unique) |
| 000007 production_finance | `production_jobs`, `production_tasks` (10 workflow stages), `purchases`, `purchase_items`, `supplier_payments`, `stock_movements`, `expenses` |
| 000008 marketing_content | `reviews`, `newsletter_subscribers`, `banners`, `gallery_items`, `faqs`, `campaigns`, `campaign_products` |
| 000009 document_access | secret-link hashes on `quotations` / `invoices`, invoice `sent_at` |
| 000011 print_shop_products (v1.2) | `products.product_type` + gift / laser families, `option1_name`, `option2_name`, `requires_proof`, `min_qty`, `max_qty`, `production_days`; `product_variants.option1/option2/color_hex`; `product_personalization_fields` (text, message, photo, font, choice, date — extra price, printed area), `product_price_tiers` (quantity breaks), `product_components` (bill of materials per product / variant); production stages `engrave`, `press`, `assemble`; `machines.work_width_mm/work_height_mm/power_w`; stock categories `blank`, `wood`, `acrylic`, `leather`, `sublimation`, `textile`; `order_customizations.field_key` |
| 000010 payments_seo_tracking (v1.1) | `payment_methods` + `min_order_amount`, `max_order_amount`, `test_mode`; `seo_pages` (title / description / noindex per page and language), `seo_redirects` (301/302 with hit counter), `seo_not_found` (404 monitor), `tracking_events` (Meta Conversions API queue, unique per event id) |

## Order statuses (`orders.status`)
`new`, `awaiting_confirmation`, `confirmed`, `awaiting_artwork_approval`, `approved_for_production`,
`printing`, `framing`, `quality_control`, `ready_for_shipping`, `shipped`, `delivered`, `cancelled`, `returned`.
Every change is written to `order_status_history`.

## Production stages (`production_tasks.stage`)
`receive` → `review_quality` → `approve_artwork` → `prepare_file` → `print` → `cut_finish` → `frame` →
`quality_control` → `package` → `ship`.

## Seed data (idempotent)
7 roles + 40 permissions, default settings (company, taxes, shipping, mail, SEO, tracking — all trackers empty), 5 payment methods with Arabic texts (online ones inactive), gift & laser categories, the Laser CO2 4040 machine (40 × 40 cm bed) and common blanks / laser sheets at zero stock, 4 Moroccan
shipping zones with city rates, 13 categories (FR + AR), 5 print substrates, 6 frames, 17 builder options.
Prices are editable starting defaults. No fake orders, customers or statistics are ever seeded.
