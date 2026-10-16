<?php
/**
 * Kilagbo Admin - Affiliators Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliators';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট ব্যবস্থাপনা';

// Handle approve
if (isset($_POST['approve'])) {
    $affiliatorId = intval($_POST['affiliator_id']);
    $pdo->prepare("UPDATE affiliators SET status = 'approved', approved_at = NOW() WHERE id = ?")->execute([$affiliatorId]);
    setFlash('success', 'অ্যাফিলিয়েট অনুমোদিত হয়েছে');
    redirect(adminUrl('affiliators.php'));
}

// Handle reject
if (isset($_POST['reject'])) {
    $affiliatorId = intval($_POST['affiliator_id']);
    $reason = sanitize($_POST['reason'] ?? '');
    
    // Get affiliator data
    $stmt = $pdo->prepare("SELECT email, phone FROM affiliators WHERE id = ?");
    $stmt->execute([$affiliatorId]);
    $affiliator = $stmt->fetch();
    
    if ($affiliator) {
        // Add to rejected credentials
        $pdo->prepare("INSERT INTO affiliator_rejected_credentials (email, phone) VALUES (?, ?)")
            ->execute([$affiliator['email'], $affiliator['phone']]);
        
        // Update affiliator status
        $pdo->prepare("UPDATE affiliators SET status = 'rejected', rejection_reason = ?, rejected_at = NOW() WHERE id = ?")
            ->execute([$reason, $affiliatorId]);
        
        setFlash('success', 'অ্যাফিলিয়েট প্রত্যাখ্যান করা হয়েছে');
    }
    redirect(adminUrl('affiliators.php'));
}

// Get filters
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($status) {
    $where .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("SELECT * FROM affiliators $where ORDER BY created_at DESC");
$stmt->execute($params);
$affiliators = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM affiliators")->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-people me-2"></i>অ্যাফিলিয়েট ব্যবস্থাপনা</h4>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total'] ?: 0; ?></h3>
                    <p class="mb-0">মোট অ্যাফিলিয়েট</p>
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
                    <h3><?php echo $stats['approved'] ?: 0; ?></h3>
                    <p class="mb-0">অনুমোদিত</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h3><?php echo $stats['rejected'] ?: 0; ?></h3>
                    <p class="mb-0">প্রত্যাখ্যাত</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="নাম, ইমেইল বা ফোন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">সব স্ট্যাটাস</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>অনুমোদিত</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>প্রত্যাখ্যাত</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">খুঁজুন</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($affiliators)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো অ্যাফিলিয়েট নেই</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>নাম</th>
                            <th>ইমেইল</th>
                            <th>ফোন</th>
                            <th>ব্যালেন্স</th>
                            <th>ক্লিক</th>
                            <th>সেলস</th>
                            <th>স্ট্যাটাস</th>
                            <th>তারিখ</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($affiliators as $affiliator): ?>
                        <tr>
                            <td><strong><?php echo sanitize($affiliator['name']); ?></strong></td>
                            <td><?php echo $affiliator['email']; ?></td>
                            <td><?php echo $affiliator['phone']; ?></td>
                            <td><?php echo formatPrice($affiliator['balance']); ?></td>
                            <td><?php echo $affiliator['total_clicks']; ?></td>
                            <td><?php echo $affiliator['total_sales']; ?></td>
                            <td>
                                <?php if ($affiliator['status'] === 'pending'): ?>
                                <span class="badge bg-warning">পেন্ডিং</span>
                                <?php elseif ($affiliator['status'] === 'approved'): ?>
                                <span class="badge bg-success">অনুমোদিত</span>
                                <?php else: ?>
                                <span class="badge bg-danger">প্রত্যাখ্যাত</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($affiliator['created_at'])); ?></td>
                            <td>
                                <?php if ($affiliator['status'] === 'pending'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="affiliator_id" value="<?php echo $affiliator['id']; ?>">
                                    <button type="submit" name="approve" class="btn btn-sm btn-success" onclick="return confirm('অনুমোদন করতে চান?')">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $affiliator['id']; ?>">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                
                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $affiliator['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">প্রত্যাখ্যান করুন</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="affiliator_id" value="<?php echo $affiliator['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">প্রত্যাখ্যানের কারণ</label>
                                                        <textarea class="form-control" name="reason" rows="3" placeholder="কারণ লিখুন (ঐচ্ছিক)"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                    <button type="submit" name="reject" class="btn btn-danger">প্রত্যাখ্যান করুন</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <a href="<?php echo adminUrl('affiliator-view.php?id=' . $affiliator['id']); ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php endif; ?>
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

<?php require_once 'includes/footer.php'; ?>
