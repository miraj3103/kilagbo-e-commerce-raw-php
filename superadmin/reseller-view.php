<?php
/**
 * Kilagbo Admin - Reseller Details View
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resellers';
require_once 'includes/header.php';

$pageTitle = 'রিসেলার বিবরণ';

$resellerId = intval($_GET['id'] ?? 0);
if (!$resellerId) {
    setFlash('error', 'রিসেলার আইডি পাওয়া যায়নি');
    redirect(adminUrl('resellers.php'));
}

// Get reseller data
$stmt = $pdo->prepare("SELECT * FROM resellers WHERE id = ?");
$stmt->execute([$resellerId]);
$reseller = $stmt->fetch();

if (!$reseller) {
    setFlash('error', 'রিসেলার পাওয়া যায়নি');
    redirect(adminUrl('resellers.php'));
}

// Get reseller orders
$stmt = $pdo->prepare("
    SELECT ro.*, COUNT(roi.id) as item_count
    FROM resell_orders ro
    LEFT JOIN resell_order_items roi ON ro.id = roi.order_id
    WHERE ro.reseller_id = ?
    GROUP BY ro.id
    ORDER BY ro.created_at DESC
    LIMIT 10
");
$stmt->execute([$resellerId]);
$recentOrders = $stmt->fetchAll();

// Get balance history
$stmt = $pdo->prepare("
    SELECT * FROM reseller_balance_history
    WHERE reseller_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$resellerId]);
$balanceHistory = $stmt->fetchAll();

// Get withdrawal history
$stmt = $pdo->prepare("
    SELECT * FROM reseller_withdrawals
    WHERE reseller_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$resellerId]);
$withdrawals = $stmt->fetchAll();

// Calculate totals
$orderStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'delivered' THEN profit ELSE 0 END) as total_profit
    FROM resell_orders
    WHERE reseller_id = ?
");
$orderStats->execute([$resellerId]);
$stats = $orderStats->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-person-circle me-2"></i>রিসেলার বিবরণ</h4>
        <a href="<?php echo adminUrl('resellers.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ফিরে যান
        </a>
    </div>

    <?php showFlash(); ?>

    <!-- Reseller Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">ব্যক্তিগত তথ্য</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>নাম:</strong> <?php echo sanitize($reseller['name']); ?></p>
                            <p><strong>ইমেইল:</strong> <?php echo $reseller['email']; ?></p>
                            <p><strong>ফোন:</strong> <?php echo $reseller['phone']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>ঠিকানা:</strong> <?php echo sanitize($reseller['address'] ?: 'নেই'); ?></p>
                            <p><strong>স্ট্যাটাস:</strong>
                                <?php if ($reseller['status'] === 'pending'): ?>
                                    <span class="badge bg-warning">পেন্ডিং</span>
                                <?php elseif ($reseller['status'] === 'approved'): ?>
                                    <span class="badge bg-success">অনুমোদিত</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">প্রত্যাখ্যাত</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>রেজিস্ট্রেশন:</strong> <?php echo date('d M Y, H:i', strtotime($reseller['created_at'])); ?></p>
                        </div>
                    </div>
                    <?php if ($reseller['status'] === 'rejected' && $reseller['rejection_reason']): ?>
                    <div class="mt-3">
                        <strong>প্রত্যাখ্যানের কারণ:</strong>
                        <div class="alert alert-danger"><?php echo sanitize($reseller['rejection_reason']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">আর্থিক সারাংশ</h5>
                </div>
                <div class="card-body">
                    <p><strong>বর্তমান ব্যালেন্স:</strong> <?php echo formatPrice($reseller['balance']); ?></p>
                    <p><strong>মোট আয়:</strong> <?php echo formatPrice($reseller['total_earned']); ?></p>
                    <p><strong>মোট উত্তোলন:</strong> <?php echo formatPrice($reseller['total_withdrawn']); ?></p>
                    <p><strong>মোট অর্ডার:</strong> <?php echo $reseller['total_orders']; ?></p>
                    <p><strong>সম্পন্ন অর্ডার:</strong> <?php echo $stats['completed_orders'] ?: 0; ?></p>
                    <p><strong>মোট প্রফিট:</strong> <?php echo formatPrice($stats['total_profit'] ?: 0); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Orders -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">সাম্প্রতিক অর্ডার</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentOrders)): ?>
                        <p class="text-muted">কোনো অর্ডার নেই</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentOrders as $order): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>#<?php echo $order['order_number']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo sanitize($order['customer_name']); ?> - <?php echo formatPrice($order['reseller_price']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="d-flex align-items-center gap-2">
                                            <div>
                                                <span class="badge bg-<?php echo $order['status'] === 'delivered' ? 'success' : 'warning'; ?>">
                                                    <?php echo $order['status'] === 'delivered' ? 'ডেলিভার্ড' : 'পেন্ডিং'; ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo date('d M', strtotime($order['created_at'])); ?></small>
                                            </div>
                                            <a href="<?php echo adminUrl('resell-order-view/' . $order['id']); ?>" class="btn btn-sm btn-outline-primary" title="বিস্তারিত দেখুন">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Balance History -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">ব্যালেন্স ইতিহাস</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($balanceHistory)): ?>
                        <p class="text-muted">কোনো ব্যালেন্স লেনদেন নেই</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($balanceHistory as $history): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo $history['type'] === 'credit' ? '+' : '-'; ?><?php echo formatPrice($history['amount']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo sanitize($history['description']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $history['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                            <?php echo $history['type'] === 'credit' ? 'যোগ' : 'বিয়োগ'; ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo date('d M', strtotime($history['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Withdrawal History -->
    <?php if (!empty($withdrawals)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">উত্তোলন ইতিহাস</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>আইডি</th>
                                    <th>পরিমাণ</th>
                                    <th>পেমেন্ট মেথড</th>
                                    <th>স্ট্যাটাস</th>
                                    <th>তারিখ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $withdrawal): ?>
                                <tr>
                                    <td><?php echo $withdrawal['id']; ?></td>
                                    <td><?php echo formatPrice($withdrawal['amount']); ?></td>
                                    <td><?php echo sanitize($withdrawal['payment_method']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $withdrawal['status'] === 'completed' ? 'success' :
                                                 ($withdrawal['status'] === 'processing' ? 'warning' : 'danger');
                                        ?>">
                                            <?php
                                            echo $withdrawal['status'] === 'completed' ? 'সম্পন্ন' :
                                                 ($withdrawal['status'] === 'processing' ? 'প্রসেসিং' : 'বাতিল');
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($withdrawal['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
