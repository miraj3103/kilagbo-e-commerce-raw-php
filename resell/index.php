<?php
/**
 * Kilagbo Reseller Dashboard - Home
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'ড্যাশবোর্ড';
$currentPage = 'dashboard';

// Get stats
$resellerId = getResellerId();

// Total orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM resell_orders WHERE reseller_id = ?");
$stmt->execute([$resellerId]);
$totalOrders = $stmt->fetchColumn();

// Pending orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM resell_orders WHERE reseller_id = ? AND status = 'pending'");
$stmt->execute([$resellerId]);
$pendingOrders = $stmt->fetchColumn();

// Delivered orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM resell_orders WHERE reseller_id = ? AND status = 'delivered'");
$stmt->execute([$resellerId]);
$deliveredOrders = $stmt->fetchColumn();

// This month earnings
$stmt = $pdo->prepare("SELECT COALESCE(SUM(profit), 0) FROM resell_orders WHERE reseller_id = ? AND status = 'delivered' AND profit_credited = 1 AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stmt->execute([$resellerId]);
$monthlyEarnings = $stmt->fetchColumn();

// Recent orders
$stmt = $pdo->prepare("SELECT * FROM resell_orders WHERE reseller_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$resellerId]);
$recentOrders = $stmt->fetchAll();

// Trending products
$trendingProducts = $pdo->query("SELECT * FROM resell_products WHERE is_hot_deal = 1 AND status = 1 ORDER BY created_at DESC LIMIT 4")->fetchAll();

// Calculate correct current balance
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN profit_credited = 1 THEN profit ELSE 0 END), 0) as total_earned_correct
    FROM resell_orders WHERE reseller_id = ?
");
$stmt->execute([$resellerId]);
$orderTotals = $stmt->fetch();

// Get total withdrawn from completed withdrawals
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_withdrawn_correct
    FROM reseller_withdrawals 
    WHERE reseller_id = ? AND status = 'completed'
");
$stmt->execute([$resellerId]);
$withdrawalTotals = $stmt->fetch();

$correctBalance = $orderTotals['total_earned_correct'] - $withdrawalTotals['total_withdrawn_correct'];

include __DIR__ . '/includes/header.php';
?>

<style>
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        height: 100%;
    }
    
    .stat-card .icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #fff;
    }
    
    .stat-card .icon.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
    .stat-card .icon.success { background: linear-gradient(135deg, #11998e, #38ef7d); }
    .stat-card .icon.warning { background: linear-gradient(135deg, #f093fb, #f5576c); }
    .stat-card .icon.info { background: linear-gradient(135deg, #4facfe, #00f2fe); }
    
    .stat-card h3 {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 15px 0 5px;
    }
    
    .stat-card p {
        color: #666;
        margin: 0;
        font-size: 0.9rem;
    }
    
    .section-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-top: 25px;
    }
    
    .section-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .order-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .order-item:last-child {
        border-bottom: none;
    }
    
    .order-number {
        font-weight: 600;
        color: var(--primary);
    }
    
    .status-badge {
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-shipped { background: #d4edda; color: #155724; }
    .status-delivered { background: #d1e7dd; color: #0f5132; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    
    .product-mini {
        display: flex;
        gap: 12px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 10px;
        margin-bottom: 10px;
    }
    
    .product-mini img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .product-mini-info h6 {
        font-weight: 600;
        margin-bottom: 5px;
        font-size: 0.9rem;
    }
    
    .product-mini-info p {
        margin: 0;
        color: var(--primary);
        font-weight: 600;
    }
    
    .welcome-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 30px;
        color: #fff;
        margin-bottom: 25px;
    }
    
    .welcome-banner h2 {
        font-weight: 700;
        margin-bottom: 10px;
    }
    
    .welcome-banner p {
        opacity: 0.9;
        margin: 0;
    }
</style>

<?php showFlash(); ?>

<div class="welcome-banner">
    <h2><i class="bi bi-stars me-2"></i>স্বাগতম, <?php echo sanitize($reseller['name']); ?>!</h2>
    <p><?php echo getSettings('reselling_welcome_message') ?: 'আপনার রিসেলার ড্যাশবোর্ডে স্বাগতম। এখানে থেকে পণ্য রিসেল করে আয় করুন।'; ?></p>
</div>

<div class="row g-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="icon primary">
                <i class="bi bi-wallet2"></i>
            </div>
            <h3><?php echo formatPrice($correctBalance); ?></h3>
            <p>বর্তমান ব্যালেন্স</p>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="icon success">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <h3><?php echo formatPrice($monthlyEarnings); ?></h3>
            <p>এই মাসের আয়</p>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="icon warning">
                <i class="bi bi-bag-check"></i>
            </div>
            <h3><?php echo $totalOrders; ?></h3>
            <p>মোট অর্ডার</p>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="icon info">
                <i class="bi bi-clock-history"></i>
            </div>
            <h3><?php echo $pendingOrders; ?></h3>
            <p>পেন্ডিং অর্ডার</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="section-card">
            <h5><i class="bi bi-clock me-2"></i>সাম্প্রতিক অর্ডার</h5>
            
            <?php if (empty($recentOrders)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">এখনো কোনো অর্ডার নেই</p>
                <a href="<?php echo baseUrl('resell/products.php'); ?>" class="btn btn-sm btn-primary">পণ্য দেখুন</a>
            </div>
            <?php else: ?>
            <?php foreach ($recentOrders as $order): ?>
            <div class="order-item">
                <div>
                    <span class="order-number">#<?php echo $order['order_number']; ?></span>
                    <small class="text-muted d-block"><?php echo date('d M Y', strtotime($order['created_at'])); ?></small>
                </div>
                <div class="text-end">
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php 
                        $statusLabels = [
                            'pending' => 'পেন্ডিং',
                            'processing' => 'প্রসেসিং',
                            'shipped' => 'শিপড',
                            'delivered' => 'ডেলিভার্ড',
                            'cancelled' => 'বাতিল'
                        ];
                        echo $statusLabels[$order['status']] ?? $order['status'];
                        ?>
                    </span>
                    <small class="text-muted d-block mt-1">প্রফিট: <?php echo formatPrice($order['profit']); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-center mt-3">
                <a href="<?php echo baseUrl('resell/orders.php'); ?>" class="btn btn-sm btn-outline-primary">সব অর্ডার দেখুন</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-lg-5">
        <div class="section-card">
            <h5><i class="bi bi-fire me-2"></i>ট্রেন্ডিং পণ্য</h5>
            
            <?php if (empty($trendingProducts)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-box-seam" style="font-size: 3rem;"></i>
                <p class="mt-2">কোনো ট্রেন্ডিং পণ্য নেই</p>
            </div>
            <?php else: ?>
            <?php foreach ($trendingProducts as $product): ?>
            <div class="product-mini">
                <img src="<?php echo imageUrl($product['image']); ?>" alt="">
                <div class="product-mini-info">
                    <h6><?php echo sanitize($product['name']); ?></h6>
                    <p><?php echo formatPrice($product['reseller_price']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-center mt-3">
                <a href="<?php echo baseUrl('resell/products.php'); ?>" class="btn btn-sm btn-outline-primary">সব পণ্য দেখুন</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
