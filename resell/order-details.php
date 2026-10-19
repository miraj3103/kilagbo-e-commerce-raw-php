<?php
/**
 * Kilagbo Reseller - Order Details Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অর্ডার বিবরণ';
$currentPage = 'orders';

$orderId = intval($_GET['id'] ?? 0);
$resellerId = getResellerId();

if (!$orderId) {
    redirect(baseUrl('resell/orders.php'));
}

// Get order
$stmt = $pdo->prepare("SELECT * FROM resell_orders WHERE id = ? AND reseller_id = ?");
$stmt->execute([$orderId, $resellerId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'অর্ডার পাওয়া যায়নি');
    redirect(baseUrl('resell/orders.php'));
}

// Get order items
$stmt = $pdo->prepare("SELECT * FROM resell_order_items WHERE order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .order-header {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .order-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .status-badge {
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: 600;
    }
    
    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-shipped { background: #d1ecf1; color: #0c5460; }
    .status-delivered { background: #d4edda; color: #155724; }
    .status-partial { background: #fff3cd; color: #856404; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    
    .detail-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        height: calc(100% - 20px);
    }
    
    .detail-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: #666;
    }
    
    .info-value {
        font-weight: 600;
        text-align: right;
    }
    
    .product-item {
        display: flex;
        gap: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        margin-bottom: 10px;
    }
    
    .product-item img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .product-item .info h6 {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .profit-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 20px;
        color: #fff;
        text-align: center;
    }
    
    .profit-box h3 {
        font-size: 2rem;
        font-weight: 700;
        margin: 10px 0;
    }
    
    .profit-box .credited {
        background: rgba(255,255,255,0.2);
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        display: inline-block;
    }
</style>

<a href="<?php echo baseUrl('resell/orders.php'); ?>" class="btn btn-outline-secondary mb-3">
    <i class="bi bi-arrow-left me-2"></i>অর্ডার তালিকায় ফিরুন
</a>

<?php if ($order['status'] === 'partial' && $order['partial_delivery_paid'] !== null): ?>
<div class="alert alert-warning">
    ডেলিভারি চার্জ: <strong><?php echo formatPrice($order['delivery_charge']); ?></strong>;
    গ্রাহক পরিশোধ করেছেন: <strong><?php echo formatPrice($order['partial_delivery_paid']); ?></strong>;
    ব্যালেন্স থেকে কর্তন: <strong><?php echo formatPrice($order['partial_delivery_deducted']); ?></strong>;
    এখনও Due: <strong><?php echo formatPrice($order['partial_delivery_due'] ?? 0); ?></strong>।
</div>
<?php endif; ?>

<div class="order-header">
    <div>
        <span class="order-number">#<?php echo $order['order_number']; ?></span>
        <p class="text-muted mb-0"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>
    </div>
    <span class="status-badge status-<?php echo $order['status']; ?>">
        <?php 
        $statusLabels = [
            'pending' => 'পেন্ডিং',
            'processing' => 'প্রসেসিং',
            'shipped' => 'শিপড',
            'delivered' => 'ডেলিভার্ড',
            'cancelled' => 'বাতিল',
            'returned' => 'রিটার্ন',
            'partial' => 'পার্শিয়াল'
        ];
        echo $statusLabels[$order['status']] ?? $order['status'];
        ?>
    </span>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="detail-card">
            <h5><i class="bi bi-person me-2"></i>কাস্টমার তথ্য</h5>
            
            <div class="info-row">
                <span class="info-label">নাম</span>
                <span class="info-value"><?php echo sanitize($order['customer_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ফোন</span>
                <span class="info-value"><?php echo $order['customer_phone']; ?></span>
            </div>
            <?php if ($order['customer_email']): ?>
            <div class="info-row">
                <span class="info-label">ইমেইল</span>
                <span class="info-value"><?php echo $order['customer_email']; ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">ঠিকানা</span>
                <span class="info-value"><?php echo sanitize($order['shipping_address']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">এলাকা</span>
                <span class="info-value"><?php echo $order['shipping_area'] === 'inside_dhaka' ? 'ঢাকার ভিতরে' : 'ঢাকার বাইরে'; ?></span>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="detail-card">
            <h5><i class="bi bi-box-seam me-2"></i>পণ্য</h5>
            
            <?php foreach ($items as $item): ?>
            <div class="product-item">
                <img src="<?php echo imageUrl($item['product_image']); ?>" alt="">
                <div class="info">
                    <h6><?php echo sanitize($item['product_name']); ?></h6>
                    <p class="mb-1">পরিমাণ: <?php echo $item['quantity']; ?> পিস</p>
                    <p class="mb-0">
                        <small class="text-muted">মূল মূল্য: <?php echo formatPrice($item['original_price']); ?></small><br>
                        <strong>বিক্রয় মূল্য: <?php echo formatPrice($item['reseller_price']); ?></strong>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="detail-card">
            <h5><i class="bi bi-receipt me-2"></i>হিসাব</h5>
            
            <div class="info-row">
                <span class="info-label">পণ্য মূল্য</span>
                <span class="info-value"><?php echo formatPrice($order['original_price']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ডেলিভারি চার্জ</span>
                <span class="info-value"><?php echo formatPrice($order['delivery_charge']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">মোট খরচ</span>
                <span class="info-value"><?php echo formatPrice($order['original_price'] + $order['delivery_charge']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">বিক্রয় মূল্য</span>
                <span class="info-value"><?php echo formatPrice($order['reseller_price']); ?></span>
            </div>
        </div>
        
        <div class="profit-box">
            <?php 
            $isCancelled = in_array($order['status'], ['cancelled', 'returned', 'rejected', 'failed']);
            $isDelivered = $order['status'] === 'delivered';
            ?>
            <p class="mb-0">আপনার প্রফিট</p>
            <h3 style="<?php echo $isCancelled ? 'text-decoration: line-through;' : ''; ?>"><?php echo formatPrice($order['profit']); ?></h3>
            <?php if ($isDelivered): ?>
            <span class="credited"><i class="bi bi-check-circle me-1"></i>ব্যালেন্সে যোগ হয়েছে</span>
            <?php elseif ($isCancelled): ?>
            <span class="credited" style="background: rgba(255,255,255,0.3);"><i class="bi bi-x-circle me-1"></i>প্রফিট বাতিল</span>
            <?php else: ?>
            <span class="credited" style="background: rgba(255,255,255,0.3);"><i class="bi bi-clock me-1"></i>পেন্ডিং</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($order['notes']): ?>
<div class="detail-card">
    <h5><i class="bi bi-sticky me-2"></i>নোট</h5>
    <p class="mb-0"><?php echo nl2br(sanitize($order['notes'])); ?></p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
