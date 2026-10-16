<?php
/**
 * Kilagbo Admin - Affiliate Orders Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-orders';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট অর্ডার';

// Handle send to steadfast action
if (isset($_POST['send_to_steadfast'])) {
    $orderId = intval($_POST['order_id']);
    
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if ($order) {
        $apiKey = getSettings('steadfast_api_key');
        $secretKey = getSettings('steadfast_secret_key');
        $steadfastActive = getSettings('steadfast_active') == 1 && $apiKey;
        
        if ($steadfastActive && $apiKey && $secretKey) {
            // Prepare data for Steadfast
            $data = [
                'invoice' => $order['order_number'],
                'recipient_name' => $order['customer_name'],
                'recipient_phone' => $order['customer_phone'],
                'recipient_address' => $order['shipping_address'] ?? '',
                'cod_amount' => $order['total_amount'],
                'note' => $order['notes'] ?? ''
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, STEADFAST_BASE_URL . '/create_order');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Api-Key: ' . $apiKey,
                'Secret-Key: ' . $secretKey
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode == 200 && isset($result['consignment']['consignment_id'])) {
                $consignmentId = $result['consignment']['consignment_id'];
                $trackingCode = $result['consignment']['tracking_code'] ?? '';
                
                $pdo->prepare("UPDATE affiliate_orders SET steadfast_consignment_id = ?, steadfast_tracking_code = ?, status = 'processing' WHERE id = ?")
                    ->execute([$consignmentId, $trackingCode, $orderId]);
                
                setFlash('success', 'অর্ডার সফলভাবে Steadfast এ পাঠানো হয়েছে');
            } else {
                setFlash('error', 'Steadfast এ পাঠাতে সমস্যা হয়েছে: ' . ($result['message'] ?? 'Unknown error'));
            }
        } else {
            setFlash('error', 'Steadfast কুরিয়ার সেটআপ করা হয়নি বা সক্রিয় নেই');
        }
    }
    redirect(adminUrl('affiliate-orders.php'));
}

// Handle tracking link update
if (isset($_POST['update_tracking'])) {
    $orderId = intval($_POST['order_id']);
    $trackingLink = sanitize($_POST['tracking_link']);
    
    $pdo->prepare("UPDATE affiliate_orders SET tracking_link = ? WHERE id = ?")->execute([$trackingLink, $orderId]);
    
    setFlash('success', 'ট্র্যাকিং লিঙ্ক আপডেট হয়েছে');
    redirect(adminUrl('affiliate-orders.php'));
}

// Handle status update
if (isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = sanitize($_POST['status']);
    
    // Get current order status before update
    $stmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $currentOrder = $stmt->fetch();
    
    if ($currentOrder) {
        $oldStatus = $currentOrder['status'];
        
        // Update the status
        $pdo->prepare("UPDATE affiliate_orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
        
        // Handle commission crediting/deducting based on status changes
        if ($newStatus === 'delivered' && !$currentOrder['commission_credited']) {
            // Status changed to delivered and commission not yet credited - add commission
            $affiliatorId = $currentOrder['affiliator_id'];
            $commission = $currentOrder['commission_amount'];
            
            // Update affiliator balance
            $pdo->prepare("UPDATE affiliators SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?")
                ->execute([$commission, $commission, $affiliatorId]);
            
            // Get new balance
            $newBalance = $pdo->query("SELECT balance FROM affiliators WHERE id = $affiliatorId")->fetchColumn();
            
            // Add balance history
            $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'credit', ?, ?, 'order', ?, ?)")
                ->execute([$affiliatorId, $commission, "অর্ডার #{$currentOrder['order_number']} থেকে কমিশন", $orderId, $newBalance]);
            
            // Mark as credited
            $pdo->prepare("UPDATE affiliate_orders SET commission_credited = 1, delivered_at = NOW() WHERE id = ?")->execute([$orderId]);
            
            // Update link stats
            $pdo->prepare("UPDATE affiliate_links SET total_earned = total_earned + ? WHERE id = ?")
                ->execute([$commission, $currentOrder['affiliate_link_id']]);
                
        } elseif ($newStatus !== 'delivered' && $currentOrder['commission_credited']) {
            // Status changed from delivered to non-delivered and commission was previously credited - deduct commission
            $affiliatorId = $currentOrder['affiliator_id'];
            $commission = $currentOrder['commission_amount'];
            
            // Update affiliator balance (deduct)
            $pdo->prepare("UPDATE affiliators SET balance = balance - ?, total_earned = total_earned - ? WHERE id = ?")
                ->execute([$commission, $commission, $affiliatorId]);
            
            // Get new balance
            $newBalance = $pdo->query("SELECT balance FROM affiliators WHERE id = $affiliatorId")->fetchColumn();
            
            // Add balance history (debit)
            $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'debit', ?, ?, 'order', ?, ?)")
                ->execute([$affiliatorId, $commission, "অর্ডার #{$currentOrder['order_number']} স্ট্যাটাস পরিবর্তন - কমিশন ফেরত", $orderId, $newBalance]);
            
            // Mark as not credited
            $pdo->prepare("UPDATE affiliate_orders SET commission_credited = 0, delivered_at = NULL WHERE id = ?")->execute([$orderId]);
            
            // Update link stats (deduct)
            $pdo->prepare("UPDATE affiliate_links SET total_earned = total_earned - ? WHERE id = ?")
                ->execute([$commission, $currentOrder['affiliate_link_id']]);
        }
    }
    
    setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
    redirect(adminUrl('affiliate-orders.php'));
}

// Handle single delete
if (isset($_POST['delete_order'])) {
    $orderId = intval($_POST['order_id']);
    
    // Get order details for commission reversal if needed
    $stmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if ($order) {
        if ($order['commission_credited']) {
            $affiliatorId = $order['affiliator_id'];
            $commission = $order['commission_amount'];
            
            // Update affiliator balance (deduct)
            $pdo->prepare("UPDATE affiliators SET balance = balance - ?, total_earned = total_earned - ? WHERE id = ?")
                ->execute([$commission, $commission, $affiliatorId]);
            
            // Get new balance
            $newBalance = $pdo->query("SELECT balance FROM affiliators WHERE id = $affiliatorId")->fetchColumn();
            
            // Add balance history (debit)
            $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'debit', ?, ?, 'order_delete', ?, ?)")
                ->execute([$affiliatorId, $commission, "অর্ডার #{$order['order_number']} ডিলিট করা হয়েছে - কমিশন ফেরত", $orderId, $newBalance]);
                
            // Update link stats (deduct)
            $pdo->prepare("UPDATE affiliate_links SET total_earned = total_earned - ? WHERE id = ?")
                ->execute([$commission, $order['affiliate_link_id']]);
        }
        
        $pdo->prepare("DELETE FROM affiliate_order_items WHERE order_id = ?")->execute([$orderId]);
        $pdo->prepare("DELETE FROM affiliate_orders WHERE id = ?")->execute([$orderId]);
        
        setFlash('success', 'অর্ডার সফলভাবে ডিলিট করা হয়েছে');
    }
    redirect(adminUrl('affiliate-orders.php'));
}

// Handle bulk delete
if (isset($_POST['bulk_delete'])) {
    $orderIds = $_POST['order_ids'] ?? [];
    if (!empty($orderIds)) {
        foreach ($orderIds as $orderId) {
            $orderId = intval($orderId);
            
            // Get order details for commission reversal
            $stmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if ($order) {
                if ($order['commission_credited']) {
                    $affiliatorId = $order['affiliator_id'];
                    $commission = $order['commission_amount'];
                    
                    // Update affiliator balance (deduct)
                    $pdo->prepare("UPDATE affiliators SET balance = balance - ?, total_earned = total_earned - ? WHERE id = ?")
                        ->execute([$commission, $commission, $affiliatorId]);
                    
                    // Get new balance
                    $newBalance = $pdo->query("SELECT balance FROM affiliators WHERE id = $affiliatorId")->fetchColumn();
                    
                    // Add balance history (debit)
                    $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'debit', ?, ?, 'order_delete', ?, ?)")
                        ->execute([$affiliatorId, $commission, "অর্ডার #{$order['order_number']} ডিলিট করা হয়েছে - কমিশন ফেরত", $orderId, $newBalance]);
                        
                    // Update link stats (deduct)
                    $pdo->prepare("UPDATE affiliate_links SET total_earned = total_earned - ? WHERE id = ?")
                        ->execute([$commission, $order['affiliate_link_id']]);
                }
                
                $pdo->prepare("DELETE FROM affiliate_order_items WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("DELETE FROM affiliate_orders WHERE id = ?")->execute([$orderId]);
            }
        }
        
        setFlash('success', count($orderIds) . 'টি অর্ডার সফলভাবে ডিলিট করা হয়েছে');
    }
    redirect(adminUrl('affiliate-orders.php'));
}

$steadfastActive = getSettings('steadfast_active') == 1 && getSettings('steadfast_api_key');

// Get filters
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($status) {
    $where .= " AND ao.status = ?";
    $params[] = $status;
}

if ($search) {
    $where .= " AND (ao.order_number LIKE ? OR ao.customer_name LIKE ? OR ao.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("
    SELECT ao.*, ap.name as product_name, ap.image as product_image, a.name as affiliator_name,
           (SELECT SUM(quantity) FROM affiliate_order_items WHERE order_id = ao.id) as total_qty
    FROM affiliate_orders ao
    JOIN affiliate_products ap ON ao.product_id = ap.id
    JOIN affiliators a ON ao.affiliator_id = a.id
    $where
    ORDER BY ao.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(total_amount) as total_sales,
    SUM(commission_amount) as total_commission
    FROM affiliate_orders")->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-receipt me-2"></i>অ্যাফিলিয়েট অর্ডার</h4>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total'] ?: 0; ?></h3>
                    <p class="mb-0">মোট অর্ডার</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h3><?php echo $stats['pending'] ?: 0; ?></h3>
                    <p class="mb-0">পেন্ডিং</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo formatPrice($stats['total_sales'] ?: 0); ?></h3>
                    <p class="mb-0">মোট বিক্রয়</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3><?php echo formatPrice($stats['total_commission'] ?: 0); ?></h3>
                    <p class="mb-0">মোট কমিশন</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <form method="GET" class="row g-2 flex-grow-1">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="অর্ডার নম্বর, গ্রাহকের নাম/ফোন..." value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="status">
                            <option value="">সব স্ট্যাটাস</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                            <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                            <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>শিপড</option>
                            <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>ডেলিভার্ড</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary w-100">খুঁজুন</button>
                    </div>
                </form>
                
                <div id="bulkActions" style="display: none;">
                    <form method="POST" id="bulkDeleteForm" onsubmit="return confirm('আপনি কি নিশ্চিত যে আপনি নির্বাচিত অর্ডারগুলো ডিলিট করতে চান?')">
                        <button type="submit" name="bulk_delete" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> নির্বাচিতগুলো ডিলিট করুন (<span id="selectedCount">0</span>)
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="bi bi-receipt" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো অর্ডার নেই</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="40">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                </div>
                            </th>
                            <th>অর্ডার</th>
                            <th>পণ্য</th>
                            <th>গ্রাহক</th>
                            <th>অ্যাফিলিয়েট</th>
                            <th>মোট</th>
                            <th>কমিশন</th>
                            <th width="140">স্ট্যাটাস</th>
                            <th>তারিখ</th>
                            <th class="text-end">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input order-checkbox" type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" form="bulkDeleteForm">
                                </div>
                            </td>
                            <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo imageUrl($order['product_image']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                    <div>
                                        <span class="d-block"><?php echo sanitize(mb_substr($order['product_name'], 0, 25)); ?>...</span>
                                        <small class="text-muted">পরিমাণ: <?php echo $order['total_qty'] ?? 1; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo sanitize($order['customer_name']); ?>
                                <small class="text-muted d-block"><?php echo $order['customer_phone']; ?></small>
                            </td>
                            <td><?php echo sanitize($order['affiliator_name']); ?></td>
                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                            <td>
                                <span class="text-success"><?php echo formatPrice($order['commission_amount']); ?></span>
                                <?php if ($order['commission_credited']): ?>
                                <i class="bi bi-check-circle-fill text-success" title="জমা হয়েছে"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 130px;">
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>শিপড</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>ডেলিভার্ড</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                            <td class="text-end">
                                <?php if ($steadfastActive && $order['status'] != 'cancelled'): ?>
                                <form method="POST" class="d-inline ms-1">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <?php if (!empty($order['steadfast_consignment_id'])): ?>
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

                                <?php $hasTrackingLink = !empty($order['tracking_link']); ?>
                                <button type="button" class="btn btn-sm <?php echo $hasTrackingLink ? 'btn-success' : 'btn-outline-secondary'; ?> me-1" 
                                        onclick="openTrackingModal(<?php echo $order['id']; ?>, '<?php echo addslashes($order['tracking_link'] ?? ''); ?>')" 
                                        title="<?php echo $hasTrackingLink ? 'ট্র্যাকিং লিঙ্ক পাঠানো হয়েছে' : 'ট্র্যাকিং লিঙ্ক পাঠান'; ?>">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                                <a href="<?php echo adminUrl('affiliate-order-view/' . $order['id']); ?>" class="btn btn-sm btn-outline-primary" title="বিস্তারিত দেখুন">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <form method="POST" class="d-inline ms-1" onsubmit="return confirm('আপনি কি নিশ্চিত যে আপনি এই অর্ডারটি ডিলিট করতে চান?')">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="delete_order" class="btn btn-sm btn-outline-danger" title="ডিলিট করুন">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tracking Link Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ট্র্যাকিং লিঙ্ক (Steadfast)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="tracking_order_id">
                    <div class="mb-3">
                        <label class="form-label">ট্র্যাকিং লিঙ্ক লিখুন</label>
                        <input type="url" name="tracking_link" id="tracking_link_input" class="form-control" placeholder="https://steadfast.com.bd/tracking/..." required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
                    <button type="submit" name="update_tracking" class="btn btn-primary">সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTrackingModal(orderId, trackingLink) {
    document.getElementById('tracking_order_id').value = orderId;
    document.getElementById('tracking_link_input').value = trackingLink;
    new bootstrap.Modal(document.getElementById('trackingModal')).show();
}

// Bulk selection logic
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');

    function updateBulkActions() {
        const checkedCount = document.querySelectorAll('.order-checkbox:checked').length;
        if (checkedCount > 0) {
            bulkActions.style.display = 'block';
            selectedCount.textContent = checkedCount;
        } else {
            bulkActions.style.display = 'none';
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            orderCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateBulkActions();
        });
    }

    orderCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                selectAll.checked = false;
            } else {
                const allChecked = Array.from(orderCheckboxes).every(c => c.checked);
                selectAll.checked = allChecked;
            }
            updateBulkActions();
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
