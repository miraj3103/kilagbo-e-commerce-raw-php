<?php
/**
 * Kilagbo - Admin Dashboard
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'ড্যাশবোর্ড';
$currentPage = 'dashboard';

require_once 'includes/header.php';

// Get statistics
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'delivered'")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Recent orders
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Top selling products
$topProducts = $pdo->query("
    SELECT p.*, COALESCE(SUM(oi.quantity), 0) as sold_count 
    FROM products p 
    LEFT JOIN order_items oi ON p.id = oi.product_id 
    GROUP BY p.id 
    ORDER BY sold_count DESC 
    LIMIT 5
")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-value"><?php echo $totalProducts; ?></div>
            <div class="stat-label">মোট পণ্য</div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3e0; color: #f57c00;">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="stat-value"><?php echo $totalOrders; ?></div>
            <div class="stat-label">মোট অর্ডার</div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fce4ec; color: #c2185b;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value"><?php echo $pendingOrders; ?></div>
            <div class="stat-label">পেন্ডিং অর্ডার</div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9; color: #388e3c;">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-value"><?php echo formatPrice($totalRevenue); ?></div>
            <div class="stat-label">মোট আয়</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="admin-card">
            <h5 class="card-title">সাম্প্রতিক অর্ডার</h5>
            
            <?php if (empty($recentOrders)): ?>
            <p class="text-muted">কোন অর্ডার নেই</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>অর্ডার নং</th>
                            <th>কাস্টমার</th>
                            <th>মোট</th>
                            <th>স্ট্যাটাস</th>
                            <th>তারিখ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><a href="<?php echo adminUrl('orders/view/' . $order['id']); ?>"><?php echo $order['order_number']; ?></a></td>
                            <td><?php echo sanitize($order['customer_name']); ?></td>
                            <td><?php echo formatPrice($order['total']); ?></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'pending' => '<span class="badge bg-warning">পেন্ডিং</span>',
                                    'processing' => '<span class="badge bg-info">প্রসেসিং</span>',
                                    'shipped' => '<span class="badge bg-primary">শিপড</span>',
                                    'delivered' => '<span class="badge bg-success">ডেলিভার্ড</span>',
                                    'cancelled' => '<span class="badge bg-danger">বাতিল</span>'
                                ];
                                echo $statusLabels[$order['status']] ?? $order['status'];
                                ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="<?php echo adminUrl('orders'); ?>" class="btn btn-admin-outline btn-sm">সব অর্ডার দেখুন</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="admin-card">
            <h5 class="card-title">বেস্ট সেলিং পণ্য</h5>
            
            <?php if (empty($topProducts)): ?>
            <p class="text-muted">কোন পণ্য নেই</p>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($topProducts as $product): ?>
                <div class="list-group-item d-flex align-items-center px-0">
                    <img src="<?php echo $product['image']; ?>" alt="" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px; margin-right: 12px;">
                    <div class="flex-grow-1">
                        <h6 class="mb-0" style="font-size: 0.9rem;"><?php echo sanitize($product['name']); ?></h6>
                        <small class="text-muted"><?php echo $product['sold_count']; ?> বিক্রি</small>
                    </div>
                    <strong><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="admin-card">
            <h5 class="card-title">দ্রুত লিংক</h5>
            <div class="d-grid gap-2">
                <a href="<?php echo adminUrl('products/add'); ?>" class="btn btn-admin">
                    <i class="bi bi-plus-circle me-2"></i>নতুন পণ্য যোগ করুন
                </a>
                <a href="<?php echo adminUrl('orders'); ?>" class="btn btn-admin-outline">
                    <i class="bi bi-receipt me-2"></i>অর্ডার দেখুন
                </a>
                <a href="<?php echo baseUrl(); ?>" target="_blank" class="btn btn-admin-outline">
                    <i class="bi bi-eye me-2"></i>ওয়েবসাইট দেখুন
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
