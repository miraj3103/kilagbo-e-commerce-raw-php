<?php
/**
 * Kilagbo Affiliate Portal - Withdraw Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'উত্তোলন';
$currentPage = 'withdraw';
$affiliatorId = getAffiliatorId();
$affiliator = getAffiliatorData();

$minWithdrawal = floatval(getSettings('affiliate_min_withdrawal') ?: 100);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $methodType = sanitize($_POST['method_type'] ?? '');
    $accountNumber = sanitize($_POST['account_number'] ?? '');
    $accountName = sanitize($_POST['account_name'] ?? '');
    
    $errors = [];
    
    if ($amount < $minWithdrawal) {
        $errors[] = "ন্যূনতম উত্তোলনযোগ্য পরিমাণ: ৳$minWithdrawal";
    }
    
    if ($amount > $affiliator['balance']) {
        $errors[] = 'আপনার ব্যালেন্সে পর্যাপ্ত টাকা নেই';
    }
    
    if (!in_array($methodType, ['bkash', 'nagad', 'rocket'])) {
        $errors[] = 'সঠিক পেমেন্ট মেথড সিলেক্ট করুন';
    }
    
    if (empty($accountNumber)) {
        $errors[] = 'অ্যাকাউন্ট নম্বর দিন';
    }
    
    // Check for pending withdrawal
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliator_withdrawals WHERE affiliator_id = ? AND status IN ('pending', 'processing')");
    $stmt->execute([$affiliatorId]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'আপনার একটি উত্তোলনের আবেদন ইতিমধ্যে প্রসেস হচ্ছে';
    }
    
    if (empty($errors)) {
        // Create withdrawal request
        $stmt = $pdo->prepare("INSERT INTO affiliator_withdrawals (affiliator_id, amount, method_type, account_number, account_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$affiliatorId, $amount, $methodType, $accountNumber, $accountName]);
        
        // Deduct from balance
        $newBalance = $affiliator['balance'] - $amount;
        $pdo->prepare("UPDATE affiliators SET balance = ? WHERE id = ?")->execute([$newBalance, $affiliatorId]);
        
        // Add balance history
        $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'debit', ?, ?, 'withdrawal', ?, ?)")
            ->execute([$affiliatorId, $amount, "উত্তোলনের আবেদন ($methodType: $accountNumber)", $pdo->lastInsertId(), $newBalance]);
        
        // Add notification
        $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('affiliate_withdrawal', ?, ?)")
            ->execute(["অ্যাফিলিয়েট উত্তোলনের আবেদন - ৳$amount", "affiliate-payments"]);
        
        setFlash('success', 'উত্তোলনের আবেদন সফলভাবে জমা হয়েছে!');
        redirect(baseUrl('affiliate/withdraw.php'));
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

// Get withdrawal history
$stmt = $pdo->prepare("SELECT * FROM affiliator_withdrawals WHERE affiliator_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$affiliatorId]);
$withdrawals = $stmt->fetchAll();

// Refresh affiliator data
$affiliator = getAffiliatorData();

include __DIR__ . '/includes/header.php';
?>

<style>
    .balance-card {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 16px;
        padding: 30px;
        color: #fff;
        margin-bottom: 25px;
    }
    
    .balance-card h2 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
    }
    
    .balance-card p {
        opacity: 0.9;
        margin: 0;
    }
    
    .balance-stats {
        display: flex;
        gap: 30px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.2);
    }
    
    .balance-stats div span {
        display: block;
    }
    
    .balance-stats .value {
        font-size: 1.3rem;
        font-weight: 600;
    }
    
    .balance-stats .label {
        font-size: 0.85rem;
        opacity: 0.8;
    }
    
    .withdraw-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }
    
    .withdraw-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .method-options {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .method-option {
        flex: 1;
        padding: 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .method-option:hover {
        border-color: #10b981;
    }
    
    .method-option.active {
        border-color: #10b981;
        background: #ecfdf5;
    }
    
    .method-option input {
        display: none;
    }
    
    .method-option img {
        height: 30px;
        margin-bottom: 8px;
    }
    
    .method-option span {
        display: block;
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    .btn-withdraw {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-withdraw:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16,185,129,0.3);
        color: #fff;
    }
    
    .history-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .history-card h5 {
        font-weight: 600;
        padding: 20px;
        margin: 0;
        border-bottom: 1px solid #eee;
    }
    
    .history-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .history-item:last-child {
        border-bottom: none;
    }
    
    .history-method {
        font-weight: 600;
        text-transform: capitalize;
    }
    
    .history-account {
        font-size: 0.85rem;
        color: #666;
    }
    
    .history-amount {
        font-weight: 700;
        color: #10b981;
    }
    
    .status-pending { color: #f59e0b; }
    .status-processing { color: #3b82f6; }
    .status-completed { color: #10b981; }
    .status-rejected { color: #ef4444; }
    
    .info-note {
        background: #fef3c7;
        border-radius: 10px;
        padding: 15px;
        font-size: 0.9rem;
        color: #92400e;
        margin-bottom: 20px;
    }
</style>

<?php showFlash(); ?>

<!-- Balance Card -->
<div class="balance-card">
    <p>বর্তমান ব্যালেন্স</p>
    <h2><?php echo formatPrice($affiliator['balance']); ?></h2>
    
    <div class="balance-stats">
        <div>
            <span class="value"><?php echo formatPrice($affiliator['total_earned']); ?></span>
            <span class="label">মোট আয়</span>
        </div>
        <div>
            <span class="value"><?php echo formatPrice($affiliator['total_withdrawn']); ?></span>
            <span class="label">মোট উত্তোলন</span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="withdraw-card">
            <h5><i class="bi bi-cash-coin me-2"></i>উত্তোলনের আবেদন</h5>
            
            <?php if ($affiliator['balance'] < $minWithdrawal): ?>
            <div class="info-note">
                <i class="bi bi-info-circle me-2"></i>
                উত্তোলনের জন্য ন্যূনতম ৳<?php echo number_format($minWithdrawal); ?> ব্যালেন্স প্রয়োজন।
            </div>
            <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">পরিমাণ (৳)</label>
                    <input type="number" class="form-control" name="amount" min="<?php echo $minWithdrawal; ?>" max="<?php echo $affiliator['balance']; ?>" step="1" required placeholder="ন্যূনতম ৳<?php echo number_format($minWithdrawal); ?>">
                </div>
                
                <label class="form-label">পেমেন্ট মেথড</label>
                <div class="method-options">
                    <label class="method-option active">
                        <input type="radio" name="method_type" value="bkash" checked>
                        <span style="color: #e2136e; font-weight: 700;">বিকাশ</span>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="method_type" value="nagad">
                        <span style="color: #f6921e; font-weight: 700;">নগদ</span>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="method_type" value="rocket">
                        <span style="color: #8c3494; font-weight: 700;">রকেট</span>
                    </label>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">অ্যাকাউন্ট নম্বর</label>
                    <input type="text" class="form-control" name="account_number" required placeholder="01XXXXXXXXX">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">অ্যাকাউন্ট নাম (ঐচ্ছিক)</label>
                    <input type="text" class="form-control" name="account_name" placeholder="অ্যাকাউন্টধারীর নাম">
                </div>
                
                <button type="submit" name="withdraw" class="btn btn-withdraw w-100">
                    <i class="bi bi-send me-2"></i>আবেদন জমা দিন
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="history-card">
            <h5><i class="bi bi-clock-history me-2"></i>উত্তোলনের ইতিহাস</h5>
            
            <?php if (empty($withdrawals)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">কোনো উত্তোলনের ইতিহাস নেই</p>
            </div>
            <?php else: ?>
            <?php foreach ($withdrawals as $withdrawal): ?>
            <div class="history-item">
                <div>
                    <span class="history-method"><?php echo $withdrawal['method_type']; ?></span>
                    <span class="history-account"><?php echo $withdrawal['account_number']; ?></span>
                    <small class="text-muted d-block"><?php echo date('d M Y', strtotime($withdrawal['created_at'])); ?></small>
                </div>
                <div class="text-end">
                    <span class="history-amount"><?php echo formatPrice($withdrawal['amount']); ?></span>
                    <span class="d-block status-<?php echo $withdrawal['status']; ?>">
                        <?php 
                        $statusLabels = [
                            'pending' => 'পেন্ডিং',
                            'processing' => 'প্রসেসিং',
                            'completed' => 'সম্পন্ন',
                            'rejected' => 'প্রত্যাখ্যাত'
                        ];
                        echo $statusLabels[$withdrawal['status']] ?? $withdrawal['status'];
                        ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.method-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.method-option').forEach(o => o.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
