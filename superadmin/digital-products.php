<?php
/**
 * Kilagbo - Admin Digital Products Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'ডিজিটাল প্রোডাক্ট';
$currentPage = 'digital-products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM digital_product_variations WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM digital_products WHERE id = ?")->execute([$id]);
        setFlash('success', 'ডিজিটাল প্রোডাক্ট মুছে ফেলা হয়েছে');
        redirect(adminUrl('digital-products'));
    }
    
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM digital_product_variations WHERE product_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM digital_products WHERE id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' টি প্রোডাক্ট মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('digital-products'));
    }
    
    if ($action === 'toggle_status') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE digital_products SET status = NOT status WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search and filter
$search = sanitize($_GET['search'] ?? '');
$categoryFilter = intval($_GET['category'] ?? 0);

$whereClause = "1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter) {
    $whereClause .= " AND d.category_id = ?";
    $params[] = $categoryFilter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM digital_products d WHERE $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Fetch products
$stmt = $pdo->prepare("
    SELECT d.*, c.name as category_name 
    FROM digital_products d 
    LEFT JOIN categories c ON d.category_id = c.id 
    WHERE $whereClause 
    ORDER BY d.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch categories for filter
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary fs-6"><?php echo $totalProducts; ?> টি ডিজিটাল প্রোডাক্ট</span>
    </div>
    <a href="<?php echo adminUrl('digital-product-add'); ?>" class="btn btn-admin">
        <i class="bi bi-plus-circle me-2"></i>নতুন ডিজিটাল প্রোডাক্ট যোগ করুন
    </a>
</div>

<div class="admin-card">
    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="প্রোডাক্ট খুঁজুন..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">সকল ক্যাটেগরি</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo sanitize($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-admin-outline w-100">
                <i class="bi bi-search me-1"></i>খুঁজুন
            </button>
        </div>
        <?php if ($search || $categoryFilter): ?>
        <div class="col-md-2">
            <a href="<?php echo adminUrl('digital-products'); ?>" class="btn btn-secondary w-100">রিসেট</a>
        </div>
        <?php endif; ?>
    </form>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">ডিজিটাল প্রোডাক্ট তালিকা</h5>
        <div id="bulkActions" style="display: none;">
            <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন
            </button>
        </div>
    </div>
    
    <?php if (empty($products)): ?>
    <div class="text-center py-5">
        <i class="bi bi-file-earmark-code display-1 text-muted"></i>
        <p class="text-muted mt-3">কোন ডিজিটাল প্রোডাক্ট পাওয়া যায়নি</p>
        <a href="<?php echo adminUrl('digital-product-add'); ?>" class="btn btn-admin">
            <i class="bi bi-plus-circle me-2"></i>প্রথম প্রোডাক্ট যোগ করুন
        </a>
    </div>
    <?php else: ?>
    <form id="bulkForm" method="POST">
        <input type="hidden" name="action" value="bulk_delete">
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <th width="80">ছবি</th>
                        <th>প্রোডাক্ট</th>
                        <th>ক্যাটেগরি</th>
                        <th>ফাইল টাইপ</th>
                        <th>মূল্য</th>
                        <th>স্ট্যাটাস</th>
                        <th width="120">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?php echo $product['id']; ?>" class="product-checkbox" onchange="updateBulkActions()">
                        </td>
                        <td>
                            <?php if ($product['image']): ?>
                            <img src="<?php echo $product['image']; ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                            <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-file-earmark-code text-muted"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo sanitize($product['name']); ?></strong>
                            <?php if ($product['file_size']): ?>
                            <br><small class="text-muted"><i class="bi bi-hdd me-1"></i><?php echo sanitize($product['file_size']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($product['category_name']): ?>
                            <span class="badge bg-secondary"><?php echo sanitize($product['category_name']); ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($product['file_type']): ?>
                            <span class="badge bg-info"><?php echo sanitize($product['file_type']); ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($product['sale_price']): ?>
                            <span class="text-decoration-line-through text-muted">৳<?php echo number_format($product['regular_price']); ?></span><br>
                            <strong class="text-success">৳<?php echo number_format($product['sale_price']); ?></strong>
                            <?php else: ?>
                            <strong>৳<?php echo number_format($product['regular_price']); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" <?php echo $product['status'] ? 'checked' : ''; ?> onchange="toggleStatus(<?php echo $product['id']; ?>)">
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo adminUrl('digital-product-edit/' . $product['id']); ?>" class="btn btn-sm btn-outline-primary" title="এডিট">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="মুছুন">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const selectAll = document.getElementById('selectAll');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.product-checkbox:checked');
    document.getElementById('bulkActions').style.display = checked.length > 0 ? 'block' : 'none';
}

function bulkDelete() {
    if (confirm('নির্বাচিত প্রোডাক্টগুলো মুছে ফেলতে চান?')) {
        document.getElementById('bulkForm').submit();
    }
}

function deleteProduct(id) {
    if (confirm('এই প্রোডাক্টটি মুছে ফেলতে চান?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleStatus(id) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_status&id=${id}`
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
