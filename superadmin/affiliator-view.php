<?php
/**
 * Kilagbo Admin - View Affiliator Details
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliators';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট বিবরণ';

$affiliatorId = intval($_GET['id'] ?? 0);

if (!$affiliatorId) {
    redirect(adminUrl('affiliators.php'));
}

// Get affiliator
$stmt = $pdo->prepare("SELECT * FROM affiliators WHERE id = ?");
$stmt->execute([$affiliatorId]);
$affiliator = $stmt->fetch();

if (!$affiliator) {
    setFlash('error', 'অ্যাফিলিয়েট পাওয়া যায়নি');
    redirect(adminUrl('affiliators.php'));
}

// Get stats
$stats = [];

// Total links
$stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_links WHERE affiliator_id = ?");
$stmt->execute([$affiliatorId]);
$stats['links'] = $stmt->fetchColumn();

// Total orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_orders WHERE affiliator_id = ?");
$stmt->execute([$affiliatorId]);
$stats['orders'] = $stmt->fetchColumn();

// Total earnings
$stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_orders WHERE affiliator_id = ? AND commission_credited = 1");
$stmt->execute([$affiliatorId]);
$stats['earnings'] = $stmt->fetchColumn();

// Recent orders
$stmt = $pdo->prepare("
    SELECT ao.*, ap.name as product_name 
    FROM affiliate_orders ao
    JOIN affiliate_products ap ON ao.product_id = ap.id
    WHERE ao.affiliator_id = ? 
    ORDER BY ao.created_at DESC LIMIT 10
");
$stmt->execute([$affiliatorId]);
$recentOrders = $stmt->fetchAll();

// Top links
$stmt = $pdo->prepare("
    SELECT al.*, ap.name as product_name, ap.image as product_image
    FROM affiliate_links al
    JOIN affiliate_products ap ON al.product_id = ap.id
    WHERE al.affiliator_id = ?
    ORDER BY al.conversions DESC LIMIT 5
");
$stmt->execute([$affiliatorId]);
$topLinks = $stmt->fetchAll();

// Withdrawal history
$stmt = $pdo->prepare("SELECT * FROM affiliator_withdrawals WHERE affiliator_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$affiliatorId]);
$withdrawals = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-person me-2"></i>অ্যাফিলিয়েট বিবরণ</h4>
        <a href="<?php echo adminUrl('affiliators.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>তালিকায় ফিরুন
        </a>
    </div>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div style="width: 80px; height: 80px; background: #10b981; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; margin: 0 auto 15px;">
                        <?php echo strtoupper(substr($affiliator['name'], 0, 1)); ?>
                    </div>
                    <h5><?php echo sanitize($affiliator['name']); ?></h5>
                    <p class="text-muted mb-2"><?php echo $affiliator['email']; ?></p>
                    <p class="text-muted mb-3"><?php echo $affiliator['phone']; ?></p>
                    
                    <span class="badge bg-<?php echo $affiliator['status'] === 'approved' ? 'success' : ($affiliator['status'] === 'pending' ? 'warning' : 'danger'); ?> mb-3">
                        <?php 
                        $statusLabels = ['pending' => 'পেন্ডিং', 'approved' => 'অনুমোদিত', 'rejected' => 'প্রত্যাখ্যাত'];
                        echo $statusLabels[$affiliator['status']] ?? $affiliator['status'];
                        ?>
                    </span>
                    
                    <?php if ($affiliator['address']): ?>
                    <p class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo sanitize($affiliator['address']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Balance Card -->
            <div class="card mb-4" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;">
                <div class="card-body">
                    <h6 class="text-white-50">বর্তমান ব্যালেন্স</h6>
                    <h2 class="mb-3"><?php echo formatPrice($affiliator['balance']); ?></h2>
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <small class="text-white-50">মোট আয়</small>
                            <h6><?php echo formatPrice($affiliator['total_earned']); ?></h6>
                        </div>
                        <div class="col-6">
                            <small class="text-white-50">মোট উত্তোলন</small>
                            <h6><?php echo formatPrice($affiliator['total_withdrawn']); ?></h6>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Card -->
            <div class="card mb-4">
                <div class="card-header">পরিসংখ্যান</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>মোট লিংক:</span>
                        <strong><?php echo $stats['links']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>মোট ক্লিক:</span>
                        <strong><?php echo $affiliator['total_clicks']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>মোট সেলস:</span>
                        <strong><?php echo $affiliator['total_sales']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>মোট অর্ডার:</span>
                        <strong><?php echo $stats['orders']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>মোট কমিশন:</span>
                        <strong class="text-success"><?php echo formatPrice($stats['earnings']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Top Links -->
            <div class="card mb-4">
                <div class="card-header">টপ পারফর্মিং লিংক</div>
                <div class="card-body">
                    <?php if (empty($topLinks)): ?>
                    <p class="text-muted text-center py-3">কোনো লিংক নেই</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>পণ্য</th>
                                    <th>লিংক কোড</th>
                                    <th>ক্লিক</th>
                                    <th>বিক্রয়</th>
                                    <th>আয়</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topLinks as $link): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?php echo imageUrl($link['product_image']); ?>" alt="" style="width: 35px; height: 35px; object-fit: cover; border-radius: 6px;">
                                            <?php echo sanitize(mb_substr($link['product_name'], 0, 30)); ?>...
                                        </div>
                                    </td>
                                    <td><code><?php echo $link['link_code']; ?></code></td>
                                    <td><?php echo $link['clicks']; ?></td>
                                    <td><?php echo $link['conversions']; ?></td>
                                    <td class="text-success"><?php echo formatPrice($link['total_earned']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card mb-4">
                <div class="card-header">সাম্প্রতিক অর্ডার</div>
                <div class="card-body">
                    <?php if (empty($recentOrders)): ?>
                    <p class="text-muted text-center py-3">কোনো অর্ডার নেই</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>অর্ডার</th>
                                    <th>পণ্য</th>
                                    <th>গ্রাহক</th>
                                    <th>মোট</th>
                                    <th>কমিশন</th>
                                    <th>স্ট্যাটাস</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                                    <td><?php echo sanitize(mb_substr($order['product_name'], 0, 20)); ?>...</td>
                                    <td><?php echo sanitize($order['customer_name']); ?></td>
                                    <td><?php echo formatPrice($order['total_amount']); ?></td>
                                    <td class="text-success"><?php echo formatPrice($order['commission_amount']); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = ['pending' => 'warning', 'processing' => 'info', 'shipped' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger'];
                                        $statusLabel = ['pending' => 'পেন্ডিং', 'processing' => 'প্রসেসিং', 'shipped' => 'শিপড', 'delivered' => 'ডেলিভার্ড', 'cancelled' => 'বাতিল'];
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass[$order['status']] ?? 'secondary'; ?>">
                                            <?php echo $statusLabel[$order['status']] ?? $order['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Withdrawal History -->
            <div class="card">
                <div class="card-header">উত্তোলন ইতিহাস</div>
                <div class="card-body">
                    <?php if (empty($withdrawals)): ?>
                    <p class="text-muted text-center py-3">কোনো উত্তোলন নেই</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>পরিমাণ</th>
                                    <th>মেথড</th>
                                    <th>অ্যাকাউন্ট</th>
                                    <th>স্ট্যাটাস</th>
                                    <th>তারিখ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $w): ?>
                                <tr>
                                    <td><strong><?php echo formatPrice($w['amount']); ?></strong></td>
                                    <td class="text-uppercase"><?php echo $w['method_type']; ?></td>
                                    <td><?php echo $w['account_number']; ?></td>
                                    <td>
                                        <?php
                                        $wStatusClass = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'rejected' => 'danger'];
                                        $wStatusLabel = ['pending' => 'পেন্ডিং', 'processing' => 'প্রসেসিং', 'completed' => 'সম্পন্ন', 'rejected' => 'প্রত্যাখ্যাত'];
                                        ?>
                                        <span class="badge bg-<?php echo $wStatusClass[$w['status']] ?? 'secondary'; ?>">
                                            <?php echo $wStatusLabel[$w['status']] ?? $w['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($w['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
