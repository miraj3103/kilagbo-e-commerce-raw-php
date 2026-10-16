<?php
/**
 * Kilagbo Admin - Resell Payments Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-payments';
require_once 'includes/header.php';

$pageTitle = 'রিসেল পেমেন্ট';

// Handle complete withdrawal
if (isset($_POST['complete'])) {
    $withdrawalId = intval($_POST['withdrawal_id']);
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    
    // Get withdrawal info
    $stmt = $pdo->prepare("SELECT * FROM reseller_withdrawals WHERE id = ?");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch();
    
    if ($withdrawal && $withdrawal['status'] !== 'completed') {
        // Update withdrawal
        $pdo->prepare("UPDATE reseller_withdrawals SET status = 'completed', transaction_id = ?, processed_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$transactionId, $withdrawalId]);
        
        // Deduct balance
        deductResellerBalance($withdrawal['reseller_id'], $withdrawal['amount'], 'উইথড্র সম্পন্ন - ' . strtoupper($withdrawal['method_type']), 'withdrawal', $withdrawalId);
        
        setFlash('success', 'পেমেন্ট সম্পন্ন হয়েছে');
    }
    redirect(adminUrl('resell-payments.php'));
}

// Handle reject withdrawal
if (isset($_POST['reject_withdrawal'])) {
    $withdrawalId = intval($_POST['withdrawal_id']);
    $notes = sanitize($_POST['admin_notes'] ?? '');
    
    $pdo->prepare("UPDATE reseller_withdrawals SET status = 'rejected', admin_notes = ?, processed_at = NOW(), updated_at = NOW() WHERE id = ?")
        ->execute([$notes, $withdrawalId]);
    
    setFlash('success', 'উইথড্র রিকোয়েস্ট প্রত্যাখ্যান হয়েছে');
    redirect(adminUrl('resell-payments.php'));
}

// Handle settings update
if (isset($_POST['update_resell_settings'])) {
    $minBalance = floatval($_POST['resell_min_balance'] ?? 0);
    $minWithdraw = floatval($_POST['resell_min_withdrawal'] ?? 0);
    
    updateSetting('resell_min_balance', $minBalance);
    updateSetting('reselling_min_withdrawal', $minWithdraw);
    
    setFlash('success', 'রিসেল সেটিংস আপডেট করা হয়েছে');
    redirect(adminUrl('resell-payments.php'));
}

// Get current settings
$resellMinBalance = getSettings('resell_min_balance') ?: 200;
$resellMinWithdrawal = getSettings('reselling_min_withdrawal') ?: 100;

// Get filters
$status = sanitize($_GET['status'] ?? '');

$where = "";
$params = [];

if ($status) {
    $where = "WHERE rw.status = ?";
    $params[] = $status;
}

$stmt = $pdo->prepare("
    SELECT rw.*, r.name as reseller_name, r.email as reseller_email, r.phone as reseller_phone 
    FROM reseller_withdrawals rw 
    LEFT JOIN resellers r ON rw.reseller_id = r.id 
    $where 
    ORDER BY rw.created_at DESC
");
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT 
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM reseller_withdrawals")->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-cash-stack me-2"></i>রিসেল পেমেন্ট</h4>
    </div>
    
    
    <?php showFlash(); ?>

    <!-- Settings Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="bi bi-gear-fill me-2"></i>রিসেল পেমেন্ট সেটিংস</h6>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">নূন্যতম ব্যালেন্স রিকোয়ারমেন্ট (অর্ডার করার জন্য)</label>
                    <div class="input-group">
                        <span class="input-group-text">৳</span>
                        <input type="number" name="resell_min_balance" class="form-control" value="<?php echo $resellMinBalance; ?>" required>
                    </div>
                    <div class="form-text">রিসেলারদের অর্ডার করার জন্য এই পরিমাণ মেইন ব্যালেন্স থাকতে হবে।</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label">নূন্যতম উইথড্র পরিমাণ</label>
                    <div class="input-group">
                        <span class="input-group-text">৳</span>
                        <input type="number" name="resell_min_withdrawal" class="form-control" value="<?php echo $resellMinWithdrawal; ?>" required>
                    </div>
                    <div class="form-text">রিসেলাররা নূন্যতম এই পরিমাণ ব্যালেন্স উইথড্র করতে পারবে।</div>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="update_resell_settings" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> সেভ করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-warning">
                <div class="card-body">
                    <h3><?php echo formatPrice($stats['pending_amount']); ?></h3>
                    <p class="mb-0">পেন্ডিং পেমেন্ট (<?php echo $stats['pending_count']; ?> টি)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo formatPrice($stats['completed_amount']); ?></h3>
                    <p class="mb-0">মোট প্রদত্ত</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">সব স্ট্যাটাস</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>সম্পন্ন</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>প্রত্যাখ্যাত</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($withdrawals)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো উইথড্র রিকোয়েস্ট নেই</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>রিসেলার</th>
                            <th>পরিমাণ</th>
                            <th>মেথড</th>
                            <th>অ্যাকাউন্ট</th>
                            <th>স্ট্যাটাস</th>
                            <th>তারিখ</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $w): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($w['reseller_name']); ?></strong><br>
                                <small class="text-muted"><?php echo $w['reseller_phone']; ?></small>
                            </td>
                            <td><strong><?php echo formatPrice($w['amount']); ?></strong></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo strtoupper($w['method_type']); ?></span>
                            </td>
                            <td>
                                <?php echo $w['account_number']; ?>
                                <?php if ($w['account_name']): ?>
                                <br><small class="text-muted"><?php echo $w['account_name']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($w['status'] === 'pending'): ?>
                                <span class="badge bg-warning">পেন্ডিং</span>
                                <?php elseif ($w['status'] === 'processing'): ?>
                                <span class="badge bg-info">প্রসেসিং</span>
                                <?php elseif ($w['status'] === 'completed'): ?>
                                <span class="badge bg-success">সম্পন্ন</span>
                                <?php if ($w['transaction_id']): ?>
                                <br><small class="text-muted">TXN: <?php echo $w['transaction_id']; ?></small>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="badge bg-danger">প্রত্যাখ্যাত</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d M Y', strtotime($w['created_at'])); ?>
                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($w['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php if ($w['status'] === 'pending' || $w['status'] === 'processing'): ?>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $w['id']; ?>">
                                    <i class="bi bi-check-lg"></i> সম্পন্ন
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $w['id']; ?>">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                
                                <!-- Complete Modal -->
                                <div class="modal fade" id="completeModal<?php echo $w['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">পেমেন্ট সম্পন্ন করুন</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="withdrawal_id" value="<?php echo $w['id']; ?>">
                                                    
                                                    <div class="alert alert-info">
                                                        <strong>পরিমাণ:</strong> <?php echo formatPrice($w['amount']); ?><br>
                                                        <strong>মেথড:</strong> <?php echo strtoupper($w['method_type']); ?><br>
                                                        <strong>অ্যাকাউন্ট:</strong> <?php echo $w['account_number']; ?>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">ট্রান্সাকশন আইডি</label>
                                                        <input type="text" class="form-control" name="transaction_id" placeholder="ঐচ্ছিক">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                    <button type="submit" name="complete" class="btn btn-success">পেমেন্ট সম্পন্ন</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $w['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">প্রত্যাখ্যান করুন</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="withdrawal_id" value="<?php echo $w['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">কারণ</label>
                                                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="প্রত্যাখ্যানের কারণ (ঐচ্ছিক)"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                    <button type="submit" name="reject_withdrawal" class="btn btn-danger">প্রত্যাখ্যান</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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
