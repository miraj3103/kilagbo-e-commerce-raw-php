CREATE TABLE IF NOT EXISTS `resell_cart` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reseller_id` INT NOT NULL,
  `resell_product_id` INT NOT NULL,
  `variation` LONGTEXT NULL,
  `variation_hash` CHAR(64) NOT NULL DEFAULT '',
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reseller_product_variation` (`reseller_id`, `resell_product_id`, `variation_hash`),
  KEY `idx_resell_cart_reseller` (`reseller_id`),
  KEY `idx_resell_cart_product` (`resell_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
