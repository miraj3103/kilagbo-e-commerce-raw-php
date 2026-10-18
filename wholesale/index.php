<?php
/**
 * Kilagbo - Wholesale Homepage
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/access.php';
wholesaleRequireApproval($pdo);

$currentPage = 'wholesale';
$pageTitle = 'পাইকারি';

// Check if wholesale_products table exists
$wholesaleTableExists = false;
try {
    $pdo->query("SELECT 1 FROM wholesale_products LIMIT 1");
    $wholesaleTableExists = true;
} catch (PDOException $e) {
    // Table doesn't exist
}

// Check if wholesale_enabled column exists in categories
$wholesaleColumnExists = false;
try {
    $pdo->query("SELECT wholesale_enabled FROM categories LIMIT 1");
    $wholesaleColumnExists = true;
} catch (PDOException $e) {
    // Column doesn't exist
}

if (!$wholesaleTableExists || !$wholesaleColumnExists) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container py-5 text-center">';
    echo '<h2>পাইকারি বিভাগ সেটআপ প্রয়োজন</h2>';
    echo '<p>অনুগ্রহ করে <a href="' . baseUrl('setup.php') . '">setup.php</a> চালান।</p>';
    echo '</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch top categories for wholesale
$topCategoriesStmt = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM wholesale_products wp WHERE wp.category_id = c.id AND wp.status = 1) as product_count 
    FROM categories c 
    WHERE c.show_on_top = 1 AND c.status = 1 AND c.wholesale_enabled = 1
    ORDER BY c.sort_order ASC 
    LIMIT 6
");
$topCategories = $topCategoriesStmt->fetchAll();

// Fetch hot deals for wholesale
$hotDealsStmt = $pdo->query("
    SELECT wp.*, c.name as category_name 
    FROM wholesale_products wp 
    LEFT JOIN categories c ON wp.category_id = c.id 
    WHERE wp.is_hot_deal = 1 AND wp.status = 1 
    ORDER BY wp.created_at DESC 
    LIMIT 12
");
$hotDeals = $hotDealsStmt->fetchAll();

// Fetch all wholesale products
$allProductsStmt = $pdo->query("
    SELECT wp.*, c.name as category_name 
    FROM wholesale_products wp 
    LEFT JOIN categories c ON wp.category_id = c.id 
    WHERE wp.status = 1 
    ORDER BY wp.created_at DESC 
    LIMIT 20
");
$allProducts = $allProductsStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Wholesale - Black & White Minimal Theme */
    .min-qty-badge {
        background: var(--bg-light);
        color: var(--text-light);
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        border: 1px solid var(--border-color);
    }
    
    .wholesale-hero {
        background: var(--primary-color);
        color: var(--secondary-color);
        padding: 30px 0;
        margin-bottom: 30px;
    }
    
    .wholesale-hero h1 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 8px;
    }
    
    .wholesale-hero p {
        opacity: 0.8;
        margin: 0;
        font-size: 0.95rem;
    }
    
    /* Category Card Styles - Standard Compact Square */
    .category-card {
        text-align: center;
        text-decoration: none;
        display: block;
        transition: all 0.3s ease;
        background: var(--secondary-color);
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        height: 100%;
        padding-bottom: 8px;
    }
    
    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border-color: var(--primary-color);
    }
    
    .category-card .category-image {
        width: 100%;
        aspect-ratio: 1;
        max-height: 160px;
        overflow: hidden;
        background: var(--bg-light);
        position: relative;
    }
    
    .category-card .category-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .category-card:hover .category-image img {
        transform: scale(1.05);
    }
    
    .category-card .category-name {
        font-weight: 600;
        color: var(--text-color);
        font-size: 0.9rem;
        padding: 8px 8px 4px;
        background: var(--secondary-color);
    }
    
    .category-card .product-count {
        font-size: 0.78rem;
        color: var(--text-light);
        margin-top: 2px;
    }

    @media (max-width: 768px) {
        .category-card .category-image {
            max-height: 80px;
        }
        .category-card .category-name {
            font-size: 0.75rem;
            padding: 5px 2px;
        }
        .category-card .product-count {
            font-size: 0.65rem;
        }
    }
    
    /* Product Card - Minimal */
    .product-card {
        background: var(--secondary-color);
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .product-card:hover {
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        border-color: var(--primary-color);
    }
    
    .product-card .product-image {
        position: relative;
        padding-top: 100%;
        overflow: hidden;
        background: var(--bg-light);
    }
    
    .product-card .product-image img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .product-card:hover .product-image img {
        transform: scale(1.03);
    }
    
    .product-card .product-info {
        padding: 12px;
    }
    
    .product-card .product-title {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 6px;
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
    
    .product-card .product-price {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    
    .product-card .current-price {
        font-size: 1rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .product-card .original-price {
        font-size: 0.8rem;
        color: var(--text-light);
        text-decoration: line-through;
    }
    
    .product-card .product-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--border-color);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-color);
    }
    
    .section-header h2 {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .section-header .view-all {
        color: var(--text-color);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    .section-header .view-all:hover {
        color: var(--primary-color);
    }
    
    .btn-wholesale {
        background: var(--primary-color);
        color: var(--secondary-color);
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.3s ease;
    }
    
    .btn-wholesale:hover {
        color: var(--secondary-color);
        background: var(--accent-color);
    }
</style>

<!-- Wholesale Hero -->
<div class="wholesale-hero">
    <div class="container">
        <h1><i class="bi bi-shop me-2"></i>পাইকারি বিভাগ</h1>
        <p>সেরা দামে পাইকারি পণ্য কিনুন। ন্যূনতম অর্ডার পরিমাণে বিশেষ ছাড়!</p>
    </div>
</div>

<div class="container">
    <!-- Top Categories -->
    <?php if (!empty($topCategories)): ?>
    <section class="mb-5">
        <div class="section-header">
            <h2><i class="bi bi-grid-3x3-gap text-primary"></i> টপ ক্যাটেগরি</h2>
            <a href="<?php echo baseUrl('wholesale/categories.php'); ?>" class="view-all">সব দেখুন <i class="bi bi-arrow-right"></i></a>
        </div>
        
        <div class="row g-2">
            <?php foreach ($topCategories as $category): ?>
            <div class="col-4 col-md-4 col-lg-3">
                <a href="<?php echo baseUrl('wholesale/category/' . $category['slug']); ?>" class="category-card">
                    <div class="category-image">
                        <?php if ($category['image']): ?>
                        <img src="<?php echo imageUrl($category['image']); ?>" alt="<?php echo $category['name']; ?>">
                        <?php else: ?>
                        <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo $category['name']; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="category-name">
                        <?php echo $category['name']; ?>
                        <div class="product-count"><?php echo $category['product_count']; ?> টি পণ্য</div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Hot Deals -->
    <?php if (!empty($hotDeals)): ?>
    <section class="mb-5">
        <div class="section-header">
            <h2><i class="bi bi-fire text-danger"></i> হট ডিলস</h2>
            <a href="<?php echo baseUrl('wholesale/hot-deals'); ?>" class="view-all">সব দেখুন <i class="bi bi-arrow-right"></i></a>
        </div>
        
        <div class="row g-3">
            <?php foreach ($hotDeals as $product): ?>
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
                        <div class="product-price">
                            <span class="current-price"><?php echo formatPrice($product['wholesale_price']); ?></span>
                            <?php if ($product['regular_price'] > $product['wholesale_price']): ?>
                            <span class="original-price"><?php echo formatPrice($product['regular_price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-meta">
                            <span class="min-qty-badge">ন্যূনতম: <?php echo $product['min_order_qty']; ?> পিস</span>
                            <?php if ($product['stock'] !== null && $product['stock'] <= 0): ?>
                            <a href="#" onclick="addToWishlist(<?php echo $product['id']; ?>, 'wholesale'); return false;" class="btn-wholesale btn-sm" style="background: #6c757d;">
                                <i class="bi bi-heart"></i> উইশলিস্ট
                            </a>
                            <?php else: ?>
                            <a href="<?php echo baseUrl('wholesale/product/' . $product['slug']); ?>" class="btn-wholesale btn-sm">
                                <i class="bi bi-bag-plus"></i> অর্ডার
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- All Products -->
    <?php if (!empty($allProducts)): ?>
    <section class="mb-5">
        <div class="section-header">
            <h2><i class="bi bi-box-seam"></i> সকল পণ্য</h2>
            <a href="<?php echo baseUrl('wholesale/shop'); ?>" class="view-all">সব দেখুন <i class="bi bi-arrow-right"></i></a>
        </div>
        
        <div class="row g-3">
            <?php foreach ($allProducts as $product): ?>
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
                        <div class="product-price">
                            <span class="current-price"><?php echo formatPrice($product['wholesale_price']); ?></span>
                            <?php if ($product['regular_price'] > $product['wholesale_price']): ?>
                            <span class="original-price"><?php echo formatPrice($product['regular_price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-meta">
                            <span class="min-qty-badge">ন্যূনতম: <?php echo $product['min_order_qty']; ?> পিস</span>
                            <?php if ($product['stock'] !== null && $product['stock'] <= 0): ?>
                            <a href="#" onclick="addToWishlist(<?php echo $product['id']; ?>, 'wholesale'); return false;" class="btn-wholesale btn-sm" style="background: #6c757d;">
                                <i class="bi bi-heart"></i> উইশলিস্ট
                            </a>
                            <?php else: ?>
                            <a href="<?php echo baseUrl('wholesale/product/' . $product['slug']); ?>" class="btn-wholesale btn-sm">
                                <i class="bi bi-bag-plus"></i> অর্ডার
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <?php if (empty($topCategories) && empty($hotDeals) && empty($allProducts)): ?>
    <div class="text-center py-5">
        <i class="bi bi-box-seam" style="font-size: 4rem; color: #ddd;"></i>
        <h3 class="mt-3">পাইকারি পণ্য শীঘ্রই আসছে!</h3>
        <p class="text-muted">আমরা পাইকারি বিভাগে নতুন পণ্য যোগ করছি। অনুগ্রহ করে অপেক্ষা করুন।</p>
        <a href="<?php echo baseUrl(); ?>" class="btn btn-primary-custom mt-3">রিটেইল দোকানে যান</a>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
