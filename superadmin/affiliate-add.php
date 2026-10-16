<?php
/**
 * Kilagbo Admin - Add Products to Affiliate from Retail
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-products';
require_once 'includes/header.php';

$pageTitle = 'রিটেইল থেকে অ্যাফিলিয়েট পণ্য যোগ করুন';

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
            
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM affiliate_products WHERE retail_product_id = ?");
            $stmt->execute([$productId]);
            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }
            
            // Get retail product
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product) {
                // Add to affiliate products
                $stmt = $pdo->prepare("
                    INSERT INTO affiliate_products (retail_product_id, name, slug, description, image, gallery, category_id, regular_price, sale_price, commission_type, commission_value, stock_quantity, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $product['id'],
                    $product['name'],
                    'aff-' . $product['slug'],
                    $product['description'],
                    $product['image'],
                    $product['gallery'],
                    $product['category_id'],
                    $product['regular_price'],
                    $product['sale_price'],
                    $commissionType,
                    $commissionValue,
                    $product['stock_quantity']
                ]);
                $added++;
            }
        }
        
        $message = "$added টি পণ্য অ্যাফিলিয়েটে যোগ করা হয়েছে";
        if ($skipped > 0) {
            $message .= " ($skipped টি আগেই ছিল)";
        }
        setFlash('success', $message);
        redirect(adminUrl('affiliate-products.php'));
    } else {
        setFlash('error', 'অন্তত একটি পণ্য সিলেক্ট করুন');
    }
}

// Get retail products not in affiliate
$products = $pdo->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 1 
    AND p.id NOT IN (SELECT retail_product_id FROM affiliate_products WHERE retail_product_id IS NOT NULL)
    ORDER BY p.created_at DESC
")->fetchAll();

$defaultCommissionType = getSettings('affiliate_default_commission_type') ?: 'percentage';
$defaultCommissionValue = getSettings('affiliate_default_commission_value') ?: 5;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-plus-circle me-2"></i>রিটেইল থেকে অ্যাফিলিয়েট পণ্য যোগ করুন</h4>
        <a href="<?php echo adminUrl('affiliate-products.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>পণ্য তালিকা
        </a>
    </div>
    
    <?php showFlash(); ?>
    
    <?php if (empty($products)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-box-seam" style="font-size: 4rem; color: #ddd;"></i>
            <p class="mt-3 text-muted">সব রিটেইল পণ্য ইতিমধ্যে অ্যাফিলিয়েটে যোগ করা হয়েছে</p>
            <a href="<?php echo adminUrl('affiliate-add-new.php'); ?>" class="btn btn-primary">নতুন পণ্য যোগ করুন</a>
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
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40"></th>
                                <th width="60">ছবি</th>
                                <th>নাম</th>
                                <th>ক্যাটেগরি</th>
                                <th>মূল্য</th>
                                <th>স্টক</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input product-checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>">
                                </td>
                                <td>
                                    <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                </td>
                                <td><?php echo sanitize($product['name']); ?></td>
                                <td><?php echo sanitize($product['category_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($product['sale_price']): ?>
                                    <span class="text-success fw-bold"><?php echo formatPrice($product['sale_price']); ?></span>
                                    <?php else: ?>
                                    <?php echo formatPrice($product['regular_price']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $product['stock_quantity']; ?></td>
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
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once 'includes/footer.php'; ?>
