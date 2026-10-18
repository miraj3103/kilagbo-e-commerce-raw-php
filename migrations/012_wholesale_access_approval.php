<?php
/** NOTE 12: idempotent wholesale customer approval schema migration. */
require_once __DIR__ . '/../config/config.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wholesale_access_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_wholesale_request_user (user_id),
        KEY idx_wholesale_request_status (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "NOTE 12 migration complete: wholesale_access_requests ready.\n";
    echo "Existing retail customers are unchanged; wholesale access requires approval.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "NOTE 12 migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
