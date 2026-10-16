<?php
/**
 * Kilagbo - Shop Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$pageTitle = 'দোকান';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 24;
$offset = ($page - 1) * $perPage;

// Filters
$search = sanitize($_GET['search'] ?? '');
$categorySlug = urldecode(trim($_GET['category'] ?? ''));
$sortBy = sanitize($_GET['sort'] ?? 'newest');

$whereClause = "p.status = 1";
$params = [];

if ($search) {
    $whereClause .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categorySlug) {
    $catStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $catStmt->execute([$categorySlug]);
    $catId = $catStmt->fetchColumn();
    if ($catId) {
        $whereClause .= " AND p.category_id = ?";
        $params[] = $catId;
    }
}

// Sort
$orderBy = "p.created_at DESC";
switch ($sortBy) {
    case 'price_low':
        $orderBy = "COALESCE(p.sale_price, p.regular_price) ASC";
        break;
    case 'price_high':
        $orderBy = "COALESCE(p.sale_price, p.regular_price) DESC";
        break;
    case 'name':
        $orderBy = "p.name ASC";
        break;
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
    ORDER BY $orderBy 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL AND status = 1 ORDER BY name")->fetchAll();

include 'includes/header.php';
?>
</style>

<div class="container" style="padding-top: 30px;">
    <?php if ($search): ?>
    <div class="alert alert-light mb-4">
        "<strong><?php echo $search; ?></strong>" এর জন্য <?php echo $totalProducts; ?> টি ফলাফল পাওয়া গেছে
        <a href="<?php echo baseUrl('shop'); ?>" class="ms-2">রিসেট</a>
    </div>
    <?php endif; ?>
    
    <!-- Products Grid -->
    <?php if (empty($products)): ?>
    <div class="text-center py-5">
        <i class="bi bi-search" style="font-size: 3rem; color: #ddd;"></i>
        <h4 class="mt-3">কোন পণ্য পাওয়া যায়নি</h4>
        <p class="text-muted">অন্য কিছু খুঁজুন অথবা আমাদের সকল পণ্য দেখুন</p>
        <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-primary-custom">সকল পণ্য দেখুন</a>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($products as $product): ?>
        <div class="col-6 col-md-4 col-lg-3 mb-4">
            <div class="product-card <?php echo ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) ? 'stock-out' : ''; ?>">
                <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-image">
                    <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>">
                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                    <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                    <span class="sale-badge"><?php echo $discount; ?>% ছাড়</span>
                    <?php endif; ?>
                    <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                    <span class="stock-out-badge">স্টক শেষ</span>
                    <?php endif; ?>
                </a>
                <div class="product-info">
                    <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-title">
                        <?php echo sanitize($product['name']); ?>
                    </a>
                    <div class="product-price">
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                        <?php endif; ?>
                        <span class="current-price"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                    </div>
                    <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                    <a href="#" onclick="addToWishlist(<?php echo $product['id']; ?>); return false;" class="btn btn-order" style="background: #6c757d;">
                        <i class="bi bi-heart me-1"></i>উইশলিস্টে যোগ করুন
                    </a>
                    <?php else: ?>
                    <a href="<?php echo baseUrl('quick-order?id=' . $product['id']); ?>" class="btn btn-order">অর্ডার করুন</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($categorySlug); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sortBy; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($categorySlug); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sortBy; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($categorySlug); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sortBy; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // DataLayer - View Item List
    pushDataLayer('view_item_list', {
        item_list_name: 'Shop Page',
        items: [
            <?php foreach (array_slice($products, 0, 10) as $i => $product): ?>
            {
                item_id: '<?php echo $product['id']; ?>',
                item_name: '<?php echo addslashes($product['name']); ?>',
                price: <?php echo $product['sale_price'] ?: $product['regular_price']; ?>,
                index: <?php echo $i; ?>
            },
            <?php endforeach; ?>
        ]
    });
</script>

<?php include 'includes/footer.php'; ?>
