<?php
/**
 * NOTE 09 - Reseller bank withdrawal migration.
 * Idempotent: safe to run more than once.
 */
require_once __DIR__ . '/../config/config.php';

function note09ColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

try {
    $table = 'reseller_withdrawals';

    // Expand the existing payment method enum without changing existing rows.
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, 'method_type']);
    $methodType = (string)$stmt->fetchColumn();
    if ($methodType === '') {
        throw new RuntimeException('reseller_withdrawals.method_type column not found');
    }
    if (stripos($methodType, "'bank'") === false) {
        $pdo->exec("ALTER TABLE `reseller_withdrawals` MODIFY `method_type` ENUM('bkash','nagad','rocket','bank') NOT NULL");
        echo "Updated reseller_withdrawals.method_type with bank\n";
    } else {
        echo "Exists reseller_withdrawals.method_type bank option\n";
    }

    $columns = [
        'bank_name' => "VARCHAR(150) NULL AFTER `account_name`",
        'bank_district' => "VARCHAR(100) NULL AFTER `bank_name`",
        'bank_branch' => "VARCHAR(150) NULL AFTER `bank_district`",
    ];

    foreach ($columns as $column => $definition) {
        if (!note09ColumnExists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "Added $table.$column\n";
        } else {
            echo "Exists $table.$column\n";
        }
    }

    echo "NOTE 09 migration complete: reseller bank withdrawal fields are ready.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "NOTE 09 migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
