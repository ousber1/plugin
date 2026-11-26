# Database schema

MySQL ≥ 5.7 / MariaDB ≥ 10.3 · InnoDB · utf8mb4_unicode_ci · 63 tables (incl. `migrations`) · 109 foreign keys.
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

## Order statuses (`orders.status`)
`new`, `awaiting_confirmation`, `confirmed`, `awaiting_artwork_approval`, `approved_for_production`,
`printing`, `framing`, `quality_control`, `ready_for_shipping`, `shipped`, `delivered`, `cancelled`, `returned`.
Every change is written to `order_status_history`.

## Production stages (`production_tasks.stage`)
`receive` → `review_quality` → `approve_artwork` → `prepare_file` → `print` → `cut_finish` → `frame` →
`quality_control` → `package` → `ship`.

## Seed data (idempotent)
7 roles + 40 permissions, 65 default settings, 5 payment methods (online ones inactive), 4 Moroccan
shipping zones with city rates, 13 categories (FR + AR), 5 print substrates, 6 frames, 17 builder options.
Prices are editable starting defaults. No fake orders, customers or statistics are ever seeded.
