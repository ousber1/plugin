<?php

declare(strict_types=1);

/*
 * v1.3 — shop by recipient ("for Mum", "for him"…) and by occasion (birthday, wedding, Eid…): tags attached to
 * products, used by the menus, the landing pages and the shop filters.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS product_tags (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS product_tag_map (
            product_id INT UNSIGNED NOT NULL,
            tag_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (product_id, tag_id),
            KEY idx_ptm_tag (tag_id),
            CONSTRAINT fk_ptm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
            CONSTRAINT fk_ptm_tag FOREIGN KEY (tag_id) REFERENCES product_tags (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS product_tag_map',
        'DROP TABLE IF EXISTS product_tags',
    ],
];
