<?php

/*
 * Customer photo uploads (private personal data) and saved custom designs.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS uploaded_images (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS custom_designs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS custom_design_images (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS custom_design_images',
        'DROP TABLE IF EXISTS custom_designs',
        'DROP TABLE IF EXISTS uploaded_images',
    ],
];
