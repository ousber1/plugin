-- BERRADI WALL ART — full schema generated from database/migrations on 2026-11-28 13:48
-- Optional: import with phpMyAdmin, then run the web installer anyway: it detects the
-- migrations already applied and only inserts the default data (roles, settings...).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration VARCHAR(191) NOT NULL,
  batch INT UNSIGNED NOT NULL,
  ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2026_01_01_000001_core_auth
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    password_changed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(191) NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NULL,
    is_guest TINYINT(1) NOT NULL DEFAULT 0,
    company_name VARCHAR(150) NULL,
    ice VARCHAR(30) NULL COMMENT 'Moroccan company identifier (B2B invoices)',
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    KEY idx_customers_phone (phone),
    KEY idx_customers_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    label VARCHAR(60) NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    country CHAR(2) NOT NULL DEFAULT 'MA',
    city VARCHAR(100) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) NULL,
    postal_code VARCHAR(20) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ca_customer (customer_id),
    CONSTRAINT fk_ca_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guard ENUM('user','customer') NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pr_token (token_hash),
    KEY idx_pr_account (guard, account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    throttle_key VARCHAR(191) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_la_key_time (throttle_key, created_at),
    KEY idx_la_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value MEDIUMTEXT NULL,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    value_type ENUM('string','text','int','decimal','bool','json','encrypted','file') NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key),
    KEY idx_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(50) NOT NULL COMMENT 'product, category, collection, banner, faq, ui...',
    entity_id INT UNSIGNED NOT NULL DEFAULT 0,
    locale CHAR(2) NOT NULL,
    field VARCHAR(60) NOT NULL,
    value MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_translation (entity_type, entity_id, locale, field),
    KEY idx_translation_lookup (entity_type, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type ENUM('user','customer','system') NOT NULL DEFAULT 'system',
    actor_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    meta JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_al_actor (actor_type, actor_id),
    KEY idx_al_entity (entity_type, entity_id),
    KEY idx_al_action (action),
    KEY idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel ENUM('email','whatsapp','internal') NOT NULL DEFAULT 'email',
    event VARCHAR(60) NOT NULL,
    recipient VARCHAR(191) NOT NULL,
    subject VARCHAR(255) NULL,
    body MEDIUMTEXT NULL,
    related_type VARCHAR(50) NULL,
    related_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL COMMENT 'internal notifications for staff',
    status ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_status (status, scheduled_at),
    KEY idx_notif_related (related_type, related_id),
    KEY idx_notif_user (user_id, status),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000001_core_auth', 1);

-- 2026_01_01_000002_inventory_base
CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) NULL,
    email VARCHAR(191) NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    ice VARCHAR(30) NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS machines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    machine_type VARCHAR(60) NOT NULL COMMENT 'printer, cutter, laminator, framing...',
    status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    hourly_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_machines_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(60) NOT NULL,
    name VARCHAR(150) NOT NULL,
    category ENUM('canvas','bache','vinyl','poster_paper','photo_paper','ink','frame','mdf','accessory','packaging','other') NOT NULL,
    unit ENUM('m','m2','ml','l','kg','piece','roll','sheet','box') NOT NULL DEFAULT 'piece',
    stock_quantity DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    min_stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'weighted average cost',
    supplier_id INT UNSIGNED NULL,
    location VARCHAR(100) NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_materials_sku (sku),
    KEY idx_materials_category (category),
    KEY idx_materials_supplier (supplier_id),
    CONSTRAINT fk_materials_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000002_inventory_base', 1);

