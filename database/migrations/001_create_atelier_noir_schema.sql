-- =============================================================================
-- ATELIER NOIR — Luxury Art Auction Institution
-- Complete Normalized MySQL 8+ / MariaDB / SQLite Compatible Schema Migration
-- Note: By default, ATELIER NOIR runs out-of-the-box using its transactional
-- flock-secured JSON Storage Engine (app/Core/JsonDatabase.php) with zero
-- external database daemon required. Use this migration if deploying to MySQL 8+.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `platform_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `brand_name` VARCHAR(120) NOT NULL DEFAULT 'ATELIER NOIR',
    `tagline` VARCHAR(255) NOT NULL DEFAULT 'Art Worth Discovering. Stories Worth Collecting.',
    `commission_rate_percent` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    `buyer_premium_percent` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    `anti_sniping_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `anti_sniping_window_seconds` INT UNSIGNED NOT NULL DEFAULT 300,
    `anti_sniping_extension_seconds` INT UNSIGNED NOT NULL DEFAULT 300,
    `default_currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `require_email_verification_for_bidding` TINYINT(1) NOT NULL DEFAULT 1,
    `contact_email` VARCHAR(180) NOT NULL DEFAULT 'concierge@ateliernoir.art',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(160) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `country` VARCHAR(100) NOT NULL,
    `role` ENUM('buyer', 'artist', 'admin') NOT NULL DEFAULT 'buyer',
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `bidding_approved` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    `is_seeded` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_name` VARCHAR(50) NOT NULL,
    `permissions_json` JSON NULL,
    PRIMARY KEY (`id`),
    KEY `idx_roles_user` (`user_id`),
    CONSTRAINT `fk_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `artist_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `display_name` VARCHAR(160) NOT NULL,
    `slug` VARCHAR(190) NOT NULL,
    `biography` TEXT NOT NULL,
    `artist_statement` TEXT NULL,
    `profile_photo` VARCHAR(500) NULL,
    `country` VARCHAR(100) NOT NULL,
    `city` VARCHAR(100) NULL,
    `discipline` VARCHAR(180) NOT NULL,
    `years_active` VARCHAR(80) NULL,
    `website` VARCHAR(255) NULL,
    `social_instagram` VARCHAR(120) NULL,
    `featured` TINYINT(1) NOT NULL DEFAULT 0,
    `verified_artist` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_artist_profiles_slug` (`slug`),
    KEY `idx_artist_profiles_user` (`user_id`),
    CONSTRAINT `fk_artist_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `buyer_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `collecting_focus` VARCHAR(255) NULL,
    `verified_payment_method` VARCHAR(120) NULL,
    `shipping_address` TEXT NULL,
    `identity_verified` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_buyer_profiles_user` (`user_id`),
    CONSTRAINT `fk_buyer_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(140) NOT NULL,
    `description` TEXT NULL,
    `image` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `artworks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `artist_id` BIGINT UNSIGNED NOT NULL,
    `category_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `medium` VARCHAR(255) NOT NULL,
    `year_created` SMALLINT UNSIGNED NOT NULL,
    `dimensions` VARCHAR(160) NOT NULL,
    `classification` VARCHAR(100) NOT NULL DEFAULT 'Original Unique Work',
    `edition_number` VARCHAR(100) NULL,
    `artist_attribution` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `provenance` TEXT NULL,
    `condition_report` TEXT NULL,
    `primary_image` VARCHAR(500) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `estimate_low` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `estimate_high` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `starting_bid` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `reserve_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft','submitted','under_review','changes_requested','approved','rejected','published','withdrawn','sold') NOT NULL DEFAULT 'draft',
    `featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_seeded` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_artworks_slug` (`slug`),
    KEY `idx_artworks_catalogue` (`status`, `category_id`, `artist_id`),
    CONSTRAINT `fk_artworks_artist` FOREIGN KEY (`artist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_artworks_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `artwork_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_artwork_images_artwork` (`artwork_id`, `sort_order`),
    CONSTRAINT `fk_artwork_images_artwork` FOREIGN KEY (`artwork_id`) REFERENCES `artworks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `artwork_documents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `artist_id` BIGINT UNSIGNED NOT NULL,
    `private_path` VARCHAR(255) NOT NULL,
    `document_type` VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_artwork_documents_artwork` (`artwork_id`),
    CONSTRAINT `fk_artwork_documents_artwork` FOREIGN KEY (`artwork_id`) REFERENCES `artworks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `artwork_submissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_code` VARCHAR(40) NOT NULL,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `artist_id` BIGINT UNSIGNED NOT NULL,
    `suggested_starting_bid` DECIMAL(14,2) NOT NULL,
    `reserve_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `preferred_duration_days` SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `estimate_low` DECIMAL(14,2) NULL,
    `estimate_high` DECIMAL(14,2) NULL,
    `seller_notes` TEXT NULL,
    `ownership_declared` TINYINT(1) NOT NULL DEFAULT 1,
    `copyright_confirmed` TINYINT(1) NOT NULL DEFAULT 1,
    `authenticity_notes` TEXT NULL,
    `status` ENUM('draft','submitted','under_review','changes_requested','approved','rejected','published','withdrawn') NOT NULL DEFAULT 'submitted',
    `admin_feedback` TEXT NULL,
    `reviewed_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_submissions_ref` (`reference_code`),
    KEY `idx_submissions_artist_status` (`artist_id`, `status`),
    CONSTRAINT `fk_submissions_artwork` FOREIGN KEY (`artwork_id`) REFERENCES `artworks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auctions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `artist_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `lot_number` VARCHAR(30) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `starting_bid` DECIMAL(14,2) NOT NULL,
    `current_bid` DECIMAL(14,2) NOT NULL,
    `reserve_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `reserve_met` TINYINT(1) NOT NULL DEFAULT 0,
    `estimate_low` DECIMAL(14,2) NULL,
    `estimate_high` DECIMAL(14,2) NULL,
    `bid_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `highest_bidder_id` BIGINT UNSIGNED NULL,
    `starts_at` TIMESTAMP NOT NULL,
    `ends_at` TIMESTAMP NOT NULL,
    `closed_at` TIMESTAMP NULL DEFAULT NULL,
    `status` ENUM('upcoming','active','closed_sold','closed_unsold') NOT NULL DEFAULT 'active',
    `anti_sniping_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `featured` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_auctions_active_ends` (`status`, `ends_at`),
    KEY `idx_auctions_artwork` (`artwork_id`),
    CONSTRAINT `fk_auctions_artwork` FOREIGN KEY (`artwork_id`) REFERENCES `artworks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bids` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `auction_id` BIGINT UNSIGNED NOT NULL,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `paddle_number` VARCHAR(30) NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `ip_hash` CHAR(64) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bids_auction_amount` (`auction_id`, `amount` DESC),
    KEY `idx_bids_user` (`user_id`),
    CONSTRAINT `fk_bids_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bids_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `watchlists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_watchlists_unique` (`user_id`, `artwork_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `followed_artists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `artist_profile_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_followed_unique` (`user_id`, `artist_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(40) NOT NULL,
    `auction_id` BIGINT UNSIGNED NOT NULL,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `buyer_id` BIGINT UNSIGNED NOT NULL,
    `artist_id` BIGINT UNSIGNED NOT NULL,
    `hammer_price` DECIMAL(14,2) NOT NULL,
    `buyer_premium` DECIMAL(14,2) NOT NULL,
    `shipping_fee` DECIMAL(14,2) NOT NULL DEFAULT 450.00,
    `total_amount` DECIMAL(14,2) NOT NULL,
    `seller_commission_rate` DECIMAL(5,2) NOT NULL,
    `seller_commission_amount` DECIMAL(14,2) NOT NULL,
    `net_artist_payout` DECIMAL(14,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `payment_status` ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
    `fulfillment_status` VARCHAR(60) NOT NULL DEFAULT 'awaiting_payment',
    `payment_deadline` TIMESTAMP NOT NULL,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_orders_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `hammer_price` DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `buyer_id` BIGINT UNSIGNED NOT NULL,
    `provider` VARCHAR(60) NOT NULL,
    `transaction_reference` VARCHAR(120) NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `status` ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'paid',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `artist_id` BIGINT UNSIGNED NOT NULL,
    `hammer_price` DECIMAL(14,2) NOT NULL,
    `commission_rate_percent` DECIMAL(5,2) NOT NULL,
    `commission_amount` DECIMAL(14,2) NOT NULL,
    `buyer_premium_amount` DECIMAL(14,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seller_payouts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `artist_id` BIGINT UNSIGNED NOT NULL,
    `gross_sale_amount` DECIMAL(14,2) NOT NULL,
    `commission_deducted` DECIMAL(14,2) NOT NULL,
    `net_payout_amount` DECIMAL(14,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `status` ENUM('pending','processing','completed','on_hold') NOT NULL DEFAULT 'pending',
    `payout_reference` VARCHAR(80) NOT NULL,
    `approved_by` BIGINT UNSIGNED NULL,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(80) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `action_url` VARCHAR(255) NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_unread` (`user_id`, `read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `token` VARCHAR(120) NOT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `token` VARCHAR(120) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'subscribed',
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_newsletter_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `editorial_articles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `category` VARCHAR(80) NOT NULL,
    `author` VARCHAR(140) NOT NULL,
    `excerpt` TEXT NOT NULL,
    `image` VARCHAR(500) NULL,
    `reading_time` VARCHAR(40) NULL,
    `body` LONGTEXT NOT NULL,
    `published_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_editorial_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `artwork_id` BIGINT UNSIGNED NOT NULL,
    `reporter_user_id` BIGINT UNSIGNED NULL,
    `reporter_name` VARCHAR(160) NULL,
    `reason` VARCHAR(160) NOT NULL,
    `details` TEXT NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `actor_name` VARCHAR(160) NOT NULL,
    `action` VARCHAR(120) NOT NULL,
    `target_type` VARCHAR(80) NOT NULL,
    `target_id` VARCHAR(80) NOT NULL,
    `details` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
