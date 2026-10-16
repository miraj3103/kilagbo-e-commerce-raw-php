<?php
/**
 * Kilagbo Admin - Reseller Balance Requests
 */

$currentPage = 'resell-payments';
require_once 'includes/header.php';

$pageTitle = 'ব্যালেন্স রিকুয়েস্ট';

// Handle Bulk Payment Method Update
if (isset($_POST['save_all_methods'])) {
    $methodIds = $_POST['method_ids'] ?? [];
    $activeMethods = $_POST['is_active'] ?? []; // This will only contain IDs of checked methods
    $allDetails = $_POST['account_details'] ?? [];

    foreach ($methodIds as $id) {
        $id = intval($id);
        $isActive = isset($activeMethods[$id]) ? 1 : 0;
        $details = sanitize($allDetails[$id] ?? '');
        
        $stmt = $pdo->prepare("UPDATE resell_payment_methods SET is_active = ?, account_details = ? WHERE id = ?");
        $stmt->execute([$isActive, $details, $id]);
    }
    
    setFlash('success', 'সকল পেমেন্ট মেথড সেভ হয়েছে');
    redirect(adminUrl('resell-balance-requests.php'));
}

// Handle Balance Request Approval
if (isset($_POST['approve_request'])) {
    $requestId = intval($_POST['request_id']);
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM resell_balance_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        
        if ($request) {
            // Update reseller balance
            $stmt = $pdo->prepare("UPDATE resellers SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$request['amount'], $request['reseller_id']]);
            
            // Add to balance history
            $stmt = $pdo->prepare("INSERT INTO reseller_balance_history (reseller_id, type, amount, description) VALUES (?, 'deposit', ?, ?)");
            $stmt->execute([$request['reseller_id'], $request['amount'], 'Add Balance: ' . $request['payment_method'] . ' (ID: ' . $request['id'] . ')']);
            
            // Update request status
            $stmt = $pdo->prepare("UPDATE resell_balance_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
            $stmt->execute([$requestId]);
            
            $pdo->commit();
            setFlash('success', 'ব্যালেন্স রিকুয়েস্ট সফলভাবে অ্যাপ্রুভ করা হয়েছে');
        } else {
            $pdo->rollBack();
            setFlash('error', 'রিকুয়েস্ট পাওয়া যায়নি বা ইতিমধ্যে প্রসেস হয়েছে');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'ত্রুটি: ' . $e->getMessage());
    }
    redirect(adminUrl('resell-balance-requests.php'));
}

// Handle Balance Request Rejection
if (isset($_POST['reject_request'])) {
    $requestId = intval($_POST['request_id']);
    $note = sanitize($_POST['admin_note'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE resell_balance_requests SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->execute([$note, $requestId]);
    
    setFlash('success', 'রিকুয়েস্ট বাতিল করা হয়েছে');
    redirect(adminUrl('resell-balance-requests.php'));
}

// Get Data
$paymentMethods = $pdo->query("SELECT * FROM resell_payment_methods")->fetchAll();
$balanceRequests = $pdo->query("
    SELECT br.*, r.name as reseller_name, r.email as reseller_email, r.balance as current_balance 
    FROM resell_balance_requests br
    JOIN resellers r ON br.reseller_id = r.id
    ORDER BY br.requested_at DESC
")->fetchAll();

?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-wallet2 me-2"></i>ব্যালেন্স রিকুয়েস্ট ব্যবস্থাপনা</h4>
    </div>
    
    <?php showFlash(); ?>

    <!-- Payment Methods Config -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">পেমেন্ট মেথড সেটিংস</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-4">
                    <?php foreach ($paymentMethods as $method): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="border p-3 rounded h-100">
                            <input type="hidden" name="method_ids[]" value="<?php echo $method['id']; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="text-uppercase mb-0 fw-bold"><?php echo $method['method_name']; ?></h6>
                                    <?php if($method['is_active']): ?>
                                        <span class="badge bg-success" style="font-size: 0.6rem;">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" style="font-size: 0.6rem;">INACTIVE</span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active[<?php echo $method['id']; ?>]" value="1"
                                           <?php echo $method['is_active'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">একাউন্ট ডিটেইলস (নাম্বার/ব্যাংক তথ্য)</label>
                                <textarea class="form-control form-control-sm" name="account_details[<?php echo $method['id']; ?>]" rows="2"><?php echo $method['account_details']; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-end">
                    <button type="submit" name="save_all_methods" class="btn btn-primary px-5 py-2">
                        <i class="bi bi-save me-2"></i> সব পরিবর্তন সেভ করুন
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card">
        <div class="card-header">
            <span>রিকুয়েস্ট তালিকা</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>রিসেলার</th>
                            <th>পরিমাণ</th>
                            <th>মেথড</th>
                            <th>ট্রানজেকশন/পেয়ার</th>
                            <th>তারিখ</th>
                            <th>স্ট্যাটাস</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($balanceRequests)): ?>
                        <tr><td colspan="7" class="text-center py-4">কোনো রিকুয়েস্ট পাওয়া যায়নি</td></tr>
                        <?php else: ?>
                        <?php foreach ($balanceRequests as $req): ?>
                        <tr class="<?php echo ($req['current_balance'] < 200) ? 'table-danger' : ''; ?>">
                            <td>
                                <strong><?php echo sanitize($req['reseller_name']); ?></strong><br>
                                <small class="text-muted"><?php echo $req['reseller_email']; ?></small><br>
                                <small class="text-danger fw-bold">ব্যালেন্স: <?php echo formatPrice($req['current_balance']); ?></small>
                            </td>
                            <td><h5 class="text-success mb-0"><?php echo formatPrice($req['amount']); ?></h5></td>
                            <td><span class="badge bg-secondary text-uppercase"><?php echo $req['payment_method']; ?></span></td>
                            <td>
                                <small>
                                    <?php if ($req['payment_method'] === 'bank'): ?>
                                        <?php echo nl2br(sanitize($req['bank_details'])); ?>
                                    <?php else: ?>
                                        <strong>Sender:</strong> <?php echo $req['sender_number']; ?><br>
                                        <strong>TrxID:</strong> <?php echo $req['transaction_id']; ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td><small><?php echo date('d M Y, h:i A', strtotime($req['requested_at'])); ?></small></td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <span class="badge bg-warning">পেন্ডিং</span>
                                <?php elseif ($req['status'] === 'approved'): ?>
                                    <span class="badge bg-success">সফল</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">বাতিল</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="approve_request" class="btn btn-sm btn-success" 
                                                onclick="return confirm('ব্যালেন্স অ্যাড করতে চান?')">
                                            <i class="bi bi-plus-circle me-1"></i> অনুমোদন
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $req['id']; ?>">
                                        বাতিল
                                    </button>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?php echo $req['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">রিকুয়েস্ট বাতিল করুন</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">বাতিল করার কারণ</label>
                                                            <textarea class="form-control" name="admin_note" rows="3" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                        <button type="submit" name="reject_request" class="btn btn-danger">কনফার্ম বাতিল</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">সম্পন্ন হয়েছে</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
