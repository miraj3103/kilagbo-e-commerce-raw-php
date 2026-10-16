<?php
/**
 * Kilagbo Reseller - Orders Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অর্ডার সমূহ';
$currentPage = 'orders';

$resellerId = getResellerId();

// Mark orders notifications as seen
markResellerNotificationsSeen($resellerId, 'orders');

// Get filters
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Build query
$where = "WHERE ro.reseller_id = ?";
$params = [$resellerId];

if ($status) {
    $where .= " AND ro.status = ?";
    $params[] = $status;
}

if ($search) {
    $where .= " AND (ro.order_number LIKE ? OR ro.customer_name LIKE ? OR ro.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get orders
$stmt = $pdo->prepare("SELECT ro.* FROM resell_orders ro $where ORDER BY ro.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Stats
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'delivered' AND profit_credited = 1 THEN profit ELSE 0 END) as total_profit
    FROM resell_orders WHERE reseller_id = ?");
$stmt->execute([$resellerId]);
$stats = $stmt->fetch();

include __DIR__ . '/includes/header.php';
?>

<style>
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-mini {
        background: #fff;
        border-radius: 10px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-mini h4 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        color: var(--primary);
    }
    
    .stat-mini p {
        margin: 0;
        color: #666;
        font-size: 0.85rem;
    }
    
    .filter-bar {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .orders-table {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .orders-table table {
        margin: 0;
    }
    
    .orders-table th {
        background: #f8f9fa;
        font-weight: 600;
        padding: 15px;
        border: none;
    }
    
    .orders-table td {
        padding: 15px;
        vertical-align: middle;
    }
    
    .order-number {
        font-weight: 600;
        color: var(--primary);
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-shipped { background: #d1ecf1; color: #0c5460; }
    .status-delivered { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .status-returned { background: #e2e3e5; color: #383d41; }
    
    .profit-badge {
        font-weight: 600;
    }
    
    .profit-badge.credited {
        color: #28a745;
    }
    
    .profit-badge.pending {
        color: #ffc107;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #ddd;
    }
    
    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .orders-table {
            overflow-x: auto;
        }
    }
</style>

<?php showFlash(); ?>

<div class="stats-row">
    <div class="stat-mini">
        <h4><?php echo $stats['total']; ?></h4>
        <p>মোট অর্ডার</p>
    </div>
    <div class="stat-mini">
        <h4><?php echo $stats['pending']; ?></h4>
        <p>পেন্ডিং</p>
    </div>
    <div class="stat-mini">
        <h4><?php echo $stats['delivered']; ?></h4>
        <p>ডেলিভার্ড</p>
    </div>
    <div class="stat-mini">
        <h4><?php echo formatPrice($stats['total_profit']); ?></h4>
        <p>ক্রেডিটেড প্রফিট</p>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">অনুসন্ধান</label>
            <input type="text" class="form-control" name="search" placeholder="অর্ডার নম্বর, নাম, ফোন..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">স্ট্যাটাস</label>
            <select class="form-select" name="status">
                <option value="">সব স্ট্যাটাস</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>শিপড</option>
                <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>ডেলিভার্ড</option>
                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search me-1"></i> খুঁজুন
            </button>
        </div>
    </form>
</div>

<div class="orders-table">
    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <i class="bi bi-bag-x"></i>
        <h4 class="mt-3">কোনো অর্ডার নেই</h4>
        <p class="text-muted">এখনো কোনো অর্ডার করেননি</p>
        <a href="<?php echo baseUrl('resell/products.php'); ?>" class="btn btn-primary mt-2">পণ্য দেখুন</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>অর্ডার নম্বর</th>
                    <th>কাস্টমার</th>
                    <th>মোট</th>
                    <th>প্রফিট</th>
                    <th>স্ট্যাটাস</th>
                    <th>তারিখ</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <span class="order-number">#<?php echo $order['order_number']; ?></span>
                    </td>
                    <td>
                        <strong><?php echo sanitize($order['customer_name']); ?></strong><br>
                        <small class="text-muted"><?php echo $order['customer_phone']; ?></small>
                    </td>
                    <td><?php echo formatPrice($order['reseller_price']); ?></td>
                    <td>
                        <?php 
                        $isCancelled = in_array($order['status'], ['cancelled', 'returned', 'rejected', 'failed']);
                        $isDelivered = $order['status'] === 'delivered';
                        ?>
                        <span class="profit-badge <?php echo $isDelivered ? 'credited' : ($isCancelled ? 'cancelled' : 'pending'); ?>" style="<?php echo $isCancelled ? 'text-decoration: line-through;' : ''; ?>">
                            <?php echo formatPrice($order['profit']); ?>
                            <?php if ($isDelivered): ?>
                            <i class="bi bi-check-circle-fill"></i>
                            <?php elseif ($isCancelled): ?>
                            <i class="bi bi-x-circle-fill text-muted"></i>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php 
                            $statusLabels = [
                                'pending' => 'পেন্ডিং',
                                'processing' => 'প্রসেসিং',
                                'shipped' => 'শিপড',
                                'delivered' => 'ডেলিভার্ড',
                                'cancelled' => 'বাতিল',
                                'returned' => 'রিটার্ন'
                            ];
                            echo $statusLabels[$order['status']] ?? $order['status'];
                            ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                    <td>
                        <a href="<?php echo baseUrl('resell/order-details.php?id=' . $order['id']); ?>" class="btn btn-sm btn-outline-primary" title="বিস্তারিত দেখুন">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php 
                        $hasTrackingLink = !empty($order['tracking_link'] ?? '');
                        ?>
                        <?php if ($hasTrackingLink): ?>
                        <a href="<?php echo $order['tracking_link']; ?>" target="_blank" class="btn btn-sm btn-success" title="ট্র্যাকিং লিংক - ক্লিক করে ট্র্যাক করুন">
                            <i class="bi bi-info-circle-fill"></i>
                        </a>
                        <?php else: ?>
                        <span class="btn btn-sm btn-outline-success disabled" title="ট্র্যাকিং লিংক এখনো পাঠানো হয়নি">
                            <i class="bi bi-info-circle"></i>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
