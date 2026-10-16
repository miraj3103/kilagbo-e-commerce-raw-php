<?php
/**
 * Kilagbo Seller Portal - Products List
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'পণ্য সমূহ';
$currentPage = 'products';

$seller = requireSellerLogin();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $productId = intval($_GET['delete']);
    
    // Check ownership
    $stmt = $pdo->prepare("SELECT id, slug FROM seller_products WHERE id = ? AND seller_id = ?");
    $stmt->execute([$productId, $seller['id']]);
    $product = $stmt->fetch();
    
    if ($product) {
        $slug = $product['slug'];
        
        // Delete from all sections (Retail, Wholesale, Resell)
        // Note: Slugs in other tables are suffixed with -retail-timestamp, etc.
        $pdo->prepare("DELETE FROM products WHERE slug LIKE ?")->execute([$slug . '-retail-%']);
        $pdo->prepare("DELETE FROM wholesale_products WHERE slug LIKE ?")->execute([$slug . '-wholesale-%']);
        $pdo->prepare("DELETE FROM resell_products WHERE slug LIKE ?")->execute([$slug . '-resell-%']);
        
        // Delete from seller_products
        $pdo->prepare("DELETE FROM seller_products WHERE id = ?")->execute([$productId]);
        
        setFlash('success', 'পণ্য সফলভাবে মুছে ফেলা হয়েছে (সকল সেকশন থেকে)');
    }
    redirect(baseUrl('seller/products.php'));
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$whereConditions = ["seller_id = ?"];
$params = [$seller['id']];

if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $whereConditions[] = "name LIKE ?";
    $params[] = "%$searchQuery%";
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM seller_products WHERE $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Get products
$stmt = $pdo->prepare("
    SELECT sp.*, c.name as category_name 
    FROM seller_products sp
    LEFT JOIN categories c ON sp.category_id = c.id
    WHERE $whereClause
    ORDER BY sp.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="text-muted">মোট <?php echo $totalProducts; ?> টি পণ্য</span>
    </div>
    <a href="<?php echo baseUrl('seller/add-product.php'); ?>" class="btn btn-seller">
        <i class="bi bi-plus me-1"></i>নতুন পণ্য যোগ করুন
    </a>
</div>

<!-- Filters -->
<div class="seller-card mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">স্ট্যাটাস</label>
            <select name="status" class="form-select">
                <option value="">সব</option>
                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                <option value="approved" <?php echo $statusFilter == 'approved' ? 'selected' : ''; ?>>অনুমোদিত</option>
                <option value="rejected" <?php echo $statusFilter == 'rejected' ? 'selected' : ''; ?>>প্রত্যাখ্যাত</option>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">অনুসন্ধান</label>
            <input type="text" name="search" class="form-control" placeholder="পণ্যের নাম..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-seller w-100">
                <i class="bi bi-search me-1"></i>অনুসন্ধান
            </button>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="seller-card">
    <?php if (empty($products)): ?>
    <div class="text-center py-5">
        <i class="bi bi-box-seam" style="font-size: 4rem; opacity: 0.3;"></i>
        <h5 class="mt-3 text-muted">কোনো পণ্য পাওয়া যায়নি</h5>
        <a href="<?php echo baseUrl('seller/add-product.php'); ?>" class="btn btn-seller mt-3">
            <i class="bi bi-plus me-1"></i>পণ্য যোগ করুন
        </a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table seller-table">
            <thead>
                <tr>
                    <th>পণ্য</th>
                    <th>ক্যাটেগরি</th>
                    <th>দাম</th>
                    <th>ন্যূনতম অর্ডার</th>
                    <th>স্ট্যাটাস</th>
                    <th>তারিখ</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($product['image']): ?>
                            <img src="<?php echo $product['image']; ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #e9ecef; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-image text-muted"></i>
                            </div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo mb_strimwidth($product['name'], 0, 40, '...'); ?></strong>
                                <?php if ($product['has_variations']): ?>
                                <br><small class="text-muted"><i class="bi bi-diagram-3 me-1"></i>ভেরিয়েশন আছে</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?php echo $product['category_name'] ?? '-'; ?></td>
                    <td><strong><?php echo formatPrice($product['seller_price']); ?></strong></td>
                    <td><?php echo $product['minimum_quantity']; ?> পিস</td>
                    <td>
                        <?php if ($product['status'] == 'pending'): ?>
                        <span class="badge badge-pending">পেন্ডিং</span>
                        <?php elseif ($product['status'] == 'approved'): ?>
                        <span class="badge badge-approved">অনুমোদিত</span>
                        <?php else: ?>
                        <span class="badge badge-rejected">প্রত্যাখ্যাত</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?php echo date('d M Y', strtotime($product['created_at'])); ?></small></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo baseUrl('seller/products.php?delete=' . $product['id']); ?>" onclick="return confirm('আপনি কি এই পণ্য মুছে ফেলতে চান? এটি সকল সেকশন থেকে মুছে যাবে।');">
                                        <i class="bi bi-trash me-2"></i>মুছুন
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <!-- Pagination -->
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
