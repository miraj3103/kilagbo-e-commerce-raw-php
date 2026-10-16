<?php
/**
 * Kilagbo - Add Retail Products to Wholesale
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'রিটেইল থেকে পাইকারিতে যোগ করুন';
$currentPage = 'wholesale-products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_to_wholesale') {
        $productIds = $_POST['product_ids'] ?? [];
        $minQtys = $_POST['min_qty'] ?? [];
        $wholesalePrices = $_POST['wholesale_price'] ?? [];
        
        $added = 0;
        foreach ($productIds as $productId) {
            $productId = (int)$productId;
            $minQty = (int)($minQtys[$productId] ?? 10);
            $wholesalePrice = (float)($wholesalePrices[$productId] ?? 0);
            
            // Check if already exists
            $checkStmt = $pdo->prepare("SELECT id FROM wholesale_products WHERE product_id = ?");
            $checkStmt->execute([$productId]);
            if ($checkStmt->fetch()) {
                continue; // Skip if already added
            }
            
            // Get retail product details
            $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch();
            
            if ($product) {
                $stmt = $pdo->prepare("
                    INSERT INTO wholesale_products 
                    (product_id, name, slug, description, short_description, category_id, image, gallery, video_url, 
                     regular_price, sale_price, wholesale_price, min_order_qty, stock, sku, is_wholesale_only) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $productId,
                    $product['name'],
                    $product['slug'] . '-wholesale',
                    $product['description'],
                    $product['short_description'],
                    $product['category_id'],
                    $product['image'],
                    $product['gallery'],
                    $product['video_url'],
                    $product['regular_price'],
                    $product['sale_price'],
                    $wholesalePrice ?: ($product['sale_price'] ?: $product['regular_price']) * 0.7,
                    $minQty,
                    $product['stock_quantity'] ?? 0,
                    null
                ]);
                $wholesaleId = $pdo->lastInsertId();
                
                // Copy variations if exist
                if ($product['has_variations']) {
                    // Get retail variations
                    $vStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ?");
                    $vStmt->execute([$productId]);
                    $variations = $vStmt->fetchAll();
                    
                    if (!empty($variations)) {
                        $wvStmt = $pdo->prepare("INSERT INTO wholesale_product_variations (wholesale_product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                        foreach ($variations as $var) {
                            $wvStmt->execute([$wholesaleId, $var['attribute_name'], $var['attribute_value']]);
                        }
                        
                        // Update has_variations in wholesale product
                        $pdo->prepare("UPDATE wholesale_products SET has_variations = 1 WHERE id = ?")->execute([$wholesaleId]);
                    }
                }
                
                $added++;
            }
        }
        
        if ($added > 0) {
            setFlash('success', $added . ' টি পণ্য পাইকারিতে যোগ করা হয়েছে');
        } else {
            setFlash('warning', 'কোনো নতুন পণ্য যোগ করা হয়নি');
        }
        redirect(adminUrl('wholesale-products'));
    }
}

// Get selected products from session or query
$selectedIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

// Get all retail products not yet in wholesale
$products = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 1 
    AND p.id NOT IN (SELECT product_id FROM wholesale_products WHERE product_id IS NOT NULL)
    ORDER BY p.created_at DESC
")->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="admin-card mb-4">
    <p class="mb-0">
        <i class="bi bi-info-circle me-2"></i>
        রিটেইল থেকে পণ্য সিলেক্ট করুন এবং পাইকারি দাম ও ন্যূনতম অর্ডার পরিমাণ সেট করুন।
    </p>
</div>

<?php if (empty($selectedIds)): ?>
<!-- Step 1: Select Products -->
<div class="admin-card">
    <h5 class="card-title mb-4">পণ্য সিলেক্ট করুন</h5>
    
    <form id="selectForm">
        <div class="mb-3">
            <input type="text" id="searchProduct" class="form-control" placeholder="পণ্য খুঁজুন...">
        </div>
        
        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
            <table class="admin-table">
                <thead style="position: sticky; top: 0; background: #fff;">
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll" class="form-check-input">
                        </th>
                        <th width="60">ছবি</th>
                        <th>পণ্যের নাম</th>
                        <th>ক্যাটেগরি</th>
                        <th>রিটেইল দাম</th>
                    </tr>
                </thead>
                <tbody id="productList">
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">সব পণ্য ইতিমধ্যে পাইকারিতে যোগ করা হয়েছে</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr class="product-row" data-name="<?php echo strtolower($product['name']); ?>">
                        <td>
                            <input type="checkbox" name="selected_ids[]" value="<?php echo $product['id']; ?>" class="form-check-input product-checkbox">
                        </td>
                        <td>
                            <?php if ($product['image']): ?>
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                            <?php else: ?>
                            <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 6px;"></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $product['name']; ?></td>
                        <td><?php echo $product['category_name'] ?? '-'; ?></td>
                        <td>
                            <?php if ($product['sale_price']): ?>
                            <strong><?php echo formatPrice($product['sale_price']); ?></strong>
                            <small class="text-muted text-decoration-line-through"><?php echo formatPrice($product['regular_price']); ?></small>
                            <?php else: ?>
                            <strong><?php echo formatPrice($product['regular_price']); ?></strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($products)): ?>
        <div class="mt-4">
            <button type="button" id="proceedBtn" class="btn btn-admin" disabled>
                <i class="bi bi-arrow-right me-2"></i>পরবর্তী ধাপে যান (<span id="selectedCount">0</span> টি সিলেক্টেড)
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.product-checkbox:not(:disabled)').forEach(cb => {
        if (cb.closest('.product-row').style.display !== 'none') {
            cb.checked = this.checked;
        }
    });
    updateSelectedCount();
});

document.querySelectorAll('.product-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    const count = document.querySelectorAll('.product-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('proceedBtn').disabled = count === 0;
}

document.getElementById('searchProduct')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.product-row').forEach(row => {
        const name = row.dataset.name;
        row.style.display = name.includes(search) ? '' : 'none';
    });
});

document.getElementById('proceedBtn')?.addEventListener('click', function() {
    const ids = [];
    document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
        ids.push(cb.value);
    });
    window.location.href = '<?php echo adminUrl('wholesale-add'); ?>?ids=' + ids.join(',');
});
</script>

<?php else: ?>
<!-- Step 2: Set Wholesale Prices -->
<?php
$selectedProducts = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id IN (" . implode(',', array_map('intval', $selectedIds)) . ")
");
$selectedProducts->execute();
$selectedProducts = $selectedProducts->fetchAll();
?>

<div class="admin-card">
    <h5 class="card-title mb-4">পাইকারি দাম ও ন্যূনতম অর্ডার সেট করুন</h5>
    
    <form method="POST">
        <input type="hidden" name="action" value="add_to_wholesale">
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="60">ছবি</th>
                        <th>পণ্যের নাম</th>
                        <th>রিটেইল দাম</th>
                        <th width="150">পাইকারি দাম *</th>
                        <th width="150">ন্যূনতম অর্ডার *</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($selectedProducts as $product): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="product_ids[]" value="<?php echo $product['id']; ?>">
                            <?php if ($product['image']): ?>
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                            <?php else: ?>
                            <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 6px;"></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $product['name']; ?></td>
                        <td>
                            <?php 
                            $retailPrice = $product['sale_price'] ?: $product['regular_price'];
                            echo formatPrice($retailPrice); 
                            ?>
                        </td>
                        <td>
                            <input type="number" name="wholesale_price[<?php echo $product['id']; ?>]" 
                                   class="form-control" step="0.01" required
                                   value="<?php echo round($retailPrice * 0.7, 2); ?>"
                                   placeholder="পাইকারি দাম">
                        </td>
                        <td>
                            <input type="number" name="min_qty[<?php echo $product['id']; ?>]" 
                                   class="form-control" min="1" required
                                   value="10"
                                   placeholder="ন্যূনতম পরিমাণ">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 d-flex gap-2">
            <a href="<?php echo adminUrl('wholesale-add'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>পিছনে যান
            </a>
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-check-lg me-2"></i>পাইকারিতে যোগ করুন
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
