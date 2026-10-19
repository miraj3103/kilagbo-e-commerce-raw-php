<?php
/** NOTE 15 Stage 2: manual Partial amounts, real debit vs unpaid due, audit. CLI only. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/config.php';
try {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resell_orders' AND COLUMN_NAME = ?");
    foreach (['partial_delivery_paid', 'partial_delivery_deducted', 'partial_delivery_source'] as $field) {
        $q->execute([$field]);
        if (!(int)$q->fetchColumn()) throw new RuntimeException('Run NOTE15 Stage 1 migration first: missing ' . $field);
    }

    $q->execute(['partial_delivery_due']);
    $alreadyMigrated = (int)$q->fetchColumn() > 0;
    // Stage 1's deducted field held the full expected charge, while Stage 2
    // stores the amount ACTUALLY debited. Review old Partial rows before upgrade.
    // Once the new column exists, rerunning this migration is safe.
    if (!$alreadyMigrated) {
        $legacy = $pdo->query("SELECT COUNT(*) FROM resell_orders WHERE status = 'partial' AND partial_delivery_paid IS NOT NULL")->fetchColumn();
        if ((int)$legacy > 0) {
            throw new RuntimeException('Found ' . (int)$legacy . ' existing Partial orders; review their debits before upgrading Stage 2. Nothing changed.');
        }
    }
    if (!$alreadyMigrated) {
        $pdo->exec('ALTER TABLE resell_orders ADD COLUMN partial_delivery_due DECIMAL(10,2) NOT NULL DEFAULT 0');
        echo "Added resell_orders.partial_delivery_due\n";
    } else {
        echo "Exists resell_orders.partial_delivery_due\n";
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS resell_partial_adjustment_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        reseller_id INT NOT NULL,
        admin_id INT NULL,
        source VARCHAR(100) NOT NULL,
        previous_paid DECIMAL(10,2) NULL,
        new_paid DECIMAL(10,2) NOT NULL,
        debited DECIMAL(10,2) NOT NULL DEFAULT 0,
        refunded DECIMAL(10,2) NOT NULL DEFAULT 0,
        outstanding_due DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        KEY idx_partial_order (order_id, created_at),
        KEY idx_partial_reseller (reseller_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Exists/created resell_partial_adjustment_history\n";
    echo "NOTE15 Stage 2 migration complete. No balances changed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'NOTE15 Stage 2 migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
