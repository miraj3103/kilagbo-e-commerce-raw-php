<?php
/**
 * Kilagbo - Admin Products Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পণ্য';
$currentPage = 'products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_hot_deal') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE products SET is_hot_deal = NOT is_hot_deal WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        setFlash('success', 'পণ্য মুছে ফেলা হয়েছে');
        redirect(adminUrl('products'));
    }
    
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' টি পণ্য মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('products'));
    }
    
    if ($action === 'set_custom_delivery') {
        $ids = $_POST['ids'] ?? [];
        $deliveryName = sanitize($_POST['delivery_name'] ?? '');
        $deliveryCost = floatval($_POST['delivery_cost'] ?? 0);
        
        if (!empty($ids) && $deliveryName) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$deliveryName, $deliveryCost], $ids);
            $pdo->prepare("UPDATE products SET custom_delivery_name = ?, custom_delivery_cost = ? WHERE id IN ($placeholders)")->execute($params);
            setFlash('success', count($ids) . ' টি পণ্যে কাস্টম ডেলিভারি সেট করা হয়েছে');
        }
        redirect(adminUrl('products'));
    }
    
    if ($action === 'remove_custom_delivery') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE products SET custom_delivery_name = NULL, custom_delivery_cost = NULL WHERE id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' টি পণ্য থেকে কাস্টম ডেলিভারি সরানো হয়েছে');
        }
        redirect(adminUrl('products'));
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
    $whereClause .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter) {
    $whereClause .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Fetch products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE $whereClause 
    ORDER BY p.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary fs-6"><?php echo $totalProducts; ?> টি পণ্য</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo adminUrl('products/add'); ?>" class="btn btn-admin">
            <i class="bi bi-plus-circle me-2"></i>নতুন পণ্য যোগ করুন
        </a>
        <a href="<?php echo adminUrl('service-add'); ?>" class="btn btn-success">
            <i class="bi bi-briefcase me-2"></i>সার্ভিস সেল করুন
        </a>
        <a href="<?php echo adminUrl('digital-product-add'); ?>" class="btn btn-info text-white">
            <i class="bi bi-file-earmark-code me-2"></i>ডিজিটাল প্রোডাক্ট সেল করুন
        </a>
    </div>
</div>

<div class="admin-card">
    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="পণ্য খুঁজুন..." value="<?php echo $search; ?>">
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
            <a href="<?php echo adminUrl('products'); ?>" class="btn btn-secondary w-100">রিসেট</a>
        </div>
        <?php endif; ?>
    </form>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">পণ্য তালিকা</h5>
        <div id="bulkActions" style="display: none;">
            <button type="button" class="btn btn-info btn-sm me-2" onclick="showCustomDeliveryModal()">
                <i class="bi bi-truck me-1"></i>কাস্টম ডেলিভারি
            </button>
            <button type="button" class="btn btn-warning btn-sm me-2" onclick="removeCustomDelivery()" id="removeCustomDeliveryBtn" style="display: none;">
                <i class="bi bi-x-circle me-1"></i>ডিফল্ট ডেলিভারি
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন
            </button>
        </div>
    </div>
    
    <?php if (empty($products)): ?>
    <p class="text-muted">কোন পণ্য পাওয়া যায়নি</p>
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
                        <th width="60">ছবি</th>
                        <th>নাম</th>
                        <th>ক্যাটেগরি</th>
                        <th>দাম</th>
                        <th>স্টক</th>
                        <th>হট ডিল</th>
                        <th>স্ট্যাটাস</th>
                        <th width="120">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?php echo $product['id']; ?>" class="item-checkbox" onchange="updateBulkBtn()">
                        </td>
                        <td>
                            <img src="<?php echo $product['image']; ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                        </td>
                        <td>
                            <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" target="_blank" class="text-dark">
                                <?php echo sanitize(mb_substr($product['name'], 0, 40)); ?>...
                            </a>
                            <?php if (!empty($product['custom_delivery_name'])): ?>
                            <br><span class="badge bg-info text-white" style="font-size: 0.7rem;">
                                <i class="bi bi-truck"></i> <?php echo sanitize($product['custom_delivery_name']); ?> (<?php echo formatPrice($product['custom_delivery_cost']); ?>)
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $product['category_name'] ? sanitize($product['category_name']) : '-'; ?></td>
                        <td>
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                            <span class="text-decoration-line-through text-muted small"><?php echo formatPrice($product['regular_price']); ?></span><br>
                            <strong><?php echo formatPrice($product['sale_price']); ?></strong>
                            <?php else: ?>
                            <strong><?php echo formatPrice($product['regular_price']); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $product['stock_quantity']; ?></td>
                        <td>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" 
                                       <?php echo $product['is_hot_deal'] ? 'checked' : ''; ?>
                                       onchange="toggleHotDeal(<?php echo $product['id']; ?>, this)">
                            </div>
                        </td>
                        <td>
                            <?php if ($product['status']): ?>
                            <span class="badge bg-success">সক্রিয়</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">নিষ্ক্রিয়</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo adminUrl('products/edit/' . $product['id']); ?>" class="btn btn-sm btn-outline-dark">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center mb-0">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Custom Delivery Form -->
<form id="customDeliveryForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="set_custom_delivery">
    <div id="customDeliveryIds"></div>
    <input type="hidden" name="delivery_name" id="deliveryNameInput">
    <input type="hidden" name="delivery_cost" id="deliveryCostInput">
</form>

<!-- Remove Custom Delivery Form -->
<form id="removeCustomDeliveryForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="remove_custom_delivery">
    <div id="removeCustomDeliveryIds"></div>
</form>

<!-- Custom Delivery Modal -->
<div class="modal fade" id="customDeliveryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i>কাস্টম ডেলিভারি সেট করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">ডেলিভারি নাম</label>
                    <input type="text" class="form-control" id="deliveryName" placeholder="যেমন: ফ্রী ডেলিভারি, এক্সপ্রেস ডেলিভারি">
                </div>
                <div class="mb-3">
                    <label class="form-label">ডেলিভারি চার্জ (৳)</label>
                    <input type="number" class="form-control" id="deliveryCost" value="0" min="0">
                </div>
                <p class="text-muted small"><i class="bi bi-info-circle me-1"></i>এই ডেলিভারি চার্জ শুধুমাত্র নির্বাচিত পণ্যগুলোতে প্রযোজ্য হবে এবং ডিফল্ট চার্জ ওভাররাইড করবে।</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-admin" onclick="applyCustomDelivery()">সেট করুন</button>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleHotDeal(id, checkbox) {
        fetch('<?php echo adminUrl('products'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=toggle_hot_deal&id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                checkbox.checked = !checkbox.checked;
            }
        });
    }
    
    function deleteProduct(id) {
        if (confirm('আপনি কি নিশ্চিত এই পণ্য মুছে ফেলতে চান?')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
    
    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = checked);
        updateBulkBtn();
    }
    
    function updateBulkBtn() {
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        const count = checkedBoxes.length;
        document.getElementById('bulkActions').style.display = count > 0 ? 'block' : 'none';
        
        // Check if any selected product has custom delivery
        let hasCustomDelivery = false;
        checkedBoxes.forEach(cb => {
            const row = cb.closest('tr');
            if (row.querySelector('.badge.bg-info')) {
                hasCustomDelivery = true;
            }
        });
        document.getElementById('removeCustomDeliveryBtn').style.display = hasCustomDelivery ? 'inline-block' : 'none';
    }
    
    function bulkDelete() {
        if (confirm('আপনি কি নিশ্চিত নির্বাচিত পণ্য গুলো মুছে ফেলতে চান?')) {
            document.getElementById('bulkForm').submit();
        }
    }
    
    function showCustomDeliveryModal() {
        const modal = new bootstrap.Modal(document.getElementById('customDeliveryModal'));
        modal.show();
    }
    
    function applyCustomDelivery() {
        const name = document.getElementById('deliveryName').value.trim();
        const cost = document.getElementById('deliveryCost').value;
        
        if (!name) {
            alert('ডেলিভারি নাম দিন');
            return;
        }
        
        // Get selected IDs
        const idsContainer = document.getElementById('customDeliveryIds');
        idsContainer.innerHTML = '';
        document.querySelectorAll('.item-checkbox:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            idsContainer.appendChild(input);
        });
        
        document.getElementById('deliveryNameInput').value = name;
        document.getElementById('deliveryCostInput').value = cost;
        document.getElementById('customDeliveryForm').submit();
    }
    
    function removeCustomDelivery() {
        if (!confirm('নির্বাচিত পণ্যগুলো থেকে কাস্টম ডেলিভারি সরিয়ে ডিফল্ট ডেলিভারি সেট করতে চান?')) {
            return;
        }
        
        const idsContainer = document.getElementById('removeCustomDeliveryIds');
        idsContainer.innerHTML = '';
        document.querySelectorAll('.item-checkbox:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            idsContainer.appendChild(input);
        });
        
        document.getElementById('removeCustomDeliveryForm').submit();
    }
</script>

<?php require_once 'includes/footer.php'; ?>
