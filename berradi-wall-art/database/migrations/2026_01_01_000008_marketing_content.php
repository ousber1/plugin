<?php

/*
 * Marketing & storefront content: reviews, newsletter, banners, installation gallery, FAQ, campaigns.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS reviews (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS banners (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS gallery_items (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS faqs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS campaigns (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS campaign_products (
            campaign_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (campaign_id, product_id),
            KEY idx_cp_product (product_id),
            CONSTRAINT fk_cp_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE,
            CONSTRAINT fk_cp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        'DROP TABLE IF EXISTS campaign_products',
        'DROP TABLE IF EXISTS campaigns',
        'DROP TABLE IF EXISTS faqs',
        'DROP TABLE IF EXISTS gallery_items',
        'DROP TABLE IF EXISTS banners',
        'DROP TABLE IF EXISTS newsletter_subscribers',
        'DROP TABLE IF EXISTS reviews',
    ],
];
