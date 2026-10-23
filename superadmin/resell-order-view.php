<?php
/**
 * Kilagbo Admin - Resell Order View
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-orders';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/resell-shortfall.php';

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

// Delivery options used by the existing reseller delivery-charge system.
$deliveryOptions = getResellDeliveryCharges();

function resellOrderShippingAreaLabel($area) {
    if ($area === 'inside_dhaka') return 'ঢাকার ভিতরে';
    if ($area === 'outside_dhaka') return 'ঢাকার বাইরে';
    if ($area === 'custom') return 'কাস্টম ডেলিভারি চার্জ';
    return $area ?: 'নির্ধারিত নয়';
}

// Stage 2: manual Partial collection uses a separate amount, not a courier COD guess.
if (empty($_SESSION['note15_partial_csrf'])) {
    $_SESSION['note15_partial_csrf'] = bin2hex(random_bytes(32));
}

// Handle status update
if (isset($_POST['update_status'])) {
    $newStatus = sanitize($_POST['status']);

    try {
        if ($newStatus === 'partial') {
            if (!isset($_POST['note15_partial_csrf']) ||
                !hash_equals($_SESSION['note15_partial_csrf'], (string)$_POST['note15_partial_csrf'])) {
                throw new RuntimeException('নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
            }
            $rawPaid = trim((string)($_POST['partial_delivery_paid'] ?? ''));
            if (!preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/D', $rawPaid)) {
                throw new InvalidArgumentException('গ্রাহকের পরিশোধিত টাকা সঠিকভাবে লিখুন (সর্বোচ্চ ২ দশমিক)।');
            }
            resellApplyManualPartialDelivery($pdo, $orderId, (float)$rawPaid);
            setFlash('success', 'Partial Amount সেভ হয়েছে; ব্যালেন্স থেকে কর্তন ও বকেয়া Due সমন্বয় হয়েছে।');
        } else {
            resellApplyOrderStatusFinancials($pdo, $orderId, $newStatus);
            setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
        }
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(adminUrl('resell-order-view/' . $orderId));
}

// Handle customer + shipping charge update
if (isset($_POST['update_customer'])) {
    $custName = sanitize($_POST['customer_name'] ?? '');
    $custPhone = sanitize($_POST['customer_phone'] ?? '');
    $custAddress = sanitize($_POST['shipping_address'] ?? '');
    $deliveryOptionId = intval($_POST['delivery_charge_id'] ?? 0);
    $manualDeliveryCharge = floatval($_POST['delivery_charge'] ?? 0);

    if ($custName === '' || $custPhone === '' || $custAddress === '') {
        setFlash('error', 'কাস্টমারের নাম, ফোন এবং ঠিকানা পূরণ করুন');
        redirect(adminUrl('resell-order-view/' . $orderId));
    }

    if ($manualDeliveryCharge < 0) {
        setFlash('error', 'ডেলিভারি চার্জ ঋণাত্মক হতে পারবে না');
        redirect(adminUrl('resell-order-view/' . $orderId));
    }

    try {
        $pdo->beginTransaction();

        // Lock the order so price/profit/balance calculations remain consistent.
        $lockStmt = $pdo->prepare("SELECT * FROM resell_orders WHERE id = ? FOR UPDATE");
        $lockStmt->execute([$orderId]);
        $currentOrder = $lockStmt->fetch();
        if (!$currentOrder) {
            throw new Exception('অর্ডার পাওয়া যায়নি');
        }

        $custArea = $currentOrder['shipping_area'];
        if ($deliveryOptionId > 0) {
            $dcStmt = $pdo->prepare("SELECT id, name, charge FROM resell_delivery_charges WHERE id = ? AND status = 1");
            $dcStmt->execute([$deliveryOptionId]);
            $deliveryOption = $dcStmt->fetch();
            if (!$deliveryOption) {
                throw new Exception('নির্বাচিত ডেলিভারি এলাকা পাওয়া যায়নি');
            }
            $custArea = $deliveryOption['name'];
        } elseif ($custArea === '' || $custArea === null) {
            $custArea = 'custom';
        }

        $oldDeliveryCharge = (float)$currentOrder['delivery_charge'];
        $newDeliveryCharge = $manualDeliveryCharge;
        // NOTE15: do not silently change a verified Partial debt's basis.
        if ($currentOrder['status'] === 'partial' && abs($newDeliveryCharge - $oldDeliveryCharge) > 0.001) {
            throw new Exception('পার্শিয়াল অর্ডারে ডেলিভারি চার্জ পরিবর্তনের আগে Courier হিসাব সমন্বয় করুন।');
        }
        if ((int)($currentOrder['pricing_version'] ?? 1) >= 2) {
            // NOTE16: delivery is charged to customer AND incurred as a cost once.
            // Persist the original online payment discount so Admin edits remain consistent.
            $newProfit = round(
                (float)$currentOrder['reseller_price'] - (float)$currentOrder['original_price']
                + (float)($currentOrder['checkout_discount'] ?? 0), 2
            );
            $newShortfall = round(max(0, -$newProfit), 2);
        } else {
            // Preserve the accounting basis of orders booked before NOTE16.
            $newProfit = round((float)$currentOrder['reseller_price'] - ((float)$currentOrder['original_price'] + $newDeliveryCharge), 2);
            $newShortfall = resellShortfallAmount(
                (float)$currentOrder['original_price'],
                $newDeliveryCharge,
                (float)$currentOrder['reseller_price']
            );
        }

        // NOTE 08: delivery changes may create/remove a below-cost shortfall.
        // Reconcile the reseller balance transactionally before saving the new totals.
        $financeState = resellReconcileOrderPricing($pdo, $currentOrder, $newProfit, $newShortfall);

        $pdo->prepare("UPDATE resell_orders
            SET customer_name = ?, customer_phone = ?, shipping_address = ?, shipping_area = ?,
                delivery_charge = ?, profit = ?, shortfall_amount = ?,
                profit_credited = ?, shortfall_deducted = ?, shortfall_refunded = ?, updated_at = NOW()
            WHERE id = ?")
            ->execute([
                $custName,
                $custPhone,
                $custAddress,
                $custArea,
                $newDeliveryCharge,
                $newProfit,
                $newShortfall,
                $financeState['profit_credited'],
                $financeState['shortfall_deducted'],
                $financeState['shortfall_refunded'],
                $orderId
            ]);

        // If a return-delivery charge was already deducted, keep that one-time
        // deduction aligned with the newly saved delivery charge.
        if (!empty($currentOrder['is_return_deducted'])) {
            $chargeDelta = $newDeliveryCharge - $oldDeliveryCharge;
            if ($chargeDelta > 0) {
                deductResellerBalance($currentOrder['reseller_id'], $chargeDelta, "অর্ডার #" . $currentOrder['order_number'] . " রিটার্ন ডেলিভারি চার্জ সমন্বয়", 'adjustment', $orderId);
            } elseif ($chargeDelta < 0) {
                addResellerBalance($currentOrder['reseller_id'], abs($chargeDelta), "অর্ডার #" . $currentOrder['order_number'] . " রিটার্ন ডেলিভারি চার্জ সমন্বয়", 'adjustment', $orderId);
            }
        }

        $pdo->commit();
        setFlash('success', 'কাস্টমার তথ্য ও ডেলিভারি চার্জ আপডেট হয়েছে');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage());
    }

    redirect(adminUrl('resell-order-view/' . $orderId));
}

$statusColors = [
    'pending' => 'warning',
    'processing' => 'info',
    'shipped' => 'primary',
    'delivered' => 'success',
    'returned' => 'secondary',
    'partial' => 'warning',
    'cancelled' => 'danger'
];

$statusLabels = [
    'pending' => 'পেন্ডিং',
    'processing' => 'প্রসেসিং',
    'shipped' => 'শিপড',
    'delivered' => 'ডেলিভার্ড',
    'returned' => 'রিটার্ন',
    'partial' => 'পার্শিয়াল',
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
                            <p class="mb-1"><strong>এলাকা:</strong> <?php echo sanitize(resellOrderShippingAreaLabel($order['shipping_area'])); ?></p>
                            <p class="mb-1"><strong>ডেলিভারি চার্জ:</strong> <?php echo formatPrice($order['delivery_charge']); ?></p>
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
                                    <td><?php
                                        // NOTE17: the parent order keeps the exact total in single-product v2 orders.
                                        $lineTotal = ((int)($order['pricing_version'] ?? 1) >= 2 && count($orderItems) === 1)
                                            ? (float)$order['reseller_price']
                                            : (float)$item['reseller_price'] * (int)$item['quantity'];
                                        echo formatPrice($lineTotal);
                                    ?></td>
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
                        <span>কাস্টমারের মোট বিক্রয় মূল্য:</span>
                        <strong class="text-primary"><?php echo formatPrice($order['reseller_price'] + $order['delivery_charge']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>ব্যালেন্স থেকে ঘাটতি:</span>
                        <strong class="<?php echo ((float)($order['shortfall_amount'] ?? 0) > 0) ? 'text-danger' : 'text-muted'; ?>">
                            <?php echo formatPrice((float)($order['shortfall_amount'] ?? 0)); ?>
                        </strong>
                    </div>
                    <?php if ((float)($order['shortfall_amount'] ?? 0) > 0): ?>
                    <small class="<?php echo !empty($order['shortfall_refunded']) ? 'text-success' : 'text-danger'; ?> d-block mb-2">
                        <?php echo !empty($order['shortfall_refunded']) ? 'ঘাটতি ব্যালেন্সে ফেরত হয়েছে' : 'ঘাটতি ব্যালেন্স থেকে কর্তন করা আছে'; ?>
                    </small>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>রিসেলার প্রফিট:</span>
                        <?php
                        $isCancelled = in_array($order['status'], ['cancelled', 'returned', 'rejected', 'failed']);
                        $isDelivered = $order['status'] === 'delivered';
                        $profitValue = (float)$order['profit'];
                        $profitClass = $isCancelled ? 'text-muted' : ($profitValue < 0 ? 'text-danger' : (($isDelivered && $profitValue > 0) ? 'text-success' : 'text-dark'));
                        ?>
                        <strong class="<?php echo $profitClass; ?>" style="<?php echo $isCancelled ? 'text-decoration: line-through;' : ''; ?>">
                            <?php echo formatPrice($order['profit']); ?>
                            <?php if ($isDelivered): ?>
                            <i class="bi bi-check-circle-fill"></i>
                            <?php elseif ($isCancelled): ?>
                            <i class="bi bi-x-circle-fill text-muted"></i>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php if ($isDelivered && $profitValue > 0 && !empty($order['profit_credited'])): ?>
                    <small class="text-success">প্রফিট ক্রেডিট হয়েছে</small>
                    <?php elseif ($isDelivered && $profitValue <= 0): ?>
                    <small class="text-muted">পজিটিভ প্রফিট নেই; ঘাটতি থাকলে অর্ডারের সময় ব্যালেন্স থেকে সমন্বয় হয়েছে</small>
                    <?php elseif ($isCancelled): ?>
                    <small class="text-muted">প্রফিট বাতিল</small>
                    <?php else: ?>
                    <small class="text-muted">পেন্ডিং প্রফিট</small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($order['status'] === 'partial' && $order['partial_delivery_paid'] !== null): ?>
            <div class="alert alert-warning">
                গ্রাহক পরিশোধ করেছেন: <strong><?php echo formatPrice($order['partial_delivery_paid']); ?></strong>;
                রিসেলার ব্যালেন্স থেকে কর্তন: <strong><?php echo formatPrice($order['partial_delivery_deducted']); ?></strong>;
                এখনও Due: <strong><?php echo formatPrice($order['partial_delivery_due'] ?? 0); ?></strong>।
                <small class="d-block mt-1">উৎস: <?php echo ($order['partial_delivery_source'] ?? '') === 'admin_manual' ? 'অ্যাডমিন (ম্যানুয়াল)' : 'কুরিয়ার API'; ?></small>
            </div>
            <?php elseif (($order['courier_status'] ?? '') === 'partial_delivered'): ?>
            <div class="alert alert-info">কুরিয়ারে আংশিক ডেলিভারি হয়েছে, কিন্তু গ্রাহকের পরিশোধিত ডেলিভারি চার্জ যাচাই হয়নি। ব্যালেন্স কর্তন স্থগিত।</div>
            <?php endif; ?>

            <!-- Status Update -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">স্ট্যাটাস আপডেট</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="note15_partial_csrf" value="<?php echo htmlspecialchars($_SESSION['note15_partial_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <select name="status" class="form-select" id="note15StatusSelect">
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>শিপড</option>
                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>ডেলিভার্ড</option>
                                <option value="partial" <?php echo $order['status'] === 'partial' ? 'selected' : ''; ?>>পার্শিয়াল</option>
                                <option value="returned" <?php echo $order['status'] === 'returned' ? 'selected' : ''; ?>>রিটার্ন</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
                            </select>
                        </div>
                        <div id="note15PartialPaymentBox" class="border rounded p-3 mb-3 bg-light" <?php echo $order['status'] === 'partial' ? '' : 'hidden'; ?>>
                            <label class="form-label fw-semibold" for="note15PartialPaid">গ্রাহক ডেলিভারি চার্জ বাবদ কত টাকা দিয়েছেন? (৳)</label>
                            <input type="number" class="form-control" name="partial_delivery_paid"
                                id="note15PartialPaid" min="0.01" max="<?php echo htmlspecialchars(number_format(max(0, (float)$order['delivery_charge'] - 0.01), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                step="0.01" inputmode="decimal" placeholder="যেমন 50.00"
                                value="<?php echo $order['partial_delivery_paid'] === null ? '' : htmlspecialchars(number_format((float)$order['partial_delivery_paid'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="text-muted d-block mt-2">ডেলিভারি চার্জ: <strong><?php echo formatPrice($order['delivery_charge']); ?></strong>।
                                পুরো চার্জ পরিশোধ হলে Partial নয়; 'ডেলিভার্ড' ব্যবহার করুন।
                                নিচের Save বাটনে পুনরায় চাপলে Amount সংশোধন করা যাবে।</small>
                            <div class="small mt-2" id="note15PartialPreview" aria-live="polite"></div>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-1"></i> আপডেট করুন
                        </button>
                    </form>
                </div>
            </div>

            <script>
            (function () {
                var status = document.getElementById('note15StatusSelect');
                var box = document.getElementById('note15PartialPaymentBox');
                var paid = document.getElementById('note15PartialPaid');
                var preview = document.getElementById('note15PartialPreview');
                var charge = <?php echo json_encode((float)$order['delivery_charge']); ?>;
                if (!status || !box || !paid || !preview) return;
                function refresh() {
                    var isPartial = status.value === 'partial';
                    box.hidden = !isPartial;
                    paid.required = isPartial;
                    if (!isPartial) return;
                    var amount = Number(paid.value);
                    if (paid.value === '' || !(amount > 0 && amount < charge)) {
                        preview.textContent = '০ টাকার বেশি এবং পূর্ণ ডেলিভারি চার্জের কম টাকা লিখুন।';
                        return;
                    }
                    preview.textContent = 'ঘাটতি: ৳' + (charge - amount).toFixed(2) +
                        '। উপলব্ধ ব্যালেন্স অনুযায়ী কর্তন হবে; বাকিটা Due থাকবে।';
                }
                status.addEventListener('change', refresh);
                paid.addEventListener('input', refresh);
                refresh();
            })();
            </script>

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
                        <?php
                        $matchedDeliveryId = 0;
                        foreach ($deliveryOptions as $dc) {
                            if ((string)$dc['name'] === (string)$order['shipping_area']) {
                                $matchedDeliveryId = (int)$dc['id'];
                                break;
                            }
                        }
                        ?>
                        <select name="delivery_charge_id" id="editDeliveryArea" class="form-select">
                            <?php if (!$matchedDeliveryId): ?>
                            <option value="0" data-charge="<?php echo (float)$order['delivery_charge']; ?>" selected>
                                বর্তমান/কাস্টম: <?php echo sanitize(resellOrderShippingAreaLabel($order['shipping_area'])); ?>
                            </option>
                            <?php endif; ?>
                            <?php foreach ($deliveryOptions as $dc): ?>
                            <option value="<?php echo (int)$dc['id']; ?>" data-charge="<?php echo (float)$dc['charge']; ?>" <?php echo $matchedDeliveryId === (int)$dc['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($dc['name']); ?> — <?php echo formatPrice($dc['charge']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">এলাকা নির্বাচন করলে নিচের চার্জ স্বয়ংক্রিয়ভাবে বসবে। প্রয়োজনে অ্যাডমিন চার্জ পরিবর্তন করতে পারবেন।</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ডেলিভারি চার্জ</label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input type="number" name="delivery_charge" id="editDeliveryCharge" class="form-control" value="<?php echo number_format((float)$order['delivery_charge'], 2, '.', ''); ?>" min="0" step="0.01" required>
                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const areaSelect = document.getElementById('editDeliveryArea');
    const chargeInput = document.getElementById('editDeliveryCharge');
    if (!areaSelect || !chargeInput) return;

    areaSelect.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const charge = parseFloat(option.dataset.charge || '0');
        if (!Number.isNaN(charge)) {
            chargeInput.value = charge.toFixed(2);
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
