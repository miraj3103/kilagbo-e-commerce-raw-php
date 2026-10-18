<?php
/**
 * Kilagbo Reseller - Cart AJAX Handler
 * NOTE 05
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isResellerLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'লগইন প্রয়োজন'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $resellerId = (int)getResellerId();
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $variationInput = $_POST['variation'] ?? null;

    if (!$productId) {
        throw new Exception('পণ্য নির্বাচন করা হয়নি');
    }

    $stmt = $pdo->prepare("SELECT * FROM resell_products WHERE id = ? AND status = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('পণ্য পাওয়া যায়নি');
    }

    if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) {
        throw new Exception('পণ্যটি স্টকে নেই');
    }

    $variation = [];
    if ($variationInput !== null && $variationInput !== '') {
        if (is_array($variationInput)) {
            $variation = $variationInput;
        } else {
            $decoded = json_decode((string)$variationInput, true);
            if (is_array($decoded)) {
                $variation = $decoded;
            }
        }
    }

    if (!empty($product['has_variations'])) {
        $vStmt = $pdo->prepare("SELECT attribute_name, attribute_value FROM resell_product_variations WHERE resell_product_id = ? ORDER BY attribute_name, id");
        $vStmt->execute([$productId]);
        $raw = $vStmt->fetchAll();
        $allowed = [];
        foreach ($raw as $row) {
            $allowed[$row['attribute_name']][] = $row['attribute_value'];
        }

        foreach ($allowed as $attribute => $values) {
            if (!array_key_exists($attribute, $variation) || !in_array($variation[$attribute], $values, true)) {
                throw new Exception('সব ভেরিয়েশন সঠিকভাবে নির্বাচন করুন');
            }
        }
        $variation = array_intersect_key($variation, $allowed);
        ksort($variation);
    } else {
        $variation = [];
    }

    $variationJson = $variation ? json_encode($variation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $variationHash = hash('sha256', $variationJson ?? '');

    $check = $pdo->prepare("SELECT id, quantity FROM resell_cart WHERE reseller_id = ? AND resell_product_id = ? AND variation_hash = ?");
    $check->execute([$resellerId, $productId, $variationHash]);
    $existing = $check->fetch();
    $newQuantity = $quantity + ($existing ? (int)$existing['quantity'] : 0);

    if ($product['stock_quantity'] !== null && $newQuantity > (int)$product['stock_quantity']) {
        throw new Exception('স্টকে সর্বোচ্চ ' . (int)$product['stock_quantity'] . ' পিস আছে');
    }

    if ($existing) {
        $pdo->prepare("UPDATE resell_cart SET quantity = ?, variation = ?, updated_at = NOW() WHERE id = ? AND reseller_id = ?")
            ->execute([$newQuantity, $variationJson, $existing['id'], $resellerId]);
    } else {
        $pdo->prepare("INSERT INTO resell_cart (reseller_id, resell_product_id, variation, variation_hash, quantity) VALUES (?, ?, ?, ?, ?)")
            ->execute([$resellerId, $productId, $variationJson, $variationHash, $quantity]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'পণ্য কার্টে যোগ হয়েছে',
        'cartCount' => getResellerCartCount($resellerId)
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
