<?php
/**
 * Kilagbo Admin - Affiliate Products Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-products';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট পণ্য';

// Handle delete
if (isset($_POST['delete'])) {
    $productId = intval($_POST['product_id']);
    $pdo->prepare("DELETE FROM affiliate_products WHERE id = ?")->execute([$productId]);
    setFlash('success', 'পণ্য মুছে ফেলা হয়েছে');
    redirect(adminUrl('affiliate-products.php'));
}

// Handle status toggle
if (isset($_POST['toggle_status'])) {
    $productId = intval($_POST['product_id']);
    $currentStatus = intval($_POST['current_status']);
    $newStatus = $currentStatus ? 0 : 1;
    $pdo->prepare("UPDATE affiliate_products SET status = ? WHERE id = ?")->execute([$newStatus, $productId]);
    redirect(adminUrl('affiliate-products.php'));
}

// Handle commission update
if (isset($_POST['update_commission'])) {
    $productId = intval($_POST['product_id']);
    $commissionType = sanitize($_POST['commission_type']);
    $commissionValue = floatval($_POST['commission_value']);
    
    $pdo->prepare("UPDATE affiliate_products SET commission_type = ?, commission_value = ? WHERE id = ?")
        ->execute([$commissionType, $commissionValue, $productId]);
    
    setFlash('success', 'কমিশন আপডেট হয়েছে');
    redirect(adminUrl('affiliate-products.php'));
}

// Get filters
$search = sanitize($_GET['search'] ?? '');
$category = intval($_GET['category'] ?? 0);

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (ap.name LIKE ? OR ap.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where .= " AND ap.category_id = ?";
    $params[] = $category;
}

$stmt = $pdo->prepare("
    SELECT ap.*, c.name as category_name,
           (SELECT COUNT(*) FROM affiliate_links WHERE product_id = ap.id) as link_count,
           (SELECT SUM(conversions) FROM affiliate_links WHERE product_id = ap.id) as total_sales
    FROM affiliate_products ap
    LEFT JOIN categories c ON ap.category_id = c.id
    $where
    ORDER BY ap.created_at DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name")->fetchAll();

// Stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM affiliate_products")->fetchColumn();
$activeProducts = $pdo->query("SELECT COUNT(*) FROM affiliate_products WHERE status = 1")->fetchColumn();
$totalLinks = $pdo->query("SELECT COUNT(*) FROM affiliate_links")->fetchColumn();
$totalSales = $pdo->query("SELECT COALESCE(SUM(conversions), 0) FROM affiliate_links")->fetchColumn();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-box-seam me-2"></i>অ্যাফিলিয়েট পণ্য</h4>
        <div>
            <a href="<?php echo adminUrl('affiliate-add'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>রিটেইল থেকে যোগ করুন
            </a>
            <a href="<?php echo adminUrl('affiliate-add-wholesale'); ?>" class="btn btn-info text-white">
                <i class="bi bi-box-seam me-1"></i>পাইকারি থেকে যোগ করুন
            </a>
            <a href="<?php echo adminUrl('affiliate-add-new'); ?>" class="btn btn-outline-primary">
                <i class="bi bi-plus me-1"></i>নতুন পণ্য যোগ করুন
            </a>
        </div>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $totalProducts; ?></h3>
                    <p class="mb-0">মোট পণ্য</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo $activeProducts; ?></h3>
                    <p class="mb-0">সক্রিয় পণ্য</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3><?php echo $totalLinks; ?></h3>
                    <p class="mb-0">মোট লিংক</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h3><?php echo $totalSales; ?></h3>
                    <p class="mb-0">মোট বিক্রয়</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="search" placeholder="পণ্যের নাম খুঁজুন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="category">
                        <option value="">সব ক্যাটেগরি</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary w-100">খুঁজুন</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="bi bi-box-seam" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো পণ্য নেই</p>
                <a href="<?php echo adminUrl('affiliate-add.php'); ?>" class="btn btn-primary">পণ্য যোগ করুন</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="60">ছবি</th>
                            <th>নাম</th>
                            <th>মূল্য</th>
                            <th>কমিশন</th>
                            <th>লিংক</th>
                            <th>বিক্রয়</th>
                            <th>স্টক</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td>
                                <strong><?php echo sanitize(mb_substr($product['name'], 0, 40)); ?><?php echo mb_strlen($product['name']) > 40 ? '...' : ''; ?></strong>
                                <?php if ($product['category_name']): ?>
                                <small class="text-muted d-block"><?php echo sanitize($product['category_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product['sale_price']): ?>
                                <span class="text-success fw-bold"><?php echo formatPrice($product['sale_price']); ?></span>
                                <small class="text-muted text-decoration-line-through d-block"><?php echo formatPrice($product['regular_price']); ?></small>
                                <?php else: ?>
                                <span class="fw-bold"><?php echo formatPrice($product['regular_price']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#commissionModal<?php echo $product['id']; ?>">
                                    <?php if ($product['commission_type'] === 'percentage'): ?>
                                    <?php echo $product['commission_value']; ?>%
                                    <?php else: ?>
                                    ৳<?php echo number_format($product['commission_value']); ?>
                                    <?php endif; ?>
                                </button>
                                
                                <!-- Commission Modal -->
                                <div class="modal fade" id="commissionModal<?php echo $product['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h6 class="modal-title">কমিশন সেট করুন</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">কমিশন টাইপ</label>
                                                        <select class="form-select" name="commission_type">
                                                            <option value="percentage" <?php echo $product['commission_type'] === 'percentage' ? 'selected' : ''; ?>>শতাংশ (%)</option>
                                                            <option value="fixed" <?php echo $product['commission_type'] === 'fixed' ? 'selected' : ''; ?>>নির্দিষ্ট টাকা (৳)</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">পরিমাণ</label>
                                                        <input type="number" class="form-control" name="commission_value" value="<?php echo $product['commission_value']; ?>" step="0.01" min="0" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="update_commission" class="btn btn-success">সেভ করুন</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $product['link_count'] ?: 0; ?></td>
                            <td><?php echo $product['total_sales'] ?: 0; ?></td>
                            <td><?php echo $product['stock_quantity']; ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $product['status']; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $product['status'] ? 'btn-success' : 'btn-secondary'; ?>">
                                        <?php echo $product['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a href="<?php echo adminUrl('affiliate-edit.php?id=' . $product['id']); ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('মুছে ফেলতে চান?')">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
