<?php
/**
 * Kilagbo Admin - Seller Activity Log
 * Track all purchasing and activity from sellers
 */

require_once __DIR__ . '/../config/config.php';
requireAdmin();

$pageTitle = 'সেলার কার্যকলাপ';
$currentPage = 'seller-activity';

// Filters
$sellerId = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : null;
$activityType = $_GET['type'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$where = [];
$params = [];

if ($sellerId) {
    $where[] = "sa.seller_id = ?";
    $params[] = $sellerId;
}

if ($activityType !== 'all') {
    $where[] = "sa.activity_type = ?";
    $params[] = $activityType;
}

if ($dateFrom) {
    $where[] = "DATE(sa.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where[] = "DATE(sa.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get activities
$sql = "SELECT sa.*, s.name as seller_name, s.business_name, sp.name as product_name, sp.image as product_image
        FROM seller_activity sa
        LEFT JOIN sellers s ON sa.seller_id = s.id
        LEFT JOIN seller_products sp ON sa.seller_product_id = sp.id
        $whereClause
        ORDER BY sa.created_at DESC
        LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get all sellers for filter
$sellers = $pdo->query("SELECT id, name, business_name FROM sellers ORDER BY name")->fetchAll();

// Get stats
$totalPurchases = $pdo->query("SELECT COUNT(*) FROM seller_activity WHERE activity_type = 'purchase'")->fetchColumn();
$totalPurchaseAmount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM seller_activity WHERE activity_type = 'purchase'")->fetchColumn();
$totalProductsAdded = $pdo->query("SELECT COUNT(*) FROM seller_activity WHERE activity_type = 'product_added'")->fetchColumn();

require_once 'includes/header.php';
?>

<style>
    .activity-timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .activity-timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e0e0e0;
    }
    
    .activity-item {
        position: relative;
        padding: 20px;
        background: #fff;
        border-radius: 12px;
        margin-bottom: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .activity-icon {
        position: absolute;
        left: -30px;
        top: 25px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        color: #fff;
    }
    
    .activity-icon.purchase { background: #16a34a; }
    .activity-icon.payment { background: #3b82f6; }
    .activity-icon.product_added { background: #8b5cf6; }
    .activity-icon.product_approved { background: #22c55e; }
    .activity-icon.product_rejected { background: #ef4444; }
    .activity-icon.seller_approved { background: #10b981; }
    .activity-icon.seller_rejected { background: #dc2626; }
    
    .activity-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 10px;
        color: #666;
        font-size: 0.85rem;
    }
    
    .activity-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .filter-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-mini {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
    }
    
    .stat-mini h4 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .stat-mini small {
        color: #666;
    }
</style>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(22, 163, 74, 0.1); color: #16a34a;">
                <i class="bi bi-cart-check"></i>
            </div>
            <div class="stat-value"><?php echo $totalPurchases; ?></div>
            <div class="stat-label">মোট ক্রয়</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-value">৳<?php echo number_format($totalPurchaseAmount); ?></div>
            <div class="stat-label">মোট ক্রয় মূল্য</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-value"><?php echo $totalProductsAdded; ?></div>
            <div class="stat-label">পণ্য যোগ হয়েছে</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-card">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">সেলার</label>
            <select name="seller_id" class="form-select">
                <option value="">সব সেলার</option>
                <?php foreach ($sellers as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $sellerId == $s['id'] ? 'selected' : ''; ?>>
                    <?php echo sanitize($s['name']); ?> (<?php echo sanitize($s['business_name']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">ধরন</label>
            <select name="type" class="form-select">
                <option value="all" <?php echo $activityType === 'all' ? 'selected' : ''; ?>>সব</option>
                <option value="purchase" <?php echo $activityType === 'purchase' ? 'selected' : ''; ?>>ক্রয়</option>
                <option value="payment" <?php echo $activityType === 'payment' ? 'selected' : ''; ?>>পেমেন্ট</option>
                <option value="product_added" <?php echo $activityType === 'product_added' ? 'selected' : ''; ?>>পণ্য যোগ</option>
                <option value="seller_approved" <?php echo $activityType === 'seller_approved' ? 'selected' : ''; ?>>সেলার অনুমোদন</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">তারিখ থেকে</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">তারিখ পর্যন্ত</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-funnel me-2"></i>ফিল্টার
            </button>
            <a href="<?php echo adminUrl('seller-activity'); ?>" class="btn btn-admin-outline">রিসেট</a>
        </div>
    </form>
</div>

<!-- Activity Timeline -->
<div class="admin-card">
    <h5 class="card-title">কার্যকলাপ লগ</h5>
    
    <?php if (empty($activities)): ?>
    <div class="text-center py-5">
        <i class="bi bi-activity" style="font-size: 4rem; color: #ddd;"></i>
        <p class="text-muted mt-3">কোনো কার্যকলাপ পাওয়া যায়নি</p>
    </div>
    <?php else: ?>
    <div class="activity-timeline">
        <?php foreach ($activities as $activity): ?>
        <div class="activity-item">
            <div class="activity-icon <?php echo $activity['activity_type']; ?>">
                <?php
                $icons = [
                    'purchase' => 'bi-cart-check',
                    'payment' => 'bi-wallet2',
                    'product_added' => 'bi-plus-circle',
                    'product_approved' => 'bi-check-circle',
                    'product_rejected' => 'bi-x-circle',
                    'seller_approved' => 'bi-person-check',
                    'seller_rejected' => 'bi-person-x'
                ];
                ?>
                <i class="bi <?php echo $icons[$activity['activity_type']] ?? 'bi-circle'; ?>"></i>
            </div>
            
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1"><?php echo sanitize($activity['description']); ?></h6>
                    <?php if ($activity['seller_name']): ?>
                    <a href="<?php echo adminUrl('seller-view&id=' . $activity['seller_id']); ?>" class="text-decoration-none">
                        <i class="bi bi-person me-1"></i><?php echo sanitize($activity['seller_name']); ?>
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($activity['amount']): ?>
                <span class="badge bg-success fs-6">৳<?php echo number_format($activity['amount']); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($activity['product_name'] && $activity['product_image']): ?>
            <div class="d-flex align-items-center gap-2 mt-2 p-2 bg-light rounded">
                <img src="<?php echo $activity['product_image']; ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                <span><?php echo sanitize($activity['product_name']); ?></span>
                <?php if ($activity['quantity']): ?>
                <span class="badge bg-secondary"><?php echo $activity['quantity']; ?> পিস</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="activity-meta">
                <span>
                    <i class="bi bi-calendar"></i>
                    <?php echo date('d M Y', strtotime($activity['created_at'])); ?>
                </span>
                <span>
                    <i class="bi bi-clock"></i>
                    <?php echo date('h:i A', strtotime($activity['created_at'])); ?>
                </span>
                <span>
                    <i class="bi bi-tag"></i>
                    <?php
                    $typeLabels = [
                        'purchase' => 'ক্রয়',
                        'payment' => 'পেমেন্ট',
                        'product_added' => 'পণ্য যোগ',
                        'product_approved' => 'পণ্য অনুমোদন',
                        'product_rejected' => 'পণ্য প্রত্যাখ্যান',
                        'seller_approved' => 'সেলার অনুমোদন',
                        'seller_rejected' => 'সেলার প্রত্যাখ্যান'
                    ];
                    echo $typeLabels[$activity['activity_type']] ?? $activity['activity_type'];
                    ?>
                </span>
            </div>
            
            <?php 
            $details = json_decode($activity['details'], true);
            if ($details && isset($details['order_number'])): 
            ?>
            <div class="mt-2">
                <small class="text-muted">অর্ডার নম্বর: <strong><?php echo $details['order_number']; ?></strong></small>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
