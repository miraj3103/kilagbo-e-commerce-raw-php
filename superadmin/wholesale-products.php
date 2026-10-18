<?php
/**
 * Kilagbo - Admin Wholesale Products
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পাইকারি পণ্য';
$currentPage = 'wholesale-products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Check if wholesale_products table exists
$wholesaleTableExists = false;
try {
    $pdo->query("SELECT 1 FROM wholesale_products LIMIT 1");
    $wholesaleTableExists = true;
} catch (PDOException $e) {
    // Table doesn't exist
}

if (!$wholesaleTableExists) {
    require_once 'includes/header.php';
    echo '<div class="alert alert-warning">';
    echo '<h5><i class="bi bi-exclamation-triangle me-2"></i>পাইকারি টেবিল তৈরি হয়নি</h5>';
    echo '<p>অনুগ্রহ করে <a href="' . baseUrl('setup.php') . '" class="alert-link">setup.php</a> চালান।</p>';
    echo '</div>';
    require_once 'includes/footer.php';
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_hot_deal') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE wholesale_products SET is_hot_deal = NOT is_hot_deal WHERE id = ?")->execute([$id]);
        setFlash('success', 'হট ডিল স্ট্যাটাস আপডেট হয়েছে');
        redirect(adminUrl('wholesale-products'));
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM wholesale_products WHERE id = ?")->execute([$id]);
        setFlash('success', 'পণ্য মুছে ফেলা হয়েছে');
        redirect(adminUrl('wholesale-products'));
    }
    
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM wholesale_products WHERE id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' টি পণ্য মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('wholesale-products'));
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search and filter
$search = sanitize($_GET['search'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (wp.name LIKE ? OR wp.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryId) {
    $where .= " AND wp.category_id = ?";
    $params[] = $categoryId;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM wholesale_products wp $where");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Get products
$stmt = $pdo->prepare("
    SELECT wp.*, c.name as category_name, p.name as retail_product_name
    FROM wholesale_products wp 
    LEFT JOIN categories c ON wp.category_id = c.id 
    LEFT JOIN products p ON wp.product_id = p.id
    $where 
    ORDER BY wp.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary"><?php echo $totalProducts; ?> টি পণ্য</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo adminUrl('wholesale-add'); ?>" class="btn btn-admin">
            <i class="bi bi-plus-lg me-2"></i>রিটেইল থেকে যোগ করুন
        </a>
        <a href="<?php echo adminUrl('wholesale-add-new'); ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle me-2"></i>আলাদা ভাবে যোগ করুন
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="admin-card mb-4">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="নাম বা SKU দিয়ে খুঁজুন..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">সব ক্যাটেগরি</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-admin w-100">
                <i class="bi bi-search me-2"></i>খুঁজুন
            </button>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="admin-card">
    <form id="bulkForm" method="POST">
        <input type="hidden" name="action" value="bulk_delete">
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll" class="form-check-input">
                        </th>
                        <th width="80">ছবি</th>
                        <th>পণ্যের নাম</th>
                        <th>ক্যাটেগরি</th>
                        <th>পাইকারি দাম</th>
                        <th>ন্যূনতম অর্ডার</th>
                        <th>হট ডিল</th>
                        <th>টাইপ</th>
                        <th width="120">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">কোনো পণ্য পাওয়া যায়নি</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?php echo $product['id']; ?>" class="form-check-input product-checkbox">
                        </td>
                        <td>
                            <?php if ($product['image']): ?>
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-image text-muted"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo $product['name']; ?></strong>
                            <?php if ($product['sku']): ?>
                            <br><small class="text-muted">SKU: <?php echo $product['sku']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $product['category_name'] ?? '-'; ?></td>
                        <td>
                            <strong class="text-primary"><?php echo formatPrice($product['wholesale_price']); ?></strong>
                            <?php if ($product['regular_price'] > $product['wholesale_price']): ?>
                            <br><small class="text-muted text-decoration-line-through"><?php echo formatPrice($product['regular_price']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $product['min_order_qty']; ?> পিস</span>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_hot_deal">
                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $product['is_hot_deal'] ? 'btn-danger' : 'btn-outline-secondary'; ?>">
                                    <i class="bi bi-fire"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <?php if ($product['is_wholesale_only']): ?>
                            <span class="badge bg-purple">শুধু পাইকারি</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">রিটেইল থেকে</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo adminUrl('wholesale-edit/' . $product['id']); ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('মুছে ফেলতে চান?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
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
        
        <?php if (!empty($products)): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('নির্বাচিত পণ্যগুলো মুছে ফেলতে চান?')">
                <i class="bi bi-trash me-2"></i>নির্বাচিত মুছুন
            </button>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryId; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>
</div>

<style>
    .bg-purple {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
</style>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once 'includes/footer.php'; ?>
