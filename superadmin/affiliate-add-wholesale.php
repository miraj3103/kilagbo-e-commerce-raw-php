<?php
/**
 * Kilagbo Admin - Add Products to Affiliate from Wholesale
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-products';
$pageTitle = 'পাইকারি থেকে অ্যাফিলিয়েট পণ্য যোগ করুন';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Check if wholesale_product_id column exists, if not create it
$columnExists = false;
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM affiliate_products LIKE 'wholesale_product_id'");
    $columnExists = ($checkColumn && $checkColumn->rowCount() > 0);
} catch (PDOException $e) {
    $columnExists = false;
}

if (!$columnExists) {
    try {
        $pdo->exec("ALTER TABLE affiliate_products ADD COLUMN wholesale_product_id INT(11) NULL DEFAULT NULL AFTER id");
    } catch (PDOException $e) {
        // Column might already exist or other error
    }
    try {
        $pdo->exec("ALTER TABLE affiliate_products ADD INDEX idx_wholesale_product_id (wholesale_product_id)");
    } catch (PDOException $e) {
        // Index might already exist
    }
}

// Handle add to affiliate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_affiliate'])) {
    $productIds = $_POST['product_ids'] ?? [];
    $commissionType = sanitize($_POST['commission_type'] ?? 'percentage');
    $commissionValue = floatval($_POST['commission_value'] ?? 5);
    
    if (!empty($productIds)) {
        $added = 0;
        $skipped = 0;
        
        foreach ($productIds as $productId) {
            $productId = intval($productId);
            
            // Check if already exists (by wholesale_product_id)
            $stmt = $pdo->prepare("SELECT id FROM affiliate_products WHERE wholesale_product_id = ?");
            $stmt->execute([$productId]);
            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }
            
            // Get wholesale product (without category join to avoid table not found error)
            $stmt = $pdo->prepare("SELECT * FROM wholesale_products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product) {
                // Add to affiliate products
                $stmt = $pdo->prepare("
                    INSERT INTO affiliate_products (wholesale_product_id, name, slug, description, image, gallery, category_id, regular_price, sale_price, commission_type, commission_value, stock_quantity, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $product['id'],
                    $product['name'],
                    'aff-ws-' . ($product['slug'] ?? 'product-' . $product['id']),
                    $product['description'] ?? '',
                    $product['image'] ?? '',
                    $product['gallery'] ?? null,
                    $product['category_id'] ?? null,
                    $product['wholesale_price'] ?: ($product['regular_price'] ?? 0),
                    $product['sale_price'] ?? null,
                    $commissionType,
                    $commissionValue,
                    $product['stock'] ?: ($product['min_order_qty'] ?? 1)
                ]);
                $added++;
            }
        }
        
        $message = "$added টি পণ্য অ্যাফিলিয়েটে যোগ করা হয়েছে";
        if ($skipped > 0) {
            $message .= " ($skipped টি আগেই ছিল)";
        }
        setFlash('success', $message);
        redirect(adminUrl('affiliate-products'));
    } else {
        setFlash('error', 'অন্তত একটি পণ্য সিলেক্ট করুন');
    }
}

// Get wholesale products not in affiliate
$products = [];

// Check if wholesale_categories table exists
$wcTableExists = false;
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'wholesale_categories'");
    $wcTableExists = ($checkTable && $checkTable->rowCount() > 0);
} catch (PDOException $e) {
    $wcTableExists = false;
}

// Build query based on table existence
if ($wcTableExists) {
    $sql = "
        SELECT wp.*, wc.name as category_name
        FROM wholesale_products wp
        LEFT JOIN wholesale_categories wc ON wp.category_id = wc.id
        WHERE wp.status = 1 
        ORDER BY wp.created_at DESC
    ";
} else {
    $sql = "
        SELECT wp.*, NULL as category_name
        FROM wholesale_products wp
        WHERE wp.status = 1 
        ORDER BY wp.created_at DESC
    ";
}

try {
    $products = $pdo->query($sql)->fetchAll();
    
    // Filter out products already in affiliate (if column exists)
    if ($columnExists) {
        $existingIds = $pdo->query("SELECT wholesale_product_id FROM affiliate_products WHERE wholesale_product_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $products = array_filter($products, function($p) use ($existingIds) {
            return !in_array($p['id'], $existingIds);
        });
        $products = array_values($products);
    }
} catch (PDOException $e) {
    $products = [];
}

$defaultCommissionType = getSettings('affiliate_default_commission_type') ?: 'percentage';
$defaultCommissionValue = getSettings('affiliate_default_commission_value') ?: 5;

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-plus-circle me-2"></i>পাইকারি থেকে অ্যাফিলিয়েট পণ্য যোগ করুন</h4>
        <a href="<?php echo adminUrl('affiliate-products'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>পণ্য তালিকা
        </a>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        পাইকারি পণ্য থেকে অ্যাফিলিয়েটে যোগ করলে পাইকারি দাম ও পরিমাণ একই থাকবে।
    </div>
    
    <?php if (empty($products)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-box-seam" style="font-size: 4rem; color: #ddd;"></i>
            <p class="mt-3 text-muted">সব পাইকারি পণ্য ইতিমধ্যে অ্যাফিলিয়েটে যোগ করা হয়েছে</p>
            <a href="<?php echo adminUrl('affiliate-add-new'); ?>" class="btn btn-primary">নতুন পণ্য যোগ করুন</a>
        </div>
    </div>
    <?php else: ?>
    <form method="POST">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">কমিশন সেটিং</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">কমিশন টাইপ</label>
                        <select class="form-select" name="commission_type">
                            <option value="percentage" <?php echo $defaultCommissionType === 'percentage' ? 'selected' : ''; ?>>শতাংশ (%)</option>
                            <option value="fixed" <?php echo $defaultCommissionType === 'fixed' ? 'selected' : ''; ?>>নির্দিষ্ট টাকা (৳)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">পরিমাণ</label>
                        <input type="number" class="form-control" name="commission_value" value="<?php echo $defaultCommissionValue; ?>" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="add_to_affiliate" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle me-1"></i>সিলেক্টেড পণ্য যোগ করুন
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>পণ্য সিলেক্ট করুন (<?php echo count($products); ?> টি পাওয়া গেছে)</span>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="selectAll">
                    <label class="form-check-label" for="selectAll">সব সিলেক্ট</label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <input type="text" id="searchProduct" class="form-control" placeholder="পণ্য খুঁজুন...">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40"></th>
                                <th width="60">ছবি</th>
                                <th>নাম</th>
                                <th>ক্যাটেগরি</th>
                                <th>পাইকারি দাম</th>
                                <th>ন্যূনতম অর্ডার</th>
                                <th>স্টক</th>
                            </tr>
                        </thead>
                        <tbody id="productList">
                            <?php foreach ($products as $product): ?>
                            <tr class="product-row" data-name="<?php echo strtolower($product['name']); ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input product-checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>">
                                </td>
                                <td>
                                    <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                </td>
                                <td><?php echo sanitize($product['name']); ?></td>
                                <td><?php echo sanitize($product['category_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($product['wholesale_price']): ?>
                                    <span class="text-success fw-bold"><?php echo formatPrice($product['wholesale_price']); ?></span>
                                    <?php elseif ($product['sale_price']): ?>
                                    <span class="text-success fw-bold"><?php echo formatPrice($product['sale_price']); ?></span>
                                    <?php else: ?>
                                    <?php echo formatPrice($product['regular_price']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $product['min_order_qty'] ?: 1; ?> পিস</td>
                                <td><?php echo $product['stock'] ?: '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        if (cb.closest('.product-row').style.display !== 'none') {
            cb.checked = this.checked;
        }
    });
});

document.getElementById('searchProduct')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.product-row').forEach(row => {
        const name = row.dataset.name;
        row.style.display = name.includes(search) ? '' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
