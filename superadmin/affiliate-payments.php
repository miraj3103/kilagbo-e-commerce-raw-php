<?php
/**
 * Kilagbo Admin - Affiliate Payments (Withdrawals) Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-payments';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট পেমেন্ট';

// Handle status update
if (isset($_POST['update_status'])) {
    $withdrawalId = intval($_POST['withdrawal_id']);
    $newStatus = sanitize($_POST['status']);
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
    
    // Get withdrawal info
    $stmt = $pdo->prepare("SELECT * FROM affiliator_withdrawals WHERE id = ?");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch();
    
    if ($withdrawal) {
        if ($newStatus === 'rejected' && $withdrawal['status'] !== 'rejected') {
            // Refund the amount back to balance
            $pdo->prepare("UPDATE affiliators SET balance = balance + ? WHERE id = ?")
                ->execute([$withdrawal['amount'], $withdrawal['affiliator_id']]);
            
            // Add balance history
            $newBalance = $pdo->query("SELECT balance FROM affiliators WHERE id = {$withdrawal['affiliator_id']}")->fetchColumn();
            $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'credit', ?, ?, 'refund', ?, ?)")
                ->execute([$withdrawal['affiliator_id'], $withdrawal['amount'], 'উত্তোলন প্রত্যাখ্যান - ফেরত', $withdrawalId, $newBalance]);
        }
        
        if ($newStatus === 'completed') {
            // Update affiliator total_withdrawn
            $pdo->prepare("UPDATE affiliators SET total_withdrawn = total_withdrawn + ? WHERE id = ?")
                ->execute([$withdrawal['amount'], $withdrawal['affiliator_id']]);
        }
        
        $processedAt = ($newStatus === 'completed' || $newStatus === 'rejected') ? 'NOW()' : 'NULL';
        
        $pdo->prepare("UPDATE affiliator_withdrawals SET status = ?, transaction_id = ?, admin_notes = ?, processed_at = $processedAt WHERE id = ?")
            ->execute([$newStatus, $transactionId, $adminNotes, $withdrawalId]);
        
        setFlash('success', 'পেমেন্ট স্ট্যাটাস আপডেট হয়েছে');
    }
    
    redirect(adminUrl('affiliate-payments.php'));
}

// Get filters
$status = sanitize($_GET['status'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($status) {
    $where .= " AND aw.status = ?";
    $params[] = $status;
}

$stmt = $pdo->prepare("
    SELECT aw.*, a.name as affiliator_name, a.email as affiliator_email
    FROM affiliator_withdrawals aw
    JOIN affiliators a ON aw.affiliator_id = a.id
    $where
    ORDER BY aw.created_at DESC
");
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_paid
    FROM affiliator_withdrawals")->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-wallet2 me-2"></i>অ্যাফিলিয়েট পেমেন্ট</h4>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total'] ?: 0; ?></h3>
                    <p class="mb-0">মোট আবেদন</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning">
                <div class="card-body">
                    <h3><?php echo $stats['pending'] ?: 0; ?></h3>
                    <p class="mb-0">পেন্ডিং</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo formatPrice($stats['total_paid'] ?: 0); ?></h3>
                    <p class="mb-0">মোট প্রদান</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" name="status">
                        <option value="">সব স্ট্যাটাস</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>সম্পন্ন</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>প্রত্যাখ্যাত</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">ফিল্টার</button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($withdrawals)): ?>
            <div class="text-center py-5">
                <i class="bi bi-wallet2" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো উত্তোলন আবেদন নেই</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>অ্যাফিলিয়েট</th>
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
                                <strong><?php echo sanitize($w['affiliator_name']); ?></strong>
                                <small class="text-muted d-block"><?php echo $w['affiliator_email']; ?></small>
                            </td>
                            <td><strong class="text-success"><?php echo formatPrice($w['amount']); ?></strong></td>
                            <td><span class="text-uppercase"><?php echo $w['method_type']; ?></span></td>
                            <td>
                                <?php echo $w['account_number']; ?>
                                <?php if ($w['account_name']): ?>
                                <small class="text-muted d-block"><?php echo sanitize($w['account_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'rejected' => 'danger'];
                                $statusLabel = ['pending' => 'পেন্ডিং', 'processing' => 'প্রসেসিং', 'completed' => 'সম্পন্ন', 'rejected' => 'প্রত্যাখ্যাত'];
                                ?>
                                <span class="badge bg-<?php echo $statusClass[$w['status']] ?? 'secondary'; ?>">
                                    <?php echo $statusLabel[$w['status']] ?? $w['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($w['created_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#actionModal<?php echo $w['id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                
                                <!-- Action Modal -->
                                <div class="modal fade" id="actionModal<?php echo $w['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">পেমেন্ট প্রসেস করুন</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="withdrawal_id" value="<?php echo $w['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">স্ট্যাটাস</label>
                                                        <select class="form-select" name="status">
                                                            <option value="pending" <?php echo $w['status'] === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                                                            <option value="processing" <?php echo $w['status'] === 'processing' ? 'selected' : ''; ?>>প্রসেসিং</option>
                                                            <option value="completed" <?php echo $w['status'] === 'completed' ? 'selected' : ''; ?>>সম্পন্ন</option>
                                                            <option value="rejected" <?php echo $w['status'] === 'rejected' ? 'selected' : ''; ?>>প্রত্যাখ্যাত</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">ট্রানজেকশন আইডি</label>
                                                        <input type="text" class="form-control" name="transaction_id" value="<?php echo $w['transaction_id']; ?>" placeholder="বিকাশ/নগদ ট্রানজেকশন আইডি">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">নোট</label>
                                                        <textarea class="form-control" name="admin_notes" rows="2"><?php echo $w['admin_notes']; ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                    <button type="submit" name="update_status" class="btn btn-primary">আপডেট করুন</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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
