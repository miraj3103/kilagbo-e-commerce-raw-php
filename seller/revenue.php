<?php
/**
 * Kilagbo Seller Portal - Revenue/Earnings
 * View all earnings and transactions
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'আয়';
$currentPage = 'revenue';

$seller = requireSellerLogin();

// Get orders and earnings data
$orders = $pdo->prepare("
    SELECT * FROM seller_orders 
    WHERE seller_id = ? 
    ORDER BY created_at DESC
");
$orders->execute([$seller['id']]);
$allOrders = $orders->fetchAll();

// Calculate stats
$totalEarnings = 0;
$pendingEarnings = 0;
$completedOrders = 0;
$pendingOrders = 0;

foreach ($allOrders as $order) {
    if ($order['status'] === 'completed') {
        $totalEarnings += $order['total_amount'];
        $completedOrders++;
    } elseif ($order['status'] === 'pending' || $order['status'] === 'processing') {
        $pendingEarnings += $order['total_amount'];
        $pendingOrders++;
    }
}

// Get monthly earnings for chart (last 6 months)
$monthlyEarnings = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM seller_orders 
        WHERE seller_id = ? AND status = 'completed' 
        AND DATE_FORMAT(completed_at, '%Y-%m') = ?
    ");
    $stmt->execute([$seller['id'], $month]);
    $monthlyEarnings[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'amount' => floatval($stmt->fetchColumn())
    ];
}

require_once 'includes/header.php';
?>

<style>
    .earnings-card {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 25px;
    }
    
    .earnings-card h2 {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
    }
    
    .earnings-card p {
        opacity: 0.9;
        margin: 5px 0 0;
    }
    
    .stat-mini-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        text-align: center;
    }
    
    .stat-mini-card h4 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 5px;
    }
    
    .stat-mini-card p {
        color: #666;
        margin: 0;
        font-size: 0.9rem;
    }
    
    .transaction-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .transaction-item:last-child {
        border-bottom: none;
    }
    
    .transaction-icon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .transaction-icon.completed {
        background: rgba(22, 163, 74, 0.1);
        color: #16a34a;
    }
    
    .transaction-icon.pending {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }
    
    .transaction-icon.cancelled {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .chart-container {
        height: 300px;
        position: relative;
    }
    
    .bar-chart {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        height: 200px;
        padding: 20px 0;
    }
    
    .bar-item {
        flex: 1;
        margin: 0 5px;
        text-align: center;
    }
    
    .bar {
        background: linear-gradient(to top, #16a34a, #22c55e);
        border-radius: 8px 8px 0 0;
        min-height: 5px;
        transition: height 0.5s ease;
    }
    
    .bar-label {
        font-size: 0.75rem;
        color: #666;
        margin-top: 10px;
    }
    
    .bar-value {
        font-size: 0.7rem;
        color: #333;
        font-weight: 600;
        margin-bottom: 5px;
    }
</style>

<!-- Main Earnings Card -->
<div class="earnings-card">
    <div class="row align-items-center">
        <div class="col-md-6">
            <p>মোট আয়</p>
            <h2>৳<?php echo number_format($totalEarnings); ?></h2>
        </div>
        <div class="col-md-6 text-md-end">
            <p>পেন্ডিং আয়</p>
            <h4>৳<?php echo number_format($pendingEarnings); ?></h4>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-mini-card">
            <h4 class="text-success">৳<?php echo number_format($totalEarnings); ?></h4>
            <p>সম্পন্ন আয়</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-mini-card">
            <h4 class="text-warning">৳<?php echo number_format($pendingEarnings); ?></h4>
            <p>পেন্ডিং আয়</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-mini-card">
            <h4 class="text-primary"><?php echo $completedOrders; ?></h4>
            <p>সম্পন্ন অর্ডার</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-mini-card">
            <h4 class="text-secondary"><?php echo $pendingOrders; ?></h4>
            <p>পেন্ডিং অর্ডার</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Monthly Chart -->
    <div class="col-lg-7">
        <div class="seller-card">
            <h5 class="card-title">মাসিক আয় চার্ট</h5>
            
            <?php 
            $maxAmount = max(array_column($monthlyEarnings, 'amount'));
            if ($maxAmount == 0) $maxAmount = 1;
            ?>
            
            <div class="bar-chart">
                <?php foreach ($monthlyEarnings as $data): ?>
                <div class="bar-item">
                    <div class="bar-value">৳<?php echo number_format($data['amount']); ?></div>
                    <div class="bar" style="height: <?php echo ($data['amount'] / $maxAmount) * 180; ?>px;"></div>
                    <div class="bar-label"><?php echo $data['month']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="col-lg-5">
        <div class="seller-card">
            <h5 class="card-title">সাম্প্রতিক লেনদেন</h5>
            
            <?php if (empty($allOrders)): ?>
            <div class="text-center py-4">
                <i class="bi bi-receipt" style="font-size: 3rem; color: #ddd;"></i>
                <p class="text-muted mt-2">কোনো লেনদেন নেই</p>
            </div>
            <?php else: ?>
            <?php foreach (array_slice($allOrders, 0, 5) as $order): ?>
            <div class="transaction-item">
                <div class="d-flex align-items-center gap-3">
                    <div class="transaction-icon <?php echo $order['status']; ?>">
                        <?php if ($order['status'] === 'completed'): ?>
                        <i class="bi bi-check-circle"></i>
                        <?php elseif ($order['status'] === 'cancelled'): ?>
                        <i class="bi bi-x-circle"></i>
                        <?php else: ?>
                        <i class="bi bi-clock"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?php echo $order['order_number']; ?></strong>
                        <br>
                        <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></small>
                    </div>
                </div>
                <div class="text-end">
                    <strong class="<?php echo $order['status'] === 'completed' ? 'text-success' : 'text-muted'; ?>">
                        ৳<?php echo number_format($order['total_amount']); ?>
                    </strong>
                    <br>
                    <small class="text-muted"><?php echo $order['quantity']; ?> পিস</small>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="text-center mt-3">
                <a href="<?php echo baseUrl('seller/orders.php'); ?>" class="btn btn-seller-outline btn-sm">সব দেখুন</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- All Transactions Table -->
<div class="seller-card mt-4">
    <h5 class="card-title">সব লেনদেন</h5>
    
    <?php if (empty($allOrders)): ?>
    <div class="text-center py-5">
        <i class="bi bi-receipt" style="font-size: 4rem; color: #ddd;"></i>
        <p class="text-muted mt-3">কোনো লেনদেন নেই</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table seller-table">
            <thead>
                <tr>
                    <th>অর্ডার</th>
                    <th>পণ্য</th>
                    <th>পরিমাণ</th>
                    <th>মোট</th>
                    <th>স্ট্যাটাস</th>
                    <th>তারিখ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allOrders as $order): ?>
                <tr>
                    <td><strong><?php echo $order['order_number']; ?></strong></td>
                    <td><?php echo mb_strimwidth(sanitize($order['product_name']), 0, 30, '...'); ?></td>
                    <td><?php echo $order['quantity']; ?> পিস</td>
                    <td><strong>৳<?php echo number_format($order['total_amount']); ?></strong></td>
                    <td>
                        <?php if ($order['status'] === 'completed'): ?>
                        <span class="badge badge-approved">সম্পন্ন</span>
                        <?php elseif ($order['status'] === 'pending'): ?>
                        <span class="badge badge-pending">পেন্ডিং</span>
                        <?php elseif ($order['status'] === 'processing'): ?>
                        <span class="badge" style="background: #dbeafe; color: #1e40af;">প্রসেসিং</span>
                        <?php elseif ($order['status'] === 'cancelled'): ?>
                        <span class="badge badge-rejected">বাতিল</span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?php echo $order['status']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo date('d M Y', strtotime($order['created_at'])); ?>
                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
