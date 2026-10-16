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
    
    $stmt = $pdo->query("DESCRIBE notifications");
    $rows = $stmt->fetchAll();
    file_put_contents('notif_schema.txt', var_export($rows, true));

} catch (PDOException $e) {
    file_put_contents('notif_schema.txt', "Error: " . $e->getMessage());
}
