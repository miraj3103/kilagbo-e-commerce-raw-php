<?php
/**
 * Kilagbo Seller Portal - Orders (from Admin)
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অর্ডার সমূহ';
$currentPage = 'orders';

$seller = requireSellerLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter
$statusFilter = $_GET['status'] ?? '';

// Build query
$whereConditions = ["seller_id = ?"];
$params = [$seller['id']];

if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM seller_orders WHERE $whereClause");
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $perPage);

    // Get orders
    $stmt = $pdo->prepare("
        SELECT so.*, sp.name as product_name, sp.image as product_image
        FROM seller_orders so
        LEFT JOIN seller_products sp ON so.seller_product_id = sp.id
        WHERE so.seller_id = ?" . ($statusFilter ? " AND so.status = ?" : "") . "
        ORDER BY so.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $totalOrders = 0;
    $totalPages = 0;
    $orders = [];
}

// Get order stats
$orderStats = [
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'total_amount' => 0
];

try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count, SUM(total_amount) as amount FROM seller_orders WHERE seller_id = ? GROUP BY status");
    $stmt->execute([$seller['id']]);
    while ($row = $stmt->fetch()) {
        $orderStats[$row['status']] = $row['count'];
        if ($row['status'] == 'completed') {
            $orderStats['total_amount'] = $row['amount'];
        }
    }
} catch (PDOException $e) {}

require_once 'includes/header.php';
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card" style="padding: 20px;">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; width: 45px; height: 45px;">
                    <i class="bi bi-clock" style="font-size: 1.2rem;"></i>
                </div>
                <div>
                    <div class="stat-value" style="font-size: 1.5rem;"><?php echo $orderStats['pending']; ?></div>
                    <div class="stat-label" style="font-size: 0.8rem;">পেন্ডিং</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="padding: 20px;">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; width: 45px; height: 45px;">
                    <i class="bi bi-arrow-repeat" style="font-size: 1.2rem;"></i>
                </div>
                <div>
                    <div class="stat-value" style="font-size: 1.5rem;"><?php echo $orderStats['processing']; ?></div>
                    <div class="stat-label" style="font-size: 0.8rem;">প্রসেসিং</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="padding: 20px;">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: rgba(22, 163, 74, 0.1); color: #16a34a; width: 45px; height: 45px;">
                    <i class="bi bi-check-circle" style="font-size: 1.2rem;"></i>
                </div>
                <div>
                    <div class="stat-value" style="font-size: 1.5rem;"><?php echo $orderStats['completed']; ?></div>
                    <div class="stat-label" style="font-size: 0.8rem;">সম্পন্ন</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="padding: 20px;">
            <div class="d-flex align-items-center">
                <div class="stat-icon me-3" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; width: 45px; height: 45px;">
                    <i class="bi bi-currency-dollar" style="font-size: 1.2rem;"></i>
                </div>
                <div>
                    <div class="stat-value" style="font-size: 1.5rem;">৳<?php echo number_format($orderStats['total_amount']); ?></div>
                    <div class="stat-label" style="font-size: 0.8rem;">মোট আয়</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="seller-card mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label">স্ট্যাটাস</label>
            <select name="status" class="form-select">
                <option value="">সব অর্ডার</option>
                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                <option value="processing" <?php echo $statusFilter == 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>সম্পন্ন</option>
                <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>বাতিল</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-seller w-100">
                <i class="bi bi-filter me-1"></i>ফিল্টার
            </button>
        </div>
        <div class="col-md-3">
            <a href="<?php echo baseUrl('seller/orders.php'); ?>" class="btn btn-outline-secondary w-100">রিসেট</a>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="seller-card">
    <h5 class="card-title">অ্যাডমিনের অর্ডার সমূহ</h5>
    
    <?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <i class="bi bi-receipt" style="font-size: 4rem; opacity: 0.3;"></i>
        <h5 class="mt-3 text-muted">কোনো অর্ডার পাওয়া যায়নি</h5>
        <p class="text-muted">অ্যাডমিন আপনার পণ্য কিনলে এখানে অর্ডার দেখতে পাবেন</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table seller-table">
            <thead>
                <tr>
                    <th>অর্ডার নম্বর</th>
                    <th>পণ্য</th>
                    <th>পরিমাণ</th>
                    <th>মূল্য</th>
                    <th>মোট</th>
                    <th>স্ট্যাটাস</th>
                    <th>তারিখ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?php echo $order['order_number']; ?></strong></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($order['product_image']): ?>
                            <img src="<?php echo $order['product_image']; ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                            <?php endif; ?>
                            <span><?php echo mb_strimwidth($order['product_name'], 0, 25, '...'); ?></span>
                        </div>
                    </td>
                    <td><?php echo $order['quantity']; ?> পিস</td>
                    <td>৳<?php echo number_format($order['unit_price']); ?></td>
                    <td><strong>৳<?php echo number_format($order['total_amount']); ?></strong></td>
                    <td>
                        <?php if ($order['status'] == 'pending'): ?>
                        <span class="badge badge-pending">পেন্ডিং</span>
                        <?php elseif ($order['status'] == 'processing'): ?>
                        <span class="badge" style="background: #dbeafe; color: #1e40af;">প্রসেসিং</span>
                        <?php elseif ($order['status'] == 'completed'): ?>
                        <span class="badge badge-approved">সম্পন্ন</span>
                        <?php else: ?>
                        <span class="badge badge-rejected">বাতিল</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <!-- Pagination -->
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
