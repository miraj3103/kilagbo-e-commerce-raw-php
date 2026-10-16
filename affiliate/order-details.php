<?php
/**
 * Kilagbo Affiliate Portal - Order Details View
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অর্ডার বিবরণ';
$currentPage = 'orders';
$affiliatorId = getAffiliatorId();

$orderId = intval($_GET['id'] ?? 0);
if (!$orderId) {
    setFlash('error', 'অর্ডার আইডি পাওয়া যায়নি');
    redirect(baseUrl('affiliate/orders.php'));
}

// Get order data
$stmt = $pdo->prepare("
    SELECT ao.*, ap.name as product_name, ap.image as product_image
    FROM affiliate_orders ao
    JOIN affiliate_products ap ON ao.product_id = ap.id
    WHERE ao.id = ? AND ao.affiliator_id = ?
");
$stmt->execute([$orderId, $affiliatorId]);

$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'অর্ডার পাওয়া যায়নি');
    redirect(baseUrl('affiliate/orders.php'));
}

// Get order items (for variation)
$stmt = $pdo->prepare("SELECT * FROM affiliate_order_items WHERE order_id = ?");
$stmt->execute([$orderId]);
$orderItem = $stmt->fetch();
$variation = $orderItem['variation'] ? json_decode($orderItem['variation'], true) : [];



include __DIR__ . '/includes/header.php';

$statusLabels = [
    'pending' => 'পেন্ডিং',
    'processing' => 'প্রসেসিং',
    'shipped' => 'শিপড',
    'delivered' => 'ডেলিভার্ড',
    'cancelled' => 'বাতিল',
    'returned' => 'রিটার্ন'
];

$statusColors = [
    'pending' => 'warning',
    'processing' => 'info',
    'shipped' => 'primary',
    'delivered' => 'success',
    'cancelled' => 'danger',
    'returned' => 'secondary'
];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-cart-check me-2"></i>অর্ডার #<?php echo $order['order_number']; ?></h4>
        <a href="<?php echo baseUrl('affiliate/orders.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ফিরে যান
        </a>
    </div>

    <?php showFlash(); ?>

    <div class="row g-4">
        <div class="col-md-8">
            <!-- Order Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">অর্ডার তথ্য</h6>
                    <span class="badge bg-<?php echo $statusColors[$order['status']] ?? 'secondary'; ?>">
                        <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4 mb-md-0">
                            <h6 class="text-muted small text-uppercase fw-bold mb-3">গ্রাহক তথ্য</h6>
                            <p class="mb-1 fw-bold"><?php echo sanitize($order['customer_name']); ?></p>
                            <p class="mb-1 text-muted"><?php echo $order['customer_phone']; ?></p>
                            <?php if (!empty($order['customer_email'])): ?>
                                <p class="mb-1 text-muted"><?php echo sanitize($order['customer_email']); ?></p>
                            <?php endif; ?>
                            <p class="mb-0 text-muted mt-2">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?php echo sanitize($order['shipping_address']); ?>
                                <br>
                                <span class="badge bg-light text-dark mt-1">
                                    <?php echo $order['shipping_area'] === 'inside_dhaka' ? 'ঢাকার ভিতরে' : 'ঢাকার বাইরে'; ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted small text-uppercase fw-bold mb-3">অর্ডার তারিখ</h6>
                            <p class="mb-0 fw-bold"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($order['order_note'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-primary">অর্ডার নোট</h6>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(sanitize($order['order_note'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Product Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">অর্ডার আইটেম</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="border-0 ps-3">পণ্য</th>
                                    <th class="border-0 text-center">পরিমাণ</th>
                                    <th class="border-0 text-end pe-3">মূল্য</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-3 py-3">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo imageUrl($order['product_image']); ?>" alt="" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover; background: #f8f9fa;">
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo sanitize($order['product_name']); ?></h6>
                                                <?php if (!empty($variation)): ?>
                                                    <div class="mt-1">
                                                        <?php foreach ($variation as $key => $value): ?>
                                                            <small class="text-muted d-block"><?php echo sanitize($key); ?>: <?php echo sanitize($value); ?></small>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <p class="text-muted small mb-0 mt-1">অ্যাফিলিয়েট পণ্য</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo $orderItem['quantity'] ?? 1; ?></td>
                                    <td class="text-end pe-3 fw-bold"><?php echo formatPrice($orderItem['total_price'] ?? $order['product_price']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Payment Summary -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">মূল্য সংগ্রহ</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">পণ্য মূল্য</span>
                        <span><?php echo formatPrice($order['product_price']); ?></span>
                    </div>
                        <span class="text-muted">ডেলিভারি চার্জ</span>
                        <span><?php echo formatPrice($order['delivery_charge']); ?></span>
                    </div>
                    <?php if (!empty($order['payment_method'])): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">পেমেন্ট মেথড</span>
                        <span class="text-uppercase fw-bold"><?php echo $order['payment_method'] == 'cod' ? 'ক্যাশ অন ডেলিভারি' : 'অনলাইন পেমেন্ট'; ?></span>
                    </div>
                    <?php if ($order['payment_method'] == 'online'): ?>
                        <?php if (!empty($order['payment_number'])): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">হিসাব নম্বর</span>
                            <span><?php echo $order['payment_number']; ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['transaction_id'])): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">TXN ID</span>
                            <span class="text-break"><?php echo $order['transaction_id']; ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; ?>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between mb-0">
                        <span class="fw-bold">মোট পরিমাণ</span>
                        <span class="fw-bold text-success" style="font-size: 1.1rem;"><?php echo formatPrice($order['total_amount']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Commission Info -->
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body text-center py-4">
                    <h6 class="text-uppercase small fw-bold mb-2 opacity-75">আপনার কমিশন</h6>
                    <h3 class="mb-2 fw-bold"><?php echo formatPrice($order['commission_amount']); ?></h3>
                    <?php if ($order['commission_credited']): ?>
                        <div class="badge bg-white text-success px-3 py-2 rounded-pill shadow-sm">
                            <i class="bi bi-check-circle-fill me-1"></i> কমিশন জমা হয়েছে
                        </div>
                    <?php else: ?>
                        <div class="badge bg-white text-warning px-3 py-2 rounded-pill shadow-sm">
                            <i class="bi bi-clock-fill me-1"></i> কমিশন পেন্ডিং
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($order['notes'])): ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold">অর্ডার নোট</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0 text-muted small"><?php echo nl2br(sanitize($order['notes'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
