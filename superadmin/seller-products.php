<?php
/**
 * Kilagbo Admin - Seller Products Management
 * View seller products, set prices, and add to sections
 */

require_once __DIR__ . '/../config/config.php';
requireAdmin();

$pageTitle = 'সেলার পণ্য সমূহ';
$currentPage = 'seller-products';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = intval($_POST['product_id'] ?? 0);
    
    if ($productId > 0) {
        // Get product info
        $stmt = $pdo->prepare("SELECT sp.*, s.name as seller_name, s.id as seller_id FROM seller_products sp JOIN sellers s ON sp.seller_id = s.id WHERE sp.id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            if ($action === 'add_reseller') {
                $resellerPrice = floatval($_POST['reseller_price'] ?? 0);
                if ($resellerPrice > 0) {
                    // Add to resell_products table
                    $slug = $product['slug'] . '-resell-' . time();
                    $stmt = $pdo->prepare("
                        INSERT INTO resell_products (name, slug, description, original_price, reseller_price, image, gallery, category_id, stock_quantity, status, seller_id, seller_product_id, has_variations)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $product['name'], $slug, $product['description'],
                        $product['seller_price'], $resellerPrice,
                        $product['image'], $product['gallery'],
                        $product['category_id'], $product['stock'],
                        $product['seller_id'], $productId, $product['has_variations']
                    ]);
                    $newProductId = $pdo->lastInsertId();
                    
                    // Copy variations if any
                    if ($product['has_variations']) {
                        try {
                            $variations = $pdo->prepare("SELECT * FROM seller_product_variations WHERE seller_product_id = ?");
                            $variations->execute([$productId]);
                            foreach ($variations->fetchAll() as $var) {
                                $pdo->prepare("INSERT INTO resell_product_variations (resell_product_id, attribute_name, attribute_value, price_adjustment, stock) VALUES (?, ?, ?, ?, ?)")
                                    ->execute([$newProductId, $var['attribute_name'], $var['attribute_value'], $var['price_adjustment'], $var['stock']]);
                            }
                        } catch (Exception $e) {
                            // Variations table might not exist, continue
                        }
                    }
                    
                    // Update seller product
                    $pdo->prepare("UPDATE seller_products SET reseller_price = ?, reseller_added = 1, status = 'approved' WHERE id = ?")
                        ->execute([$resellerPrice, $productId]);
                    
                    // Log activity
                    $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, seller_product_id, description) VALUES ('product_added', ?, ?, ?)")
                        ->execute([$product['seller_id'], $productId, "পণ্য রিসেলার সেকশনে যোগ করা হয়েছে - ৳$resellerPrice"]);
                    
                    setFlash('success', 'পণ্য সফলভাবে রিসেলার সেকশনে যোগ হয়েছে।');
                }
            } elseif ($action === 'add_wholesale') {
                $wholesalePrice = floatval($_POST['wholesale_price'] ?? 0);
                $wholesaleMinQty = intval($_POST['wholesale_min_qty'] ?? 1);
                if ($wholesalePrice > 0) {
                    // Add to wholesale_products table
                    $slug = $product['slug'] . '-wholesale-' . time();
                    $stmt = $pdo->prepare("
                        INSERT INTO wholesale_products (name, slug, description, wholesale_price, min_order_qty, image, gallery, video_url, category_id, stock, status, seller_id, seller_product_id, has_variations)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $product['name'], $slug, $product['description'],
                        $wholesalePrice, $wholesaleMinQty,
                        $product['image'], $product['gallery'], $product['video_url'],
                        $product['category_id'], $product['stock'],
                        $product['seller_id'], $productId, $product['has_variations']
                    ]);
                    $newProductId = $pdo->lastInsertId();
                    
                    // Copy variations if any
                    if ($product['has_variations']) {
                        try {
                            $variations = $pdo->prepare("SELECT * FROM seller_product_variations WHERE seller_product_id = ?");
                            $variations->execute([$productId]);
                            foreach ($variations->fetchAll() as $var) {
                                $pdo->prepare("INSERT INTO wholesale_product_variations (wholesale_product_id, attribute_name, attribute_value, price_adjustment, stock) VALUES (?, ?, ?, ?, ?)")
                                    ->execute([$newProductId, $var['attribute_name'], $var['attribute_value'], $var['price_adjustment'], $var['stock']]);
                            }
                        } catch (Exception $e) {
                            // Variations table might not exist, continue
                        }
                    }
                    
                    // Update seller product
                    $pdo->prepare("UPDATE seller_products SET wholesale_price = ?, wholesale_min_qty = ?, wholesale_added = 1, status = 'approved' WHERE id = ?")
                        ->execute([$wholesalePrice, $wholesaleMinQty, $productId]);
                    
                    // Log activity
                    $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, seller_product_id, description) VALUES ('product_added', ?, ?, ?)")
                        ->execute([$product['seller_id'], $productId, "পণ্য পাইকারি সেকশনে যোগ করা হয়েছে - ৳$wholesalePrice (ন্যূনতম: $wholesaleMinQty)"]);
                    
                    setFlash('success', 'পণ্য সফলভাবে পাইকারি সেকশনে যোগ হয়েছে।');
                }
            } elseif ($action === 'add_retail') {
                $retailPrice = floatval($_POST['retail_price'] ?? 0);
                if ($retailPrice > 0) {
                    // Add to products table (retail)
                    $slug = $product['slug'] . '-retail-' . time();
                    $stmt = $pdo->prepare("
                        INSERT INTO products (name, slug, description, regular_price, image, gallery, video_url, category_id, has_variations, stock_quantity, status, seller_id, seller_product_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                    ");
                    $stmt->execute([
                        $product['name'], $slug, $product['description'],
                        $retailPrice,
                        $product['image'], $product['gallery'], $product['video_url'],
                        $product['category_id'], $product['has_variations'], $product['stock'],
                        $product['seller_id'], $productId
                    ]);
                    $newProductId = $pdo->lastInsertId();
                    
                    // Copy variations if any
                    if ($product['has_variations']) {
                        try {
                            $variations = $pdo->prepare("SELECT * FROM seller_product_variations WHERE seller_product_id = ?");
                            $variations->execute([$productId]);
                            foreach ($variations->fetchAll() as $var) {
                                $pdo->prepare("INSERT INTO product_variations (product_id, attribute_name, attribute_value, price_adjustment, stock) VALUES (?, ?, ?, ?, ?)")
                                    ->execute([$newProductId, $var['attribute_name'], $var['attribute_value'], $var['price_adjustment'], $var['stock']]);
                            }
                        } catch (Exception $e) {
                            // Variations table might not exist, continue
                        }
                    }
                    
                    // Update seller product
                    $pdo->prepare("UPDATE seller_products SET retail_price = ?, retail_added = 1, status = 'approved' WHERE id = ?")
                        ->execute([$retailPrice, $productId]);
                    
                    // Log activity
                    $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, seller_product_id, description) VALUES ('product_added', ?, ?, ?)")
                        ->execute([$product['seller_id'], $productId, "পণ্য খুচরা সেকশনে যোগ করা হয়েছে - ৳$retailPrice"]);
                    
                    setFlash('success', 'পণ্য সফলভাবে খুচরা সেকশনে যোগ হয়েছে।');
                }
            } elseif ($action === 'buy_now') {
                $quantity = intval($_POST['quantity'] ?? 1);
                $minQty = $product['minimum_quantity'] ?: 1;
                
                if ($quantity >= $minQty) {
                    $totalAmount = $product['seller_price'] * $quantity;
                    $orderNumber = 'SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    
                    // Create order
                    $stmt = $pdo->prepare("
                        INSERT INTO seller_orders (order_number, seller_id, seller_product_id, product_name, quantity, unit_price, total_amount, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $orderNumber, $product['seller_id'], $productId,
                        $product['name'], $quantity, $product['seller_price'], $totalAmount
                    ]);
                    $orderId = $pdo->lastInsertId();
                    
                    // Log activity
                    $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, seller_order_id, seller_product_id, amount, quantity, description, details) VALUES ('purchase', ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([
                            $product['seller_id'], $orderId, $productId, $totalAmount, $quantity,
                            "সেলার থেকে পণ্য ক্রয় - {$product['name']}",
                            json_encode(['order_number' => $orderNumber, 'seller_name' => $product['seller_name']])
                        ]);
                    
                    setFlash('success', "অর্ডার সফল! অর্ডার নম্বর: $orderNumber, মোট: ৳" . number_format($totalAmount));
                } else {
                    setFlash('error', "সর্বনিম্ন অর্ডার পরিমাণ $minQty পিস");
                }
            } elseif ($action === 'update_reseller') {
                $resellerPrice = floatval($_POST['reseller_price'] ?? 0);
                if ($resellerPrice > 0) {
                    $pdo->prepare("UPDATE seller_products SET reseller_price = ? WHERE id = ?")
                        ->execute([$resellerPrice, $productId]);
                        
                    // Propagate to resell_products
                    $slugPattern = $product['slug'] . '-resell-%';
                    $pdo->prepare("UPDATE resell_products SET reseller_price = ? WHERE slug LIKE ?")
                        ->execute([$resellerPrice, $slugPattern]);
                        
                    setFlash('success', 'রিসেলার প্রাইস আপডেট হয়েছে।');
                }
            } elseif ($action === 'update_wholesale') {
                $wholesalePrice = floatval($_POST['wholesale_price'] ?? 0);
                $wholesaleMinQty = intval($_POST['wholesale_min_qty'] ?? 1);
                if ($wholesalePrice > 0) {
                    $pdo->prepare("UPDATE seller_products SET wholesale_price = ?, wholesale_min_qty = ? WHERE id = ?")
                        ->execute([$wholesalePrice, $wholesaleMinQty, $productId]);
                        
                    // Propagate to wholesale_products
                    $slugPattern = $product['slug'] . '-wholesale-%';
                    $pdo->prepare("UPDATE wholesale_products SET wholesale_price = ?, min_order_qty = ? WHERE slug LIKE ?")
                        ->execute([$wholesalePrice, $wholesaleMinQty, $slugPattern]);
                        
                    setFlash('success', 'পাইকারি প্রাইস আপডেট হয়েছে।');
                }
            } elseif ($action === 'update_retail') {
                $retailPrice = floatval($_POST['retail_price'] ?? 0);
                if ($retailPrice > 0) {
                    $pdo->prepare("UPDATE seller_products SET retail_price = ? WHERE id = ?")
                        ->execute([$retailPrice, $productId]);
                        
                    // Propagate to products (retail)
                    $slugPattern = $product['slug'] . '-retail-%';
                    $pdo->prepare("UPDATE products SET regular_price = ? WHERE slug LIKE ?")
                        ->execute([$retailPrice, $slugPattern]);
                        
                    setFlash('success', 'খুচরা প্রাইস আপডেট হয়েছে।');
                }
            } elseif ($action === 'reject') {
                $reason = sanitize($_POST['reason'] ?? '');
                $pdo->prepare("UPDATE seller_products SET status = 'rejected', admin_notes = ? WHERE id = ?")
                    ->execute([$reason, $productId]);
                
                $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, seller_product_id, description) VALUES ('product_rejected', ?, ?, ?)")
                    ->execute([$product['seller_id'], $productId, "পণ্য প্রত্যাখ্যান: $reason"]);
                
                setFlash('success', 'পণ্য প্রত্যাখ্যান করা হয়েছে।');
            } elseif ($action === 'delete') {
                $slug = $product['slug'];
                
                // Delete from all sections
                $pdo->prepare("DELETE FROM products WHERE slug LIKE ?")->execute([$slug . '-retail-%']);
                $pdo->prepare("DELETE FROM wholesale_products WHERE slug LIKE ?")->execute([$slug . '-wholesale-%']);
                $pdo->prepare("DELETE FROM resell_products WHERE slug LIKE ?")->execute([$slug . '-resell-%']);
                
                // Delete from seller_products
                $pdo->prepare("DELETE FROM seller_products WHERE id = ?")->execute([$productId]);
                
                setFlash('success', 'পণ্য ও সম্পর্কিত সকল লিস্টিং সফলভাবে মুছে ফেলা হয়েছে।');
            }
        }
    }
    
    redirect(adminUrl('seller-products.php' . (isset($_GET['seller_id']) ? '?seller_id=' . intval($_GET['seller_id']) : '')));
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
    $productIds = $_POST['product_ids'] ?? [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $pdo->prepare("DELETE FROM seller_products WHERE id IN ($placeholders)")->execute($productIds);
        setFlash('success', count($productIds) . ' টি পণ্য মুছে ফেলা হয়েছে।');
    }
    redirect(adminUrl('seller-products.php'));
}

