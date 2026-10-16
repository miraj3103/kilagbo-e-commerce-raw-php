<?php
/**
 * Kilagbo - Cart AJAX Handler
 * Developed by Webnify Bangladesh
 */

require_once '../config/config.php';

// Enable error reporting for debugging
ini_set('display_errors', 0); // Keep 0 for JSON response, errors will break JSON
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../php-error.log');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request');
    }

$productId = intval($_POST['product_id'] ?? 0);
$quantity = max(1, intval($_POST['quantity'] ?? 1));
$variation = isset($_POST['variation']) ? json_decode($_POST['variation'], true) : null;
$section = $_POST['section'] ?? 'retail'; // retail or wholesale

if (!$productId) {
    throw new Exception('পণ্য আইডি প্রয়োজন');
}

// Verify product exists based on section
$tableName = 'products';
$priceColumn = 'sale_price, regular_price';

if ($section === 'wholesale') {
    $tableName = 'wholesale_products';
    $productStmt = $pdo->prepare("SELECT id, name, min_order_qty, stock FROM wholesale_products WHERE id = ? AND status = 1");
} elseif ($section === 'affiliate') {
    $tableName = 'affiliate_products';
    $productStmt = $pdo->prepare("SELECT id, name, stock_quantity as stock, retail_product_id, wholesale_product_id FROM affiliate_products WHERE id = ? AND status = 1");
} else {
    $productStmt = $pdo->prepare("SELECT id, name, stock_quantity as stock FROM products WHERE id = ? AND status = 1");
}

$productStmt->execute([$productId]);
$product = $productStmt->fetch();

if (!$product) {
    throw new Exception('পণ্য পাওয়া যায়নি');
}

// Handling min order quantity for affiliate products linked to wholesale
if ($section === 'affiliate' && !empty($product['wholesale_product_id'])) {
    $stmt = $pdo->prepare("SELECT min_order_qty FROM wholesale_products WHERE id = ?");
    $stmt->execute([$product['wholesale_product_id']]);
    $wp = $stmt->fetch();
    if ($wp && $quantity < $wp['min_order_qty']) {
        throw new Exception('সর্বনিম্ন অর্ডার পরিমাণ ' . $wp['min_order_qty'] . ' পিস');
    }
}

// Check minimum order quantity for wholesale
if ($section === 'wholesale' && $quantity < $product['min_order_qty']) {
    throw new Exception('সর্বনিম্ন অর্ডার পরিমাণ ' . $product['min_order_qty'] . ' পিস');
}

// Check stock
if ($product['stock'] !== null && $quantity > $product['stock']) {
    throw new Exception('পর্যাপ্ত স্টক নেই');
}

$sessionId = getSessionId();
$userId = $_SESSION['user_id'] ?? null;
$variationJson = $variation ? json_encode($variation, JSON_UNESCAPED_UNICODE) : null;
$affiliateLinkId = intval($_POST['affiliate_link_id'] ?? 0) ?: null;
$storeId = intval($_POST['store_id'] ?? 0) ?: null;
$affiliatorId = intval($_POST['affiliator_id'] ?? 0) ?: null;

// Check if already in cart
if ($userId) {
    $checkStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND section = ? AND (variation = ? OR (variation IS NULL AND ? IS NULL))");
    $checkStmt->execute([$userId, $productId, $section, $variationJson, $variationJson]);
} else {
    $checkStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ? AND section = ? AND (variation = ? OR (variation IS NULL AND ? IS NULL))");
    $checkStmt->execute([$sessionId, $productId, $section, $variationJson, $variationJson]);
}
$existing = $checkStmt->fetch();

if ($existing) {
    $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")->execute([$quantity, $existing['id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO cart (user_id, session_id, product_id, section, variation, quantity, affiliate_link_id, store_id, affiliator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $productId, $section, $variationJson, $quantity, $affiliateLinkId, $storeId, $affiliatorId]);
}

    $cartCount = getCartCount();

    echo json_encode([
        'success' => true,
        'message' => 'পণ্য কার্টে যোগ হয়েছে',
        'cartCount' => $cartCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
