<?php
/**
 * Kilagbo - Wholesale Categories Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';

$currentPage = 'wholesale';
$pageTitle = 'পাইকারি ক্যাটেগরি';

// Fetch all wholesale enabled categories
$categoriesStmt = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM wholesale_products wp WHERE wp.category_id = c.id AND wp.status = 1) as product_count 
    FROM categories c 
    WHERE c.status = 1 AND c.wholesale_enabled = 1
    ORDER BY c.sort_order ASC, c.name ASC
");
$categories = $categoriesStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
    .categories-page {
        padding: 40px 0;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: var(--text-light);
        margin: 0;
    }
    
    /* Category Card - Large Square */
    .category-card {
        text-align: center;
        text-decoration: none;
        display: block;
        transition: all 0.3s ease;
        background: var(--secondary-color);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        height: 100%;
    }
    
    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border-color: var(--primary-color);
    }
    
    .category-card .category-image {
        width: 100%;
        aspect-ratio: 1;
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
    
    .category-card .category-info {
        padding: 20px 15px;
        background: var(--secondary-color);
    }
    
    .category-card .category-name {
        font-weight: 600;
        color: var(--text-color);
        font-size: 1.1rem;
        margin-bottom: 5px;
    }
    
    .category-card .product-count {
        font-size: 0.85rem;
        color: var(--text-light);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border-radius: 12px;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #ddd;
    }
</style>

<div class="categories-page">
    <div class="container">
        <div class="page-header">
            <h1><i class="bi bi-grid-3x3-gap me-2"></i>পাইকারি ক্যাটেগরি</h1>
            <p>আমাদের সকল পাইকারি পণ্যের ক্যাটেগরি ব্রাউজ করুন</p>
        </div>
        
        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <i class="bi bi-folder"></i>
            <h4 class="mt-3">কোনো ক্যাটেগরি পাওয়া যায়নি</h4>
            <p class="text-muted">পাইকারি ক্যাটেগরি শীঘ্রই যোগ করা হবে</p>
            <a href="<?php echo baseUrl('wholesale'); ?>" class="btn btn-primary-custom mt-3">পাইকারি হোমে যান</a>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($categories as $category): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?php echo baseUrl('wholesale/category/' . $category['slug']); ?>" class="category-card">
                    <div class="category-image">
                        <?php if ($category['image']): ?>
                        <img src="<?php echo imageUrl($category['image']); ?>" alt="<?php echo $category['name']; ?>">
                        <?php else: ?>
                        <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo $category['name']; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="category-info">
                        <div class="category-name"><?php echo $category['name']; ?></div>
                        <div class="product-count"><?php echo $category['product_count']; ?> টি পণ্য</div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
