<?php
/**
 * NOTE 05 local migration: reseller cart table
 * Run from D:\laragon\www\kilagbo with:
 * php migrations/005_reseller_cart.php
 *
 * LOCAL ONLY. This intentionally connects to the Laragon database directly
 * and does not load production configuration.
 */

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=kilagbo;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $pdo->exec($sql);
    echo "NOTE 05 migration complete: resell_cart is ready in local database 'kilagbo'." . PHP_EOL;
} catch (PDOException $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
