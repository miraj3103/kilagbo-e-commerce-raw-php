<?php
/**
 * Kilagbo Admin - Resell Orders Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-orders';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/steadfast.php';
require_once __DIR__ . '/../includes/resell-shortfall.php';

$pageTitle = 'রিসেল অর্ডার';

// Handle status update
if (isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = sanitize($_POST['status']);

    if ($newStatus === 'partial') {
        setFlash('info', 'Partial করতে অর্ডার বিবরণ পেজে গ্রাহকের পরিশোধিত Amount লিখুন।');
        redirect(adminUrl('resell-order-view/' . $orderId));
    }

    try {
        resellApplyOrderStatusFinancials($pdo, $orderId, $newStatus);
        setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(adminUrl('resell-orders.php'));
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_orders'])) {
    $selectedIds = array_map('intval', $_POST['selected_orders']);
    if (!empty($selectedIds)) {
        $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
        // Delete order items first
        $pdo->prepare("DELETE FROM resell_order_items WHERE order_id IN ($placeholders)")->execute($selectedIds);
        // Delete orders
        $pdo->prepare("DELETE FROM resell_orders WHERE id IN ($placeholders)")->execute($selectedIds);
        setFlash('success', count($selectedIds) . ' টি অর্ডার মুছে ফেলা হয়েছে');
        redirect(adminUrl('resell-orders.php'));
    }
}

// Handle bulk courier actions
if (isset($_POST['bulk_send_steadfast'])) {
    $selectedIds = array_map('intval', $_POST['selected_orders'] ?? []);
    $result = steadfastSendOrders($pdo, steadfastRefsFromIds('resell', $selectedIds));
    steadfastFlashSendResult($result);
    redirect(adminUrl('resell-orders.php'));
}

if (isset($_POST['bulk_sync_steadfast'])) {
    $selectedIds = array_map('intval', $_POST['selected_orders'] ?? []);
    $result = steadfastSyncOrders($pdo, steadfastRefsFromIds('resell', $selectedIds), true);
    steadfastFlashSyncResult($result);
    redirect(adminUrl('resell-orders.php'));
}

// Handle send to steadfast action
if (isset($_POST['send_to_steadfast'])) {
    $orderId = intval($_POST['order_id']);
    $result = steadfastSendOrders($pdo, [['type' => 'resell', 'id' => $orderId]], true);
    steadfastFlashSendResult($result);
    redirect(adminUrl('resell-orders.php'));
}

// Handle tracking link submission
if (isset($_POST['send_tracking_link'])) {
    $orderId = intval($_POST['order_id']);
    $trackingLink = sanitize($_POST['tracking_link'] ?? '');

    if ($orderId && $trackingLink) {
        // Check if tracking_link column exists, if not add it
        try {
            $pdo->query("SELECT tracking_link FROM resell_orders LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE resell_orders ADD COLUMN tracking_link VARCHAR(500) DEFAULT NULL");
        }

        // Update the tracking link
        $pdo->prepare("UPDATE resell_orders SET tracking_link = ?, updated_at = NOW() WHERE id = ?")->execute([$trackingLink, $orderId]);

        // Get order details for notification
        $orderStmt = $pdo->prepare("SELECT order_number, reseller_id FROM resell_orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $orderData = $orderStmt->fetch();

        if ($orderData) {
            // Create notification for reseller
            try {
                $pdo->prepare("INSERT INTO reseller_notifications (reseller_id, type, message, link, created_at) VALUES (?, 'tracking', ?, ?, NOW())")
                    ->execute([$orderData['reseller_id'], 'অর্ডার #' . $orderData['order_number'] . ' এর ট্র্যাকিং লিংক পাওয়া গেছে', 'orders.php']);
            } catch (PDOException $e) {
                // Notification table might not exist or have different structure, ignore
            }
        }

        setFlash('success', 'ট্র্যাকিং লিংক সফলভাবে পাঠানো হয়েছে');
    } else {
        setFlash('error', 'ট্র্যাকিং লিংক প্রদান করুন');
    }
    redirect(adminUrl('resell-orders.php'));
}

// Refresh recent courier statuses on page load (2-minute per-order throttle).
steadfastAutoSyncRecent($pdo, 'resell', 20);

// Get filters
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

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

$stmt = $pdo->prepare("
    SELECT ro.*, r.name as reseller_name
    FROM resell_orders ro
    LEFT JOIN resellers r ON ro.reseller_id = r.id
    $where
    ORDER BY ro.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$steadfastActive = getSettings('steadfast_active') == 1 && getSettings('steadfast_api_key');

// Stats - only count delivered orders
$stats = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'delivered' THEN reseller_price ELSE 0 END) as total_sales,
    SUM(CASE WHEN status = 'delivered' THEN profit ELSE 0 END) as total_profit
    FROM resell_orders")->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-bag-check me-2"></i>রিসেল অর্ডার</h4>
    </div>

    <?php showFlash(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p class="mb-0">মোট অর্ডার</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p class="mb-0">পেন্ডিং</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo formatPrice($stats['total_sales']); ?></h3>
                    <p class="mb-0">কনফার্মড সেলস</p>
                    <small>ডেলিভার্ড অর্ডার থেকে</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3><?php echo formatPrice($stats['total_profit']); ?></h3>
                    <p class="mb-0">ক্রেডিটেড প্রফিট</p>
                    <small>রিসেলারদের কাছে যোগ হয়েছে</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="অর্ডার নম্বর, নাম, ফোন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">সব স্ট্যাটাস</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>শিপড</option>
                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>ডেলিভার্ড</option>
                        <option value="partial" <?php echo $status === 'partial' ? 'selected' : ''; ?>>পার্শিয়াল</option>
                        <option value="returned" <?php echo $status === 'returned' ? 'selected' : ''; ?>>রিটার্ন</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">খুঁজুন</button>
                </div>
            </form>
        </div>
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>অর্ডার তালিকা</span>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($steadfastActive): ?>
                <button type="button" class="btn btn-success btn-sm courierBulkBtn" style="display:none;" onclick="submitSelectedCourier('bulk_send_steadfast')">
                    <i class="bi bi-send me-1"></i>কুরিয়ারে পাঠান (<span class="selectedCourierCount">0</span>)
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm courierBulkBtn" style="display:none;" onclick="submitSelectedCourier('bulk_sync_steadfast')">
                    <i class="bi bi-arrow-repeat me-1"></i>স্ট্যাটাস Sync
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                    <i class="bi bi-trash"></i> বাল্ক ডিলিট (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bag-x" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো অর্ডার নেই</p>
            </div>
            <?php else: ?>
            <form method="POST" id="bulkForm">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>অর্ডার</th>
                                <th>রিসেলার</th>
                                <th>কাস্টমার</th>
                                <th>মূল মূল্য</th>
                                <th>কাস্টমারের মোট বিল</th>
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
                                    <input type="checkbox" class="form-check-input order-checkbox" name="selected_orders[]" value="<?php echo $order['id']; ?>">
                                </td>
                                <td>
                                    <strong class="text-primary">#<?php echo $order['order_number']; ?></strong>
                                </td>
                                <td><?php echo sanitize($order['reseller_name']); ?></td>
                                <td>
                                    <strong><?php echo sanitize($order['customer_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo $order['customer_phone']; ?></small>
                                </td>
                                <td><?php echo formatPrice($order['original_price'] + $order['delivery_charge']); ?></td>
                                <td><?php echo formatPrice($order['reseller_price'] + $order['delivery_charge']); ?></td>
                                <td>
                                    <?php
                                    $isCancelled = in_array($order['status'], ['cancelled', 'returned', 'rejected', 'failed']);
                                    $isDelivered = $order['status'] === 'delivered';
                                    $profitValue = (float)$order['profit'];
                                    $profitClass = $isCancelled ? 'text-muted' : ($profitValue < 0 ? 'text-danger' : (($isDelivered && $profitValue > 0) ? 'text-success' : 'text-dark'));
                                    ?>
                                    <span class="<?php echo $profitClass; ?>" style="<?php echo $isCancelled ? 'text-decoration: line-through;' : ''; ?>">
                                        <?php echo formatPrice($order['profit']); ?>
                                        <?php if ($isDelivered): ?>
                                        <i class="bi bi-check-circle-fill"></i>
                                        <?php elseif ($isCancelled): ?>
                                        <i class="bi bi-x-circle-fill text-muted"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: auto;"
                                            data-detail-url="<?php echo htmlspecialchars(adminUrl('resell-order-view/' . $order['id']), ENT_QUOTES, 'UTF-8'); ?>"
                                            onchange="if (this.value === 'partial') { window.location.href = this.dataset.detailUrl; } else { this.form.submit(); }">
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>শিপড</option>
                                            <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>ডেলিভার্ড</option>
                                            <option value="partial" <?php echo $order['status'] === 'partial' ? 'selected' : ''; ?>>পার্শিয়াল</option>
                                            <option value="returned" <?php echo $order['status'] === 'returned' ? 'selected' : ''; ?>>রিটার্ন</option>
                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                    <?php if ($order['status'] === 'partial' && $order['partial_delivery_paid'] !== null): ?>
                                    <small class="d-block text-danger mt-1">ডেলিভারি চার্জ আদায়: <?php echo formatPrice($order['partial_delivery_paid']); ?>; কর্তন: <?php echo formatPrice($order['partial_delivery_deducted']); ?>; Due: <?php echo formatPrice($order['partial_delivery_due'] ?? 0); ?></small>
                                    <?php endif; ?>
                                    <?php if (($order['courier_status'] ?? '') === 'partial_delivered' && $order['status'] !== 'partial'): ?>
                                    <small class="d-block text-warning mt-1">আংশিক ডেলিভারি; আদায়কৃত চার্জ যাচাই হয়নি</small>
                                    <?php endif; ?>
                                    <?php if (!empty($order['courier_status'])): ?>
                                    <span class="badge bg-light text-dark border mt-1"><i class="bi bi-truck me-1"></i><?php echo steadfastCourierStatusLabel($order['courier_status']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($order['steadfast_tracking_code'])): ?>
                                    <small class="d-block text-muted mt-1"><?php echo sanitize($order['steadfast_tracking_code']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo adminUrl('resell-order-view/' . $order['id']); ?>" class="btn btn-sm btn-outline-primary" title="বিস্তারিত দেখুন">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php
                                        $hasTrackingLink = !empty($order['tracking_link'] ?? '');
                                        ?>
                                        <button type="button" class="btn btn-sm <?php echo $hasTrackingLink ? 'btn-success' : 'btn-outline-secondary'; ?>"
                                                onclick="openTrackingModal(<?php echo $order['id']; ?>, '<?php echo addslashes($order['tracking_link'] ?? ''); ?>')"
                                                title="<?php echo $hasTrackingLink ? 'ট্র্যাকিং লিংক পাঠানো হয়েছে' : 'ট্র্যাকিং লিংক পাঠান'; ?>">
                                            <i class="bi bi-link-45deg"></i>
                                        </button>

                                        <?php if ($steadfastActive && $order['status'] != 'cancelled'): ?>
                                        <form method="POST" class="d-inline ms-1">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <?php if ($order['steadfast_consignment_id']): ?>
                                            <button type="submit" name="send_to_steadfast" class="btn btn-sm btn-danger" title="Steadfast এ আবার পাঠান" onclick="return confirm('ইতিমধ্যে পাঠানো হয়েছে। আবার পাঠাতে চান?')">
                                                <i class="bi bi-send-check"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="submit" name="send_to_steadfast" class="btn btn-sm btn-success" title="Steadfast এ পাঠান" onclick="return confirm('Steadfast এ পাঠাতে চান?')">
                                                <i class="bi bi-send"></i>
                                            </button>
                                            <?php endif; ?>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="bulkDeleteForm">
                    <input type="hidden" name="bulk_delete" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title">বাল্ক ডিলিট নিশ্চিত করুন</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>আপনি কি নিশ্চিত যে সিলেক্ট করা অর্ডারগুলো মুছে ফেলতে চান?</p>
                        <div class="alert alert-danger">
                            <strong>সতর্কতা:</strong> এই অ্যাকশন পূর্বাবস্থায় ফেরানো যাবে না।
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                        <button type="submit" class="btn btn-danger">ডিলিট করুন</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tracking Link Modal -->
    <div class="modal fade" id="trackingLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="send_tracking_link" value="1">
                    <input type="hidden" name="order_id" id="trackingOrderId">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>ট্র্যাকিং লিংক পাঠান</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Steadfast ট্র্যাকিং লিংক *</label>
                            <input type="url" class="form-control" name="tracking_link" id="trackingLinkInput"
                                   placeholder="https://steadfast.com.bd/tracking/..." required>
                            <small class="text-muted">এখানে Steadfast এর ট্র্যাকিং URL দিন</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>পাঠান</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    function updateCount() {
        const count = document.querySelectorAll('.order-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = count;
        document.querySelectorAll('.selectedCourierCount').forEach(el => el.textContent = count);
        bulkDeleteBtn.style.display = count > 0 ? 'inline-block' : 'none';
        document.querySelectorAll('.courierBulkBtn').forEach(btn => btn.style.display = count > 0 ? 'inline-block' : 'none');
    }

    selectAllCheckbox?.addEventListener('change', function() {
        orderCheckboxes.forEach(cb => cb.checked = this.checked);
        updateCount();
    });

    orderCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateCount();
            const checkedCount = document.querySelectorAll('.order-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === orderCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < orderCheckboxes.length;
        });
    });

    bulkDeleteBtn?.addEventListener('click', function() {
        const form = document.getElementById('bulkDeleteForm');
        document.querySelectorAll('.order-checkbox:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_orders[]';
            input.value = cb.value;
            form.appendChild(input);
        });
    });
});

function submitSelectedCourier(action) {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length === 0) return;
    const verb = action === 'bulk_send_steadfast' ? 'Steadfast এ পাঠাতে' : 'courier status sync করতে';
    if (!confirm(checked.length + ' টি অর্ডার ' + verb + ' চান?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden'; actionInput.name = action; actionInput.value = '1';
    form.appendChild(actionInput);
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'selected_orders[]'; input.value = cb.value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

// Open tracking link modal
function openTrackingModal(orderId, existingLink) {
    document.getElementById('trackingOrderId').value = orderId;
    document.getElementById('trackingLinkInput').value = existingLink || '';
    const modal = new bootstrap.Modal(document.getElementById('trackingLinkModal'));
    modal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
