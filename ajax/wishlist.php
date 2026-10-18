<?php
/**
 * Kilagbo - Wishlist AJAX Handler
 * Developed by Webnify Bangladesh
 */

require_once '../config/config.php';
require_once __DIR__ . '/../wholesale/includes/access.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$productId = intval($_POST['product_id'] ?? 0);
$section = sanitize($_POST['section'] ?? 'retail');

// Validate section
if (!in_array($section, ['retail', 'wholesale'])) {
    $section = 'retail';
}

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'পণ্য আইডি প্রয়োজন']);
    exit;
}

if ($section === 'wholesale' && !wholesaleIsApproved($pdo)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'পাইকারি পণ্য দেখতে অনুমোদন প্রয়োজন।']);
    exit;
}

// Verify product exists based on section
if ($section === 'wholesale') {
    $productStmt = $pdo->prepare("SELECT id, name FROM wholesale_products WHERE id = ? AND status = 1");
} else {
    $productStmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND status = 1");
}
$productStmt->execute([$productId]);
$product = $productStmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'পণ্য পাওয়া যায়নি']);
    exit;
}

$sessionId = getSessionId();
$userId = $_SESSION['user_id'] ?? null;

// Check if already in wishlist (section-aware)
if ($userId) {
    $checkStmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ? AND section = ?");
    $checkStmt->execute([$userId, $productId, $section]);
} else {
    $checkStmt = $pdo->prepare("SELECT id FROM wishlist WHERE session_id = ? AND product_id = ? AND section = ?");
    $checkStmt->execute([$sessionId, $productId, $section]);
}
$existing = $checkStmt->fetch();

if ($existing) {
    // Remove from wishlist
    $pdo->prepare("DELETE FROM wishlist WHERE id = ?")->execute([$existing['id']]);
    $message = 'উইশলিস্ট থেকে সরানো হয়েছে';
} else {
    // Add to wishlist with section
    $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, session_id, product_id, section) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $productId, $section]);
    $message = 'উইশলিস্টে যোগ হয়েছে';
}

$wishlistCount = getWishlistCount();

echo json_encode([
    'success' => true,
    'message' => $message,
    'wishlistCount' => $wishlistCount
]);
