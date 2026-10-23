<?php
/**
 * NOTE16: Historical reseller orders retain previous profit policy.
 * New orders set pricing_version=2 and treat delivery as one customer charge.
 * Migration does not change order amounts, balances, or existing rows.
 */
require_once __DIR__ . '/../config/config.php';

function note16ColumnExists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute(['resell_orders', $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    if (!note16ColumnExists($pdo, 'pricing_version')) {
        $pdo->exec('ALTER TABLE `resell_orders` ADD COLUMN `pricing_version` TINYINT UNSIGNED NOT NULL DEFAULT 1');
        echo "Added resell_orders.pricing_version\n";
    } else {
        echo "Exists resell_orders.pricing_version\n";
    }
    if (!note16ColumnExists($pdo, 'checkout_discount')) {
        $pdo->exec('ALTER TABLE `resell_orders` ADD COLUMN `checkout_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        echo "Added resell_orders.checkout_discount\n";
    } else {
        echo "Exists resell_orders.checkout_discount\n";
    }
    echo "NOTE16 migration complete. Existing order amounts/balances were NOT modified.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'NOTE16 migration FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
