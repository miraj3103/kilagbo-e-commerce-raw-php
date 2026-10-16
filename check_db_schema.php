<?php
// Set server name for config.php to pick local config
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/config/config.php';
$tables = ['seller_products', 'products', 'resell_products', 'wholesale_products', 'affiliate_products'];
foreach ($tables as $table) {
    echo "\n--- Table: $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        if (strpos(strtolower($row['Field']), 'price') !== false) {
            echo "Field: {$row['Field']}, Type: {$row['Type']}\n";
        }
    }
}
