<?php
/**
 * Kilagbo Reseller - Get Product Details (AJAX)
 */

require_once __DIR__ . '/config.php';

// Ensure reseller is logged in
if (!isResellerLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$productId = intval($_GET['id'] ?? 0);

if (!$productId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT rp.*, c.name as category_name FROM resell_products rp LEFT JOIN categories c ON rp.category_id = c.id WHERE rp.id = ? AND rp.status = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Format gallery images
    $gallery = [];
    if (!empty($product['gallery'])) {
        $gallery = json_decode($product['gallery'], true);
        if (!is_array($gallery)) $gallery = [];
    }

    // Fetch variations if the product has them
    $variations = [];
    if ($product['has_variations']) {
        $vStmt = $pdo->prepare("SELECT attribute_name, attribute_value FROM resell_product_variations WHERE resell_product_id = ?");
        $vStmt->execute([$productId]);
        $rawVariations = $vStmt->fetchAll();
        
        // Group variations by attribute name
        foreach ($rawVariations as $var) {
            $variations[$var['attribute_name']][] = $var['attribute_value'];
        }
    }

    // Format data for JSON response
    $data = [
        'success' => true,
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'short_description' => $product['short_description'],
            'image' => imageUrl($product['image']),
            'gallery' => array_map(function($img) { return imageUrl($img); }, $gallery),
            'reseller_price' => formatPrice($product['reseller_price']),
            'suggested_selling_price' => $product['suggested_selling_price'] ? formatPrice($product['suggested_selling_price']) : null,
            'category' => $product['category_name'],
            'stock_status' => ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) ? 'out_of_stock' : 'in_stock',
            'has_variations' => (bool)$product['has_variations'],
            'variations' => $variations,
            'video_url' => $product['video_url'],
            'order_url' => baseUrl('resell/order.php?product=' . $product['id'])
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($data);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
