-- ============================================================================
-- INSOPHINIA POS v7 — MySQL 8.0+ Schema
-- ----------------------------------------------------------------------------
-- Pure PHP port of the original Next.js + PostgreSQL application.
-- All UUIDs are stored as CHAR(36) and generated in PHP via ramsey/uuid.
-- Money fields use DECIMAL(10,2); quantities use DECIMAL(10,3).
-- Enums use native MySQL ENUM columns.
-- Soft-delete columns (deleted_at) are TIMESTAMP NULL.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- ----------------------------------------------------------------------------
-- ENUMs are inlined via ENUM() column definitions below.
-- ----------------------------------------------------------------------------

-- ============================================================================
-- USERS
-- ============================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`                      CHAR(36)        NOT NULL,
    `username`                VARCHAR(100)    NOT NULL,
    `email`                   VARCHAR(255)    NOT NULL,
    `password`                VARCHAR(255)    NOT NULL,
    `role`                    ENUM('admin','cashier','supervisor','master_admin') NOT NULL DEFAULT 'cashier',
    `first_name`              VARCHAR(100)    NOT NULL DEFAULT '',
    `last_name`               VARCHAR(100)    NOT NULL DEFAULT '',
    `is_active`               TINYINT(1)      NOT NULL DEFAULT 1,
    `password_changed_at`     DATETIME        NULL,
    `password_expires_at`     DATETIME        NULL,
    `must_change_password`    TINYINT(1)      NOT NULL DEFAULT 1,
    `failed_login_attempts`   INT             NOT NULL DEFAULT 0,
    `locked_until`            DATETIME        NULL,
    `two_factor_secret`       TEXT            NULL,
    `two_factor_enabled`      TINYINT(1)      NOT NULL DEFAULT 0,
    `two_factor_backup_codes` TEXT            NULL,
    `created_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`              DATETIME        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_deleted_at` (`deleted_at`),
    KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CATEGORIES
-- ============================================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id`          CHAR(36)     NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT         NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`),
    KEY `idx_categories_deleted_at` (`deleted_at`),
    KEY `idx_categories_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ITEMS
-- ============================================================================
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
    `id`                   CHAR(36)     NOT NULL,
    `plu_code`             VARCHAR(50)  NOT NULL,
    `name`                 VARCHAR(255) NOT NULL,
    `description`          TEXT         NULL,
    `category_id`          CHAR(36)     NOT NULL,
    `barcode`              VARCHAR(100) NULL,
    `qr_code`              VARCHAR(100) NULL,
    `price`                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cost_price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `unit`                 VARCHAR(50)  NOT NULL DEFAULT 'pcs',
    `stock_quantity`       DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `total_quantity`       DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `sell_quantity`        DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `return_quantity`      DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `damage_quantity`      DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `lost_quantity`        DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `low_stock_threshold`  DECIMAL(10,3) NOT NULL DEFAULT 10.000,
    `version`              INT          NOT NULL DEFAULT 0,
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `has_discount`         TINYINT(1)   NOT NULL DEFAULT 0,
    `discount_percentage`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `warranty_days`        INT          NOT NULL DEFAULT 0,
    `item_type`            VARCHAR(20)  NOT NULL DEFAULT 'product',
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`           DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_items_plu_code` (`plu_code`),
    UNIQUE KEY `uq_items_barcode` (`barcode`),
    UNIQUE KEY `uq_items_qr_code` (`qr_code`),
    KEY `idx_items_category_id` (`category_id`),
    KEY `idx_items_deleted_at` (`deleted_at`),
    KEY `idx_items_is_active_stock` (`is_active`, `stock_quantity`),
    KEY `idx_items_item_type` (`item_type`),
    KEY `idx_items_cat_active_deleted` (`category_id`, `is_active`, `deleted_at`),
    CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ITEM_VARIANTS
-- ============================================================================
DROP TABLE IF EXISTS `item_variants`;
CREATE TABLE `item_variants` (
    `id`               CHAR(36)     NOT NULL,
    `item_id`          CHAR(36)     NOT NULL,
    `sku`              VARCHAR(100) NOT NULL,
    `name`             VARCHAR(255) NOT NULL,
    `price_adjustment` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_quantity`   DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `specs`            TEXT         NOT NULL,
    `warranty_days`    INT          NOT NULL DEFAULT 0,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item_variants_sku` (`sku`),
    KEY `idx_item_variants_item_id` (`item_id`),
    KEY `idx_item_variants_is_active` (`is_active`),
    CONSTRAINT `fk_item_variants_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ITEM_SUB_ITEMS
