<?php
/**
 * Kilagbo Affiliate Portal - Wholesale Products Page
 * Shows products added from wholesale section
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'পাইকারি পণ্য';
$currentPage = 'products-wholesale';
$affiliatorId = getAffiliatorId();

// Handle link generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_link'])) {
    $productId = intval($_POST['product_id']);
    
    // Check if link already exists
    $stmt = $pdo->prepare("SELECT * FROM affiliate_links WHERE affiliator_id = ? AND product_id = ?");
    $stmt->execute([$affiliatorId, $productId]);
    $existingLink = $stmt->fetch();
    
    if ($existingLink) {
        setFlash('info', 'এই পণ্যের জন্য আপনার অ্যাফিলিয়েট লিংক আগেই তৈরি হয়েছে');
    } else {
        // Generate unique link code
        $linkCode = generateAffiliateLinkCode();
        
        $stmt = $pdo->prepare("INSERT INTO affiliate_links (affiliator_id, product_id, link_code) VALUES (?, ?, ?)");
        $stmt->execute([$affiliatorId, $productId, $linkCode]);
        
        setFlash('success', 'অ্যাফিলিয়েট লিংক সফলভাবে তৈরি হয়েছে!');
    }
    
    redirect(baseUrl('affiliate/products-wholesale.php'));
}

// Get search/filter params
$search = sanitize($_GET['search'] ?? '');

// Build query - only get products from wholesale (wholesale_product_id is NOT NULL)
$where = "WHERE ap.status = 1 AND ap.wholesale_product_id IS NOT NULL AND ap.wholesale_product_id > 0";
$params = [];

if ($search) {
    $where .= " AND (ap.name LIKE ? OR ap.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get products with affiliator's link info
$stmt = $pdo->prepare("
    SELECT ap.*, 
           al.id as link_id, al.link_code, al.clicks, al.conversions, al.total_earned
    FROM affiliate_products ap
    LEFT JOIN affiliate_links al ON ap.id = al.product_id AND al.affiliator_id = ?
    $where
    ORDER BY ap.created_at DESC
");
$stmt->execute(array_merge([$affiliatorId], $params));
$products = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .filter-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .product-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .product-card .product-image {
        position: relative;
        aspect-ratio: 1;
        background: #f8f9fa;
    }
    
    .product-card .product-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 15px;
    }
    
    .product-card .commission-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #10b981;
        color: #fff;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .product-card .source-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #f59e0b;
        color: #fff;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .product-card .product-info {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    
    .product-card .product-name {
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.95rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-card .product-price {
        margin-bottom: 10px;
    }
    
    .product-card .current-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: #10b981;
    }
    
    .product-card .regular-price {
        font-size: 0.85rem;
        color: #999;
        text-decoration: line-through;
        margin-left: 5px;
    }
    
    .product-card .commission-info {
        background: #ecfdf5;
        border-radius: 8px;
        padding: 10px;
        margin-bottom: 15px;
        font-size: 0.85rem;
    }
    
    .product-card .commission-info strong {
        color: #10b981;
    }
    
    .product-card .link-actions {
        margin-top: auto;
    }
    
    .product-card .link-box {
        background: #f3f4f6;
        border-radius: 8px;
        padding: 10px;
        margin-bottom: 10px;
    }
    
    .product-card .link-stats {
        display: flex;
        gap: 15px;
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 8px;
    }
    
    .product-card .link-url {
        font-size: 0.75rem;
        word-break: break-all;
        color: #10b981;
    }
    
    .btn-generate {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16,185,129,0.3);
        color: #fff;
    }
    
    .btn-copy {
        font-size: 0.8rem;
        padding: 5px 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #666;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #ddd;
    }
</style>

<?php showFlash(); ?>

<div class="alert alert-warning mb-4">
    <i class="bi bi-boxes me-2"></i>
    এখানে শুধুমাত্র পাইকারি সেকশন থেকে যোগ করা পণ্য দেখানো হচ্ছে।
</div>

<!-- Filter -->
<div class="filter-card">
    <form method="GET" class="row g-3">
        <div class="col-md-10">
            <input type="text" class="form-control" name="search" placeholder="পণ্যের নাম খুঁজুন..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-success w-100"><i class="bi bi-search me-1"></i>খুঁজুন</button>
        </div>
    </form>
</div>

<!-- Products Grid -->
<?php if (empty($products)): ?>
<div class="empty-state aff-card">
    <i class="bi bi-boxes"></i>
    <h5 class="mt-3">কোনো পাইকারি পণ্য পাওয়া যায়নি</h5>
    <p>অ্যাডমিন শীঘ্রই পণ্য যোগ করবেন</p>
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($products as $product): ?>
    <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="product-card">
            <div class="product-image">
                <img src="<?php echo imageUrl($product['image']); ?>" alt="<?php echo sanitize($product['name']); ?>">
                <span class="source-badge">
                    <i class="bi bi-boxes me-1"></i>পাইকারি
                </span>
                <span class="commission-badge">
                    <?php if ($product['commission_type'] === 'percentage'): ?>
                    <?php echo $product['commission_value']; ?>% কমিশন
                    <?php else: ?>
                    ৳<?php echo number_format($product['commission_value']); ?> কমিশন
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="product-info">
                <h6 class="product-name"><?php echo sanitize(html_entity_decode($product['name'] ?? '', ENT_QUOTES | ENT_HTML5)); ?></h6>
                
                <div class="product-price">
                    <span class="current-price"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                    <?php if ($product['sale_price']): ?>
                    <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="commission-info">
                    <i class="bi bi-cash-coin me-1"></i>
                    প্রতি বিক্রয়ে আপনার আয়: 
                    <strong>
                        <?php 
                        $price = $product['sale_price'] ?: $product['regular_price'];
                        $commission = calculateAffiliateCommission($price, $product['commission_type'], $product['commission_value']);
                        echo formatPrice($commission);
                        ?>
                    </strong>
                </div>
                
                <div class="link-actions">
                    <?php if ($product['link_id']): ?>
                    <!-- Already has link -->
                    <div class="link-box">
                        <div class="link-stats">
                            <span><i class="bi bi-cursor"></i> <?php echo $product['clicks']; ?> ক্লিক</span>
                            <span><i class="bi bi-bag-check"></i> <?php echo $product['conversions']; ?> বিক্রয়</span>
                            <span><i class="bi bi-cash"></i> <?php echo formatPrice($product['total_earned']); ?></span>
                        </div>
                        <div class="link-url" id="link-<?php echo $product['link_id']; ?>">
                            <?php echo baseUrl('a/' . $product['link_code']); ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-copy w-100" onclick="copyToClipboard('<?php echo baseUrl('a/' . $product['link_code']); ?>', this)">
                        <i class="bi bi-clipboard me-1"></i>লিংক কপি করুন
                    </button>
                    <?php else: ?>
                    <!-- Generate link button -->
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" name="generate_link" class="btn btn-generate">
                            <i class="bi bi-link-45deg me-1"></i>অ্যাফিলিয়েট লিংক তৈরি করুন
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
