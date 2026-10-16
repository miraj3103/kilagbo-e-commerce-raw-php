<?php
/**
 * Kilagbo - Admin Wholesale Orders
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পাইকারি অর্ডার';
$currentPage = 'wholesale-orders';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $orderId = (int)$_POST['order_id'];
        $status = sanitize($_POST['status']);
        
        $pdo->prepare("UPDATE wholesale_orders SET order_status = ? WHERE id = ?")
            ->execute([$status, $orderId]);
        
        setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
        redirect(adminUrl('wholesale-orders'));
    }
    
    if ($action === 'send_to_steadfast') {
        $orderId = (int)$_POST['order_id'];
        
        // Get order details
        $orderStmt = $pdo->prepare("SELECT * FROM wholesale_orders WHERE id = ?");
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
                    'recipient_address' => $order['shipping_address'] ?? $order['customer_address'] ?? '',
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
                    
                    $pdo->prepare("UPDATE wholesale_orders SET steadfast_consignment_id = ?, steadfast_tracking_code = ?, order_status = 'processing' WHERE id = ?")
                        ->execute([$consignmentId, $trackingCode, $orderId]);
                    
                    setFlash('success', 'অর্ডার সফলভাবে Steadfast এ পাঠানো হয়েছে');
                } else {
                    setFlash('error', 'Steadfast এ পাঠাতে সমস্যা হয়েছে: ' . ($result['message'] ?? 'Unknown error'));
                }
            } else {
                setFlash('error', 'Steadfast কুরিয়ার সেটআপ করা হয়নি বা সক্রিয় নেই');
            }
        }
        redirect(adminUrl('wholesale-orders'));
    }
    
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Delete order items first
            $pdo->prepare("DELETE FROM wholesale_order_items WHERE order_id IN ($placeholders)")->execute($ids);
            
            // Delete orders
            $pdo->prepare("DELETE FROM wholesale_orders WHERE id IN ($placeholders)")->execute($ids);
            
            setFlash('success', count($ids) . ' টি অর্ডার মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('wholesale-orders'));
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($status) {
    $where .= " AND order_status = ?";
    $params[] = $status;
}

if ($search) {
    $where .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM wholesale_orders $where");
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Get orders
$stmt = $pdo->prepare("
    SELECT * FROM wholesale_orders 
    $where 
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status labels
$statusLabels = [
    'pending' => ['label' => 'পেন্ডিং', 'class' => 'warning'],
    'confirmed' => ['label' => 'কনফার্মড', 'class' => 'info'],
    'processing' => ['label' => 'প্রসেসিং', 'class' => 'primary'],
    'shipped' => ['label' => 'শিপড', 'class' => 'secondary'],
    'delivered' => ['label' => 'ডেলিভারড', 'class' => 'success'],
    'cancelled' => ['label' => 'বাতিল', 'class' => 'danger'],
    'returned' => ['label' => 'রিটার্নড', 'class' => 'dark']
];

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<!-- Filter -->
<div class="admin-card mb-4">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="অর্ডার নম্বর, নাম বা ফোন দিয়ে খুঁজুন..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">সব স্ট্যাটাস</option>
                <?php foreach ($statusLabels as $key => $val): ?>
                <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-admin w-100">
                <i class="bi bi-search me-2"></i>খুঁজুন
            </button>
        </div>
    </form>
</div>

<div id="bulkActions" class="admin-card mb-4" style="display: none;">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <span id="selectedCount">0</span> টি অর্ডার সিলেক্ট করা হয়েছে
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
            <i class="bi bi-trash me-2"></i>নির্বাচিত গুলো মুছুন
        </button>
    </div>
</div>

<!-- Orders Table -->
<div class="admin-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll()">
                        </th>
                        <th>অর্ডার নম্বর</th>
                        <th>কাস্টমার</th>
                        <th>ফোন</th>
                        <th>মোট</th>
                        <th>স্ট্যাটাস</th>
                        <th>তারিখ</th>
                        <th width="150">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">কোনো অর্ডার পাওয়া যায়নি</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?php echo $order['id']; ?>" class="form-check-input item-checkbox" onchange="updateBulkBtn()">
                        </td>
                        <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                    <td><?php echo $order['customer_name']; ?></td>
                    <td><?php echo $order['customer_phone']; ?></td>
                    <td><strong><?php echo formatPrice($order['total']); ?></strong></td>
                    <td>
                        <span class="badge bg-<?php echo $statusLabels[$order['order_status']]['class']; ?>">
                            <?php echo $statusLabels[$order['order_status']]['label']; ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                    <td>
                        <a href="<?php echo adminUrl('wholesale-order-view/' . $order['id']); ?>" class="btn btn-sm btn-outline-primary" title="বিস্তারিত দেখুন">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php 
                        $steadfastActive = getSettings('steadfast_active') == 1 && getSettings('steadfast_api_key');
                        if ($steadfastActive && $order['order_status'] != 'cancelled'): 
                        ?>
                        <form method="POST" class="d-inline" action="<?php echo adminUrl('wholesale-orders'); ?>">
                            <input type="hidden" name="action" value="send_to_steadfast">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <?php if (!empty($order['steadfast_consignment_id'])): ?>
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
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<script>
function toggleSelectAll() {
    const isChecked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkBtn();
}

function updateBulkBtn() {
    const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (checkedCount > 0) {
        bulkActions.style.display = 'block';
        selectedCount.textContent = checkedCount;
    } else {
        bulkActions.style.display = 'none';
        document.getElementById('selectAll').checked = false;
    }
}

function bulkDelete() {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    const count = checked.length;
    if (count === 0) return;
    
    if (confirm('আপনি কি নিশ্চিত যে আপনি নির্বাচিত ' + count + ' টি অর্ডার মুছে ফেলতে চান? এটি অর্ডার আইটেমগুলিকেও মুছে ফেলবে।')) {
        const idsContainer = document.getElementById('bulkDeleteIds');
        idsContainer.innerHTML = '';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            idsContainer.appendChild(input);
        });
        document.getElementById('bulkDeleteForm').submit();
    }
}
</script>

<!-- Bulk delete form -->
<form id="bulkDeleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="bulk_delete">
    <div id="bulkDeleteIds"></div>
</form>

<?php require_once 'includes/footer.php'; ?>
