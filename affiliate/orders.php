<?php
/**
 * Kilagbo Affiliate Portal - Orders Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

// Handle send to steadfast action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_to_steadfast') {
    $orderId = (int)$_POST['order_id'];
    $affiliatorId = getAffiliatorId();
    
    // Get order details - verify it belongs to this affiliator
    $orderStmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE id = ? AND affiliator_id = ?");
    $orderStmt->execute([$orderId, $affiliatorId]);
    $order = $orderStmt->fetch();
    
    if ($order) {
        $apiKey = getSettings('steadfast_api_key');
        $secretKey = getSettings('steadfast_secret_key');
        $isActive = getSettings('steadfast_active');
        
        if ($isActive && $apiKey && $secretKey) {
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
    } else {
        setFlash('error', 'অর্ডার পাওয়া যায়নি');
    }
    redirect(baseUrl('affiliate/orders.php'));
}

// Handle single delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_order') {
    $orderId = (int)$_POST['order_id'];
    $affiliatorId = getAffiliatorId();
    
    // Verify order belongs to this affiliator
    $stmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE id = ? AND affiliator_id = ?");
    $stmt->execute([$orderId, $affiliatorId]);
    $order = $stmt->fetch();
    
    if ($order) {
        // If commission was already credited, we should probably handle it or only allow deleting if not credited.
        // For consistency with admin, let's reverse commission if it was credited.
        if ($order['commission_credited']) {
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
    } else {
        setFlash('error', 'অর্ডার পাওয়া যায়নি বা আপনার ডিলিট করার অনুমতি নেই');
    }
    redirect(baseUrl('affiliate/orders.php'));
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $orderIds = $_POST['order_ids'] ?? [];
    $affiliatorId = getAffiliatorId();
    
    if (!empty($orderIds)) {
        $deletedCount = 0;
        foreach ($orderIds as $orderId) {
            $orderId = (int)$orderId;
            
            // Verify order belongs to this affiliator
            $stmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE id = ? AND affiliator_id = ?");
            $stmt->execute([$orderId, $affiliatorId]);
            $order = $stmt->fetch();
            
            if ($order) {
                if ($order['commission_credited']) {
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
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            setFlash('success', $deletedCount . 'টি অর্ডার সফলভাবে ডিলিট করা হয়েছে');
        }
    }
    redirect(baseUrl('affiliate/orders.php'));
}

$pageTitle = 'অর্ডার সমূহ';
$currentPage = 'orders';
$affiliatorId = getAffiliatorId();

// Get filter params
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Build query
$where = "WHERE ao.affiliator_id = ?";
$params = [$affiliatorId];

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

// Get orders
$stmt = $pdo->prepare("
    SELECT ao.*, ap.name as product_name, ap.image as product_image
    FROM affiliate_orders ao
    JOIN affiliate_products ap ON ao.product_id = ap.id
    $where
    ORDER BY ao.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get stats
$stats = [
    'total' => 0,
    'pending' => 0,
    'delivered' => 0,
    'total_commission' => 0
];

$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN commission_credited = 1 THEN commission_amount ELSE 0 END) as total_commission
    FROM affiliate_orders WHERE affiliator_id = ?
");
$statsStmt->execute([$affiliatorId]);
$stats = $statsStmt->fetch();

include __DIR__ . '/includes/header.php';
?>

<style>
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    
    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    .stat-mini {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-mini h4 {
        font-weight: 700;
        margin-bottom: 5px;
        color: #10b981;
    }
    
    .stat-mini p {
        font-size: 0.85rem;
        color: #666;
        margin: 0;
    }
    
    .filter-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .orders-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .order-item {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .order-item:last-child {
        border-bottom: none;
    }
    
    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .order-number {
        font-weight: 600;
        color: #10b981;
    }
    
    .order-date {
        font-size: 0.85rem;
        color: #666;
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-shipped { background: #d4edda; color: #155724; }
    .status-delivered { background: #d1e7dd; color: #0f5132; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .status-returned { background: #e2e3e5; color: #383d41; }
    
    .order-body {
        display: flex;
        gap: 15px;
    }
    
    .order-product-img {
        width: 70px;
        height: 70px;
        border-radius: 8px;
        object-fit: cover;
        background: #f8f9fa;
    }
    
    .order-details {
        flex-grow: 1;
    }
    
    .order-details h6 {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .order-details p {
        font-size: 0.9rem;
        color: #666;
        margin: 3px 0;
    }
    
    .order-commission {
        text-align: right;
    }
    
    .order-commission .amount {
        font-size: 1.1rem;
        font-weight: 700;
        color: #10b981;
    }
    
    .order-commission .label {
        font-size: 0.8rem;
        color: #666;
    }
    
    .commission-credited {
        font-size: 0.75rem;
        color: #10b981;
    }
    
    .commission-pending {
        font-size: 0.75rem;
        color: #f59e0b;
    }
    
    .commission-deducted {
        font-size: 0.75rem;
        color: #dc2626;
    }
    
    .commission-deducted-amount {
        text-decoration: line-through;
        color: #dc2626 !important;
        opacity: 0.7;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #ddd;
    }

    /* Bulk Actions Style */
    #bulkActions {
        background: #fff;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #fee2e2;
    }

    .order-checkbox-container {
        display: flex;
        align-items: center;
        padding-right: 15px;
    }

    .order-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .select-all-container {
        background: #fff;
        padding: 10px 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
    }
