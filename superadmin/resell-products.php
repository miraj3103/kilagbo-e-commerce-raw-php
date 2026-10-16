<?php
/**
 * Kilagbo Admin - Resell Products Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-products';
require_once 'includes/header.php';

$pageTitle = 'রিসেল পণ্য';

// Handle hot deal toggle
if (isset($_POST['toggle_hot_deal'])) {
    $productId = intval($_POST['product_id']);
    $pdo->prepare("UPDATE resell_products SET is_hot_deal = NOT is_hot_deal WHERE id = ?")->execute([$productId]);
    setFlash('success', 'হট ডিল স্ট্যাটাস পরিবর্তন হয়েছে');
    redirect(adminUrl('resell-products.php'));
}

// Handle status toggle
if (isset($_POST['toggle_status'])) {
    $productId = intval($_POST['product_id']);
    $pdo->prepare("UPDATE resell_products SET status = NOT status WHERE id = ?")->execute([$productId]);
    setFlash('success', 'স্ট্যাটাস পরিবর্তন হয়েছে');
    redirect(adminUrl('resell-products.php'));
}

// Handle delete
if (isset($_POST['delete'])) {
    $productId = intval($_POST['product_id']);
    $pdo->prepare("DELETE FROM resell_products WHERE id = ?")->execute([$productId]);
    setFlash('success', 'পণ্য মুছে ফেলা হয়েছে');
    redirect(adminUrl('resell-products.php'));
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_products'])) {
    $selectedIds = array_map('intval', $_POST['selected_products']);
    if (!empty($selectedIds)) {
        $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
        $pdo->prepare("DELETE FROM resell_products WHERE id IN ($placeholders)")->execute($selectedIds);
        setFlash('success', count($selectedIds) . ' টি পণ্য রিসেল থেকে সরানো হয়েছে');
        redirect(adminUrl('resell-products.php'));
    }
}

// Get products
$search = sanitize($_GET['search'] ?? '');
$where = "";
$params = [];

if ($search) {
    $where = "WHERE name LIKE ?";
    $params[] = "%$search%";
}

$products = $pdo->prepare("SELECT rp.*, c.name as category_name FROM resell_products rp LEFT JOIN categories c ON rp.category_id = c.id $where ORDER BY rp.created_at DESC");
$products->execute($params);
$products = $products->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-box-seam me-2"></i>রিসেল পণ্য</h4>
        <div>
            <a href="<?php echo adminUrl('resell-add.php'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> রিটেইল থেকে যোগ করুন
            </a>
            <a href="<?php echo adminUrl('resell-add-new.php'); ?>" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> আলাদা ভাবে যোগ করুন
            </a>
        </div>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="পণ্যের নাম দিয়ে খুঁজুন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">খুঁজুন</button>
                </div>
            </form>
        </div>
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>পণ্য তালিকা</span>
            <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                <i class="bi bi-trash"></i> বাল্ক ডিলিট (<span id="selectedCount">0</span>)
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো রিসেল পণ্য নেই</p>
                <a href="<?php echo adminUrl('resell-add.php'); ?>" class="btn btn-primary">পণ্য যোগ করুন</a>
            </div>
            <?php else: ?>
            <form method="POST" id="bulkForm">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>ছবি</th>
                                <th>পণ্যের নাম</th>
                                <th>ক্যাটেগরি</th>
                                <th>মূল মূল্য</th>
                                <th>রিসেলার মূল্য</th>
                                <th>স্টক</th>
                                <th>হট ডিল</th>
                                <th>স্ট্যাটাস</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input product-checkbox" name="selected_products[]" value="<?php echo $product['id']; ?>">
                                </td>
                                <td>
                                    <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                </td>
                                <td>
                                    <strong><?php echo sanitize($product['name']); ?></strong>
                                    <?php if ($product['is_standalone']): ?>
                                    <br><small class="badge bg-info">স্বতন্ত্র</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $product['category_name'] ?? '-'; ?></td>
                                <td><?php echo formatPrice($product['original_price']); ?></td>
                                <td><?php echo formatPrice($product['reseller_price']); ?></td>
                                <td><?php echo $product['stock_quantity']; ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="toggle_hot_deal" class="btn btn-sm <?php echo $product['is_hot_deal'] ? 'btn-warning' : 'btn-outline-secondary'; ?>">
                                            <i class="bi bi-fire"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $product['status'] ? 'btn-success' : 'btn-danger'; ?>">
                                            <?php echo $product['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <a href="<?php echo adminUrl('resell-edit.php?id=' . $product['id']); ?>" class="btn btn-sm btn-outline-primary">
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
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="bulkDeleteForm">
                    <input type="hidden" name="bulk_delete" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title">বাল্ক ডিলিট নিশ্চিত করুন</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>আপনি কি নিশ্চিত যে সিলেক্ট করা পণ্যগুলো রিসেল থেকে সরাতে চান?</p>
                        <div class="alert alert-warning">
                            <strong>নোট:</strong> এই অ্যাকশন পূর্বাবস্থায় ফেরানো যাবে না।
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                        <button type="submit" class="btn btn-danger">ডিলিট করুন</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    function updateCount() {
        const count = document.querySelectorAll('.product-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = count;
        bulkDeleteBtn.style.display = count > 0 ? 'inline-block' : 'none';
    }
    
    selectAllCheckbox?.addEventListener('change', function() {
        productCheckboxes.forEach(cb => cb.checked = this.checked);
        updateCount();
    });
    
    productCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateCount();
            const checkedCount = document.querySelectorAll('.product-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === productCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < productCheckboxes.length;
        });
    });
    
    bulkDeleteBtn?.addEventListener('click', function() {
        const form = document.getElementById('bulkDeleteForm');
        document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_products[]';
            input.value = cb.value;
            form.appendChild(input);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
