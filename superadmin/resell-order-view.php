<?php
/**
 * Kilagbo Admin - Resell Order View
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-orders';
require_once 'includes/header.php';

$pageTitle = 'রিসেল অর্ডার বিবরণ';

$orderId = intval($_GET['id'] ?? 0);
if (!$orderId) {
    setFlash('error', 'অর্ডার আইডি পাওয়া যায়নি');
    redirect(adminUrl('resell-orders.php'));
}

// Get order data
$stmt = $pdo->prepare("
    SELECT ro.*, r.name as reseller_name, r.email as reseller_email, r.phone as reseller_phone
    FROM resell_orders ro
    LEFT JOIN resellers r ON ro.reseller_id = r.id
    WHERE ro.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'অর্ডার পাওয়া যায়নি');
    redirect(adminUrl('resell-orders.php'));
}

// Get order items
$stmt = $pdo->prepare("
    SELECT roi.*, rp.name as product_name, rp.image as product_image
    FROM resell_order_items roi
    LEFT JOIN resell_products rp ON roi.resell_product_id = rp.id
    WHERE roi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

// Handle status update
if (isset($_POST['update_status'])) {
    $newStatus = sanitize($_POST['status']);
    $oldStatus = $order['status'];

    // Update the status
    $pdo->prepare("UPDATE resell_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $orderId]);

        // Handle profit crediting/deducting based on status changes
        if ($newStatus === 'delivered' && !$order['profit_credited']) {
            // Status changed to delivered and profit not yet credited - add profit
            addResellerBalance($order['reseller_id'], $order['profit'], "অর্ডার #" . $order['order_number'] . " থেকে প্রফিট", 'order', $orderId);
            $pdo->prepare("UPDATE resell_orders SET profit_credited = 1, delivered_at = NOW() WHERE id = ?")->execute([$orderId]);
        } elseif ($newStatus !== 'delivered' && $order['profit_credited']) {
            // Status changed to non-delivered and profit was previously credited - deduct profit
            deductResellerBalance($order['reseller_id'], $order['profit'], "অর্ডার #" . $order['order_number'] . " স্ট্যাটাস পরিবর্তন - প্রফিট ফেরত", 'order', $orderId);
            $pdo->prepare("UPDATE resell_orders SET profit_credited = 0, delivered_at = NULL WHERE id = ?")->execute([$orderId]);
        }

        // --- Handle Delivery Charge Deduction for Return ---
        // We use a flag 'is_return_deducted' to prevent double deduction if status toggles
        try {
            $pdo->query("SELECT is_return_deducted FROM resell_orders LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE resell_orders ADD COLUMN is_return_deducted TINYINT(1) DEFAULT 0");
        }

        if ($newStatus === 'return' && !$order['is_return_deducted']) {
            // Deduct delivery charge from reseller
            deductResellerBalance($order['reseller_id'], $order['delivery_charge'], "অর্ডার #" . $order['order_number'] . " রিটার্ন - ডেলিভারি চার্জ কর্তন", 'order', $orderId);
            $pdo->prepare("UPDATE resell_orders SET is_return_deducted = 1 WHERE id = ?")->execute([$orderId]);
        } elseif ($newStatus !== 'return' && $order['is_return_deducted']) {
            // If it was returned but now changed back to something else, we should ideally refund the charge
            addResellerBalance($order['reseller_id'], $order['delivery_charge'], "অর্ডার #" . $order['order_number'] . " স্ট্যাটাস পরিবর্তন - ডেলিভারি চার্জ রিফান্ড", 'order', $orderId);
            $pdo->prepare("UPDATE resell_orders SET is_return_deducted = 0 WHERE id = ?")->execute([$orderId]);
        }

    setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
    redirect(adminUrl('resell-order-view/' . $orderId));
}

// Handle customer details update
if (isset($_POST['update_customer'])) {
    $custName = sanitize($_POST['customer_name']);
    $custPhone = sanitize($_POST['customer_phone']);
    $custAddress = sanitize($_POST['shipping_address']);
    $custArea = sanitize($_POST['shipping_area']);
    
    $pdo->prepare("UPDATE resell_orders SET customer_name = ?, customer_phone = ?, shipping_address = ?, shipping_area = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$custName, $custPhone, $custAddress, $custArea, $orderId]);
        
    setFlash('success', 'কাস্টমার তথ্য আপডেট হয়েছে');
    redirect(adminUrl('resell-order-view/' . $orderId));
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
        <h4><i class="bi bi-bag-check me-2"></i>অর্ডার #<?php echo $order['order_number']; ?></h4>
        <a href="<?php echo adminUrl('resell-orders.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ফিরে যান
        </a>
    </div>

    <?php showFlash(); ?>

    <div class="row">
        <!-- Order Info -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">অর্ডার তথ্য</h5>
                    <span class="badge bg-<?php echo $statusColors[$order['status']] ?? 'secondary'; ?>">
                        <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">কাস্টমার তথ্য</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCustomerModal">
                                    <i class="bi bi-pencil"></i> এডিট
                                </button>
                            </div>
                            <p class="mb-1"><strong>নাম:</strong> <?php echo sanitize($order['customer_name']); ?></p>
                            <p class="mb-1"><strong>ফোন:</strong> <?php echo $order['customer_phone']; ?></p>
                            <p class="mb-1"><strong>ঠিকানা:</strong> <?php echo sanitize($order['shipping_address']); ?></p>
                            <p class="mb-1"><strong>এলাকা:</strong> <?php echo $order['shipping_area'] === 'inside_dhaka' ? 'ঢাকার ভিতরে' : 'ঢাকার বাইরে'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>রিসেলার তথ্য</h6>
                            <p><strong>নাম:</strong> <?php echo sanitize($order['reseller_name']); ?></p>
                            <p><strong>ইমেইল:</strong> <?php echo $order['reseller_email']; ?></p>
                            <p><strong>ফোন:</strong> <?php echo $order['reseller_phone']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">অর্ডার আইটেম</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>পণ্য</th>
                                    <th>মূল্য</th>
                                    <th>পরিমাণ</th>
                                    <th>মোট</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo imageUrl($item['product_image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;" class="me-2">
                                            <div>
                                                <strong><?php echo sanitize($item['product_name']); ?></strong>
                                                <?php 
                                                if (!empty($item['variation'])) {
                                                    // Try to decode as array/object
                                                    $variation = json_decode($item['variation'], true);
                                                    
                                                    if (json_last_error() === JSON_ERROR_NONE) {
                                                        if (is_array($variation)) {
                                                            echo '<br><small class="text-muted">';
                                                            foreach ($variation as $key => $value) {
                                                                if ($key === 'section') continue;
                                                                echo sanitize($key) . ': ' . sanitize($value) . ' ';
                                                            }
                                                            echo '</small>';
                                                        } else {
                                                            // If it decoded to a string (which means it was a JSON string)
                                                            echo '<br><small class="text-muted">' . sanitize($variation) . '</small>';
                                                        }
                                                    } else {
                                                        // It wasn't valid JSON, just echo it (though likely it IS json string if it has \u...)
                                                        // Try one more time treating it as a string if it starts with "
                                                        if (substr($item['variation'], 0, 1) === '"') {
                                                             $decoded = json_decode($item['variation']);
                                                             if ($decoded) {
                                                                 echo '<br><small class="text-muted">' . sanitize($decoded) . '</small>';
                                                             } else {
                                                                 echo '<br><small class="text-muted">' . sanitize($item['variation']) . '</small>';
                                                             }
                                                        } else {
                                                            echo '<br><small class="text-muted">' . sanitize($item['variation']) . '</small>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrice($item['reseller_price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo formatPrice($item['reseller_price'] * $item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-md-4">
            <!-- Payment Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">পেমেন্ট তথ্য</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($order['payment_method']) && $order['payment_method'] === 'online'): ?>
                        <p class="mb-1"><strong class="text-success">অনলাইন পেমেন্ট</strong></p>
                        <?php if (!empty($order['payment_type'])): ?>
                            <p class="mb-1"><strong>মাধ্যম:</strong> <?php echo ucfirst($order['payment_type']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['payment_number'])): ?>
                            <p class="mb-1"><strong>নম্বর:</strong> <?php echo $order['payment_number']; ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['transaction_id'])): ?>
                            <p class="mb-1"><strong>TrxID:</strong> <?php echo $order['transaction_id']; ?></p>
                        <?php endif; ?>
                        
                         <div class="mt-2">
                            <strong>স্ট্যাটাস:</strong> 
                            <span class="badge bg-<?php echo ($order['payment_status'] ?? '') === 'paid' ? 'success' : (($order['payment_status'] ?? '') === 'pending_verification' ? 'warning' : 'secondary'); ?>">
                                <?php 
                                $pStatus = $order['payment_status'] ?? 'pending';
                                if ($pStatus === 'paid') echo 'পেইড';
                                elseif ($pStatus === 'pending_verification') echo 'ভেরিফিকেশন পেন্ডিং';
                                else echo 'পেন্ডিং';
                                ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <p class="mb-0"><strong class="text-secondary">ক্যাশ অন ডেলিভারি</strong></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">মূল্য সারাংশ</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>মূল মূল্য:</span>
                        <strong><?php echo formatPrice($order['original_price']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>ডেলিভারি চার্জ:</span>
                        <strong><?php echo formatPrice($order['delivery_charge']); ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>মোট খরচ:</span>
                        <strong><?php echo formatPrice($order['original_price'] + $order['delivery_charge']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>বিক্রয় মূল্য:</span>
                        <strong class="text-primary"><?php echo formatPrice($order['reseller_price']); ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>রিসেলার প্রফিট:</span>
                        <?php 
                        $isCancelled = in_array($order['status'], ['cancelled', 'returned', 'rejected', 'failed']);
                        $isDelivered = $order['status'] === 'delivered';
                        ?>
                        <strong class="<?php echo $isDelivered ? 'text-success' : ($isCancelled ? 'text-muted' : 'text-dark'); ?>" style="<?php echo $isCancelled ? 'text-decoration: line-through;' : ''; ?>">
                            <?php echo formatPrice($order['profit']); ?>
                            <?php if ($isDelivered): ?>
                            <i class="bi bi-check-circle-fill"></i>
                            <?php elseif ($isCancelled): ?>
                            <i class="bi bi-x-circle-fill text-muted"></i>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php if ($isDelivered): ?>
                    <small class="text-success">প্রফিট ক্রেডিট হয়েছে</small>
                    <?php elseif ($isCancelled): ?>
                    <small class="text-muted">প্রফিট বাতিল</small>
                    <?php else: ?>
                    <small class="text-muted">পেন্ডিং প্রফিট</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Update -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">স্ট্যাটাস আপডেট</h5>
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
                        <button type="submit" name="update_status" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-1"></i> আপডেট করুন
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">টাইমলাইন</h5>
                </div>
                <div class="card-body">
                    <p><strong>অর্ডার তারিখ:</strong><br><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></p>
                    <?php if ($order['delivered_at']): ?>
                    <p><strong>ডেলিভারি তারিখ:</strong><br><?php echo date('d M Y, H:i', strtotime($order['delivered_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">কাস্টমার তথ্য এডিট করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">নাম</label>
                        <input type="text" name="customer_name" class="form-control" value="<?php echo sanitize($order['customer_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ফোন</label>
                        <input type="text" name="customer_phone" class="form-control" value="<?php echo sanitize($order['customer_phone']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ঠিকানা</label>
                        <textarea name="shipping_address" class="form-control" rows="3" required><?php echo sanitize($order['shipping_address']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ডেলিভারি এলাকা</label>
                        <select name="shipping_area" class="form-select" required>
                            <option value="inside_dhaka" <?php echo $order['shipping_area'] === 'inside_dhaka' ? 'selected' : ''; ?>>ঢাকার ভিতরে</option>
                            <option value="outside_dhaka" <?php echo $order['shipping_area'] === 'outside_dhaka' ? 'selected' : ''; ?>>ঢাকার বাইরে</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
                    <button type="submit" name="update_customer" class="btn btn-primary">সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