// Filters
$sellerId = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : null;
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($sellerId) {
    $where[] = "sp.seller_id = ?";
    $params[] = $sellerId;
}

if ($status !== 'all') {
    $where[] = "sp.status = ?";
    $params[] = $status;
}

if ($search) {
    $where[] = "(sp.name LIKE ? OR s.name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get products
$sql = "SELECT sp.*, s.name as seller_name, s.business_name, s.phone as seller_phone
        FROM seller_products sp 
        JOIN sellers s ON sp.seller_id = s.id 
        $whereClause 
        ORDER BY sp.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get seller info if filtered
$sellerInfo = null;
if ($sellerId) {
    $stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
    $stmt->execute([$sellerId]);
    $sellerInfo = $stmt->fetch();
}

// Get counts
$totalProducts = $pdo->query("SELECT COUNT(*) FROM seller_products")->fetchColumn();
$pendingProducts = $pdo->query("SELECT COUNT(*) FROM seller_products WHERE status = 'pending'")->fetchColumn();

require_once 'includes/header.php';
?>

<style>
    .product-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .product-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .price-cell {
        white-space: nowrap;
    }
    
    .price-form {
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    
    .price-form input[type="number"] {
        width: 70px;
        padding: 3px 6px;
        font-size: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .price-form .btn-price {
        padding: 3px 6px;
        font-size: 0.7rem;
        border-radius: 4px;
        line-height: 1;
    }
    
    .wholesale-form {
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    
    .wholesale-form .inputs {
        display: inline-flex;
        gap: 2px;
    }
    
    .wholesale-form input[type="number"] {
        padding: 3px 4px;
        font-size: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .wholesale-form .price-input {
        width: 55px;
    }
    
    .wholesale-form .qty-input {
        width: 35px;
    }
    
    .wholesale-form .btn-price {
        padding: 3px 6px;
        font-size: 0.7rem;
        border-radius: 4px;
        line-height: 1;
    }
    
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
    }
    
    .status-pending { background: #fef3cd; color: #856404; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    
    .filter-tabs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .filter-tab {
        padding: 8px 16px;
        border-radius: 20px;
        text-decoration: none;
        color: #666;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }
    
    .filter-tab:hover, .filter-tab.active {
        background: #000;
        color: #fff;
    }
    
    .seller-info-banner {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
</style>

<?php if ($sellerInfo): ?>
<div class="seller-info-banner d-flex justify-content-between align-items-center">
    <div>
        <h5 class="mb-1"><?php echo sanitize($sellerInfo['name']); ?></h5>
        <small><?php echo sanitize($sellerInfo['business_name']); ?> | <?php echo sanitize($sellerInfo['phone']); ?></small>
    </div>
    <a href="<?php echo adminUrl('seller-products'); ?>" class="btn btn-light btn-sm">সব পণ্য দেখুন</a>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(0,0,0,0.1); color: #000;">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-value"><?php echo $totalProducts; ?></div>
            <div class="stat-label">মোট পণ্য</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?php echo $pendingProducts; ?></div>
            <div class="stat-label">পেন্ডিং পণ্য</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <!-- Filters -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div class="filter-tabs">
            <a href="?status=all<?php echo $sellerId ? "&seller_id=$sellerId" : ''; ?>" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">সব</a>
            <a href="?status=pending<?php echo $sellerId ? "&seller_id=$sellerId" : ''; ?>" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">পেন্ডিং</a>
            <a href="?status=approved<?php echo $sellerId ? "&seller_id=$sellerId" : ''; ?>" class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">অনুমোদিত</a>
        </div>
        
        <form method="GET" class="d-flex gap-2">
            <?php if ($sellerId): ?><input type="hidden" name="seller_id" value="<?php echo $sellerId; ?>"><?php endif; ?>
            <input type="hidden" name="status" value="<?php echo $status; ?>">
            <input type="text" name="search" class="form-control" placeholder="পণ্য খুঁজুন..." value="<?php echo sanitize($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-admin"><i class="bi bi-search"></i></button>
        </form>
    </div>
    

    <!-- Products List -->
    <?php if (empty($products)): ?>
    <div class="empty-state">
        <i class="bi bi-box-seam"></i>
        <h4>কোনো পণ্য পাওয়া যায়নি</h4>
        <p>ফিল্টার পরিবর্তন করে আবার চেষ্টা করুন</p>
    </div>
    <?php else: ?>
    
    <!-- Bulk Actions -->
    <form method="POST" id="bulkForm">
        <input type="hidden" name="bulk_action" value="delete">
        
        <div class="bulk-action-bar" id="bulkActionBar" style="display: none;">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark ms-2"><span id="selectedCount">0</span> টি নির্বাচিত</span>
                </div>
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('নির্বাচিত পণ্যগুলো মুছে ফেলতে চান?')">
                    <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
            <div class="form-check">
                <input type="checkbox" id="selectAll" class="form-check-input" onclick="toggleSelectAll()">
                <label for="selectAll" class="form-check-label fw-bold">সব সিলেক্ট করুন</label>
            </div>
        </div>
    
        <div class="product-list-container">
            <?php foreach ($products as $product): ?>
            <div class="admin-product-card">
                <div class="card-header-section d-flex justify-content-between align-items-start">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" class="form-check-input product-checkbox" form="bulkForm" onchange="updateBulkBtn()">
                        </div>
                        <div class="product-img-wrapper">
                            <?php if ($product['image']): ?>
                            <img src="<?php echo $product['image']; ?>" alt="">
                            <?php else: ?>
                            <div class="placeholder-img"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info-main">
                            <h5 class="product-title"><?php echo sanitize($product['name']); ?></h5>
                            <div class="d-flex gap-3 text-muted small mt-1">
                                <span><i class="bi bi-shop me-1"></i><?php echo sanitize($product['seller_name']); ?></span>
                                <span><i class="bi bi-box-seam me-1"></i>স্টক: <span class="badge <?php echo ($product['stock'] === null || $product['stock'] > 0) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                                    <?php echo ($product['stock'] === null || $product['stock'] === -1) ? 'Unlimited' : (($product['stock'] == 0) ? 'Out of Stock' : $product['stock']); ?>
                                </span></span>
                            </div>
                        </div>
                    </div>
                    <div class="status-wrapper">
                        <?php if ($product['status'] === 'pending'): ?>
                        <span class="status-badge status-pending"><i class="bi bi-clock me-1"></i>পেন্ডিং</span>
                        <?php elseif ($product['status'] === 'approved'): ?>
                        <span class="status-badge status-approved"><i class="bi bi-check-circle me-1"></i>অনুমোদিত</span>
                        <?php else: ?>
                        <span class="status-badge status-rejected"><i class="bi bi-x-circle me-1"></i>প্রত্যাখ্যাত</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body-section">
                    <div class="row align-items-end g-3">
                        <!-- Seller Price (Read Only) -->
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label-sm text-muted">সেলার প্রাইস</label>
                            <div class="price-display-box">
                                <?php echo formatPrice($product['seller_price']); ?>
                            </div>
                        </div>

                        <!-- Reseller Price -->
                        <div class="col-md-3 col-sm-6">
                            <form method="POST" class="price-update-form">
                                <input type="hidden" name="action" value="<?php echo $product['reseller_added'] ? 'update_reseller' : 'add_reseller'; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <label class="form-label-sm <?php echo $product['reseller_added'] ? 'text-primary' : 'text-muted'; ?>">
                                    <i class="bi bi-shop me-1"></i>রিসেলার প্রাইস
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" name="reseller_price" class="form-control" placeholder="0.00" step="0.01" value="<?php echo $product['reseller_price'] ?: ''; ?>" required>
                                    <button type="submit" class="btn <?php echo $product['reseller_added'] ? 'btn-primary' : 'btn-outline-primary'; ?>" title="সেভ করুন">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Wholesale Price -->
                        <div class="col-md-4 col-sm-12">
                            <form method="POST" class="price-update-form">
                                <input type="hidden" name="action" value="<?php echo $product['wholesale_added'] ? 'update_wholesale' : 'add_wholesale'; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <label class="form-label-sm <?php echo $product['wholesale_added'] ? 'text-warning' : 'text-muted'; ?>">
                                    <i class="bi bi-box me-1"></i>পাইকারি (দাম + নূন্যতম)
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" name="wholesale_price" class="form-control" placeholder="দাম" step="0.01" value="<?php echo $product['wholesale_price'] ?: ''; ?>" required>
                                    <span class="input-group-text bg-white border-end-0">Min</span>
                                    <input type="number" name="wholesale_min_qty" class="form-control" placeholder="Qty" value="<?php echo $product['wholesale_min_qty'] ?: 1; ?>" min="1" style="max-width: 60px;">
                                    <button type="submit" class="btn <?php echo $product['wholesale_added'] ? 'btn-warning text-dark' : 'btn-outline-warning text-dark'; ?>" title="সেভ করুন">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Retail Price -->
                        <div class="col-md-3 col-sm-6">
                            <form method="POST" class="price-update-form">
                                <input type="hidden" name="action" value="<?php echo $product['retail_added'] ? 'update_retail' : 'add_retail'; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <label class="form-label-sm <?php echo $product['retail_added'] ? 'text-success' : 'text-muted'; ?>">
                                    <i class="bi bi-bag me-1"></i>খুচরা প্রাইস
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" name="retail_price" class="form-control" placeholder="0.00" step="0.01" value="<?php echo $product['retail_price'] ?: ''; ?>" required>
                                    <button type="submit" class="btn <?php echo $product['retail_added'] ? 'btn-success' : 'btn-outline-success'; ?>" title="সেভ করুন">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card-footer-section d-flex justify-content-between align-items-center bg-light px-3 py-2 rounded-bottom">
                    <div class="small text-muted">
                        <?php echo date('d M, Y h:i A', strtotime($product['created_at'])); ?>
                    </div>
                    <div class="action-buttons d-flex gap-2">
                         <button type="button" class="btn btn-sm btn-light border text-dark" onclick="showBuyModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" title="সেলার থেকে কিনুন">
                            <i class="bi bi-cart-plus me-1"></i>কিনুন
                        </button>
                        
                        <?php if ($product['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="showRejectModal(<?php echo $product['id']; ?>)" title="প্রত্যাখ্যান করুন">
                            <i class="bi bi-x-circle me-1"></i>রিজেক্ট
                        </button>
                        <?php endif; ?>

                        <form method="POST" onsubmit="return confirm('আপনি কি এই পণ্যটি মুছতে চান? এটি সকল সেকশন থেকে মুছে যাবে।');" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-light border text-danger hover-danger" title="মুছে ফেলুন">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<style>
    /* New Styles for Product List */
    .admin-product-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        transition: all 0.2s ease;
    }

    .admin-product-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        border-color: #d1d5db;
    }

    .card-header-section {
        padding: 15px;
        border-bottom: 1px solid #f3f4f6;
    }

    .card-body-section {
        padding: 15px;
    }

    .product-img-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
        border: 1px solid #eee;
    }

    .product-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-img-wrapper .placeholder-img {
        width: 100%;
        height: 100%;
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
    }

    .product-title {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        margin: 0;
        line-height: 1.4;
    }

    .status-badge {
        font-size: 0.75rem;
        padding: 5px 10px;
        border-radius: 20px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
    }
    
    .status-pending { background-color: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .status-approved { background-color: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
    .status-rejected { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

    .price-display-box {
        font-size: 1.1rem;
        font-weight: 700;
        color: #059669;
        padding: 5px 0;
    }

    .form-label-sm {
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 4px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bulk-action-bar {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #1f2937;
        color: white;
        padding: 10px 20px;
        border-radius: 50px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 1000;
        width: auto;
        min-width: 300px;
    }

    .hover-danger:hover {
        background-color: #fee2e2 !important;
        border-color: #ef4444 !important;
        color: #ef4444 !important;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border-radius: 12px;
        border: 1px dashed #e5e7eb;
    }
    
    .empty-state i {
        font-size: 3rem;
        color: #d1d5db;
        margin-bottom: 15px;
        display: block;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .card-header-section {
            flex-direction: column;
            gap: 10px;
        }
        .status-wrapper {
            width: 100%;
            text-align: right;
        }
    }
</style>
</div>

<!-- Buy Modal -->
<div class="modal fade" id="buyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="buy_now">
                <input type="hidden" name="product_id" id="buyProductId">
                <div class="modal-header">
                    <h5 class="modal-title">সেলার থেকে কিনুন</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">পণ্য</label>
                        <p class="mb-0" id="buyProductName"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">সেলার</label>
                        <p class="mb-0" id="buySellerName"></p>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">একক দাম</label>
                            <p class="mb-0 fw-bold text-success" id="buyUnitPrice"></p>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">সর্বনিম্ন অর্ডার</label>
                            <p class="mb-0" id="buyMinQty"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">পরিমাণ</label>
                        <input type="number" name="quantity" id="buyQuantity" class="form-control" min="1" value="1" required onchange="calculateTotal()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">মোট</label>
                        <p class="mb-0 fw-bold fs-5 text-primary" id="buyTotal">৳0</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-admin">
                        <i class="bi bi-cart-check me-2"></i>অর্ডার করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="product_id" id="rejectProductId">
                <div class="modal-header">
                    <h5 class="modal-title">পণ্য প্রত্যাখ্যান</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">প্রত্যাখ্যানের কারণ</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="কেন প্রত্যাখ্যান করছেন..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-danger">প্রত্যাখ্যান করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentProduct = null;

function showBuyModal(product) {
    currentProduct = product;
    document.getElementById('buyProductId').value = product.id;
    document.getElementById('buyProductName').textContent = product.name;
    document.getElementById('buySellerName').textContent = product.seller_name + ' (' + product.business_name + ')';
    document.getElementById('buyUnitPrice').textContent = '৳' + parseFloat(product.seller_price).toLocaleString();
    document.getElementById('buyMinQty').textContent = product.minimum_quantity + ' পিস';
    document.getElementById('buyQuantity').min = product.minimum_quantity;
    document.getElementById('buyQuantity').value = product.minimum_quantity;
    calculateTotal();
    new bootstrap.Modal(document.getElementById('buyModal')).show();
}

function calculateTotal() {
    if (currentProduct) {
        const qty = parseInt(document.getElementById('buyQuantity').value) || 0;
        const total = qty * parseFloat(currentProduct.seller_price);
        document.getElementById('buyTotal').textContent = '৳' + total.toLocaleString();
    }
}

function showRejectModal(productId) {
    document.getElementById('rejectProductId').value = productId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkBtn();
}

function updateBulkBtn() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const btn = document.getElementById('bulkDeleteBtn');
    const count = document.getElementById('selectedCount');
    if (checkboxes.length > 0) {
        btn.style.display = 'inline-block';
        count.textContent = checkboxes.length;
    } else {
        btn.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