</style>

<?php showFlash(); ?>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-mini">
        <h4><?php echo $stats['total'] ?: 0; ?></h4>
        <p>মোট অর্ডার</p>
    </div>
    <div class="stat-mini">
        <h4><?php echo $stats['pending'] ?: 0; ?></h4>
        <p>পেন্ডিং</p>
    </div>
    <div class="stat-mini">
        <h4><?php echo $stats['delivered'] ?: 0; ?></h4>
        <p>ডেলিভার্ড</p>
    </div>
    <div class="stat-mini">
        <h4><?php echo formatPrice($stats['total_commission'] ?: 0); ?></h4>
        <p>মোট কমিশন</p>
    </div>
</div>

<!-- Filter -->
<div class="filter-card">
    <form method="GET" class="row g-3">
        <div class="col-md-5">
            <input type="text" class="form-control" name="search" placeholder="অর্ডার নম্বর বা গ্রাহকের নাম/ফোন..." value="<?php echo $search; ?>">
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
            <button type="submit" class="btn btn-success w-100"><i class="bi bi-search me-1"></i>খুঁজুন</button>
        </div>
    </form>
</div>

<?php if (!empty($orders)): ?>
<!-- Bulk Actions -->
<div id="bulkActions" style="display: none;">
    <div class="d-flex align-items-center">
        <span class="text-muted me-3"><span id="selectedCount">0</span>টি অর্ডার নির্বাচিত</span>
        <form method="POST" id="bulkDeleteForm" onsubmit="return confirm('আপনি কি নিশ্চিত যে আপনি নির্বাচিত অর্ডারগুলো ডিলিট করতে চান?')">
            <input type="hidden" name="action" value="bulk_delete">
            <div id="bulkIdsContainer"></div>
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-trash me-1"></i> নির্বাচিতগুলো ডিলিট করুন
            </button>
        </form>
    </div>
</div>

<!-- Select All -->
<div class="select-all-container">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="selectAll">
        <label class="form-check-label ms-2" for="selectAll">
            সবগুলো সিলেক্ট করুন
        </label>
    </div>
</div>
<?php endif; ?>

