<?php
/**
 * Kilagbo NOTE 07 migration - courier status columns.
 * Idempotent: existing columns are left untouched.
 *
 * Uses INFORMATION_SCHEMA instead of SHOW COLUMNS ... LIKE ? because
 * placeholders are not valid in that SHOW statement on this MySQL version.
 */
require_once __DIR__ . '/../config/config.php';

$changes = [
    'orders' => [
        'courier_status' => "VARCHAR(64) NULL",
        'courier_status_updated_at' => "DATETIME NULL",
        'courier_last_sync_at' => "DATETIME NULL",
    ],
    'wholesale_orders' => [
        'steadfast_consignment_id' => "VARCHAR(100) NULL",
        'steadfast_tracking_code' => "VARCHAR(100) NULL",
        'courier_status' => "VARCHAR(64) NULL",
        'courier_status_updated_at' => "DATETIME NULL",
        'courier_last_sync_at' => "DATETIME NULL",
    ],
    'resell_orders' => [
        'steadfast_consignment_id' => "VARCHAR(100) NULL",
        'steadfast_tracking_code' => "VARCHAR(100) NULL",
        'courier_status' => "VARCHAR(64) NULL",
        'courier_status_updated_at' => "DATETIME NULL",
        'courier_last_sync_at' => "DATETIME NULL",
    ],
    'affiliate_orders' => [
        'steadfast_consignment_id' => "VARCHAR(100) NULL",
        'steadfast_tracking_code' => "VARCHAR(100) NULL",
        'tracking_link' => "VARCHAR(500) NULL",
        'courier_status' => "VARCHAR(64) NULL",
        'courier_status_updated_at' => "DATETIME NULL",
        'courier_last_sync_at' => "DATETIME NULL",
    ],
];

function note07ColumnExists(PDO $pdo, string $table, string $column): bool
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
    foreach ($changes as $table => $columns) {
        foreach ($columns as $column => $definition) {
            if (!note07ColumnExists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                echo "Added $table.$column" . PHP_EOL;
            } else {
                echo "Exists $table.$column" . PHP_EOL;
            }
        }
    }

    echo "NOTE 07 migration complete: courier status schema is ready." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "NOTE 07 migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
