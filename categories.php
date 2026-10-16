<?php
/**
 * Kilagbo - All Categories Page (Retail)
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$pageTitle = 'সকল ক্যাটেগরি';

// Fetch all active categories
$categoriesStmt = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 1) as product_count 
    FROM categories c 
    WHERE c.status = 1 
    ORDER BY c.sort_order ASC, c.name ASC
");
$categories = $categoriesStmt->fetchAll();

include 'includes/header.php';
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
    
    /* Category Card - Compact */
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
        padding: 10px;
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
        background: #f8f9fa;
        position: relative;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    
    .category-card .category-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 10px;
        transition: transform 0.3s ease;
    }
    
    .category-card:hover .category-image img {
        transform: scale(1.05);
    }
    
    .category-card .category-info {
        padding: 5px;
    }
    
    .category-card .category-name {
        font-weight: 600;
        color: var(--text-color);
        font-size: 1rem;
        margin-bottom: 2px;
    }
    
    .category-card .product-count {
        font-size: 0.8rem;
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

    @media (max-width: 768px) {
        .category-card .category-name {
            font-size: 0.9rem;
        }
        .category-card .category-image {
            margin-bottom: 5px;
        }
    }
</style>

<div class="categories-page">
    <div class="container">
        <div class="page-header">
            <h1><i class="bi bi-grid-3x3-gap me-2"></i>সকল ক্যাটেগরি</h1>
            <p>আমাদের সকল পণ্যের ক্যাটেগরি ব্রাউজ করুন</p>
        </div>
        
        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <i class="bi bi-folder"></i>
            <h4 class="mt-3">কোনো ক্যাটেগরি পাওয়া যায়নি</h4>
            <p class="text-muted">ক্যাটেগরি শীঘ্রই যোগ করা হবে</p>
            <a href="<?php echo baseUrl(); ?>" class="btn btn-primary-custom mt-3">হোমে যান</a>
        </div>
        <?php else: ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($categories as $category): ?>
            <div class="col-4 col-md-3 col-lg-2">
                <a href="<?php echo baseUrl('category/' . $category['slug']); ?>" class="category-card">
                    <div class="category-image">
                        <?php if ($category['image']): ?>
                        <img src="<?php echo $category['image']; ?>" alt="<?php echo sanitize($category['name']); ?>">
                        <?php else: ?>
                        <img src="https://via.placeholder.com/300x300?text=Category" alt="<?php echo sanitize($category['name']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="category-info">
                        <div class="category-name"><?php echo sanitize($category['name']); ?></div>
                        <div class="product-count"><?php echo $category['product_count']; ?> টি পণ্য</div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
