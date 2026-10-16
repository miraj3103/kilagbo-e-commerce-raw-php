<?php
/**
 * Kilagbo Seller Portal - Dashboard
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'ড্যাশবোর্ড';
$currentPage = 'dashboard';

$seller = requireSellerLogin();

// Get recent products
$recentProducts = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM seller_products WHERE seller_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$seller['id']]);
    $recentProducts = $stmt->fetchAll();
} catch (PDOException $e) {}

// Get recent orders
$recentOrders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM seller_orders WHERE seller_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$seller['id']]);
    $recentOrders = $stmt->fetchAll();
} catch (PDOException $e) {}

require_once 'includes/header.php';
?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(22, 163, 74, 0.1); color: #16a34a;">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-value"><?php echo $sellerStats['total_products']; ?></div>
            <div class="stat-label">মোট পণ্য</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?php echo $sellerStats['pending_products']; ?></div>
            <div class="stat-label">পেন্ডিং পণ্য</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="stat-value"><?php echo $sellerStats['total_orders']; ?></div>
            <div class="stat-label">মোট অর্ডার</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-value">৳<?php echo number_format($sellerStats['total_earnings']); ?></div>
            <div class="stat-label">মোট আয়</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Products -->
    <div class="col-lg-6">
        <div class="seller-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="card-title mb-0" style="border: none; padding: 0;">সাম্প্রতিক পণ্য</h5>
                <a href="<?php echo baseUrl('seller/products.php'); ?>" class="btn btn-sm btn-seller-outline">সব দেখুন</a>
            </div>
            
            <?php if (empty($recentProducts)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-box-seam" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-2 mb-0">কোনো পণ্য নেই</p>
                <a href="<?php echo baseUrl('seller/add-product.php'); ?>" class="btn btn-seller btn-sm mt-3">
                    <i class="bi bi-plus me-1"></i>পণ্য যোগ করুন
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table seller-table">
                    <thead>
                        <tr>
                            <th>পণ্য</th>
                            <th>দাম</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentProducts as $product): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($product['image']): ?>
                                    <img src="<?php echo $product['image']; ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                    <?php endif; ?>
                                    <span><?php echo mb_strimwidth($product['name'], 0, 30, '...'); ?></span>
                                </div>
                            </td>
                            <td><?php echo formatPrice($product['seller_price']); ?></td>
                            <td>
                                <?php if ($product['status'] == 'pending'): ?>
                                <span class="badge badge-pending">পেন্ডিং</span>
                                <?php elseif ($product['status'] == 'approved'): ?>
                                <span class="badge badge-approved">অনুমোদিত</span>
                                <?php else: ?>
                                <span class="badge badge-rejected">প্রত্যাখ্যাত</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="col-lg-6">
        <div class="seller-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="card-title mb-0" style="border: none; padding: 0;">সাম্প্রতিক অর্ডার</h5>
                <a href="<?php echo baseUrl('seller/orders.php'); ?>" class="btn btn-sm btn-seller-outline">সব দেখুন</a>
            </div>
            
            <?php if (empty($recentOrders)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-receipt" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-2 mb-0">কোনো অর্ডার নেই</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table seller-table">
                    <thead>
                        <tr>
                            <th>অর্ডার</th>
                            <th>পরিমাণ</th>
                            <th>মোট</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?php echo $order['order_number']; ?></td>
                            <td><?php echo $order['quantity']; ?> পিস</td>
                            <td>৳<?php echo number_format($order['total_amount']); ?></td>
                            <td>
                                <?php if ($order['status'] == 'pending'): ?>
                                <span class="badge badge-pending">পেন্ডিং</span>
                                <?php elseif ($order['status'] == 'processing'): ?>
                                <span class="badge" style="background: #dbeafe; color: #1e40af;">প্রসেসিং</span>
                                <?php elseif ($order['status'] == 'completed'): ?>
                                <span class="badge badge-approved">সম্পন্ন</span>
                                <?php else: ?>
                                <span class="badge badge-rejected">বাতিল</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="seller-card mt-4">
    <h5 class="card-title">দ্রুত অ্যাকশন</h5>
    <div class="row g-3">
        <div class="col-md-3">
            <a href="<?php echo baseUrl('seller/add-product.php'); ?>" class="d-block p-4 text-center rounded-3 text-decoration-none" style="background: rgba(22, 163, 74, 0.1);">
                <i class="bi bi-plus-circle text-success" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0 text-dark fw-500">পণ্য যোগ করুন</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?php echo baseUrl('seller/products.php'); ?>" class="d-block p-4 text-center rounded-3 text-decoration-none" style="background: rgba(59, 130, 246, 0.1);">
                <i class="bi bi-box-seam text-primary" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0 text-dark fw-500">পণ্য দেখুন</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?php echo baseUrl('seller/orders.php'); ?>" class="d-block p-4 text-center rounded-3 text-decoration-none" style="background: rgba(245, 158, 11, 0.1);">
                <i class="bi bi-receipt text-warning" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0 text-dark fw-500">অর্ডার দেখুন</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?php echo baseUrl('seller/revenue.php'); ?>" class="d-block p-4 text-center rounded-3 text-decoration-none" style="background: rgba(139, 92, 246, 0.1);">
                <i class="bi bi-graph-up text-purple" style="font-size: 2rem; color: #8b5cf6;"></i>
                <p class="mt-2 mb-0 text-dark fw-500">আয় দেখুন</p>
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
