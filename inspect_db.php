<?php
require_once 'config/config.php';

try {
    $stmt = $pdo->query("DESCRIBE affiliate_orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in affiliate_orders:\n";
    foreach ($columns as $col) {
        echo "{$col['Field']} - Type: {$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']}, Default: {$col['Default']}, Extra: {$col['Extra']}\n";
    }
} catch (PDOException $e) {
    echo "Error describing table: " . $e->getMessage();
}
