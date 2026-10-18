<?php
/**
 * Kilagbo Admin - Sellers Management
 * List, approve, reject, and manage sellers
 */

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

$pageTitle = 'সেলার সমূহ';
$currentPage = 'sellers';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sellerId = intval($_POST['seller_id'] ?? 0);
    
    if ($sellerId > 0) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE sellers SET status = 'approved' WHERE id = ?");
            $stmt->execute([$sellerId]);
            
            // Log activity
            $seller = $pdo->prepare("SELECT name FROM sellers WHERE id = ?")->execute([$sellerId]);
            $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, description) VALUES ('seller_approved', ?, ?)")
                ->execute([$sellerId, "সেলার অনুমোদন করা হয়েছে"]);
            
            setFlash('success', 'সেলার সফলভাবে অনুমোদন করা হয়েছে।');
        } elseif ($action === 'reject') {
            $reason = sanitize($_POST['reason'] ?? '');
            
            // Get seller info before rejecting
            $stmt = $pdo->prepare("SELECT email, phone FROM sellers WHERE id = ?");
            $stmt->execute([$sellerId]);
            $seller = $stmt->fetch();
            
            if ($seller) {
                // Add to rejected credentials
                $pdo->prepare("INSERT INTO seller_rejected_credentials (email, phone, seller_id, reason) VALUES (?, ?, ?, ?)")
                    ->execute([$seller['email'], $seller['phone'], $sellerId, $reason]);
                
                // Update status
                $pdo->prepare("UPDATE sellers SET status = 'rejected' WHERE id = ?")->execute([$sellerId]);
                
                // Log activity
                $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, description) VALUES ('seller_rejected', ?, ?)")
                    ->execute([$sellerId, "সেলার প্রত্যাখ্যান করা হয়েছে: $reason"]);
                
                setFlash('success', 'সেলার প্রত্যাখ্যান করা হয়েছে।');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM sellers WHERE id = ?")->execute([$sellerId]);
            setFlash('success', 'সেলার সফলভাবে মুছে ফেলা হয়েছে।');
        }
    }
    
    redirect(adminUrl('sellers'));
}

// Filters
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($status !== 'all') {
    $where[] = "status = ?";
    $params[] = $status;
}

if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR business_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get sellers
$sql = "SELECT * FROM sellers $whereClause ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sellers = $stmt->fetchAll();

// Get counts
$totalSellers = $pdo->query("SELECT COUNT(*) FROM sellers")->fetchColumn();
$pendingSellers = $pdo->query("SELECT COUNT(*) FROM sellers WHERE status = 'pending'")->fetchColumn();
$approvedSellers = $pdo->query("SELECT COUNT(*) FROM sellers WHERE status = 'approved'")->fetchColumn();
$rejectedSellers = $pdo->query("SELECT COUNT(*) FROM sellers WHERE status = 'rejected'")->fetchColumn();

require_once 'includes/header.php';
?>

