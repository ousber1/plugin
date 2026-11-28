<?php

declare(strict_types=1);

/*
 * v1.2 — from a wall-art shop to a complete print shop: personalised gifts (sublimation / DTF printing, textiles,
 * stationery) and CO2-laser products (engraving and cutting), next to decorative wall art.
 *
 *  - product families (product_type) and up to two option dimensions per product (e.g. Colour × Size);
 *  - personalisation fields filled in by the customer (text, photo, font, choice, date) with optional extra price;
 *  - quantity price breaks (corporate gifts), minimum / maximum quantity, proof (BAT) before production;
 *  - bill of materials per product / variant (blank mug, MDF sheet…) consumed by production;
 *  - production stages for laser engraving, heat press and assembly; machine work area (laser bed).
 */
return [
    'up' => [
        "ALTER TABLE products
            MODIFY product_type ENUM('canvas','framed','poster','photo_print','multi_panel','limited',
                                     'gift_print','textile','stationery','laser_engraving','laser_cut','gift_other') NOT NULL DEFAULT 'canvas',
            ADD COLUMN option1_name VARCHAR(60) NULL COMMENT 'e.g. Couleur' AFTER allow_custom_size,
            ADD COLUMN option2_name VARCHAR(60) NULL COMMENT 'e.g. Taille' AFTER option1_name,
            ADD COLUMN requires_proof TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'customer validates a proof before production' AFTER option2_name,
            ADD COLUMN min_qty INT UNSIGNED NOT NULL DEFAULT 1 AFTER requires_proof,
            ADD COLUMN max_qty INT UNSIGNED NOT NULL DEFAULT 50 AFTER min_qty,
            ADD COLUMN production_days TINYINT UNSIGNED NULL COMMENT 'lead time override' AFTER max_qty,
            ADD KEY idx_products_type (product_type, is_active)",
        "ALTER TABLE product_variants
            ADD COLUMN option1 VARCHAR(80) NULL AFTER name,
            ADD COLUMN option2 VARCHAR(80) NULL AFTER option1,
            ADD COLUMN color_hex CHAR(7) NULL AFTER option2",
        "CREATE TABLE IF NOT EXISTS product_personalization_fields (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS product_price_tiers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT UNSIGNED NOT NULL,
            min_qty INT UNSIGNED NOT NULL,
            discount_percent DECIMAL(5,2) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ppt (product_id, min_qty),
            CONSTRAINT fk_ppt_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS product_components (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE production_tasks
            MODIFY stage ENUM('receive','review_quality','approve_artwork','prepare_file','print','engrave','press','cut_finish',
                              'assemble','frame','quality_control','package','ship') NOT NULL",
        "ALTER TABLE machines
            ADD COLUMN work_width_mm SMALLINT UNSIGNED NULL COMMENT 'laser / printer bed' AFTER hourly_cost,
            ADD COLUMN work_height_mm SMALLINT UNSIGNED NULL AFTER work_width_mm,
            ADD COLUMN power_w SMALLINT UNSIGNED NULL AFTER work_height_mm",
        "ALTER TABLE materials
            MODIFY category ENUM('canvas','bache','vinyl','poster_paper','photo_paper','ink','frame','mdf','accessory','packaging','other',
                                 'blank','wood','acrylic','leather','sublimation','textile') NOT NULL",
        "ALTER TABLE order_customizations ADD COLUMN field_key VARCHAR(40) NULL COMMENT 'personalisation field of a gift' AFTER panel_index",
    ],
    'down' => [
        'ALTER TABLE order_customizations DROP COLUMN field_key',
        "ALTER TABLE materials MODIFY category ENUM('canvas','bache','vinyl','poster_paper','photo_paper','ink','frame','mdf','accessory','packaging','other') NOT NULL",
        'ALTER TABLE machines DROP COLUMN power_w, DROP COLUMN work_height_mm, DROP COLUMN work_width_mm',
        "ALTER TABLE production_tasks MODIFY stage ENUM('receive','review_quality','approve_artwork','prepare_file','print','cut_finish','frame','quality_control','package','ship') NOT NULL",
        'DROP TABLE IF EXISTS product_components',
        'DROP TABLE IF EXISTS product_price_tiers',
        'DROP TABLE IF EXISTS product_personalization_fields',
        'ALTER TABLE product_variants DROP COLUMN color_hex, DROP COLUMN option2, DROP COLUMN option1',
        "ALTER TABLE products DROP KEY idx_products_type, DROP COLUMN production_days, DROP COLUMN max_qty, DROP COLUMN min_qty, DROP COLUMN requires_proof,
            DROP COLUMN option2_name, DROP COLUMN option1_name,
            MODIFY product_type ENUM('canvas','framed','poster','photo_print','multi_panel','limited') NOT NULL DEFAULT 'canvas'",
    ],
];
