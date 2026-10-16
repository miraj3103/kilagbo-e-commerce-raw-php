<?php
/**
 * Kilagbo - Hot Deals Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$pageTitle = 'হট ডিলস';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 24;
$offset = ($page - 1) * $perPage;

// Get total count
$totalStmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_hot_deal = 1 AND status = 1");
$totalProducts = $totalStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Fetch hot deal products
$stmt = $pdo->prepare("SELECT * FROM products WHERE is_hot_deal = 1 AND status = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
    .hot-deals-page {
        padding: 40px 0;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-header h1 {
        font-size: 1.8rem;
        font-weight: 600;
        margin: 0;
        color: #ff6600;
    }
    
    .products-count {
        color: var(--text-light);
        font-size: 0.95rem;
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }
    
    @media (max-width: 576px) {
        .products-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
    }
    
    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 40px;
    }
    
    .pagination .page-link {
        color: var(--text-color);
        border: 1px solid var(--border-color);
        padding: 10px 16px;
    }
    
    .pagination .page-item.active .page-link {
        background: #ff6600;
        border-color: #ff6600;
        color: #fff;
    }
    
    .pagination .page-link:hover {
        background: #fff5ee;
        color: #ff6600;
    }
</style>

<section class="hot-deals-page">
    <div class="container">
        <div class="page-header">
            <h1>হট ডিলস</h1>
            <span class="products-count"><?php echo $totalProducts; ?>টি পণ্য পাওয়া গেছে</span>
        </div>
        
        <?php if (count($products) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-image">
                    <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>">
                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                    <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                    <span class="sale-badge"><?php echo $discount; ?>% ছাড়</span>
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
                    <a href="<?php echo baseUrl('quick-order?id=' . $product['id']); ?>" class="btn btn-order">অর্ডার করুন</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="pagination-wrapper">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?php echo baseUrl('hot-deals?page=' . ($page - 1)); ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo baseUrl('hot-deals?page=' . $i); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?php echo baseUrl('hot-deals?page=' . ($page + 1)); ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-box-seam" style="font-size: 4rem; color: var(--text-light);"></i>
            <h4 class="mt-3">কোনো হট ডিল পাওয়া যায়নি</h4>
            <p class="text-muted">শীঘ্রই নতুন অফার আসছে!</p>
            <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-order" style="width: auto; display: inline-block; padding: 10px 30px;">
                দোকান দেখুন
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
