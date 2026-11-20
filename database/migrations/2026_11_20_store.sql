-- Store: goods for sale to students and staff (uniforms, books, stationery, branded items).
-- Kept apart from Supplies & Stock (stock_items), which holds internal consumables that are not sold.
-- Applied automatically by App\Services\AcademicCatalogSync::upgradeSchema(); every statement
-- is safe to run again.

CREATE TABLE IF NOT EXISTS `store_products` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`           VARCHAR(40)   NOT NULL,
  `name`          VARCHAR(150)  NOT NULL,
  `category`      VARCHAR(80)   NOT NULL DEFAULT 'General',
  `description`   VARCHAR(255)           DEFAULT NULL,
  `unit`          VARCHAR(30)   NOT NULL DEFAULT 'piece',
  `price`         DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'selling price',
  `cost_price`    DECIMAL(12,2)          DEFAULT NULL COMMENT 'buying price, for margin',
  `quantity`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'available for sale',
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sp_sku` (`sku`),
  KEY `ix_sp_category` (`category`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_sales` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_number`  VARCHAR(30)              DEFAULT NULL,
  `customer_type`   ENUM('student','staff','walk_in') NOT NULL DEFAULT 'walk_in',
  `student_id`      BIGINT UNSIGNED          DEFAULT NULL,
  `staff_id`        BIGINT UNSIGNED          DEFAULT NULL,
  `customer_name`   VARCHAR(150)             DEFAULT NULL COMMENT 'name shown on the receipt',
  `subtotal`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `discount`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `payment_method`  ENUM('cash','mpesa','card','bank_transfer') NOT NULL DEFAULT 'cash',
  `reference`       VARCHAR(80)              DEFAULT NULL,
  `amount_tendered` DECIMAL(12,2)            DEFAULT NULL,
  `change_due`      DECIMAL(12,2)            DEFAULT NULL,
  `note`            VARCHAR(255)             DEFAULT NULL,
  `status`          ENUM('completed','voided') NOT NULL DEFAULT 'completed',
  `void_reason`     VARCHAR(255)             DEFAULT NULL,
  `voided_by`       BIGINT UNSIGNED          DEFAULT NULL,
  `voided_at`       DATETIME                 DEFAULT NULL,
  `sold_by`         BIGINT UNSIGNED          DEFAULT NULL,
  `sold_at`         DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ss_receipt` (`receipt_number`),
  KEY `ix_ss_sold_at` (`sold_at`, `status`),
  KEY `ix_ss_student` (`student_id`),
  KEY `ix_ss_staff` (`staff_id`),
  CONSTRAINT `fk_store_sale_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_store_sale_staff`   FOREIGN KEY (`staff_id`)   REFERENCES `staff` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_sale_items` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`   INT UNSIGNED             DEFAULT NULL,
  `sku`          VARCHAR(40)     NOT NULL,
  `product_name` VARCHAR(150)    NOT NULL COMMENT 'as sold, so receipts survive renames',
  `unit_price`   DECIMAL(12,2)   NOT NULL,
  `quantity`     DECIMAL(12,2)   NOT NULL,
  `line_total`   DECIMAL(12,2)   NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ssi_sale` (`sale_id`),
  KEY `ix_ssi_product` (`product_id`),
  CONSTRAINT `fk_store_item_sale`    FOREIGN KEY (`sale_id`)    REFERENCES `store_sales` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_store_item_product` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_stock_movements` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED    NOT NULL,
  `direction`     ENUM('in','sale','void','adjust') NOT NULL,
  `quantity`      DECIMAL(12,2)   NOT NULL,
  `balance_after` DECIMAL(12,2)   NOT NULL,
  `sale_id`       BIGINT UNSIGNED          DEFAULT NULL,
  `reference`     VARCHAR(80)              DEFAULT NULL,
  `note`          VARCHAR(255)             DEFAULT NULL,
  `created_by`    BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ssm_product` (`product_id`, `created_at`),
  CONSTRAINT `fk_store_move_product` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

