<?php
/**
 * Kilagbo Admin - Affiliate Order View
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-orders';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট অর্ডার বিবরণ';

$orderId = intval($_GET['id'] ?? 0);
if (!$orderId) {
    setFlash('error', 'অর্ডার আইডি পাওয়া যায়নি');
    redirect(adminUrl('affiliate-orders.php'));
}

// Get order data
$stmt = $pdo->prepare("
    SELECT ao.*, ap.name as product_name, ap.image as product_image, 
           a.name as affiliator_name, a.email as affiliator_email, a.phone as affiliator_phone
    FROM affiliate_orders ao
    JOIN affiliate_products ap ON ao.product_id = ap.id
    JOIN affiliators a ON ao.affiliator_id = a.id
    WHERE ao.id = ?
");
$stmt->execute([$orderId]);

$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'অর্ডার পাওয়া যায়নি');
    redirect(adminUrl('affiliate-orders.php'));
}

// Get order items
$stmt = $pdo->prepare("SELECT * FROM affiliate_order_items WHERE order_id = ?");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();
$orderItem = $orderItems[0] ?? null;
$variation = ($orderItem && $orderItem['variation']) ? json_decode($orderItem['variation'], true) : [];



// Handle status update
if (isset($_POST['update_status'])) {
    $newStatus = sanitize($_POST['status']);
    
    // Handle commission crediting/deducting based on status changes
    if ($newStatus === 'delivered' && !$order['commission_credited']) {
        // Status changed to delivered and commission not yet credited - add commission
        $affiliatorId = $order['affiliator_id'];
        $commission = $order['commission_amount'];
        
        // Update affiliator balance
        $pdo->prepare("UPDATE affiliators SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?")
            ->execute([$commission, $commission, $affiliatorId]);
        
        // Get new balance
        $newBalance = $pdo->query("SELECT balance FROM affiliators WHERE id = $affiliatorId")->fetchColumn();
        
        // Add balance history
        $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'credit', ?, ?, 'order', ?, ?)")
            ->execute([$affiliatorId, $commission, "অর্ডার #{$order['order_number']} থেকে কমিশন", $orderId, $newBalance]);
        
        // Mark as credited
        $pdo->prepare("UPDATE affiliate_orders SET status = ?, commission_credited = 1, delivered_at = NOW() WHERE id = ?")
            ->execute([$newStatus, $orderId]);
            
        // Update link stats
        $pdo->prepare("UPDATE affiliate_links SET total_earned = total_earned + ? WHERE id = ?")
            ->execute([$commission, $order['affiliate_link_id']]);
            
    } elseif ($newStatus !== 'delivered' && $order['commission_credited']) {
        // Status changed from delivered to non-delivered and commission was previously credited - deduct commission
        $affiliatorId = $order['affiliator_id'];
        $commission = $order['commission_amount'];
        
        // Update affiliator balance (deduct)
        $pdo->prepare("UPDATE affiliators SET balance = balance - ?, total_earned = total_earned - ? WHERE id = ?")
            ->execute([$commission, $commission, $affiliatorId]);
        
        // Get new balance
        $newBalance = $pdo->query("SELECT balance FROM affiliators WHERE id = $affiliatorId")->fetchColumn();
        
        // Add balance history (debit)
        $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'debit', ?, ?, 'order', ?, ?)")
            ->execute([$affiliatorId, $commission, "অর্ডার #{$order['order_number']} স্ট্যাটাস পরিবর্তন - কমিশন ফেরত", $orderId, $newBalance]);
        
        // Mark as not credited
        $pdo->prepare("UPDATE affiliate_orders SET status = ?, commission_credited = 0, delivered_at = NULL WHERE id = ?")
            ->execute([$newStatus, $orderId]);
            
        // Update link stats (deduct)
        $pdo->prepare("UPDATE affiliate_links SET total_earned = total_earned - ? WHERE id = ?")
            ->execute([$commission, $order['affiliate_link_id']]);
    } else {
        // Just update status
        $pdo->prepare("UPDATE affiliate_orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
    }
    
    setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
    redirect(adminUrl('affiliate-order-view/' . $orderId));
}

// Handle customer details update
if (isset($_POST['update_customer'])) {
    $custName = sanitize($_POST['customer_name']);
    $custPhone = sanitize($_POST['customer_phone']);
    $custAddress = sanitize($_POST['shipping_address']);
    $custArea = sanitize($_POST['shipping_area']);
    
    $pdo->prepare("UPDATE affiliate_orders SET customer_name = ?, customer_phone = ?, shipping_address = ?, shipping_area = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$custName, $custPhone, $custAddress, $custArea, $orderId]);
        
    setFlash('success', 'কাস্টমার তথ্য আপডেট হয়েছে');
    redirect(adminUrl('affiliate-order-view/' . $orderId));
}

$statusColors = [
    'pending' => 'warning',
    'processing' => 'info',
    'shipped' => 'primary',
    'delivered' => 'success',
    'return' => 'secondary',
    'cancelled' => 'danger'
];

$statusLabels = [
    'pending' => 'পেন্ডিং',
    'processing' => 'প্রসেসিং',
    'shipped' => 'শিপড',
    'delivered' => 'ডেলিভার্ড',
    'return' => 'রিটার্ন',
    'cancelled' => 'বাতিল'
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-receipt me-2"></i>অর্ডার #<?php echo $order['order_number']; ?></h4>
        <a href="<?php echo adminUrl('affiliate-orders.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ফিরে যান
        </a>
    </div>

    <?php showFlash(); ?>

    <div class="row">
        <!-- Order Info -->
        <div class="col-md-8">
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold">অর্ডার তথ্য</h5>
                    <span class="badge bg-<?php echo $statusColors[$order['status']] ?? 'secondary'; ?>">
                        <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">কাস্টমার তথ্য</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCustomerModal">
                                    <i class="bi bi-pencil"></i> এডিট
                                </button>
                            </div>
                            <p class="mb-1"><strong>নাম:</strong> <?php echo sanitize($order['customer_name']); ?></p>
                            <p class="mb-1"><strong>ফোন:</strong> <?php echo $order['customer_phone']; ?></p>
                            <p class="mb-1"><strong>ঠিকানা:</strong> <?php echo sanitize($order['shipping_address']); ?></p>
                            <p class="mb-1"><strong>এলাকা:</strong> <?php echo $order['shipping_area'] === 'inside_dhaka' ? 'ঢাকার ভিতরে' : 'ঢাকার বাইরে'; ?></p>
                        </div>
                        <div class="col-md-6 ps-md-4">
                            <h6 class="mb-3 fw-bold">অ্যাফিলিয়েটর তথ্য</h6>
                            <p class="mb-1"><strong>নাম:</strong> <?php echo sanitize($order['affiliator_name']); ?></p>
                            <p class="mb-1"><strong>ইমেইল:</strong> <?php echo $order['affiliator_email']; ?></p>
                            <p class="mb-1"><strong>ফোন:</strong> <?php echo $order['affiliator_phone']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">অর্ডার আইটেম</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">পণ্য</th>
                                    <th>মূল্য</th>
                                    <th>পরিমাণ</th>
                                    <th class="pe-3 text-end">মোট</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): 
                                    $itemVariation = !empty($item['variation']) ? json_decode($item['variation'], true) : [];
                                ?>
                                <tr>
                                    <td class="ps-3 py-3">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo imageUrl($item['product_image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;" class="me-3">
                                            <div>
                                                <strong class="d-block"><?php echo sanitize($item['product_name']); ?></strong>
                                                <?php if (!empty($itemVariation)): ?>
                                                    <div class="mt-1">
                                                        <?php foreach ($itemVariation as $key => $value): ?>
                                                            <small class="text-muted d-block"><?php echo sanitize($key); ?>: <?php echo sanitize($value); ?></small>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <small class="text-muted">অ্যাফিলিয়েট পণ্য</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrice($item['unit_price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td class="pe-3 text-end fw-bold"><?php echo formatPrice($item['total_price']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($orderItems)): ?>
                                <tr>
                                    <td class="ps-3 py-3">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo imageUrl($order['product_image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;" class="me-3">
                                            <div>
                                                <strong class="d-block"><?php echo sanitize($order['product_name']); ?></strong>
                                                <?php if (!empty($variation)): ?>
                                                    <div class="mt-1">
                                                        <?php foreach ($variation as $key => $value): ?>
                                                            <small class="text-muted d-block"><?php echo sanitize($key); ?>: <?php echo sanitize($value); ?></small>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <small class="text-muted">অ্যাফিলিয়েট পণ্য</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrice($order['product_price']); ?></td>
                                    <td><?php 
                                        // Fallback to 1 if no items, but try to calculate from total if possible
                                        // For very old orders where items didn't exist
                                        echo 1; 
                                    ?></td>
                                    <td class="pe-3 text-end fw-bold"><?php echo formatPrice($order['product_price']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (!empty($order['order_note'])): ?>
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-danger">অর্ডার নোট</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(sanitize($order['order_note'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Order Summary -->
        <div class="col-md-4">
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">মূল্য সারাংশ</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>পণ্য মূল্য:</span>
                        <strong><?php echo formatPrice($order['product_price']); ?></strong>
                    </div>
                        <span>ডেলিভারি চার্জ:</span>
                        <strong><?php echo formatPrice($order['delivery_charge']); ?></strong>
                    </div>

                    <?php if (!empty($order['payment_method'])): ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>পেমেন্ট মেথড:</span>
                        <strong class="text-uppercase"><?php echo $order['payment_method'] == 'cod' ? 'ক্যাশ অন ডেলিভারি' : 'অনলাইন পেমেন্ট'; ?></strong>
                    </div>
                    <?php if ($order['payment_method'] == 'online'): ?>
                        <?php if (!empty($order['payment_number'])): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>হিসাব নম্বর:</span>
                            <strong><?php echo $order['payment_number']; ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['transaction_id'])): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>TXN ID:</span>
                            <strong class="text-break"><?php echo $order['transaction_id']; ?></strong>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold">মোট বিক্রয় মূল্য:</span>
                        <strong class="text-primary h5 mb-0"><?php echo formatPrice($order['total_amount']); ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">অ্যাফিলিয়েট কমিশন:</span>
                        <div class="text-end">
                            <strong class="text-success h5 mb-0 d-block"><?php echo formatPrice($order['commission_amount']); ?></strong>
                            <?php if ($order['commission_credited']): ?>
                                <small class="text-success"><i class="bi bi-check-circle-fill"></i> জমা হয়েছে</small>
                            <?php else: ?>
                                <small class="text-muted"><i class="bi bi-clock"></i> পেন্ডিং</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Update -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">স্ট্যাটাস আপডেট</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <select name="status" class="form-select">
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>শিপড</option>
                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>ডেলিভার্ড</option>
                                <option value="return" <?php echo $order['status'] === 'return' ? 'selected' : ''; ?>>রিটার্ন</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-check-lg me-1"></i> আপডেট করুন
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">টাইমলাইন</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1">অর্ডার তারিখ</small>
                        <p class="mb-0"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <?php if ($order['delivered_at']): ?>
                    <div>
                        <small class="text-muted text-uppercase fw-bold d-block mb-1">ডেলিভারি তারিখ</small>
                        <p class="mb-0"><?php echo date('d M Y, h:i A', strtotime($order['delivered_at'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">কাস্টমার তথ্য এডিট করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">নাম</label>
                        <input type="text" name="customer_name" class="form-control" value="<?php echo sanitize($order['customer_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ফোন</label>
                        <input type="text" name="customer_phone" class="form-control" value="<?php echo sanitize($order['customer_phone']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ঠিকানা</label>
                        <textarea name="shipping_address" class="form-control" rows="3" required><?php echo sanitize($order['shipping_address']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ডেলিভারি এলাকা</label>
                        <select name="shipping_area" class="form-select" required>
                            <option value="inside_dhaka" <?php echo $order['shipping_area'] === 'inside_dhaka' ? 'selected' : ''; ?>>ঢাকার ভিতরে</option>
                            <option value="outside_dhaka" <?php echo $order['shipping_area'] === 'outside_dhaka' ? 'selected' : ''; ?>>ঢাকার বাইরে</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">বন্ধ করুন</button>
                    <button type="submit" name="update_customer" class="btn btn-primary px-4">সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
