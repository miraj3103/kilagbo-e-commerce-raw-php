<?php
/**
 * Kilagbo - Admin Orders Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'অর্ডার';
$currentPage = 'orders';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $orderId = intval($_POST['order_id']);
        $status = sanitize($_POST['status']);
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
        setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
        redirect(adminUrl('orders'));
    }
    
    if ($action === 'bulk_delete') {
        $orderIds = $_POST['order_ids'] ?? [];
        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            // Delete order items first
            $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)")->execute($orderIds);
            // Delete orders
            $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)")->execute($orderIds);
            setFlash('success', count($orderIds) . ' টি অর্ডার মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('orders'));
    }
    
    if ($action === 'send_to_steadfast') {
        $orderId = intval($_POST['order_id']);
        
        // Get order details
        $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
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
                    'recipient_address' => $order['customer_address'],
                    'cod_amount' => $order['total'],
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
                    
                    $pdo->prepare("UPDATE orders SET steadfast_consignment_id = ?, steadfast_tracking_code = ?, status = 'processing' WHERE id = ?")
                        ->execute([$consignmentId, $trackingCode, $orderId]);
                    
                    setFlash('success', 'অর্ডার সফলভাবে Steadfast এ পাঠানো হয়েছে');
                } else {
                    setFlash('error', 'Steadfast এ পাঠাতে সমস্যা হয়েছে: ' . ($result['message'] ?? 'Unknown error'));
                }
            } else {
                setFlash('error', 'Steadfast কুরিয়ার সেটআপ করা হয়নি বা সক্রিয় নেই');
            }
        }
        redirect(adminUrl('orders'));
    }
}

// Pagination and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$statusFilter = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$whereClause = "1=1";
$params = [];

if ($statusFilter) {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $whereClause .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $whereClause");
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Fetch orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Check if Steadfast is active
$steadfastActive = getSettings('steadfast_active') == 1 && getSettings('steadfast_api_key');

$statusLabels = [
    'pending' => ['label' => 'পেন্ডিং', 'class' => 'bg-warning'],
    'processing' => ['label' => 'প্রসেসিং', 'class' => 'bg-info'],
    'shipped' => ['label' => 'শিপড', 'class' => 'bg-primary'],
    'delivered' => ['label' => 'ডেলিভার্ড', 'class' => 'bg-success'],
    'cancelled' => ['label' => 'বাতিল', 'class' => 'bg-danger']
];

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="admin-card">
    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="অর্ডার নং, নাম বা ফোন দিয়ে খুঁজুন" value="<?php echo $search; ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">সকল স্ট্যাটাস</option>
                <?php foreach ($statusLabels as $key => $val): ?>
                <option value="<?php echo $key; ?>" <?php echo $statusFilter == $key ? 'selected' : ''; ?>>
                    <?php echo $val['label']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-admin-outline w-100">
                <i class="bi bi-search me-1"></i>খুঁজুন
            </button>
        </div>
        <?php if ($search || $statusFilter): ?>
        <div class="col-md-2">
            <a href="<?php echo adminUrl('orders'); ?>" class="btn btn-secondary w-100">রিসেট</a>
        </div>
        <?php endif; ?>
    </form>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">অর্ডার তালিকা (<?php echo $totalOrders; ?>)</h5>
        <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" style="display: none;" onclick="bulkDelete()">
            <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন (<span id="selectedCount">0</span>)
        </button>
    </div>
    
    <?php if (empty($orders)): ?>
    <p class="text-muted">কোন অর্ডার পাওয়া যায়নি</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>অর্ডার নং</th>
                        <th>কাস্টমার</th>
                        <th>ফোন</th>
                        <th>মোট</th>
                        <th>স্ট্যাটাস</th>
                        <th>তারিখ</th>
                        <th width="120">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" class="order-checkbox" onchange="updateSelectedCount()"></td>
                        <td>
                            <a href="<?php echo adminUrl('orders/view/' . $order['id']); ?>" class="fw-bold">
                                <?php echo $order['order_number']; ?>
                            </a>
                        </td>
                    <td><?php echo sanitize($order['customer_name']); ?></td>
                    <td><?php echo sanitize($order['customer_phone']); ?></td>
                    <td><strong><?php echo formatPrice($order['total']); ?></strong></td>
                    <td>
                        <span class="badge <?php echo $statusLabels[$order['status']]['class']; ?>">
                            <?php echo $statusLabels[$order['status']]['label']; ?>
                        </span>
                        <?php if ($order['steadfast_tracking_code']): ?>
                        <br><small class="text-muted">ট্র্যাকিং: <?php echo $order['steadfast_tracking_code']; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                    <td>
                        <a href="<?php echo adminUrl('orders/view/' . $order['id']); ?>" class="btn btn-sm btn-outline-dark" title="বিস্তারিত দেখুন">
                            <i class="bi bi-eye"></i>
                        </a>
                        
                        <?php if ($steadfastActive && $order['status'] != 'cancelled'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="send_to_steadfast">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <?php if ($order['steadfast_consignment_id']): ?>
                            <button type="submit" class="btn btn-sm btn-danger" title="Steadfast এ আবার পাঠান (ইতিমধ্যে পাঠানো হয়েছে)" onclick="return confirm('এই অর্ডার ইতিমধ্যে Steadfast এ পাঠানো হয়েছে। আবার পাঠাতে চান?')">
                                <i class="bi bi-send-check"></i>
                            </button>
                            <?php else: ?>
                            <button type="submit" class="btn btn-sm btn-success" title="Steadfast এ পাঠান" onclick="return confirm('Steadfast এ পাঠাতে চান?')">
                                <i class="bi bi-send"></i>
                            </button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center mb-0">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Bulk delete form -->
<form id="bulkDeleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="bulk_delete">
    <div id="bulkDeleteIds"></div>
</form>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('bulkDeleteBtn').style.display = count > 0 ? 'inline-block' : 'none';
}

function bulkDelete() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    const count = checked.length;
    if (count === 0) {
        return;
    }
    if (confirm(count + ' টি অর্ডার মুছে ফেলতে চান?')) {
        const idsContainer = document.getElementById('bulkDeleteIds');
        idsContainer.innerHTML = '';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'order_ids[]';
            input.value = cb.value;
            idsContainer.appendChild(input);
        });
        document.getElementById('bulkDeleteForm').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
