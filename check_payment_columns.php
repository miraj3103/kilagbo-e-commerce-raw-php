<?php
$host = 'localhost';
$db = 'kilagboc_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->query("DESCRIBE affiliate_orders");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $check = ['payment_method', 'payment_number', 'transaction_id'];
    
    foreach ($check as $col) {
        echo "$col: " . (in_array($col, $columns) ? "YES" : "NO") . "\n";
    }
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage();
}
