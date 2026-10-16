<?php
/**
 * Kilagbo - Wishlist Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$pageTitle = 'উইশলিস্ট';

$sessionId = getSessionId();
$userId = $_SESSION['user_id'] ?? null;

// Handle remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove'])) {
    $wishlistId = intval($_POST['wishlist_id']);
    $pdo->prepare("DELETE FROM wishlist WHERE id = ?")->execute([$wishlistId]);
    setFlash('success', 'উইশলিস্ট থেকে সরানো হয়েছে');
    redirect(baseUrl('wishlist'));
}

// Fetch retail wishlist items
if ($userId) {
    $retailStmt = $pdo->prepare("
        SELECT w.*, w.section, p.name, p.slug, p.image, p.regular_price, p.sale_price, p.stock_quantity 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.id 
        WHERE w.user_id = ? AND w.section = 'retail'
    ");
    $retailStmt->execute([$userId]);
} else {
    $retailStmt = $pdo->prepare("
        SELECT w.*, w.section, p.name, p.slug, p.image, p.regular_price, p.sale_price, p.stock_quantity 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.id 
        WHERE w.session_id = ? AND w.section = 'retail'
    ");
    $retailStmt->execute([$sessionId]);
}
$retailItems = $retailStmt->fetchAll();

// Fetch wholesale wishlist items
if ($userId) {
    $wholesaleStmt = $pdo->prepare("
        SELECT w.*, w.section, wp.name, wp.slug, wp.image, wp.regular_price, wp.wholesale_price as sale_price, wp.stock as stock_quantity, wp.min_order_qty 
        FROM wishlist w 
        JOIN wholesale_products wp ON w.product_id = wp.id 
        WHERE w.user_id = ? AND w.section = 'wholesale'
    ");
    $wholesaleStmt->execute([$userId]);
} else {
    $wholesaleStmt = $pdo->prepare("
        SELECT w.*, w.section, wp.name, wp.slug, wp.image, wp.regular_price, wp.wholesale_price as sale_price, wp.stock as stock_quantity, wp.min_order_qty 
        FROM wishlist w 
        JOIN wholesale_products wp ON w.product_id = wp.id 
        WHERE w.session_id = ? AND w.section = 'wholesale'
    ");
    $wholesaleStmt->execute([$sessionId]);
}
$wholesaleItems = $wholesaleStmt->fetchAll();

// Combine all items
$wishlistItems = array_merge($retailItems, $wholesaleItems);

include 'includes/header.php';
?>

<style>
    .wishlist-page {
        padding: 40px 0;
    }
    
    .wishlist-header {
        margin-bottom: 30px;
    }
    
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 25px;
    }
    
    .wishlist-item {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .wishlist-item .item-image {
        position: relative;
        aspect-ratio: 1;
    }
    
    .wishlist-item .item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .wishlist-item .remove-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #fff;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .wishlist-item .remove-btn:hover {
        background: #dc3545;
        color: #fff;
    }
    
    .wishlist-item .item-info {
        padding: 20px;
    }
    
    .wishlist-item .item-name {
        font-weight: 500;
        margin-bottom: 10px;
    }
    
    .wishlist-item .item-name a {
        color: var(--text-color);
        text-decoration: none;
    }
    
    .wishlist-item .item-price {
        margin-bottom: 15px;
    }
    
    .wishlist-item .current-price {
        font-size: 1.2rem;
        font-weight: 600;
        color: #ff6600;
    }
    
    .wishlist-item .regular-price {
        text-decoration: line-through;
        color: var(--text-light);
        margin-right: 10px;
    }
    
    .empty-wishlist {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border-radius: 12px;
    }
    
    .empty-wishlist i {
        font-size: 4rem;
        color: #ddd;
    }
    
    .section-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        z-index: 1;
    }
    
    .section-badge.wholesale {
        background: #dc3545;
        color: #fff;
    }
    
    .section-badge.retail {
        background: #28a745;
        color: #fff;
    }
    
    .stock-out-badge {
        position: absolute;
        bottom: 10px;
        left: 10px;
        background: #6c757d;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .wishlist-item.stock-out {
        opacity: 0.7;
    }
</style>

<?php showFlash(); ?>

<div class="wishlist-page">
    <div class="container">
        <div class="wishlist-header">
            <h1>আমার উইশলিস্ট</h1>
            <p class="text-muted"><?php echo count($wishlistItems); ?> টি পণ্য</p>
        </div>
        
        <?php if (empty($wishlistItems)): ?>
        <div class="empty-wishlist">
            <i class="bi bi-heart"></i>
            <h4 class="mt-3">আপনার উইশলিস্ট খালি</h4>
            <p class="text-muted">পছন্দের পণ্য গুলো উইশলিস্টে যোগ করুন</p>
            <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-primary-custom">শপিং করুন</a>
        </div>
        <?php else: ?>
        <div class="wishlist-grid">
            <?php foreach ($wishlistItems as $item): 
                $isWholesale = ($item['section'] ?? 'retail') === 'wholesale';
                $isStockOut = $item['stock_quantity'] !== null && $item['stock_quantity'] <= 0;
                $productUrl = $isWholesale 
                    ? baseUrl('wholesale/product/' . $item['slug']) 
                    : baseUrl('product/' . $item['slug']);
                $orderUrl = $isWholesale 
                    ? baseUrl('wholesale/checkout?product_id=' . $item['product_id']) 
                    : baseUrl('quick-order?id=' . $item['product_id']);
                $imageUrl = $isWholesale ? imageUrl($item['image']) : $item['image'];
            ?>
            <div class="wishlist-item <?php echo $isStockOut ? 'stock-out' : ''; ?>">
                <div class="item-image">
                    <span class="section-badge <?php echo $isWholesale ? 'wholesale' : 'retail'; ?>">
                        <?php echo $isWholesale ? 'পাইকারি' : 'রিটেইল'; ?>
                    </span>
                    <?php if ($isStockOut): ?>
                    <span class="stock-out-badge">স্টক শেষ</span>
                    <?php endif; ?>
                    <a href="<?php echo $productUrl; ?>">
                        <img src="<?php echo $imageUrl; ?>" alt="<?php echo sanitize($item['name']); ?>">
                    </a>
                    <form method="POST">
                        <input type="hidden" name="wishlist_id" value="<?php echo $item['id']; ?>">
                        <button type="submit" name="remove" class="remove-btn" title="সরান">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </form>
                </div>
                <div class="item-info">
                    <h6 class="item-name">
                        <a href="<?php echo $productUrl; ?>"><?php echo sanitize($item['name']); ?></a>
                    </h6>
                    <div class="item-price">
                        <?php if ($item['sale_price'] && $item['sale_price'] < $item['regular_price']): ?>
                        <span class="regular-price"><?php echo formatPrice($item['regular_price']); ?></span>
                        <?php endif; ?>
                        <span class="current-price"><?php echo formatPrice($item['sale_price'] ?: $item['regular_price']); ?></span>
                    </div>
                    <?php if ($isStockOut): ?>
                    <button class="btn btn-secondary w-100" disabled>
                        <i class="bi bi-x-circle me-1"></i>স্টক শেষ
                    </button>
                    <?php else: ?>
                    <a href="<?php echo $orderUrl; ?>" class="btn btn-primary-custom w-100">
                        অর্ডার করুন
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
