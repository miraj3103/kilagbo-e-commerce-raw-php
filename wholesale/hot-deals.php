<?php
/**
 * Kilagbo - Wholesale Hot Deals
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';

$currentPage = 'wholesale';
$pageTitle = 'পাইকারি হট ডিলস';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$totalProducts = $pdo->query("SELECT COUNT(*) FROM wholesale_products WHERE is_hot_deal = 1 AND status = 1")->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Get hot deals
$stmt = $pdo->prepare("
    SELECT wp.*, c.name as category_name 
    FROM wholesale_products wp 
    LEFT JOIN categories c ON wp.category_id = c.id 
    WHERE wp.is_hot_deal = 1 AND wp.status = 1 
    ORDER BY wp.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$products = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Wholesale Hot Deals - Black & White Minimal Theme */
    .hot-deals-header {
        background: var(--primary-color);
        color: var(--secondary-color);
        padding: 30px 0;
        margin-bottom: 30px;
    }
    
    .hot-deals-header h1 {
        font-size: 2rem;
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
    
    .product-card .hot-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: var(--primary-color);
        color: var(--secondary-color);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
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

<div class="hot-deals-header">
    <div class="container">
        <h1><i class="bi bi-fire me-2"></i>পাইকারি হট ডিলস</h1>
        <p class="mb-0">সীমিত সময়ের জন্য বিশেষ ছাড়!</p>
    </div>
</div>

<div class="container mb-5">
    <?php if (empty($products)): ?>
    <div class="text-center py-5">
        <i class="bi bi-fire" style="font-size: 4rem; color: #ddd;"></i>
        <h3 class="mt-3">কোনো হট ডিল পাওয়া যায়নি</h3>
        <p class="text-muted">শীঘ্রই নতুন হট ডিল আসছে!</p>
        <a href="<?php echo baseUrl('wholesale/shop'); ?>" class="btn btn-wholesale mt-3">দোকানে যান</a>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($products as $product): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card <?php echo ($product['stock'] !== null && $product['stock'] <= 0) ? 'stock-out' : ''; ?>">
                <a href="<?php echo baseUrl('wholesale/product/' . $product['slug']); ?>" class="product-image">
                    <span class="wholesale-badge">পাইকারি</span>
                    <span class="hot-badge" style="left: auto; right: 8px;"><i class="bi bi-fire"></i> হট</span>
                    <?php if ($product['stock'] !== null && $product['stock'] <= 0): ?>
                    <span class="stock-out-badge">স্টক শেষ</span>
                    <?php endif; ?>
                    <?php if ($product['image']): ?>
                    <img src="<?php echo imageUrl($product['image']); ?>" alt="<?php echo $product['name']; ?>">
                    <?php else: ?>
                    <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo $product['name']; ?>">
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
                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