<style>
    .seller-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-pending { background: #fef3cd; color: #856404; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    
    .filter-tabs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .filter-tab {
        padding: 8px 16px;
        border-radius: 20px;
        text-decoration: none;
        color: #666;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }
    
    .filter-tab:hover, .filter-tab.active {
        background: #000;
        color: #fff;
    }
    
    .filter-tab .count {
        background: rgba(0,0,0,0.1);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.75rem;
        margin-left: 5px;
    }
    
    .filter-tab.active .count {
        background: rgba(255,255,255,0.2);
    }
</style>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(0,0,0,0.1); color: #000;">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?php echo $totalSellers; ?></div>
            <div class="stat-label">মোট সেলার</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?php echo $pendingSellers; ?></div>
            <div class="stat-label">পেন্ডিং</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(22, 163, 74, 0.1); color: #16a34a;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $approvedSellers; ?></div>
            <div class="stat-label">অনুমোদিত</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="stat-value"><?php echo $rejectedSellers; ?></div>
            <div class="stat-label">প্রত্যাখ্যাত</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <!-- Filters -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                সব <span class="count"><?php echo $totalSellers; ?></span>
            </a>
            <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                পেন্ডিং <span class="count"><?php echo $pendingSellers; ?></span>
            </a>
            <a href="?status=approved" class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                অনুমোদিত <span class="count"><?php echo $approvedSellers; ?></span>
            </a>
            <a href="?status=rejected" class="filter-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                প্রত্যাখ্যাত <span class="count"><?php echo $rejectedSellers; ?></span>
            </a>
        </div>
        
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="status" value="<?php echo $status; ?>">
            <input type="text" name="search" class="form-control" placeholder="সেলার খুঁজুন..." value="<?php echo sanitize($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>
    
    <!-- Sellers Table -->
    <?php if (empty($sellers)): ?>
    <div class="text-center py-5">
        <i class="bi bi-people" style="font-size: 4rem; color: #ddd;"></i>
        <p class="text-muted mt-3">কোনো সেলার পাওয়া যায়নি</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table">
            <thead>
                <tr>
                    <th>সেলার</th>
                    <th>ব্যবসা</th>
                    <th>যোগাযোগ</th>
                    <th>জেলা</th>
                    <th>স্ট্যাটাস</th>
                    <th>তারিখ</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sellers as $seller): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="seller-avatar">
                                <?php echo strtoupper(substr($seller['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo sanitize($seller['name']); ?></strong>
                                <?php if ($seller['nid_number']): ?>
                                <br><small class="text-muted">NID: <?php echo sanitize($seller['nid_number']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong><?php echo sanitize($seller['business_name']); ?></strong>
                        <br><small class="text-muted">
                            <?php
                            $types = [
                                'manufacturer' => 'ম্যানুফ্যাকচারার',
                                'wholesaler' => 'পাইকারি বিক্রেতা',
                                'importer' => 'আমদানিকারক',
                                'distributor' => 'ডিস্ট্রিবিউটর',
                                'other' => 'অন্যান্য'
                            ];
                            echo $types[$seller['business_type']] ?? 'N/A';
                            ?>
                        </small>
                    </td>
                    <td>
                        <a href="mailto:<?php echo $seller['email']; ?>" class="text-decoration-none"><?php echo sanitize($seller['email']); ?></a>
                        <br><a href="tel:<?php echo $seller['phone']; ?>" class="text-decoration-none"><?php echo sanitize($seller['phone']); ?></a>
                    </td>
                    <td><?php echo sanitize($seller['district'] ?: 'N/A'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $seller['status']; ?>">
                            <?php 
                            echo $seller['status'] === 'pending' ? 'পেন্ডিং' : 
                                 ($seller['status'] === 'approved' ? 'অনুমোদিত' : 'প্রত্যাখ্যাত'); 
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php echo date('d M Y', strtotime($seller['created_at'])); ?>
                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($seller['created_at'])); ?></small>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?php echo adminUrl('seller-view.php?id=' . $seller['id']); ?>" class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center px-2 py-1" title="বিস্তারিত">
                                <i class="bi bi-eye"></i>
                            </a>
                            
                            <?php if ($seller['status'] === 'pending'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('সেলারকে অনুমোদন করতে চান?')">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="অনুমোদন">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-warning" onclick="showRejectModal(<?php echo $seller['id']; ?>)" title="প্রত্যাখ্যান">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <?php endif; ?>
                            
                            <form method="POST" class="d-inline" onsubmit="return confirm('সত্যিই মুছে ফেলতে চান? এটি ফিরিয়ে আনা যাবে না।')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="মুছুন">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="seller_id" id="rejectSellerId">
                <div class="modal-header">
                    <h5 class="modal-title">সেলার প্রত্যাখ্যান</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">প্রত্যাখ্যানের কারণ</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="কেন প্রত্যাখ্যান করছেন..." required></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>সতর্কতা:</strong> প্রত্যাখ্যানের পর এই সেলার একই ইমেইল ও ফোন নম্বর দিয়ে আবার রেজিস্ট্রেশন করতে পারবে না।
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-warning">প্রত্যাখ্যান করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRejectModal(sellerId) {
    document.getElementById('rejectSellerId').value = sellerId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
