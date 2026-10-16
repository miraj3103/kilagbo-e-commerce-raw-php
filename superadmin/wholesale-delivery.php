<?php
/**
 * Kilagbo - Admin Wholesale Delivery Charges
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পাইকারি ডেলিভারি চার্জ';
$currentPage = 'wholesale-delivery';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Check if table exists
try {
    $pdo->query("SELECT 1 FROM wholesale_delivery_charges LIMIT 1");
} catch (PDOException $e) {
    require_once 'includes/header.php';
    echo '<div class="alert alert-warning">';
    echo '<h5><i class="bi bi-exclamation-triangle me-2"></i>টেবিল তৈরি হয়নি</h5>';
    echo '<p>অনুগ্রহ করে <a href="' . baseUrl('setup.php') . '" class="alert-link">setup.php</a> চালান।</p>';
    echo '</div>';
    require_once 'includes/footer.php';
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add default rate
    if ($action === 'add_default') {
        $deliveryName = sanitize($_POST['delivery_name'] ?? '');
        $deliveryCost = (float)($_POST['delivery_cost'] ?? 0);
        
        if (empty($deliveryName)) {
            setFlash('error', 'ডেলিভারি নাম আবশ্যক');
        } else {
            $pdo->prepare("INSERT INTO wholesale_delivery_charges (delivery_name, delivery_cost, is_default) VALUES (?, ?, 1)")
                ->execute([$deliveryName, $deliveryCost]);
            setFlash('success', 'নতুন ডিফল্ট ডেলিভারি চার্জ যোগ হয়েছে');
        }
        redirect(adminUrl('wholesale-delivery'));
    }

    // Delete default rate
    if ($action === 'delete_default') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM wholesale_delivery_charges WHERE id = ? AND is_default = 1")->execute([$id]);
        setFlash('success', 'ডেলিভারি চার্জ মুছে ফেলা হয়েছে');
        redirect(adminUrl('wholesale-delivery'));
    }
    
    // Add custom product delivery
    if ($action === 'add_custom') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $deliveryName = sanitize($_POST['custom_delivery_name'] ?? '');
        $deliveryCost = (float)($_POST['custom_delivery_cost'] ?? 0);
        
        if (!$productId || empty($deliveryName)) {
            setFlash('error', 'পণ্য এবং ডেলিভারি নাম আবশ্যক');
        } else {
            // Check if already exists for this product
            $checkStmt = $pdo->prepare("SELECT id FROM wholesale_delivery_charges WHERE wholesale_product_id = ?");
            $checkStmt->execute([$productId]);
            if ($checkStmt->fetch()) {
                setFlash('error', 'এই পণ্যের জন্য কাস্টম ডেলিভারি ইতিমধ্যে আছে');
            } else {
                $pdo->prepare("INSERT INTO wholesale_delivery_charges (delivery_name, delivery_cost, is_default, wholesale_product_id) VALUES (?, ?, 0, ?)")
                    ->execute([$deliveryName, $deliveryCost, $productId]);
                setFlash('success', 'কাস্টম ডেলিভারি চার্জ যোগ হয়েছে');
            }
        }
        redirect(adminUrl('wholesale-delivery'));
    }
    
    // Delete custom delivery
    if ($action === 'delete_custom') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM wholesale_delivery_charges WHERE id = ? AND is_default = 0")->execute([$id]);
        setFlash('success', 'কাস্টম ডেলিভারি চার্জ মুছে ফেলা হয়েছে');
        redirect(adminUrl('wholesale-delivery'));
    }
}

// Get default delivery charges
$defaultDeliveries = $pdo->query("SELECT * FROM wholesale_delivery_charges WHERE is_default = 1 AND wholesale_product_id IS NULL ORDER BY created_at ASC")->fetchAll();

// Get custom product deliveries
$customDeliveries = $pdo->query("
    SELECT wdc.*, wp.name as product_name, wp.image as product_image
    FROM wholesale_delivery_charges wdc
    LEFT JOIN wholesale_products wp ON wdc.wholesale_product_id = wp.id
    WHERE wdc.is_default = 0 AND wdc.wholesale_product_id IS NOT NULL
    ORDER BY wdc.created_at DESC
")->fetchAll();

// Get wholesale products for dropdown (excluding ones already with custom delivery)
$existingProductIds = array_column($customDeliveries, 'wholesale_product_id');
$productsQuery = "SELECT id, name, image FROM wholesale_products WHERE status = 1";
if (!empty($existingProductIds)) {
    $productsQuery .= " AND id NOT IN (" . implode(',', $existingProductIds) . ")";
}
$productsQuery .= " ORDER BY name";
$products = $pdo->query($productsQuery)->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <!-- Default Delivery Charge -->
    <div class="col-lg-5 mb-4">
        <div class="admin-card">
            <h5 class="card-title"><i class="bi bi-truck me-2"></i>ডিফল্ট ডেলিভারি চার্জ</h5>
            <p class="text-muted small">এই চার্জগুলো পাইকারি চেকআউটে অপশন হিসেবে থাকবে।</p>
            
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="add_default">
                
                <div class="mb-3">
                    <label class="form-label">ডেলিভারি নাম *</label>
                    <input type="text" name="delivery_name" class="form-control" required
                           placeholder="যেমন: স্ট্যান্ডার্ড ডেলিভারি">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ডেলিভারি খরচ (৳) *</label>
                    <input type="number" name="delivery_cost" class="form-control" step="0.01" required
                           placeholder="0.00">
                </div>
                
                <button type="submit" class="btn btn-admin w-100">
                    <i class="bi bi-plus-lg me-2"></i>যোগ করুন
                </button>
            </form>

            <h6 class="border-bottom pb-2 mb-3">বিদ্যমান চার্জ সমূহ</h6>
            <div class="list-group">
                <?php if (empty($defaultDeliveries)): ?>
                <div class="text-center text-muted py-3">কোনো ডিফল্ট চার্জ নেই</div>
                <?php else: ?>
                <?php foreach ($defaultDeliveries as $dd): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold"><?php echo sanitize($dd['delivery_name']); ?></div>
                        <small class="text-muted">৳<?php echo number_format($dd['delivery_cost'], 2); ?></small>
                    </div>
                    <form method="POST" onsubmit="return confirm('আপনি কি নিশ্চিত?');">
                        <input type="hidden" name="action" value="delete_default">
                        <input type="hidden" name="id" value="<?php echo $dd['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger p-1 px-2">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Custom Product Delivery -->
    <div class="col-lg-7 mb-4">
        <div class="admin-card">
            <h5 class="card-title"><i class="bi bi-plus-circle me-2"></i>কাস্টম ডেলিভারি চার্জ যোগ করুন</h5>
            <p class="text-muted small">নির্দিষ্ট পণ্যের জন্য আলাদা ডেলিভারি চার্জ সেট করুন।</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_custom">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">পণ্য সিলেক্ট করুন *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- পণ্য বাছাই করুন --</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">ডেলিভারি নাম *</label>
                        <input type="text" name="custom_delivery_name" class="form-control" required placeholder="যেমন: বিশেষ ডেলিভারি">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">খরচ (৳) *</label>
                        <input type="number" name="custom_delivery_cost" class="form-control" step="0.01" required placeholder="0">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-admin w-100">
                            <i class="bi bi-plus-lg"></i> যোগ
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom Deliveries List -->
<div class="admin-card">
    <h5 class="card-title"><i class="bi bi-list-ul me-2"></i>কাস্টম ডেলিভারি চার্জ তালিকা</h5>
    
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th width="60">ছবি</th>
                    <th>পণ্যের নাম</th>
                    <th>ডেলিভারি নাম</th>
                    <th>খরচ</th>
                    <th width="100">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customDeliveries)): ?>
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">কোনো কাস্টম ডেলিভারি চার্জ নেই</td>
                </tr>
                <?php else: ?>
                <?php foreach ($customDeliveries as $delivery): ?>
                <tr>
                    <td>
                        <?php if ($delivery['product_image']): ?>
                        <img src="<?php echo imageUrl($delivery['product_image']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                        <?php else: ?>
                        <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 6px;"></div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo $delivery['product_name']; ?></strong></td>
                    <td><?php echo $delivery['delivery_name']; ?></td>
                    <td><strong><?php echo formatPrice($delivery['delivery_cost']); ?></strong></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('মুছে ফেলতে চান?')">
                            <input type="hidden" name="action" value="delete_custom">
                            <input type="hidden" name="id" value="<?php echo $delivery['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
