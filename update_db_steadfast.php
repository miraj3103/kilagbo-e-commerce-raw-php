<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=kilagbo', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = ['resell_orders', 'affiliate_orders'];
    $columns = ['steadfast_consignment_id', 'steadfast_tracking_code'];

    foreach ($tables as $table) {
        foreach ($columns as $column) {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
            $stmt->execute([$column]);
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column VARCHAR(255) DEFAULT NULL");
                echo "Added $column to $table\n";
            } else {
                echo "$column already exists in $table\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
