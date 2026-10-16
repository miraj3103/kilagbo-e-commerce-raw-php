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
    
    echo "RESELL PRODUCTS:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM resell_products");
    foreach($stmt->fetchAll() as $row) {
        echo $row['Field'] . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
