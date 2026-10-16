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
    
    $stmt = $pdo->query("DESCRIBE orders");
    file_put_contents('orders_desc.txt', var_export($stmt->fetchAll(), true));
    
    $stmt = $pdo->query("DESCRIBE order_items");
    file_put_contents('order_items_desc.txt', var_export($stmt->fetchAll(), true));

} catch (PDOException $e) {
    file_put_contents('schema_error.txt', $e->getMessage());
}
