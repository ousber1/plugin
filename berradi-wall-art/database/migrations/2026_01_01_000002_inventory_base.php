<?php

/*
 * Inventory foundations: suppliers, machines, raw materials (needed by catalogue & production).
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS suppliers (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS machines (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS materials (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS materials',
        'DROP TABLE IF EXISTS machines',
        'DROP TABLE IF EXISTS suppliers',
    ],
];
