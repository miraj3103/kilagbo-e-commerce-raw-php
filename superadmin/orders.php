<?php
/**
 * Kilagbo - Admin Orders Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'অর্ডার';
$currentPage = 'orders';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();
require_once __DIR__ . '/../includes/steadfast.php';

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

    if ($action === 'bulk_send_steadfast') {
        $orderIds = array_map('intval', $_POST['order_ids'] ?? []);
        $result = steadfastSendOrders($pdo, steadfastRefsFromIds('retail', $orderIds));
        steadfastFlashSendResult($result);
        redirect(adminUrl('orders'));
    }

    if ($action === 'bulk_sync_steadfast') {
        $orderIds = array_map('intval', $_POST['order_ids'] ?? []);
        $result = steadfastSyncOrders($pdo, steadfastRefsFromIds('retail', $orderIds), true);
        steadfastFlashSyncResult($result);
        redirect(adminUrl('orders'));
    }

    if ($action === 'send_to_steadfast') {
        $orderId = intval($_POST['order_id']);
        $result = steadfastSendOrders($pdo, [['type' => 'retail', 'id' => $orderId]], true);
        steadfastFlashSendResult($result);
        redirect(adminUrl('orders'));
    }
}

// Refresh recent courier statuses on page load (2-minute per-order throttle).
steadfastAutoSyncRecent($pdo, 'retail', 20);

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

    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <h5 class="card-title mb-0">অর্ডার তালিকা (<?php echo $totalOrders; ?>)</h5>
        <div id="bulkActionButtons" class="d-flex gap-2 flex-wrap" style="display: none !important;">
            <?php if ($steadfastActive): ?>
            <button type="button" class="btn btn-success btn-sm" onclick="submitBulkCourier('bulk_send_steadfast')">
                <i class="bi bi-send me-1"></i>কুরিয়ারে পাঠান (<span class="selectedCountText">0</span>)
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="submitBulkCourier('bulk_sync_steadfast')">
                <i class="bi bi-arrow-repeat me-1"></i>স্ট্যাটাস Sync
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" onclick="bulkDelete()">
                <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন (<span id="selectedCount">0</span>)
            </button>
        </div>
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
                        <?php if (!empty($order['courier_status'])): ?>
                        <br><span class="badge bg-light text-dark border mt-1"><i class="bi bi-truck me-1"></i><?php echo steadfastCourierStatusLabel($order['courier_status']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($order['steadfast_tracking_code'])): ?>
                        <br><small class="text-muted">ট্র্যাকিং: <?php echo sanitize($order['steadfast_tracking_code']); ?></small>
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
<form id="bulkCourierForm" method="POST" style="display:none;">
    <input type="hidden" name="action" id="bulkCourierAction" value="">
    <div id="bulkCourierIds"></div>
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
    document.querySelectorAll('.selectedCountText').forEach(el => el.textContent = count);
    const actions = document.getElementById('bulkActionButtons');
    if (actions) actions.style.setProperty('display', count > 0 ? 'flex' : 'none', 'important');
}

function submitBulkCourier(action) {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length === 0) return;
    const verb = action === 'bulk_send_steadfast' ? 'Steadfast এ পাঠাতে' : 'courier status sync করতে';
    if (!confirm(checked.length + ' টি অর্ডার ' + verb + ' চান?')) return;
    const container = document.getElementById('bulkCourierIds');
    container.innerHTML = '';
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'order_ids[]'; input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('bulkCourierAction').value = action;
    document.getElementById('bulkCourierForm').submit();
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
