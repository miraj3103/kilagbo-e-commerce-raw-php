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
    
    $stmt = $pdo->query("SHOW CREATE TABLE wholesale_order_items");
    file_put_contents('wholesale_order_items_create.txt', $stmt->fetch(PDO::FETCH_ASSOC)['Create Table']);

} catch (PDOException $e) {
    file_put_contents('schema_error_wholesale.txt', $e->getMessage());
}
