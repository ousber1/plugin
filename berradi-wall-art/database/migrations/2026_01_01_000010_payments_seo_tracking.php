<?php

declare(strict_types=1);

/*
 * v1.1: payment method limits and test mode, SEO tools (per-page meta, redirects, 404 monitor)
 * and the server-side conversion queue (Meta Conversions API).
 */
return [
    'up' => [
        "ALTER TABLE payment_methods ADD COLUMN min_order_amount DECIMAL(12,2) NULL COMMENT 'offered only from this order total' AFTER fee_value,
                                     ADD COLUMN max_order_amount DECIMAL(12,2) NULL COMMENT 'offered only up to this order total' AFTER min_order_amount,
                                     ADD COLUMN test_mode TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'online providers: test gateway' AFTER max_order_amount",
        "CREATE TABLE IF NOT EXISTS seo_pages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_key VARCHAR(60) NOT NULL COMMENT 'canonical path of the page, e.g. /shop',
            locale CHAR(2) NOT NULL,
            meta_title VARCHAR(191) NULL,
            meta_description VARCHAR(320) NULL,
            noindex TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_seo_pages (page_key, locale)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS seo_redirects (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS seo_not_found (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tracking_events (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS tracking_events',
        'DROP TABLE IF EXISTS seo_not_found',
        'DROP TABLE IF EXISTS seo_redirects',
        'DROP TABLE IF EXISTS seo_pages',
        'ALTER TABLE payment_methods DROP COLUMN test_mode, DROP COLUMN max_order_amount, DROP COLUMN min_order_amount',
    ],
];
