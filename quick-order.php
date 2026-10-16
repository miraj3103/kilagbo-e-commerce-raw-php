<?php
/**
 * Kilagbo - Quick Order (Direct to Checkout)
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$productId = intval($_GET['id'] ?? 0);
$quantity = max(1, intval($_GET['qty'] ?? 1));

if (!$productId) {
    redirect(baseUrl('shop'));
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect(baseUrl('shop'));
}

$sessionId = getSessionId();
$userId = $_SESSION['user_id'] ?? null;

// Clear cart and add only this product for direct checkout
if ($userId) {
    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
} else {
    $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([$sessionId]);
}

// Add product to cart
$stmt = $pdo->prepare("INSERT INTO cart (user_id, session_id, product_id, variation, quantity) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$userId, $sessionId, $product['id'], null, $quantity]);

// Redirect directly to checkout
redirect(baseUrl('checkout'));
