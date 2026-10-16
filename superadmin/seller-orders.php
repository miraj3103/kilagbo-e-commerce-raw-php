<?php
/**
 * Kilagbo Admin - Seller Orders
 * View orders containing seller products
 */

require_once __DIR__ . '/../config/config.php';
requireAdmin();

$pageTitle = 'সেলার অর্ডার';
$currentPage = 'seller-orders';

// Handle search
$search = $_GET['search'] ?? '';
$sellerId = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : null;

// Build query
$params = [];
$searchClause = "";
if ($search) {
    $searchClause = " AND (o.order_number LIKE ? OR oi.product_name LIKE ? OR s.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sellerClause = "";
if ($sellerId) {
    $sellerClause = " AND s.id = ?";
    $params[] = $sellerId;
}

// Combine params for 3 queries (Retail, Wholesale, Resell)
// Since we use UNION, we need to repeat params for each part if they are used
// But PDO execution with named parameters is cleaner, or positional?
// Positional is tricky with UNION if variable number of params.
// Let's use string interpolation for search/sellerId if simple, or duplicate params array.

// Actually, let's construct the SQL dynamically
$sqlParts = [];
$allParams = [];

// 1. Retail Orders
$sqlParts[] = "SELECT 
    'retail' COLLATE utf8mb4_general_ci as type,
    oi.id as item_id,
    oi.order_id,
    o.order_number COLLATE utf8mb4_general_ci as order_number,
    o.created_at,
    oi.product_name COLLATE utf8mb4_general_ci as product_name,
    p.image COLLATE utf8mb4_general_ci as product_image,
    s.id as seller_id,
    s.name COLLATE utf8mb4_general_ci as seller_name,
    s.business_name COLLATE utf8mb4_general_ci as business_name,
    oi.quantity,
    oi.total as total_price,
    o.status COLLATE utf8mb4_general_ci as order_status
FROM order_items oi
JOIN products p ON oi.product_id = p.id
JOIN sellers s ON p.seller_id = s.id
JOIN orders o ON oi.order_id = o.id
WHERE 1=1 $searchClause $sellerClause";

// Duplicate params for first query
$allParams = array_merge($allParams, $params);

// 2. Wholesale Orders
$sqlParts[] = "SELECT 
    'wholesale' COLLATE utf8mb4_general_ci as type,
    oi.id as item_id,
    oi.order_id,
    o.order_number COLLATE utf8mb4_general_ci as order_number,
    o.created_at,
    oi.product_name COLLATE utf8mb4_general_ci as product_name,
    p.image COLLATE utf8mb4_general_ci as product_image,
    s.id as seller_id,
    s.name COLLATE utf8mb4_general_ci as seller_name,
    s.business_name COLLATE utf8mb4_general_ci as business_name,
    oi.quantity,
    oi.total_price as total_price,
    o.order_status COLLATE utf8mb4_general_ci as order_status
FROM wholesale_order_items oi
JOIN wholesale_products p ON oi.wholesale_product_id = p.id
JOIN sellers s ON p.seller_id = s.id
JOIN wholesale_orders o ON oi.order_id = o.id
WHERE 1=1 $searchClause $sellerClause";

// Duplicate params for second query
$allParams = array_merge($allParams, $params);

// 3. Resell Orders
// Need to check resell tables names correctly.
// resell_order_items likely has resell_product_id
// resell_orders likely has order_number
// Let's wrap in try-catch in case table doesn't exist or column name mismatch
$resellExists = true; // Assume exists based on header.php
if ($resellExists) {
    $sqlParts[] = "SELECT 
        'resell' COLLATE utf8mb4_general_ci as type,
        oi.id as item_id,
        oi.order_id,
        o.order_number COLLATE utf8mb4_general_ci as order_number,
        o.created_at,
        oi.product_name COLLATE utf8mb4_general_ci as product_name,
        p.image COLLATE utf8mb4_general_ci as product_image,
        s.id as seller_id,
        s.name COLLATE utf8mb4_general_ci as seller_name,
        s.business_name COLLATE utf8mb4_general_ci as business_name,
        oi.quantity,
        (oi.reseller_price * oi.quantity) as total_price,
        o.status COLLATE utf8mb4_general_ci as order_status
    FROM resell_order_items oi
    JOIN resell_products p ON oi.resell_product_id = p.id
    JOIN sellers s ON p.seller_id = s.id
    JOIN resell_orders o ON oi.order_id = o.id
    WHERE 1=1 $searchClause $sellerClause";
    
    // Duplicate params for third query
    $allParams = array_merge($allParams, $params);
}

$sql = implode(" UNION ALL ", $sqlParts) . " ORDER BY created_at DESC";

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Fetch data
$orders = [];
$totalOrders = 0;
$totalPages = 0;

try {
    // Count total
    $countSql = "SELECT COUNT(*) FROM ($sql) as total_table";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($allParams);
    $totalOrders = $stmt->fetchColumn();
    $totalPages = ceil($totalOrders / $perPage);

    $sql .= " LIMIT $offset, $perPage";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    // If table or column missing, log error and show empty
    // error_log("Seller Orders Error: " . $e->getMessage());
    $error = "ডাটাবেস এরর: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<?php if (isset($error)): ?>
<div class="alert alert-danger m-4">
    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>


<div class="admin-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title mb-0">সেলার অর্ডার সমূহ</h5>
        
        <form method="GET" class="d-flex gap-2">
            <?php if ($sellerId): ?>
            <input type="hidden" name="seller_id" value="<?php echo $sellerId; ?>">
            <?php endif; ?>
            <input type="text" name="search" class="form-control" placeholder="অর্ডার নং, পণ্য বা সেলার..." value="<?php echo sanitize($search); ?>">
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    <?php if (empty($orders)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-receipt display-4"></i>
        <p class="mt-2">কোনো অর্ডার পাওয়া যায়নি</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th>অর্ডার নং</th>
                    <th>তারিখ</th>
                    <th>পণ্যের ছবি</th>
                    <th>পণ্যের নাম</th>
                    <th>সেলার</th>
                    <th>পরিমাণ</th>
                    <th>মূল্য</th>
                    <th>স্ট্যাটাস</th>
                    <th>বিস্তারিত</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <span class="fw-bold">#<?php echo $order['order_number']; ?></span>
                        <br>
                        <small class="badge bg-light text-dark border">
                            <?php 
                                if ($order['type'] == 'retail') echo 'খুচরা';
                                elseif ($order['type'] == 'wholesale') echo 'পাইকারি';
                                elseif ($order['type'] == 'resell') echo 'রিসেলিং';
                            ?>
                        </small>
                    </td>
                    <td>
                        <?php echo date('d M, Y', strtotime($order['created_at'])); ?><br>
                        <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                    </td>
                    <td>
                        <img src="<?php echo imageUrl($order['product_image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                    </td>
                    <td>
                        <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo sanitize(html_entity_decode($order['product_name'])); ?>">
                            <?php echo sanitize(html_entity_decode($order['product_name'])); ?>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div>
                                <div class="fw-bold"><?php echo sanitize(html_entity_decode($order['seller_name'])); ?></div>
                                <small class="text-muted"><?php echo sanitize(html_entity_decode($order['business_name'])); ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?php echo $order['quantity']; ?></td>
                    <td>৳<?php echo number_format($order['total_price']); ?></td>
                    <td>
                        <?php 
                        $statusClass = 'bg-secondary';
                        if ($order['order_status'] == 'pending') $statusClass = 'bg-warning text-dark';
                        elseif ($order['order_status'] == 'processing') $statusClass = 'bg-info text-white';
                        elseif ($order['order_status'] == 'shipped') $statusClass = 'bg-primary';
                        elseif ($order['order_status'] == 'delivered' || $order['order_status'] == 'completed') $statusClass = 'bg-success';
                        elseif ($order['order_status'] == 'cancelled') $statusClass = 'bg-danger';
                        ?>
                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($order['order_status']); ?></span>
                    </td>
                    <td>
                        <a href="<?php echo adminUrl('seller-view.php?id=' . $order['seller_id']); ?>" class="btn btn-sm btn-light border" title="সেলার বিস্তারিত">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $sellerId ? '&seller_id=' . $sellerId : ''; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
