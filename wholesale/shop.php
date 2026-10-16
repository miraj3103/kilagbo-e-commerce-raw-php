<?php
/**
 * Kilagbo - Wholesale Shop
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';

$currentPage = 'wholesale';
$pageTitle = 'পাইকারি দোকান';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$search = sanitize($_GET['search'] ?? '');
$categorySlug = sanitize($_GET['category'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'newest');

$where = "WHERE wp.status = 1";
$params = [];

if ($search) {
    $where .= " AND (wp.name LIKE ? OR wp.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categorySlug) {
    $catStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $catStmt->execute([$categorySlug]);
    $cat = $catStmt->fetch();
    if ($cat) {
        $where .= " AND wp.category_id = ?";
        $params[] = $cat['id'];
    }
}

// Sort
$orderBy = "ORDER BY wp.created_at DESC";
if ($sort === 'price_low') {
    $orderBy = "ORDER BY wp.wholesale_price ASC";
} elseif ($sort === 'price_high') {
    $orderBy = "ORDER BY wp.wholesale_price DESC";
} elseif ($sort === 'name') {
    $orderBy = "ORDER BY wp.name ASC";
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM wholesale_products wp $where");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Get products
$stmt = $pdo->prepare("
    SELECT wp.*, c.name as category_name 
    FROM wholesale_products wp 
    LEFT JOIN categories c ON wp.category_id = c.id 
    $where 
    $orderBy 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("
    SELECT c.*, COUNT(wp.id) as product_count 
    FROM categories c 
    LEFT JOIN wholesale_products wp ON wp.category_id = c.id AND wp.status = 1
    WHERE c.status = 1 AND c.wholesale_enabled = 1
    GROUP BY c.id
    HAVING product_count > 0
    ORDER BY c.name
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Wholesale Shop - Black & White Minimal Theme */
    .shop-header {
        background: var(--primary-color);
        color: var(--secondary-color);
        padding: 25px 0;
        margin-bottom: 30px;
    }
    
    .shop-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }
    
    .product-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .product-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transform: translateY(-5px);
    }
    
    .product-card .product-image {
        position: relative;
        padding-top: 100%;
        overflow: hidden;
    }
    
    .product-card .product-image img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .product-card .product-info {
        padding: 15px;
    }
    
    .product-card .product-title {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 8px;
        color: var(--text-color);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-decoration: none;
    }
    
    .product-card .product-title:hover {
        color: var(--primary-color);
    }
    
    .product-card .current-price {
        font-size: 1rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .product-card .original-price {
        font-size: 0.85rem;
        color: #999;
        text-decoration: line-through;
    }
    
    .min-qty-badge {
        background: #f0f0f0;
        color: #666;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
    }
    
    .btn-wholesale {
        background: var(--primary-color);
        color: var(--secondary-color);
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.8rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .btn-wholesale:hover {
        color: var(--secondary-color);
        background: var(--accent-color);
    }
</style>

<div class="shop-header">
    <div class="container">
        <h1><i class="bi bi-shop me-2"></i>পাইকারি দোকান</h1>
    </div>
</div>

<div class="container mb-5">
    <div class="row">
        <!-- Products -->
        <div class="col-lg-12">
            <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="bi bi-box-seam" style="font-size: 4rem; color: #ddd;"></i>
                <h3 class="mt-3">কোনো পণ্য পাওয়া যায়নি</h3>
                <p class="text-muted">অন্য কিওয়ার্ড দিয়ে খুঁজুন বা ফিল্টার পরিবর্তন করুন।</p>
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="product-card <?php echo ($product['stock'] !== null && $product['stock'] <= 0) ? 'stock-out' : ''; ?>">
                        <a href="<?php echo baseUrl('wholesale/product/' . $product['slug']); ?>" class="product-image">
                            <span class="wholesale-badge">পাইকারি</span>
                            <?php if ($product['image']): ?>
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="<?php echo $product['name']; ?>">
                            <?php else: ?>
                            <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo $product['name']; ?>">
                            <?php endif; ?>
                            <?php if ($product['stock'] !== null && $product['stock'] <= 0): ?>
                            <span class="stock-out-badge">স্টক শেষ</span>
                            <?php endif; ?>
                        </a>
                        <div class="product-info">
                            <a href="<?php echo baseUrl('wholesale/product/' . $product['slug']); ?>" class="product-title"><?php echo $product['name']; ?></a>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="current-price"><?php echo formatPrice($product['wholesale_price']); ?></span>
                                <?php if ($product['regular_price'] > $product['wholesale_price']): ?>
                                <span class="original-price"><?php echo formatPrice($product['regular_price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="min-qty-badge">ন্যূনতম: <?php echo $product['min_order_qty']; ?> পিস</span>
                                <?php if ($product['stock'] !== null && $product['stock'] <= 0): ?>
                                <a href="#" onclick="addToWishlist(<?php echo $product['id']; ?>, 'wholesale'); return false;" class="btn-wholesale btn-sm" style="background: #6c757d;">
                                    <i class="bi bi-heart me-1"></i>উইশলিস্ট
                                </a>
                                <?php else: ?>
                                <a href="<?php echo baseUrl('wholesale/product/' . $product['slug']); ?>" class="btn-wholesale btn-sm">অর্ডার</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($categorySlug); ?>&sort=<?php echo $sort; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