<!-- Orders List -->
<div class="orders-card">
    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <i class="bi bi-receipt"></i>
        <h5 class="mt-3">কোনো অর্ডার পাওয়া যায়নি</h5>
        <p class="text-muted">আপনার অ্যাফিলিয়েট লিংক থেকে অর্ডার হলে এখানে দেখা যাবে</p>
        <a href="<?php echo baseUrl('affiliate/products.php'); ?>" class="btn btn-success mt-2">পণ্য দেখুন</a>
    </div>
    <?php else: ?>
    <?php foreach ($orders as $order): ?>
    <div class="order-item">
        <div class="d-flex">
            <div class="order-checkbox-container">
                <input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>">
            </div>
            <div class="flex-grow-1">
                <div class="order-header">
                    <div>
                        <span class="order-number">#<?php echo $order['order_number']; ?></span>
                        <span class="order-date ms-2"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
                    </div>
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
                </div>
                
                <div class="order-body">
                    <img src="<?php echo imageUrl($order['product_image']); ?>" alt="" class="order-product-img">
                    
                    <div class="order-details">
                        <h6><?php echo sanitize($order['product_name']); ?></h6>
                        <p><i class="bi bi-person me-1"></i><?php echo sanitize($order['customer_name']); ?> | <?php echo $order['customer_phone']; ?></p>
                        <p><i class="bi bi-geo-alt me-1"></i><?php echo sanitize($order['shipping_address']); ?></p>
                        <p><i class="bi bi-cash me-1"></i>মোট: <?php echo formatPrice($order['total_amount']); ?></p>
                    </div>
                    
                    <div class="order-commission">
                        <?php 
                        // Check if commission was deducted (there's a debit entry for this order)
                        $deductedStmt = $pdo->prepare("SELECT id FROM affiliator_balance_history WHERE affiliator_id = ? AND reference_type = 'order' AND reference_id = ? AND type = 'debit' LIMIT 1");
                        $deductedStmt->execute([$affiliatorId, $order['id']]);
                        $wasDeducted = $deductedStmt->fetch();
                        ?>
                        <div class="amount <?php echo $wasDeducted ? 'commission-deducted-amount' : ''; ?>"><?php echo formatPrice($order['commission_amount']); ?></div>
                        <div class="label">কমিশন</div>
                        <?php if ($wasDeducted): ?>
                        <div class="commission-deducted"><i class="bi bi-x-circle"></i> কর্তন হয়েছে</div>
                        <?php elseif ($order['commission_credited']): ?>
                        <div class="commission-credited"><i class="bi bi-check-circle"></i> জমা হয়েছে</div>
                        <?php else: ?>
                        <div class="commission-pending"><i class="bi bi-clock"></i> পেন্ডিং</div>
                        <?php endif; ?>
                        
                        <div class="mt-2 d-flex gap-1 justify-content-end">
                            <a href="<?php echo baseUrl('affiliate/order-details.php?id=' . $order['id']); ?>" class="btn btn-sm btn-outline-success" title="বিস্তারিত দেখুন">
                                <i class="bi bi-eye"></i>
                            </a>
                            
                            <form method="POST" class="d-inline" onsubmit="return confirm('আপনি কি নিশ্চিত যে আপনি এই অর্ডারটি ডিলিট করতে চান?')">
                                <input type="hidden" name="action" value="delete_order">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="ডিলিট করুন">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                        
                        <?php if (!empty($order['tracking_link'])): ?>
                        <a href="<?php echo $order['tracking_link']; ?>" target="_blank" class="btn btn-sm btn-success mt-1 w-100" title="ট্র্যাকিং লিঙ্ক">
                            <i class="bi bi-info-circle"></i> ট্র্যাকিং
                        </a>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-secondary mt-1 w-100" disabled title="ট্র্যাকিং লিঙ্ক নেই">
                            <i class="bi bi-info-circle"></i> ট্র্যাকিং
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const bulkIdsContainer = document.getElementById('bulkIdsContainer');

    function updateBulkActions() {
        const checkedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
        const count = checkedCheckboxes.length;
        
        if (count > 0) {
            bulkActions.style.display = 'flex';
            selectedCount.textContent = count;
            
            // Update hidden inputs for bulk delete
            bulkIdsContainer.innerHTML = '';
            checkedCheckboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order_ids[]';
                input.value = cb.value;
                bulkIdsContainer.appendChild(input);
            });
        } else {
            bulkActions.style.display = 'none';
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            orderCheckboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            updateBulkActions();
        });
    }

    orderCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
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
