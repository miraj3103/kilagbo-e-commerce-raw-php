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
    
    $stmt = $pdo->query("SHOW COLUMNS FROM affiliate_orders LIKE 'order_source'");
    $col = $stmt->fetch();
    echo "order_source Default: " . ($col['Default'] ?? 'NULL');
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage();
}