-- 2026_01_01_000003_catalog
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    slug VARCHAR(191) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    seo_title VARCHAR(191) NULL,
    seo_description VARCHAR(320) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id),
    KEY idx_categories_sort (is_active, sort_order),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(191) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_limited TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    seo_title VARCHAR(191) NULL,
    seo_description VARCHAR(320) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_collections_slug (slug),
    KEY idx_collections_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_materials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    material_type ENUM('canvas','poster','photo_paper','bache','vinyl','other') NOT NULL,
    price_per_m2 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    minimum_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost_per_m2 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inventory_material_id INT UNSIGNED NULL COMMENT 'stock item consumed when produced',
    consumption_factor DECIMAL(6,3) NOT NULL DEFAULT 1.000 COMMENT 'm2 consumed per m2 printed (bleed/waste)',
    min_width_cm DECIMAL(7,1) NOT NULL DEFAULT 10.0,
    max_width_cm DECIMAL(7,1) NOT NULL DEFAULT 300.0,
    min_height_cm DECIMAL(7,1) NOT NULL DEFAULT 10.0,
    max_height_cm DECIMAL(7,1) NOT NULL DEFAULT 300.0,
    supports_frame TINYINT(1) NOT NULL DEFAULT 1,
    image VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pm_code (code),
    KEY idx_pm_inventory (inventory_material_id),
    CONSTRAINT fk_pm_inventory FOREIGN KEY (inventory_material_id) REFERENCES materials (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS frames (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    color_name VARCHAR(60) NOT NULL,
    color_hex CHAR(7) NOT NULL DEFAULT '#171717',
    profile_width_mm SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    frame_type ENUM('wood','aluminium','floating','box','canvas_stretcher') NOT NULL DEFAULT 'wood',
    price_per_meter DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'charged on perimeter',
    fixed_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost_per_meter DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inventory_material_id INT UNSIGNED NULL,
    image VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_frames_code (code),
    KEY idx_frames_inventory (inventory_material_id),
    CONSTRAINT fk_frames_inventory FOREIGN KEY (inventory_material_id) REFERENCES materials (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customization_options (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    option_group ENUM('size','border','panel_layout','finish','orientation','extra') NOT NULL,
    code VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    width_cm DECIMAL(7,1) NULL,
    height_cm DECIMAL(7,1) NULL,
    panels TINYINT UNSIGNED NULL,
    price_type ENUM('fixed','percent','per_m2') NOT NULL DEFAULT 'fixed',
    price_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    meta JSON NULL COMMENT 'layout geometry, gaps, ratios...',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_co_group_code (option_group, code),
    KEY idx_co_group (option_group, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NULL,
    collection_id INT UNSIGNED NULL,
    product_type ENUM('canvas','framed','poster','photo_print','multi_panel','limited') NOT NULL DEFAULT 'canvas',
    title VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    sku VARCHAR(60) NOT NULL,
    short_description VARCHAR(500) NULL,
    description MEDIUMTEXT NULL,
    main_image VARCHAR(255) NULL,
    base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'catalogue reference price',
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'current list price',
    promo_price DECIMAL(12,2) NULL,
    promo_starts_at DATETIME NULL,
    promo_ends_at DATETIME NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    track_stock TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = made to order',
    stock_quantity INT NOT NULL DEFAULT 0,
    is_customizable TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'accepts customer photos',
    min_panels TINYINT UNSIGNED NOT NULL DEFAULT 1,
    max_panels TINYINT UNSIGNED NOT NULL DEFAULT 1,
    allow_custom_size TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
    is_new TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    seo_title VARCHAR(191) NULL,
    seo_description VARCHAR(320) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    KEY idx_products_collection (collection_id),
    KEY idx_products_listing (is_active, deleted_at, sort_order),
    KEY idx_products_flags (is_featured, is_best_seller, is_new),
    KEY idx_products_price (selling_price),
    FULLTEXT KEY ft_products_search (title, short_description),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_products_collection FOREIGN KEY (collection_id) REFERENCES collections (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    thumb_path VARCHAR(255) NULL,
    alt VARCHAR(191) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pi_product (product_id, sort_order),
    CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    sku VARCHAR(60) NOT NULL,
    name VARCHAR(150) NOT NULL,
    width_cm DECIMAL(7,1) NOT NULL,
    height_cm DECIMAL(7,1) NOT NULL,
    product_material_id INT UNSIGNED NULL,
    frame_id INT UNSIGNED NULL,
    panels TINYINT UNSIGNED NOT NULL DEFAULT 1,
    price DECIMAL(12,2) NOT NULL,
    promo_price DECIMAL(12,2) NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pv_sku (sku),
    KEY idx_pv_product (product_id, is_active, sort_order),
    KEY idx_pv_material (product_material_id),
    KEY idx_pv_frame (frame_id),
    CONSTRAINT fk_pv_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pv_material FOREIGN KEY (product_material_id) REFERENCES product_materials (id) ON DELETE SET NULL,
    CONSTRAINT fk_pv_frame FOREIGN KEY (frame_id) REFERENCES frames (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_material_map (
    product_id INT UNSIGNED NOT NULL,
    product_material_id INT UNSIGNED NOT NULL,
    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (product_id, product_material_id),
    KEY idx_pmm_material (product_material_id),
    CONSTRAINT fk_pmm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pmm_material FOREIGN KEY (product_material_id) REFERENCES product_materials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_frame_map (
    product_id INT UNSIGNED NOT NULL,
    frame_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, frame_id),
    KEY idx_pfm_frame (frame_id),
    CONSTRAINT fk_pfm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pfm_frame FOREIGN KEY (frame_id) REFERENCES frames (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_option_map (
    product_id INT UNSIGNED NOT NULL,
    customization_option_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, customization_option_id),
    KEY idx_pom_option (customization_option_id),
    CONSTRAINT fk_pom_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pom_option FOREIGN KEY (customization_option_id) REFERENCES customization_options (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS related_products (
    product_id INT UNSIGNED NOT NULL,
    related_product_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id, related_product_id),
    KEY idx_rel_related (related_product_id),
    CONSTRAINT fk_rel_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_rel_related FOREIGN KEY (related_product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000003_catalog', 1);

-- 2026_01_01_000004_customization
CREATE TABLE IF NOT EXISTS uploaded_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    customer_id INT UNSIGNED NULL,
    owner_token CHAR(64) NULL COMMENT 'hashed guest/session ownership token',
    original_name VARCHAR(191) NOT NULL,
    stored_path VARCHAR(255) NOT NULL COMMENT 'relative to storage/private_uploads',
    preview_path VARCHAR(255) NULL COMMENT 'relative to storage/previews',
    thumb_path VARCHAR(255) NULL,
    mime_type VARCHAR(50) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    width_px INT UNSIGNED NOT NULL,
    height_px INT UNSIGNED NOT NULL,
    checksum CHAR(64) NOT NULL,
    status ENUM('temporary','attached','ordered','purged') NOT NULL DEFAULT 'temporary',
    retention_until DATETIME NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ui_uuid (uuid),
    KEY idx_ui_customer (customer_id),
    KEY idx_ui_owner (owner_token),
    KEY idx_ui_retention (status, retention_until),
    CONSTRAINT fk_ui_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_designs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    customer_id INT UNSIGNED NULL,
    owner_token CHAR(64) NULL,
    product_id INT UNSIGNED NULL,
    product_material_id INT UNSIGNED NULL,
    frame_id INT UNSIGNED NULL,
    width_cm DECIMAL(7,1) NOT NULL,
    height_cm DECIMAL(7,1) NOT NULL,
    orientation ENUM('portrait','landscape','square') NOT NULL DEFAULT 'portrait',
    panels TINYINT UNSIGNED NOT NULL DEFAULT 1,
    border_option VARCHAR(60) NULL,
    config JSON NULL COMMENT 'full builder state (options, layout, gaps)',
    preview_path VARCHAR(255) NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'server-calculated',
    quality_status ENUM('ok','warning','low') NOT NULL DEFAULT 'ok',
    status ENUM('draft','in_cart','ordered','abandoned') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cd_uuid (uuid),
    KEY idx_cd_customer (customer_id),
    KEY idx_cd_owner (owner_token),
    KEY idx_cd_status (status, updated_at),
    CONSTRAINT fk_cd_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_cd_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_cd_material FOREIGN KEY (product_material_id) REFERENCES product_materials (id) ON DELETE SET NULL,
    CONSTRAINT fk_cd_frame FOREIGN KEY (frame_id) REFERENCES frames (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_design_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    custom_design_id INT UNSIGNED NOT NULL,
    uploaded_image_id INT UNSIGNED NOT NULL,
    panel_index TINYINT UNSIGNED NOT NULL DEFAULT 0,
    crop JSON NULL COMMENT '{x,y,width,height,rotate,zoom} in source pixels',
    effective_dpi SMALLINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cdi_panel (custom_design_id, panel_index),
    KEY idx_cdi_image (uploaded_image_id),
    CONSTRAINT fk_cdi_design FOREIGN KEY (custom_design_id) REFERENCES custom_designs (id) ON DELETE CASCADE,
    CONSTRAINT fk_cdi_image FOREIGN KEY (uploaded_image_id) REFERENCES uploaded_images (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000004_customization', 1);

-- 2026_01_01_000005_sales
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    instructions TEXT NULL COMMENT 'shown to customer (bank details, etc.)',
    is_online TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'requires provider confirmation',
    provider VARCHAR(40) NULL,
    config TEXT NULL COMMENT 'encrypted JSON provider credentials',
    fee_type ENUM('none','fixed','percent') NOT NULL DEFAULT 'none',
    fee_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paym_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_zones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    country CHAR(2) NOT NULL DEFAULT 'MA',
    description VARCHAR(255) NULL,
    default_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    free_shipping_threshold DECIMAL(12,2) NULL,
    min_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
    max_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_rates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipping_zone_id INT UNSIGNED NOT NULL,
    city VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    free_shipping_threshold DECIMAL(12,2) NULL COMMENT 'overrides zone threshold',
    min_days TINYINT UNSIGNED NULL,
    max_days TINYINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_zone_city (shipping_zone_id, city),
    KEY idx_sr_city (city),
    CONSTRAINT fk_sr_zone FOREIGN KEY (shipping_zone_id) REFERENCES shipping_zones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    discount_type ENUM('percent','fixed','free_shipping') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_order_amount DECIMAL(12,2) NULL,
    max_discount_amount DECIMAL(12,2) NULL,
    product_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    usage_limit INT UNSIGNED NULL,
    usage_limit_per_customer INT UNSIGNED NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupons_code (code),
    KEY idx_coupons_active (is_active, starts_at, ends_at),
    CONSTRAINT fk_coupons_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_coupons_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token CHAR(64) NOT NULL,
    customer_id INT UNSIGNED NULL,
    coupon_id INT UNSIGNED NULL,
    email VARCHAR(191) NULL COMMENT 'captured at checkout for abandoned cart follow-up',
    phone VARCHAR(30) NULL,
    status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',
    abandoned_notified_at DATETIME NULL,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_token (token),
    KEY idx_carts_customer (customer_id),
    KEY idx_carts_status (status, last_activity_at),
    CONSTRAINT fk_carts_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_carts_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_variant_id INT UNSIGNED NULL,
    custom_design_id INT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL COMMENT 'server-calculated snapshot',
    options JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ci_cart (cart_id),
    KEY idx_ci_product (product_id),
    CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_design FOREIGN KEY (custom_design_id) REFERENCES custom_designs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'new',
    payment_status ENUM('unpaid','partial','paid','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',
    payment_method_id INT UNSIGNED NULL,
    source ENUM('storefront','admin','quotation','phone','whatsapp') NOT NULL DEFAULT 'storefront',
    quotation_id INT UNSIGNED NULL,
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    currency CHAR(3) NOT NULL DEFAULT 'MAD',
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(191) NULL,
    customer_phone VARCHAR(30) NOT NULL,
    shipping_country CHAR(2) NOT NULL DEFAULT 'MA',
    shipping_city VARCHAR(100) NOT NULL,
    shipping_address VARCHAR(255) NOT NULL,
    shipping_postal_code VARCHAR(20) NULL,
    delivery_instructions VARCHAR(500) NULL,
    shipping_zone_id INT UNSIGNED NULL,
    shipping_rate_id INT UNSIGNED NULL,
    estimated_delivery_min DATE NULL,
    estimated_delivery_max DATE NULL,
    coupon_id INT UNSIGNED NULL,
    coupon_code VARCHAR(50) NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'confirmed payments minus refunds',
    balance_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost_total DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'estimated production cost snapshot',
    customer_notes VARCHAR(1000) NULL,
    internal_notes TEXT NULL,
    access_token CHAR(64) NOT NULL COMMENT 'hashed token for guest tracking links',
    ip VARCHAR(45) NULL,
    placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    shipped_at DATETIME NULL,
    delivered_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (order_number),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_status (status, placed_at),
    KEY idx_orders_payment (payment_status),
    KEY idx_orders_placed (placed_at),
    KEY idx_orders_phone (customer_phone),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_paymethod FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_zone FOREIGN KEY (shipping_zone_id) REFERENCES shipping_zones (id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_rate FOREIGN KEY (shipping_rate_id) REFERENCES shipping_rates (id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_variant_id INT UNSIGNED NULL,
    custom_design_id INT UNSIGNED NULL,
    sku VARCHAR(60) NULL,
    title VARCHAR(191) NOT NULL,
    product_type VARCHAR(30) NOT NULL,
    width_cm DECIMAL(7,1) NULL,
    height_cm DECIMAL(7,1) NULL,
    material_name VARCHAR(120) NULL,
    frame_name VARCHAR(120) NULL,
    panels TINYINT UNSIGNED NOT NULL DEFAULT 1,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(12,2) NOT NULL,
    options JSON NULL,
    preview_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_oi_order (order_id),
    KEY idx_oi_product (product_id),
    CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_oi_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE SET NULL,
    CONSTRAINT fk_oi_design FOREIGN KEY (custom_design_id) REFERENCES custom_designs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_customizations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_item_id INT UNSIGNED NOT NULL,
    uploaded_image_id INT UNSIGNED NULL,
    panel_index TINYINT UNSIGNED NOT NULL DEFAULT 0,
    crop JSON NULL,
    orientation ENUM('portrait','landscape','square') NULL,
    effective_dpi SMALLINT UNSIGNED NULL,
    final_preview_path VARCHAR(255) NULL,
    print_file_path VARCHAR(255) NULL COMMENT 'prepared print-ready file (private)',
    approval_status ENUM('pending','sent','approved','changes_requested') NOT NULL DEFAULT 'pending',
    approval_token CHAR(64) NULL,
    approved_at DATETIME NULL,
    approved_by_user_id INT UNSIGNED NULL,
    customer_feedback TEXT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_oc_item (order_item_id),
    KEY idx_oc_image (uploaded_image_id),
    KEY idx_oc_approval (approval_status),
    CONSTRAINT fk_oc_item FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_oc_image FOREIGN KEY (uploaded_image_id) REFERENCES uploaded_images (id) ON DELETE SET NULL,
    CONSTRAINT fk_oc_user FOREIGN KEY (approved_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    comment VARCHAR(1000) NULL,
    customer_notified TINYINT(1) NOT NULL DEFAULT 0,
    changed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_osh_order (order_id, created_at),
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_osh_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    discount_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cr_order (coupon_id, order_id),
    KEY idx_cr_customer (coupon_id, customer_id),
    CONSTRAINT fk_cr_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    courier VARCHAR(100) NOT NULL,
    tracking_number VARCHAR(100) NULL,
    tracking_url VARCHAR(255) NULL,
    status ENUM('pending','picked_up','in_transit','out_for_delivery','delivered','returned','failed') NOT NULL DEFAULT 'pending',
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'actual courier cost paid by the company',
    cod_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'cash to collect on delivery',
    packages TINYINT UNSIGNED NOT NULL DEFAULT 1,
    shipped_at DATETIME NULL,
    delivered_at DATETIME NULL,
    notes VARCHAR(1000) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shipments_order (order_id),
    KEY idx_shipments_tracking (tracking_number),
    KEY idx_shipments_status (status),
    CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_shipments_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000005_sales', 1);

-- 2026_01_01_000006_documents_payments
CREATE TABLE IF NOT EXISTS document_sequences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sequence_type VARCHAR(30) NOT NULL COMMENT 'order, invoice, credit_note, quotation, payment, purchase, production',
    period_year SMALLINT UNSIGNED NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seq_type_year (sequence_type, period_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quotation_number VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NULL,
    status ENUM('draft','sent','accepted','rejected','expired','converted','cancelled') NOT NULL DEFAULT 'draft',
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    currency CHAR(3) NOT NULL DEFAULT 'MAD',
    customer_name VARCHAR(150) NOT NULL,
    customer_company VARCHAR(150) NULL,
    customer_ice VARCHAR(30) NULL,
    customer_email VARCHAR(191) NULL,
    customer_phone VARCHAR(30) NULL,
    customer_address VARCHAR(500) NULL,
    issue_date DATE NOT NULL,
    valid_until DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'HT before discount',
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'TTC',
    terms TEXT NULL,
    notes TEXT NULL,
    sent_at DATETIME NULL,
    accepted_at DATETIME NULL,
    rejected_at DATETIME NULL,
    converted_order_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quotations_number (quotation_number),
    KEY idx_quotations_customer (customer_id),
    KEY idx_quotations_status (status, issue_date),
    CONSTRAINT fk_quotations_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_quotations_order FOREIGN KEY (converted_order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_quotations_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quotation_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    description VARCHAR(500) NOT NULL,
    width_cm DECIMAL(7,1) NULL,
    height_cm DECIMAL(7,1) NULL,
    material_name VARCHAR(120) NULL,
    frame_name VARCHAR(120) NULL,
    panels TINYINT UNSIGNED NOT NULL DEFAULT 1,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL COMMENT 'HT',
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    line_total_ht DECIMAL(12,2) NOT NULL,
    line_tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total_ttc DECIMAL(12,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_qi_quotation (quotation_id, sort_order),
    CONSTRAINT fk_qi_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE CASCADE,
    CONSTRAINT fk_qi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders ADD CONSTRAINT fk_orders_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number VARCHAR(30) NULL COMMENT 'assigned only when issued (no gaps)',
    invoice_type ENUM('invoice','credit_note') NOT NULL DEFAULT 'invoice',
    original_invoice_id INT UNSIGNED NULL COMMENT 'invoice corrected by this credit note',
    order_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    status ENUM('draft','issued','partially_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    currency CHAR(3) NOT NULL DEFAULT 'MAD',
    issue_date DATE NULL,
    due_date DATE NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_company VARCHAR(150) NULL,
    customer_ice VARCHAR(30) NULL,
    customer_email VARCHAR(191) NULL,
    customer_phone VARCHAR(30) NULL,
    customer_address VARCHAR(500) NULL,
    company_snapshot JSON NULL COMMENT 'company legal info frozen at issue time',
    subtotal_ht DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_ht DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_ttc DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_terms VARCHAR(500) NULL,
    notes TEXT NULL,
    integrity_hash CHAR(64) NULL COMMENT 'sha256 of issued content, detects tampering',
    pdf_path VARCHAR(255) NULL,
    issued_at DATETIME NULL,
    issued_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancelled_by INT UNSIGNED NULL,
    cancel_reason VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_number (invoice_number),
    KEY idx_invoices_order (order_id),
    KEY idx_invoices_customer (customer_id),
    KEY idx_invoices_status (status, issue_date),
    KEY idx_invoices_original (original_invoice_id),
    CONSTRAINT fk_invoices_original FOREIGN KEY (original_invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_issued_by FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id INT UNSIGNED NOT NULL,
    order_item_id INT UNSIGNED NULL,
    description VARCHAR(500) NOT NULL,
    specifications VARCHAR(1000) NULL COMMENT 'dimensions, material, frame...',
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price_ht DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    total_ht DECIMAL(12,2) NOT NULL,
    total_tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_ttc DECIMAL(12,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_ii_invoice (invoice_id, sort_order),
    CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ii_order_item FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_number VARCHAR(30) NOT NULL,
    payment_type ENUM('payment','refund') NOT NULL DEFAULT 'payment',
    order_id INT UNSIGNED NULL,
    invoice_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    payment_method_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL COMMENT 'always positive; direction given by payment_type',
    currency CHAR(3) NOT NULL DEFAULT 'MAD',
    status ENUM('pending','confirmed','failed','cancelled') NOT NULL DEFAULT 'pending',
    reference VARCHAR(120) NULL COMMENT 'bank transfer ref, cheque number, courier COD slip',
    provider_transaction_id VARCHAR(191) NULL,
    provider_payload JSON NULL,
    paid_at DATETIME NULL,
    confirmed_by INT UNSIGNED NULL,
    notes VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_number (payment_number),
    UNIQUE KEY uq_payments_provider_tx (provider_transaction_id),
    KEY idx_payments_order (order_id),
    KEY idx_payments_invoice (invoice_id),
    KEY idx_payments_customer (customer_id),
    KEY idx_payments_status_date (status, paid_at),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_user FOREIGN KEY (confirmed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000006_documents_payments', 1);

-- 2026_01_01_000007_production_finance
CREATE TABLE IF NOT EXISTS production_jobs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_number VARCHAR(30) NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    order_item_id INT UNSIGNED NULL,
    status ENUM('pending','in_progress','on_hold','completed','cancelled') NOT NULL DEFAULT 'pending',
    current_stage VARCHAR(40) NOT NULL DEFAULT 'receive',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    assigned_user_id INT UNSIGNED NULL,
    machine_id INT UNSIGNED NULL,
    deadline_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    material_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    labor_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    machine_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    other_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pj_number (job_number),
    KEY idx_pj_order (order_id),
    KEY idx_pj_status (status, deadline_at),
    KEY idx_pj_assigned (assigned_user_id),
    CONSTRAINT fk_pj_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_pj_item FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE SET NULL,
    CONSTRAINT fk_pj_user FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_pj_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL,
    CONSTRAINT fk_pj_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_job_id INT UNSIGNED NOT NULL,
    stage ENUM('receive','review_quality','approve_artwork','prepare_file','print','cut_finish','frame','quality_control','package','ship') NOT NULL,
    status ENUM('pending','in_progress','done','skipped') NOT NULL DEFAULT 'pending',
    assigned_user_id INT UNSIGNED NULL,
    machine_id INT UNSIGNED NULL,
    due_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    duration_minutes INT UNSIGNED NULL,
    notes VARCHAR(1000) NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pt_job_stage (production_job_id, stage),
    KEY idx_pt_assigned (assigned_user_id, status),
    CONSTRAINT fk_pt_job FOREIGN KEY (production_job_id) REFERENCES production_jobs (id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_user FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_pt_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchases (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_number VARCHAR(30) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    supplier_invoice_ref VARCHAR(100) NULL,
    status ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
    purchase_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    received_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_purchases_number (purchase_number),
    KEY idx_purchases_supplier (supplier_id),
    KEY idx_purchases_date (purchase_date),
    CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchases_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_id INT UNSIGNED NOT NULL,
    material_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    tax_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_pui_purchase (purchase_id),
    KEY idx_pui_material (material_id),
    CONSTRAINT fk_pui_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE CASCADE,
    CONSTRAINT fk_pui_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    purchase_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'bank_transfer',
    reference VARCHAR(120) NULL,
    paid_at DATE NOT NULL,
    notes VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sp_supplier (supplier_id),
    KEY idx_sp_purchase (purchase_id),
    CONSTRAINT fk_sp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sp_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE SET NULL,
    CONSTRAINT fk_sp_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    material_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in','out','adjustment','production','waste','return') NOT NULL,
    quantity DECIMAL(14,3) NOT NULL COMMENT 'signed: + entry, - exit',
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_after DECIMAL(14,3) NOT NULL,
    purchase_id INT UNSIGNED NULL,
    production_job_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sm_material (material_id, created_at),
    KEY idx_sm_type (movement_type, created_at),
    KEY idx_sm_job (production_job_id),
    KEY idx_sm_purchase (purchase_id),
    CONSTRAINT fk_sm_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sm_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE SET NULL,
    CONSTRAINT fk_sm_job FOREIGN KEY (production_job_id) REFERENCES production_jobs (id) ON DELETE SET NULL,
    CONSTRAINT fk_sm_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category ENUM('rent','salaries','utilities','marketing','shipping','equipment','maintenance','software','taxes_fees','bank_fees','transport','other') NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL COMMENT 'TTC paid',
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'recoverable TVA included in amount',
    expense_date DATE NOT NULL,
    payment_method VARCHAR(40) NULL,
    supplier_id INT UNSIGNED NULL,
    reference VARCHAR(120) NULL,
    attachment_path VARCHAR(255) NULL COMMENT 'private receipt scan',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expenses_date (expense_date),
    KEY idx_expenses_category (category, expense_date),
    CONSTRAINT fk_expenses_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000007_production_finance', 1);

-- 2026_01_01_000008_marketing_content
CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    author_name VARCHAR(120) NOT NULL,
    author_city VARCHAR(100) NULL,
    rating TINYINT UNSIGNED NOT NULL,
    title VARCHAR(191) NULL,
    body TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reviews_product (product_id, is_approved),
    KEY idx_reviews_featured (is_featured, is_approved),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(191) NOT NULL,
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
    unsubscribe_token CHAR(64) NOT NULL,
    source VARCHAR(50) NULL,
    ip VARCHAR(45) NULL,
    subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ns_email (email),
    UNIQUE KEY uq_ns_token (unsubscribe_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banners (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    position ENUM('hero','promo','category','popup') NOT NULL DEFAULT 'hero',
    title VARCHAR(191) NOT NULL,
    subtitle VARCHAR(500) NULL,
    image VARCHAR(255) NULL,
    mobile_image VARCHAR(255) NULL,
    button_text VARCHAR(80) NULL,
    link_url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_banners_position (position, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(191) NULL,
    city VARCHAR(100) NULL,
    image VARCHAR(255) NOT NULL,
    product_id INT UNSIGNED NULL,
    customer_consent TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'customer agreed to publication',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gallery_active (is_active, sort_order),
    CONSTRAINT fk_gallery_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(60) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_faqs_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    campaign_type ENUM('discount','collection','newsletter','banner') NOT NULL DEFAULT 'discount',
    description TEXT NULL,
    coupon_id INT UNSIGNED NULL,
    collection_id INT UNSIGNED NULL,
    banner_id INT UNSIGNED NULL,
    discount_type ENUM('percent','fixed') NULL,
    discount_value DECIMAL(10,2) NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_campaigns_active (is_active, starts_at, ends_at),
    CONSTRAINT fk_campaigns_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL,
    CONSTRAINT fk_campaigns_collection FOREIGN KEY (collection_id) REFERENCES collections (id) ON DELETE SET NULL,
    CONSTRAINT fk_campaigns_banner FOREIGN KEY (banner_id) REFERENCES banners (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_products (
    campaign_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (campaign_id, product_id),
    KEY idx_cp_product (product_id),
    CONSTRAINT fk_cp_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000008_marketing_content', 1);

-- 2026_01_01_000009_document_access
ALTER TABLE quotations ADD COLUMN access_token CHAR(64) NULL COMMENT 'sha256 of the link sent to the customer' AFTER notes,
                        ADD COLUMN response_ip VARCHAR(45) NULL AFTER rejected_at,
                        ADD KEY idx_quotations_validity (status, valid_until);

ALTER TABLE invoices ADD COLUMN access_token CHAR(64) NULL COMMENT 'sha256 of the download link sent to the customer' AFTER pdf_path,
                      ADD COLUMN sent_at DATETIME NULL AFTER issued_by,
                      ADD KEY idx_invoices_order_type (order_id, invoice_type, status);

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000009_document_access', 1);

-- 2026_01_01_000010_payments_seo_tracking
ALTER TABLE payment_methods ADD COLUMN min_order_amount DECIMAL(12,2) NULL COMMENT 'offered only from this order total' AFTER fee_value,
                             ADD COLUMN max_order_amount DECIMAL(12,2) NULL COMMENT 'offered only up to this order total' AFTER min_order_amount,
                             ADD COLUMN test_mode TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'online providers: test gateway' AFTER max_order_amount;

CREATE TABLE IF NOT EXISTS seo_pages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_key VARCHAR(60) NOT NULL COMMENT 'canonical path of the page, e.g. /shop',
    locale CHAR(2) NOT NULL,
    meta_title VARCHAR(191) NULL,
    meta_description VARCHAR(320) NULL,
    noindex TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seo_pages (page_key, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_redirects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_path VARCHAR(500) NOT NULL,
    source_hash CHAR(40) NOT NULL COMMENT 'sha1 of the normalized path',
    target_url VARCHAR(500) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    note VARCHAR(191) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seo_redirects_source (source_hash),
    CONSTRAINT fk_seo_redirects_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_not_found (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    path VARCHAR(500) NOT NULL,
    path_hash CHAR(40) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    last_referrer VARCHAR(500) NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seo_not_found_path (path_hash),
    KEY idx_seo_not_found_hits (hits)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracking_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(20) NOT NULL,
    event_name VARCHAR(40) NOT NULL,
    event_id VARCHAR(64) NOT NULL COMMENT 'same id as the browser pixel event (deduplication)',
    order_id INT UNSIGNED NULL,
    payload MEDIUMTEXT NOT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tracking_event (provider, event_name, event_id),
    KEY idx_tracking_status (status, created_at),
    CONSTRAINT fk_tracking_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000010_payments_seo_tracking', 1);

-- 2026_01_01_000011_print_shop_products
ALTER TABLE products
    MODIFY product_type ENUM('canvas','framed','poster','photo_print','multi_panel','limited',
                             'gift_print','textile','stationery','laser_engraving','laser_cut','gift_other') NOT NULL DEFAULT 'canvas',
    ADD COLUMN option1_name VARCHAR(60) NULL COMMENT 'e.g. Couleur' AFTER allow_custom_size,
    ADD COLUMN option2_name VARCHAR(60) NULL COMMENT 'e.g. Taille' AFTER option1_name,
    ADD COLUMN requires_proof TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'customer validates a proof before production' AFTER option2_name,
    ADD COLUMN min_qty INT UNSIGNED NOT NULL DEFAULT 1 AFTER requires_proof,
    ADD COLUMN max_qty INT UNSIGNED NOT NULL DEFAULT 50 AFTER min_qty,
    ADD COLUMN production_days TINYINT UNSIGNED NULL COMMENT 'lead time override' AFTER max_qty,
    ADD KEY idx_products_type (product_type, is_active);

ALTER TABLE product_variants
    ADD COLUMN option1 VARCHAR(80) NULL AFTER name,
    ADD COLUMN option2 VARCHAR(80) NULL AFTER option1,
    ADD COLUMN color_hex CHAR(7) NULL AFTER option2;

CREATE TABLE IF NOT EXISTS product_personalization_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(40) NOT NULL,
    field_type ENUM('text','textarea','photo','font','select','date') NOT NULL DEFAULT 'text',
    label VARCHAR(120) NOT NULL,
    help VARCHAR(255) NULL,
    placeholder VARCHAR(120) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    max_length SMALLINT UNSIGNED NULL,
    choices JSON NULL COMMENT 'select / font values',
    price_add DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    print_width_cm DECIMAL(6,1) NULL COMMENT 'photo: printed size, for the resolution check',
    print_height_cm DECIMAL(6,1) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ppf_key (product_id, field_key),
    CONSTRAINT fk_ppf_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_price_tiers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    min_qty INT UNSIGNED NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ppt (product_id, min_qty),
    CONSTRAINT fk_ppt_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    product_variant_id INT UNSIGNED NULL COMMENT 'NULL = every variant',
    material_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL COMMENT 'per unit produced, in the material unit',
    PRIMARY KEY (id),
    KEY idx_pc_product (product_id),
    CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE production_tasks
    MODIFY stage ENUM('receive','review_quality','approve_artwork','prepare_file','print','engrave','press','cut_finish',
                      'assemble','frame','quality_control','package','ship') NOT NULL;

ALTER TABLE machines
    ADD COLUMN work_width_mm SMALLINT UNSIGNED NULL COMMENT 'laser / printer bed' AFTER hourly_cost,
    ADD COLUMN work_height_mm SMALLINT UNSIGNED NULL AFTER work_width_mm,
    ADD COLUMN power_w SMALLINT UNSIGNED NULL AFTER work_height_mm;

ALTER TABLE materials
    MODIFY category ENUM('canvas','bache','vinyl','poster_paper','photo_paper','ink','frame','mdf','accessory','packaging','other',
                         'blank','wood','acrylic','leather','sublimation','textile') NOT NULL;

ALTER TABLE order_customizations ADD COLUMN field_key VARCHAR(40) NULL COMMENT 'personalisation field of a gift' AFTER panel_index;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000011_print_shop_products', 1);

-- 2026_01_01_000012_gift_tags
CREATE TABLE IF NOT EXISTS product_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tag_type ENUM('recipient','occasion') NOT NULL,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(40) NULL COMMENT 'Bootstrap icon name',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_tags_slug (tag_type, slug),
    KEY idx_product_tags_type (tag_type, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_tag_map (
    product_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, tag_id),
    KEY idx_ptm_tag (tag_id),
    CONSTRAINT fk_ptm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_ptm_tag FOREIGN KEY (tag_id) REFERENCES product_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migrations (migration, batch) VALUES ('2026_01_01_000012_gift_tags', 1);

SET FOREIGN_KEY_CHECKS = 1;
