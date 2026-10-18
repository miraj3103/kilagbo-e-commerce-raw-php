<?php
/**
 * Kilagbo - Admin Digital Product Orders Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'ডিজিটাল অর্ডার';
$currentPage = 'digital-orders';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = sanitize($_POST['status']);
        $paymentStatus = sanitize($_POST['payment_status']);
        $adminNotes = sanitize($_POST['admin_notes'] ?? '');
        
        // If payment verified, set status to paid and generate download link
        $downloadLink = null;
        if ($paymentStatus === 'verified') {
            // Get product's file_url
            $orderData = $pdo->prepare("SELECT o.*, dp.file_url FROM digital_orders o JOIN digital_products dp ON o.product_id = dp.id WHERE o.id = ?");
            $orderData->execute([$id]);
            $order = $orderData->fetch();
            $downloadLink = $order['file_url'];
            $status = 'paid';
        }
        
        if ($downloadLink) {
            $pdo->prepare("UPDATE digital_orders SET status = ?, payment_status = ?, admin_notes = ?, download_link = ?, expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?")->execute([$status, $paymentStatus, $adminNotes, $downloadLink, $id]);
        } else {
            $pdo->prepare("UPDATE digital_orders SET status = ?, payment_status = ?, admin_notes = ? WHERE id = ?")->execute([$status, $paymentStatus, $adminNotes, $id]);
        }
        
        setFlash('success', 'অর্ডার আপডেট হয়েছে');
        redirect(adminUrl('digital-orders'));
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM digital_orders WHERE id = ?")->execute([$id]);
        setFlash('success', 'অর্ডার মুছে ফেলা হয়েছে');
        redirect(adminUrl('digital-orders'));
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search and filter
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$paymentFilter = sanitize($_GET['payment'] ?? '');

$whereClause = "1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.transaction_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $whereClause .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if ($paymentFilter) {
    $whereClause .= " AND o.payment_status = ?";
    $params[] = $paymentFilter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM digital_orders o WHERE $whereClause");
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Fetch orders
$stmt = $pdo->prepare("
    SELECT o.*, dp.name as product_name, dp.image as product_image, dp.file_url
    FROM digital_orders o 
    LEFT JOIN digital_products dp ON o.product_id = dp.id 
    WHERE $whereClause 
    ORDER BY o.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status labels
$statusLabels = [
    'pending' => ['label' => 'পেন্ডিং', 'class' => 'bg-warning'],
    'paid' => ['label' => 'পেইড', 'class' => 'bg-success'],
    'delivered' => ['label' => 'ডেলিভার্ড', 'class' => 'bg-info'],
    'expired' => ['label' => 'মেয়াদ উত্তীর্ণ', 'class' => 'bg-secondary'],
    'cancelled' => ['label' => 'বাতিল', 'class' => 'bg-danger']
];

$paymentLabels = [
    'pending' => ['label' => 'অপেক্ষমান', 'class' => 'bg-warning'],
    'verified' => ['label' => 'যাচাইকৃত', 'class' => 'bg-success'],
    'rejected' => ['label' => 'বাতিল', 'class' => 'bg-danger']
];

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary fs-6"><?php echo $totalOrders; ?> টি ডিজিটাল অর্ডার</span>
    </div>
</div>

<div class="admin-card">
    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control" placeholder="অর্ডার নং, নাম, ফোন, TrxID..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">সকল স্ট্যাটাস</option>
                <?php foreach ($statusLabels as $key => $val): ?>
                <option value="<?php echo $key; ?>" <?php echo $statusFilter == $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="payment" class="form-select">
                <option value="">পেমেন্ট স্ট্যাটাস</option>
                <?php foreach ($paymentLabels as $key => $val): ?>
                <option value="<?php echo $key; ?>" <?php echo $paymentFilter == $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-admin-outline w-100">
                <i class="bi bi-search me-1"></i>খুঁজুন
            </button>
        </div>
        <?php if ($search || $statusFilter || $paymentFilter): ?>
        <div class="col-md-2">
            <a href="<?php echo adminUrl('digital-orders'); ?>" class="btn btn-secondary w-100">রিসেট</a>
        </div>
        <?php endif; ?>
    </form>
    
    <h5 class="card-title mb-0">ডিজিটাল অর্ডার তালিকা</h5>
    
    <?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <i class="bi bi-file-earmark-code display-1 text-muted"></i>
        <p class="text-muted mt-3">কোন ডিজিটাল অর্ডার পাওয়া যায়নি</p>
    </div>
    <?php else: ?>
    
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>অর্ডার নং</th>
                    <th>প্রোডাক্ট</th>
                    <th>গ্রাহক</th>
                    <th>পেমেন্ট</th>
                    <th>স্ট্যাটাস</th>
                    <th>তারিখ</th>
                    <th width="120">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?php echo $order['order_number']; ?></strong></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if ($order['product_image']): ?>
                            <img src="<?php echo $order['product_image']; ?>" alt="" style="width: 40px; height: 40px; object-fit: contain; border-radius: 6px; margin-right: 10px; background: #f8f9fa;">
                            <?php endif; ?>
                            <span><?php echo sanitize($order['product_name']); ?></span>
                        </div>
                    </td>
                    <td>
                        <strong><?php echo sanitize($order['customer_name']); ?></strong><br>
                        <small class="text-muted"><i class="bi bi-phone me-1"></i><?php echo sanitize($order['customer_phone']); ?></small>
                    </td>
                    <td>
                        <strong>৳<?php echo number_format($order['price']); ?></strong><br>
                        <small>
                            <span class="badge bg-dark"><?php echo strtoupper($order['payment_method']); ?></span>
                            <span class="badge <?php echo $paymentLabels[$order['payment_status']]['class']; ?>">
                                <?php echo $paymentLabels[$order['payment_status']]['label']; ?>
                            </span>
                        </small>
                    </td>
                    <td>
                        <span class="badge <?php echo $statusLabels[$order['status']]['class']; ?>">
                            <?php echo $statusLabels[$order['status']]['label']; ?>
                        </span>
                    </td>
                    <td>
                        <small><?php echo date('d M Y', strtotime($order['created_at'])); ?><br>
                        <?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>" title="বিস্তারিত">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteOrder(<?php echo $order['id']; ?>)" title="মুছুন">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                
                <!-- Order Modal -->
                <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">অর্ডার #<?php echo $order['order_number']; ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>গ্রাহকের তথ্য</h6>
                                        <p>
                                            <strong>নাম:</strong> <?php echo sanitize($order['customer_name']); ?><br>
                                            <strong>ফোন:</strong> <?php echo sanitize($order['customer_phone']); ?><br>
                                            <strong>ইমেইল:</strong> <?php echo sanitize($order['customer_email']); ?>
                                        </p>
                                        
                                        <h6>পেমেন্ট তথ্য</h6>
                                        <div class="alert alert-info">
                                            <strong>পেমেন্ট মেথড:</strong> <?php echo strtoupper($order['payment_method']); ?><br>
                                            <strong>পাঠানো নম্বর:</strong> <?php echo sanitize($order['payment_number']); ?><br>
                                            <strong>ট্রানজেকশন আইডি:</strong> <span class="fw-bold text-primary"><?php echo sanitize($order['transaction_id']); ?></span><br>
                                            <strong>পরিমাণ:</strong> ৳<?php echo number_format($order['price']); ?>
                                        </div>
                                        
                                        <?php if ($order['download_link']): ?>
                                        <h6>ডাউনলোড লিংক</h6>
                                        <div class="alert alert-success">
                                            <small class="text-break"><?php echo $order['download_link']; ?></small><br>
                                            <small class="text-muted">ডাউনলোড: <?php echo $order['download_count']; ?>/<?php echo $order['max_downloads']; ?></small>
                                            <?php if ($order['expires_at']): ?>
                                            <br><small class="text-muted">মেয়াদ: <?php echo date('d M Y', strtotime($order['expires_at'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>অর্ডার তথ্য</h6>
                                        <p>
                                            <strong>প্রোডাক্ট:</strong> <?php echo sanitize($order['product_name']); ?><br>
                                            <strong>মূল্য:</strong> ৳<?php echo number_format($order['price']); ?><br>
                                            <strong>তারিখ:</strong> <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                                        </p>
                                        
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">পেমেন্ট স্ট্যাটাস</label>
                                                <select name="payment_status" class="form-select">
                                                    <?php foreach ($paymentLabels as $key => $val): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $order['payment_status'] == $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">পেমেন্ট যাচাই করলে স্বয়ংক্রিয়ভাবে ডাউনলোড লিংক সক্রিয় হবে</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">অর্ডার স্ট্যাটাস</label>
                                                <select name="status" class="form-select">
                                                    <?php foreach ($statusLabels as $key => $val): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $order['status'] == $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">এডমিন নোট</label>
                                                <textarea name="admin_notes" class="form-control" rows="3"><?php echo sanitize($order['admin_notes']); ?></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-admin">
                                                <i class="bi bi-check-circle me-1"></i>আপডেট করুন
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $statusFilter; ?>&payment=<?php echo $paymentFilter; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function deleteOrder(id) {
    if (confirm('এই অর্ডারটি মুছে ফেলতে চান?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
