<?php
/** NOTE15 - reseller courier tracking/verified partial accounting. CLI only. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/config.php';

function note15ColumnInfo(PDO $pdo, string $table, string $column): ?array
{
    $s = $pdo->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$table, $column]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    $status = note15ColumnInfo($pdo, 'resell_orders', 'status');
    if (!$status) throw new RuntimeException('resell_orders.status not found');
    $oldEnum = "enum('pending','processing','shipped','delivered','cancelled','returned')";
    $newEnum = "enum('pending','processing','shipped','delivered','cancelled','returned','partial')";
    if (!in_array(strtolower($status['COLUMN_TYPE']), [$oldEnum, $newEnum], true)) {
        throw new RuntimeException('Unexpected resell_orders.status definition. Review manually before migrating.');
    }
    $changes = [
        'tracking_link' => 'VARCHAR(500) NULL',
        'partial_delivery_paid' => 'DECIMAL(10,2) NULL',
        'partial_delivery_deducted' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'partial_delivery_source' => 'VARCHAR(100) NULL',
        'partial_delivery_verified_at' => 'DATETIME NULL',
    ];
    foreach ($changes as $field => $ddl) {
        if (!note15ColumnInfo($pdo, 'resell_orders', $field)) {
            $pdo->exec("ALTER TABLE resell_orders ADD COLUMN `{$field}` {$ddl}");
            echo "Added resell_orders.{$field}\n";
        } else {
            echo "Exists resell_orders.{$field}\n";
        }
    }
    if (strtolower($status['COLUMN_TYPE']) === $oldEnum) {
        $pdo->exec("ALTER TABLE resell_orders MODIFY COLUMN `status` ENUM('pending','processing','shipped','delivered','cancelled','returned','partial') DEFAULT 'pending'");
        echo "Added local order status: partial\n";
    }
    echo "NOTE15 migration complete (no balance transaction was executed).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'NOTE15 migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
