<?php
/**
 * Kilagbo NOTE 08 migration - reseller below-cost shortfall tracking.
 * Idempotent: existing columns are left untouched.
 */
require_once __DIR__ . '/../config/config.php';

$changes = [
    'shortfall_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `profit`",
    'shortfall_deducted' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `shortfall_amount`",
    'shortfall_refunded' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `shortfall_deducted`",
];

function note08ColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    foreach ($changes as $column => $definition) {
        if (!note08ColumnExists($pdo, 'resell_orders', $column)) {
            $pdo->exec("ALTER TABLE `resell_orders` ADD COLUMN `$column` $definition");
            echo "Added resell_orders.$column" . PHP_EOL;
        } else {
            echo "Exists resell_orders.$column" . PHP_EOL;
        }
    }

    echo "NOTE 08 migration complete: reseller shortfall tracking is ready." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "NOTE 08 migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
