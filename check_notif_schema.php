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
    
    echo "NOTIFICATIONS COLUMNS:\n";
    $stmt = $pdo->query("DESCRIBE notifications");
    $rows = $stmt->fetchAll();
    var_export($rows);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