-- ============================================================================
DROP TABLE IF EXISTS `item_sub_items`;
CREATE TABLE `item_sub_items` (
    `id`                 CHAR(36)     NOT NULL,
    `item_id`            CHAR(36)     NOT NULL,
    `name`               VARCHAR(255) NOT NULL,
    `description`        TEXT         NULL,
    `base_price`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `revisions_included` INT          NOT NULL DEFAULT 2,
    `revision_price`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estimated_hours`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`         INT          NOT NULL DEFAULT 0,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_item_sub_items_item_id` (`item_id`),
    KEY `idx_item_sub_items_is_active` (`is_active`),
    KEY `idx_item_sub_items_sort_order` (`sort_order`),
    CONSTRAINT `fk_item_sub_items_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PROMOTIONS
-- ============================================================================
DROP TABLE IF EXISTS `promotions`;
CREATE TABLE `promotions` (
    `id`             CHAR(36)     NOT NULL,
    `item_id`        CHAR(36)     NULL,
    `category_id`    CHAR(36)     NULL,
    `name`           VARCHAR(255) NOT NULL,
    `description`    TEXT         NULL,
    `discount_type`  ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_quantity`   INT          NOT NULL DEFAULT 1,
    `start_date`     DATETIME     NOT NULL,
    `end_date`       DATETIME     NOT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_promotions_item_id` (`item_id`),
    KEY `idx_promotions_category_id` (`category_id`),
    KEY `idx_promotions_date_range` (`start_date`, `end_date`),
    KEY `idx_promotions_active_dates_deleted` (`is_active`, `start_date`, `end_date`, `deleted_at`),
    KEY `idx_promotions_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_promotions_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_promotions_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CUSTOMERS
-- ============================================================================
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id`                            CHAR(36)     NOT NULL,
    `name`                          VARCHAR(255) NOT NULL,
    `phone`                         VARCHAR(20)  NULL,
    `email`                         VARCHAR(255) NULL,
    `email_verified`                DATETIME     NULL,
    `email_verification_token`      VARCHAR(128) NULL,
    `email_verification_expires_at` DATETIME     NULL,
    `marketing_opt_in`              TINYINT(1)   NOT NULL DEFAULT 0,
    `marketing_opt_in_at`           DATETIME     NULL,
    `marketing_opt_out_at`          DATETIME     NULL,
    `loyalty_points`                INT          NOT NULL DEFAULT 0,
    `address`                       TEXT         NULL,
    `created_at`                    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`                    DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customers_phone` (`phone`),
    UNIQUE KEY `uq_customers_email` (`email`),
    KEY `idx_customers_deleted_at` (`deleted_at`),
    KEY `idx_customers_marketing_opt_in` (`marketing_opt_in`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BILLS
-- ============================================================================
DROP TABLE IF EXISTS `bills`;
CREATE TABLE `bills` (
    `id`                    CHAR(36)     NOT NULL,
    `bill_number`           VARCHAR(50)  NOT NULL,
    `customer_id`           CHAR(36)     NULL,
    `customer_name`         VARCHAR(255) NOT NULL DEFAULT 'Customer',
    `customer_phone`        VARCHAR(50)  NULL,
    `customer_email`        VARCHAR(255) NULL,
    `customer_address`      TEXT         NULL,
    `staff_id`              CHAR(36)     NOT NULL,
    `staff_name`            VARCHAR(255) NOT NULL DEFAULT '',
    `start_time`            DATETIME     NULL,
    `end_time`              DATETIME     NULL,
    `subtotal`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_discount`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `vat_amount`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sscl_amount`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_total`             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance`               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`                ENUM('pending','completed','returned','cancelled') NOT NULL DEFAULT 'pending',
    `return_reason`         TEXT         NULL,
    `return_authorized_by`  CHAR(36)     NULL,
    `return_authorized_at`  DATETIME     NULL,
    `remarks`               TEXT         NULL,
    `is_printed`            TINYINT(1)   NOT NULL DEFAULT 0,
    `last_print_format`     ENUM('thermal','a4') NULL,
    `earned_points`         INT          NOT NULL DEFAULT 0,
    `points_redeemed`       INT          NOT NULL DEFAULT 0,
    `points_value`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `points_authorized_by`  VARCHAR(255) NULL,
    `points_authorized_at`  DATETIME     NULL,
    `total_points_after`    INT          NOT NULL DEFAULT 0,
    `terms_snapshot`        TEXT         NULL,
    `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bills_bill_number` (`bill_number`),
    KEY `idx_bills_customer_id` (`customer_id`),
    KEY `idx_bills_staff_id` (`staff_id`),
    KEY `idx_bills_status` (`status`),
    KEY `idx_bills_created_at` (`created_at`),
    KEY `idx_bills_deleted_at` (`deleted_at`),
    KEY `idx_bills_staff_created` (`staff_id`, `created_at`),
    KEY `idx_bills_status_created` (`status`, `created_at`),
    KEY `idx_bills_customer_email` (`customer_email`),
    KEY `idx_bills_customer_phone` (`customer_phone`),
    CONSTRAINT `fk_bills_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bills_staff` FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bills_return_authorizer` FOREIGN KEY (`return_authorized_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BILL_ITEMS
-- ============================================================================
DROP TABLE IF EXISTS `bill_items`;
CREATE TABLE `bill_items` (
    `id`                  CHAR(36)     NOT NULL,
    `bill_id`             CHAR(36)     NOT NULL,
    `item_id`             CHAR(36)     NOT NULL,
    `plu_code`            VARCHAR(50)  NOT NULL DEFAULT '',
    `item_name`           VARCHAR(255) NOT NULL,
    `quantity`            DECIMAL(10,3) NOT NULL,
    `unit`                VARCHAR(50)  NOT NULL DEFAULT 'pcs',
    `unit_price`          DECIMAL(10,2) NOT NULL,
    `actual_price`        DECIMAL(10,2) NOT NULL,
    `discount`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `subtotal`            DECIMAL(10,2) NOT NULL,
    `variant_id`          CHAR(36)     NULL,
    `variant_sku`         VARCHAR(100) NULL,
    `variant_name`        VARCHAR(255) NULL,
    `variant_specs`       TEXT         NULL,
    `sub_item_id`         CHAR(36)     NULL,
    `sub_item_name`       VARCHAR(255) NULL,
    `revisions_included`  INT          NOT NULL DEFAULT 0,
    `revisions_purchased` INT          NOT NULL DEFAULT 0,
    `revision_price`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `revisions_subtotal`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `warranty_days`       INT          NOT NULL DEFAULT 0,
    `warranty_expires_at` DATETIME     NULL,
    `line_notes`          TEXT         NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bill_items_bill_id` (`bill_id`),
    KEY `idx_bill_items_item_id` (`item_id`),
    KEY `idx_bill_items_variant_id` (`variant_id`),
    KEY `idx_bill_items_sub_item_id` (`sub_item_id`),
    CONSTRAINT `fk_bill_items_bill` FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bill_items_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_bill_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `item_variants`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bill_items_sub_item` FOREIGN KEY (`sub_item_id`) REFERENCES `item_sub_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STOCK_MOVEMENTS
-- ============================================================================
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
    `id`             CHAR(36)     NOT NULL,
    `item_id`        CHAR(36)     NOT NULL,
    `movement_type`  ENUM('in','out','return_item','damage','lost') NOT NULL,
    `quantity`       DECIMAL(10,3) NOT NULL,
    `reference_type` ENUM('bill','manual','adjustment','repair') NULL,
    `reference_id`   CHAR(36)     NULL,
    `notes`          TEXT         NULL,
    `movement_date`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by`     CHAR(36)     NOT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_stock_movements_item_id` (`item_id`),
    KEY `idx_stock_movements_movement_type` (`movement_type`),
    KEY `idx_stock_movements_movement_date` (`movement_date`),
    KEY `idx_stock_movements_reference` (`reference_type`, `reference_id`),
    KEY `idx_stock_movements_item_date` (`item_id`, `movement_date`),
    CONSTRAINT `fk_stock_movements_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_stock_movements_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- WARRANTIES
-- ============================================================================
DROP TABLE IF EXISTS `warranties`;
CREATE TABLE `warranties` (
    `id`              CHAR(36)     NOT NULL,
    `warranty_number` VARCHAR(50)  NOT NULL,
    `bill_id`         CHAR(36)     NOT NULL,
    `bill_item_id`    CHAR(36)     NOT NULL,
    `customer_id`     CHAR(36)     NULL,
    `item_id`         CHAR(36)     NOT NULL,
    `item_name`       VARCHAR(255) NOT NULL,
    `variant_sku`     VARCHAR(100) NULL,
    `serial_number`   VARCHAR(200) NULL,
    `purchase_date`   DATETIME     NOT NULL,
    `warranty_days`   INT          NOT NULL DEFAULT 0,
    `expires_at`      DATETIME     NOT NULL,
    `status`          ENUM('active','expired','claimed','voided') NOT NULL DEFAULT 'active',
    `notes`           TEXT         NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_warranties_warranty_number` (`warranty_number`),
    KEY `idx_warranties_bill_id` (`bill_id`),
    KEY `idx_warranties_bill_item_id` (`bill_item_id`),
    KEY `idx_warranties_customer_id` (`customer_id`),
    KEY `idx_warranties_item_id` (`item_id`),
    KEY `idx_warranties_status` (`status`),
    KEY `idx_warranties_expires_at` (`expires_at`),
    KEY `idx_warranties_serial_number` (`serial_number`),
    CONSTRAINT `fk_warranties_bill` FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_warranties_bill_item` FOREIGN KEY (`bill_item_id`) REFERENCES `bill_items`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_warranties_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- WARRANTY_CLAIMS
-- ============================================================================
DROP TABLE IF EXISTS `warranty_claims`;
CREATE TABLE `warranty_claims` (
    `id`               CHAR(36)     NOT NULL,
    `claim_number`     VARCHAR(50)  NOT NULL,
    `warranty_id`      CHAR(36)     NOT NULL,
    `customer_id`      CHAR(36)     NULL,
    `status`           ENUM('filed','under_review','approved','rejected','repaired','replaced','closed') NOT NULL DEFAULT 'filed',
    `issue_description` TEXT        NOT NULL,
    `fault_category`   VARCHAR(50)  NULL,
    `assessment_notes` TEXT         NULL,
    `resolution_notes` TEXT         NULL,
    `resolution_type`  VARCHAR(20)  NULL,
    `filed_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at`      DATETIME     NULL,
    `reviewed_by`      CHAR(36)     NULL,
    `resolved_at`      DATETIME     NULL,
    `closed_at`        DATETIME     NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_warranty_claims_claim_number` (`claim_number`),
    KEY `idx_warranty_claims_warranty_id` (`warranty_id`),
    KEY `idx_warranty_claims_customer_id` (`customer_id`),
    KEY `idx_warranty_claims_status` (`status`),
    KEY `idx_warranty_claims_filed_at` (`filed_at`),
    KEY `idx_warranty_claims_reviewed_by` (`reviewed_by`),
    CONSTRAINT `fk_warranty_claims_warranty` FOREIGN KEY (`warranty_id`) REFERENCES `warranties`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_warranty_claims_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_warranty_claims_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- REPAIR_JOBS
-- ============================================================================
DROP TABLE IF EXISTS `repair_jobs`;
CREATE TABLE `repair_jobs` (
    `id`                   CHAR(36)     NOT NULL,
    `job_number`           VARCHAR(50)  NOT NULL,
    `customer_id`          CHAR(36)     NULL,
    `customer_name`        VARCHAR(255) NOT NULL,
    `customer_phone`       VARCHAR(50)  NULL,
    `customer_email`       VARCHAR(255) NULL,
    `item_description`     VARCHAR(500) NOT NULL,
    `item_serial`          VARCHAR(255) NULL,
    `item_id`              CHAR(36)     NULL,
    `fault_description`    TEXT         NOT NULL,
    `access_password`      VARCHAR(200) NULL,
    `accessories`          TEXT         NULL,
    `condition_notes`      TEXT         NULL,
    `scope_of_work`        TEXT         NULL,
    `estimated_cost`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `final_cost`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estimated_hours`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `actual_hours`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `labor_cost`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `parts_cost`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_cost`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`               ENUM('received','in_progress','awaiting_parts','ready_for_pickup','completed','cancelled','returned_unrepaired') NOT NULL DEFAULT 'received',
    `technician_id`        CHAR(36)     NULL,
    `received_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at`           DATETIME     NULL,
    `completed_at`         DATETIME     NULL,
    `delivered_at`         DATETIME     NULL,
    `repair_warranty_days` INT          NOT NULL DEFAULT 0,
    `notes`                TEXT         NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_repair_jobs_job_number` (`job_number`),
    KEY `idx_repair_jobs_customer_id` (`customer_id`),
    KEY `idx_repair_jobs_status` (`status`),
    KEY `idx_repair_jobs_technician_id` (`technician_id`),
    KEY `idx_repair_jobs_received_at` (`received_at`),
    KEY `idx_repair_jobs_customer_phone` (`customer_phone`),
    CONSTRAINT `fk_repair_jobs_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_repair_jobs_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_repair_jobs_technician` FOREIGN KEY (`technician_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- REPAIR_PARTS
-- ============================================================================
DROP TABLE IF EXISTS `repair_parts`;
CREATE TABLE `repair_parts` (
    `id`            CHAR(36)     NOT NULL,
    `repair_job_id` CHAR(36)     NOT NULL,
    `item_id`       CHAR(36)     NULL,
    `part_name`     VARCHAR(255) NOT NULL,
    `part_sku`      VARCHAR(100) NULL,
    `quantity`      DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    `unit_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `subtotal`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `from_inventory` TINYINT(1)  NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_repair_parts_repair_job_id` (`repair_job_id`),
    KEY `idx_repair_parts_item_id` (`item_id`),
    CONSTRAINT `fk_repair_parts_job` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_repair_parts_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EMAIL_TEMPLATES
-- ============================================================================
DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
    `id`                CHAR(36)     NOT NULL,
    `name`              VARCHAR(255) NOT NULL,
    `type`              ENUM('bill','marketing','warranty','repair') NOT NULL DEFAULT 'marketing',
    `subject`           VARCHAR(200) NOT NULL,
    `html_body`         TEXT         NOT NULL,
    `text_body`         TEXT         NULL,
    `default_variables` TEXT         NULL,
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_email_templates_type` (`type`),
    KEY `idx_email_templates_is_active` (`is_active`),
    KEY `idx_email_templates_deleted_at` (`deleted_at`),
    KEY `idx_email_templates_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MARKETING_CAMPAIGNS
-- ============================================================================
DROP TABLE IF EXISTS `marketing_campaigns`;
CREATE TABLE `marketing_campaigns` (
    `id`                CHAR(36)     NOT NULL,
    `name`              VARCHAR(255) NOT NULL,
    `template_id`       CHAR(36)     NOT NULL,
    `audience_type`     VARCHAR(20)  NOT NULL DEFAULT 'single',
    `audience_filter`   TEXT         NULL,
    `recipient_email`   VARCHAR(255) NULL,
    `recipient_name`    VARCHAR(255) NULL,
    `subject_override`  VARCHAR(255) NULL,
    `custom_variables`  TEXT         NULL,
    `status`            ENUM('draft','scheduled','sending','sent','failed','partial') NOT NULL DEFAULT 'draft',
    `total_recipients`  INT          NOT NULL DEFAULT 0,
    `sent_count`        INT          NOT NULL DEFAULT 0,
    `failed_count`      INT          NOT NULL DEFAULT 0,
    `scheduled_at`      DATETIME     NULL,
    `started_at`        DATETIME     NULL,
    `completed_at`      DATETIME     NULL,
    `created_by`        CHAR(36)     NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_marketing_campaigns_template_id` (`template_id`),
    KEY `idx_marketing_campaigns_status` (`status`),
    KEY `idx_marketing_campaigns_audience_type` (`audience_type`),
    KEY `idx_marketing_campaigns_scheduled_at` (`scheduled_at`),
    KEY `idx_marketing_campaigns_created_by` (`created_by`),
    KEY `idx_marketing_campaigns_created_at` (`created_at`),
    CONSTRAINT `fk_marketing_campaigns_template` FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_marketing_campaigns_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MARKETING_RECIPIENTS
-- ============================================================================
DROP TABLE IF EXISTS `marketing_recipients`;
CREATE TABLE `marketing_recipients` (
    `id`            CHAR(36)     NOT NULL,
    `campaign_id`   CHAR(36)     NOT NULL,
    `customer_id`   CHAR(36)     NULL,
    `email`         VARCHAR(255) NOT NULL,
    `name`          VARCHAR(255) NULL,
    `status`        VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `error_message` TEXT         NULL,
    `sent_at`       DATETIME     NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_marketing_recipients_campaign_id` (`campaign_id`),
    KEY `idx_marketing_recipients_customer_id` (`customer_id`),
    KEY `idx_marketing_recipients_email` (`email`),
    KEY `idx_marketing_recipients_status` (`status`),
    CONSTRAINT `fk_marketing_recipients_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_marketing_recipients_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TERMS_TEMPLATES
-- ============================================================================
DROP TABLE IF EXISTS `terms_templates`;
CREATE TABLE `terms_templates` (
    `id`         CHAR(36)     NOT NULL,
    `name`       VARCHAR(255) NOT NULL,
    `format`     VARCHAR(20)  NOT NULL DEFAULT 'html',
    `content`    TEXT         NOT NULL,
    `applies_to` TEXT         NOT NULL DEFAULT '["a4"]',
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `version`    INT          NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_terms_templates_is_active` (`is_active`),
    KEY `idx_terms_templates_format` (`format`),
    KEY `idx_terms_templates_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SETTINGS
-- ============================================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id`          CHAR(36)     NOT NULL,
    `key`         VARCHAR(100) NOT NULL,
    `value`       TEXT         NULL,
    `type`        ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string',
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BACKUPS
-- ============================================================================
DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
    `id`          CHAR(36)     NOT NULL,
    `filename`    VARCHAR(255) NOT NULL,
    `file_path`   VARCHAR(512) NOT NULL,
    `file_size`   BIGINT       NOT NULL DEFAULT 0,
    `backup_type` VARCHAR(20)  NOT NULL DEFAULT 'manual',
    `created_by`  CHAR(36)     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_backups_backup_type` (`backup_type`),
    KEY `idx_backups_created_at` (`created_at`),
    CONSTRAINT `fk_backups_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LOGIN_SESSIONS
-- ============================================================================
DROP TABLE IF EXISTS `login_sessions`;
CREATE TABLE `login_sessions` (
    `id`                CHAR(36)     NOT NULL,
    `user_id`           CHAR(36)     NOT NULL,
    `session_token`     VARCHAR(255) NOT NULL,
    `ip_address`        VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent`        TEXT         NULL,
    `browser`           VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `os`                VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `device`            VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `city`              VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `country`           VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `region`            VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`        DATETIME     NOT NULL,
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `revoked_at`        DATETIME     NULL,
    `revoke_reason`     VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_login_sessions_session_token` (`session_token`),
    KEY `idx_login_sessions_user_active` (`user_id`, `is_active`),
    KEY `idx_login_sessions_expires_at` (`expires_at`),
    CONSTRAINT `fk_login_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TOKEN_BLACKLIST
-- ============================================================================
DROP TABLE IF EXISTS `token_blacklist`;
CREATE TABLE `token_blacklist` (
    `id`         CHAR(36)     NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `user_id`    CHAR(36)     NOT NULL,
    `reason`     VARCHAR(255) NOT NULL DEFAULT 'logout',
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token_blacklist_token_hash` (`token_hash`),
    KEY `idx_token_blacklist_user_id` (`user_id`),
    KEY `idx_token_blacklist_expires_at` (`expires_at`),
    CONSTRAINT `fk_token_blacklist_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECURITY_EVENTS
-- ============================================================================
DROP TABLE IF EXISTS `security_events`;
CREATE TABLE `security_events` (
    `id`         CHAR(36)     NOT NULL,
    `user_id`    CHAR(36)     NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent` TEXT         NULL,
    `details`    TEXT         NULL,
    `severity`   VARCHAR(20)  NOT NULL DEFAULT 'low',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_security_events_user_event` (`user_id`, `event_type`),
    KEY `idx_security_events_created_at` (`created_at`),
    KEY `idx_security_events_ip_event_created` (`ip_address`, `event_type`, `created_at`),
    CONSTRAINT `fk_security_events_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AUDIT_LOGS
-- ============================================================================
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id`         CHAR(36)     NOT NULL,
    `user_id`    CHAR(36)     NULL,
    `action`     VARCHAR(100) NOT NULL,
    `entity`     VARCHAR(100) NOT NULL,
    `entity_id`  VARCHAR(100) NULL,
    `details`    TEXT         NULL,
    `ip_address` VARCHAR(45)  NULL,
    `user_agent` TEXT         NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_user_id` (`user_id`),
    KEY `idx_audit_logs_action` (`action`),
    KEY `idx_audit_logs_entity` (`entity`),
    KEY `idx_audit_logs_created_at` (`created_at`),
    KEY `idx_audit_logs_entity_id` (`entity`, `entity_id`),
    CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SYSTEM_LOGS
-- ============================================================================
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
    `id`         CHAR(36)     NOT NULL,
    `level`      VARCHAR(20)  NOT NULL DEFAULT 'info',
    `category`   VARCHAR(100) NOT NULL DEFAULT '',
    `action`     VARCHAR(255) NOT NULL DEFAULT '',
    `message`    TEXT         NOT NULL,
    `metadata`   TEXT         NULL,
    `user_id`    CHAR(36)     NULL,
    `ip_address` VARCHAR(200) NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_system_logs_level` (`level`),
    KEY `idx_system_logs_category` (`category`),
    KEY `idx_system_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SYSTEM_STATE (deadman switch — single row)
-- ============================================================================
DROP TABLE IF EXISTS `system_state`;
CREATE TABLE `system_state` (
    `id`               CHAR(36)     NOT NULL,
    `is_locked`        TINYINT(1)   NOT NULL DEFAULT 0,
    `lock_reason`      TEXT         NULL,
    `locked_by`        CHAR(36)     NULL,
    `locked_at`        DATETIME     NULL,
    `last_checkin_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deadman_days`     INT          NOT NULL DEFAULT 72,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
