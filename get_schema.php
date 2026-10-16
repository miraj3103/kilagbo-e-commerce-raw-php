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
    
    $output = "WHOLESALE_PRODUCTS COLUMNS:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wholesale_products");
    foreach($stmt->fetchAll() as $row) {
        $output .= $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    file_put_contents('schema_output.txt', $output);

} catch (PDOException $e) {
    file_put_contents('schema_output.txt', "Error: " . $e->getMessage());
}
