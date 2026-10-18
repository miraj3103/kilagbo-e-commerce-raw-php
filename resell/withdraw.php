<?php
/**
 * Kilagbo Reseller - Withdraw Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'উইথড্র';
$currentPage = 'withdraw';

$resellerId = getResellerId();
$reseller = getResellerData();

// Mark withdrawals notifications as seen
markResellerNotificationsSeen($resellerId, 'withdrawals');

// Calculate correct totals
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN profit_credited = 1 THEN profit ELSE 0 END), 0) as total_earned_correct
    FROM resell_orders WHERE reseller_id = ?
");
$stmt->execute([$resellerId]);
$orderTotals = $stmt->fetch();

// Get total withdrawn from completed withdrawals
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_withdrawn_correct
    FROM reseller_withdrawals
    WHERE reseller_id = ? AND status = 'completed'
");
$stmt->execute([$resellerId]);
$withdrawalTotals = $stmt->fetch();

$totals = array_merge($orderTotals, $withdrawalTotals);

// Use the reseller's actual main balance. This stays correct after NOTE 08
// shortfall deductions/refunds, while the totals above remain display statistics.
$correctBalance = (float)($reseller['balance'] ?? 0);

$minWithdraw = (float)(getSettings('reselling_min_withdrawal') ?: 100);
$error = '';
$success = '';

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $methodType = sanitize($_POST['method_type'] ?? '');
    $accountNumber = sanitize($_POST['account_number'] ?? '');
    $accountName = sanitize($_POST['account_name'] ?? '');
    $bankName = sanitize($_POST['bank_name'] ?? '');
    $bankDistrict = sanitize($_POST['bank_district'] ?? '');
    $bankBranch = sanitize($_POST['bank_branch'] ?? '');

    $allowedMethods = ['bkash', 'nagad', 'rocket', 'bank'];
    $isBank = $methodType === 'bank';

    if ($amount < $minWithdraw) {
        $error = "ন্যূনতম উইথড্র পরিমাণ " . formatPrice($minWithdraw);
    } elseif ($amount > $correctBalance) {
        $error = "আপনার ব্যালেন্স অপর্যাপ্ত";
    } elseif (!in_array($methodType, $allowedMethods, true) || empty($accountNumber)) {
        $error = "পেমেন্ট মেথড এবং অ্যাকাউন্ট নম্বর দিন";
    } elseif ($isBank && (empty($bankName) || empty($accountName) || empty($bankDistrict) || empty($bankBranch))) {
        $error = "ব্যাংকের নাম, অ্যাকাউন্ট হোল্ডার, অ্যাকাউন্ট নম্বর, জেলা এবং শাখা দিন";
    } else {
        // Check if pending withdrawal exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reseller_withdrawals WHERE reseller_id = ? AND status IN ('pending', 'processing')");
        $stmt->execute([$resellerId]);
        if ($stmt->fetchColumn() > 0) {
            $error = "আপনার একটি উইথড্র রিকোয়েস্ট পেন্ডিং আছে";
        } else {
            // Create withdrawal request. Bank details are stored only for bank withdrawals.
            $stmt = $pdo->prepare("
                INSERT INTO reseller_withdrawals
                    (reseller_id, amount, method_type, account_number, account_name, bank_name, bank_district, bank_branch)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $resellerId,
                $amount,
                $methodType,
                $accountNumber,
                $accountName,
                $isBank ? $bankName : null,
                $isBank ? $bankDistrict : null,
                $isBank ? $bankBranch : null,
            ]);

            // Add notification
            $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('withdrawal', ?, ?)")
                ->execute(["নতুন উইথড্র রিকোয়েস্ট: " . formatPrice($amount), "resell-payments"]);

            $success = "উইথড্র রিকোয়েস্ট সফল! অ্যাডমিন অনুমোদনের পর পেমেন্ট পাঠানো হবে।";
        }
    }
}

// Handle payment method save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_method'])) {
    $methodType = sanitize($_POST['method_type'] ?? '');
    $accountNumber = sanitize($_POST['account_number'] ?? '');
    $accountName = sanitize($_POST['account_name'] ?? '');

    if (!empty($methodType) && !empty($accountNumber)) {
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM reseller_payment_methods WHERE reseller_id = ? AND method_type = ?");
        $stmt->execute([$resellerId, $methodType]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE reseller_payment_methods SET account_number = ?, account_name = ? WHERE id = ?")
                ->execute([$accountNumber, $accountName, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO reseller_payment_methods (reseller_id, method_type, account_number, account_name) VALUES (?, ?, ?, ?)")
                ->execute([$resellerId, $methodType, $accountNumber, $accountName]);
        }

        $success = "পেমেন্ট মেথড সেভ হয়েছে";
    }
}

// Get saved payment methods
$stmt = $pdo->prepare("SELECT * FROM reseller_payment_methods WHERE reseller_id = ?");
$stmt->execute([$resellerId]);
$paymentMethods = $stmt->fetchAll();
$methodsMap = [];
foreach ($paymentMethods as $m) {
    $methodsMap[$m['method_type']] = $m;
}

// Get withdrawal history
$stmt = $pdo->prepare("SELECT * FROM reseller_withdrawals WHERE reseller_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$resellerId]);
$withdrawals = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .balance-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 30px;
        color: #fff;
        margin-bottom: 25px;
    }

    .balance-card h2 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 5px;
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

    .balance-stats .stat span {
        display: block;
        font-size: 1.3rem;
        font-weight: 600;
    }

    .balance-stats .stat small {
        opacity: 0.8;
    }

    .section-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }

    .section-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .method-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .method-tab {
        flex: 1;
        padding: 15px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .method-tab:hover,
    .method-tab.active {
        border-color: var(--primary);
        background: rgba(102,126,234,0.05);
    }

    .method-tab img {
        height: 30px;
        margin-bottom: 5px;
    }

    .method-tab span {
        display: block;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .method-tab.saved::after {
        content: '✓';
        position: absolute;
        top: 5px;
        right: 10px;
        color: #28a745;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }

    .history-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #eee;
    }

    .history-item:last-child {
        border-bottom: none;
    }
</style>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<div class="balance-card">
    <p>বর্তমান ব্যালেন্স</p>
    <h2><?php echo formatPrice($correctBalance); ?></h2>

    <div class="balance-stats">
        <div class="stat">
            <span><?php echo formatPrice($totals['total_earned_correct']); ?></span>
            <small>মোট আয়</small>
        </div>
        <div class="stat">
            <span><?php echo formatPrice($totals['total_withdrawn_correct']); ?></span>
            <small>মোট উইথড্র</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="section-card">
            <h5><i class="bi bi-cash-stack me-2"></i>উইথড্র রিকোয়েস্ট</h5>

            <?php if ($correctBalance < $minWithdraw): ?>
            <div class="alert alert-warning">
                উইথড্র করতে ন্যূনতম <?php echo formatPrice($minWithdraw); ?> ব্যালেন্স থাকতে হবে।
            </div>
            <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">পরিমাণ *</label>
                    <input type="number" class="form-control" name="amount" min="<?php echo $minWithdraw; ?>" max="<?php echo $correctBalance; ?>" value="<?php echo $correctBalance; ?>" required>
                    <small class="text-muted">ন্যূনতম: <?php echo formatPrice($minWithdraw); ?>, সর্বোচ্চ: <?php echo formatPrice($correctBalance); ?></small>
                </div>

                <div class="mb-3">
                    <label class="form-label">পেমেন্ট মেথড *</label>
                    <select class="form-select" name="method_type" id="withdrawMethod" required>
                        <option value="">সিলেক্ট করুন</option>
                        <option value="bkash" <?php echo isset($methodsMap['bkash']) ? 'data-number="'.$methodsMap['bkash']['account_number'].'" data-name="'.$methodsMap['bkash']['account_name'].'"' : ''; ?>>বিকাশ</option>
                        <option value="nagad" <?php echo isset($methodsMap['nagad']) ? 'data-number="'.$methodsMap['nagad']['account_number'].'" data-name="'.$methodsMap['nagad']['account_name'].'"' : ''; ?>>নগদ</option>
                        <option value="rocket" <?php echo isset($methodsMap['rocket']) ? 'data-number="'.$methodsMap['rocket']['account_number'].'" data-name="'.$methodsMap['rocket']['account_name'].'"' : ''; ?>>রকেট</option>
                        <option value="bank" <?php echo (($_POST['method_type'] ?? '') === 'bank') ? 'selected' : ''; ?>>ব্যাংক</option>
                    </select>
                </div>

                <div id="bankDetails" class="border rounded-3 p-3 mb-3 bg-light" style="display:none;">
                    <div class="mb-3">
                        <label class="form-label">ব্যাংকের নাম *</label>
                        <input type="text" class="form-control bank-required" name="bank_name" id="bankName" value="<?php echo sanitize($_POST['bank_name'] ?? ''); ?>" placeholder="যেমন: Dutch-Bangla Bank">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">জেলা *</label>
                            <input type="text" class="form-control bank-required" name="bank_district" id="bankDistrict" value="<?php echo sanitize($_POST['bank_district'] ?? ''); ?>" placeholder="যেমন: ঢাকা">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">শাখা *</label>
                            <input type="text" class="form-control bank-required" name="bank_branch" id="bankBranch" value="<?php echo sanitize($_POST['bank_branch'] ?? ''); ?>" placeholder="যেমন: মিরপুর শাখা">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" id="accountNumberLabel">অ্যাকাউন্ট নম্বর *</label>
                    <input type="text" class="form-control" name="account_number" id="accountNumber" value="<?php echo sanitize($_POST['account_number'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" id="accountNameLabel">অ্যাকাউন্ট হোল্ডার নাম</label>
                    <input type="text" class="form-control" name="account_name" id="accountName" value="<?php echo sanitize($_POST['account_name'] ?? ''); ?>">
                </div>

                <button type="submit" name="withdraw" class="btn btn-primary w-100">
                    <i class="bi bi-send me-2"></i>উইথড্র রিকোয়েস্ট পাঠান
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="section-card">
            <h5><i class="bi bi-clock-history me-2"></i>উইথড্র হিস্ট্রি</h5>

            <?php if (empty($withdrawals)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">কোনো উইথড্র রিকোয়েস্ট নেই</p>
            </div>
            <?php else: ?>
            <?php foreach ($withdrawals as $w): ?>
            <div class="history-item">
                <div>
                    <strong><?php echo formatPrice($w['amount']); ?></strong>
                    <small class="d-block text-muted">
                        <?php if ($w['method_type'] === 'bank'): ?>
                            ব্যাংক - <?php echo sanitize($w['bank_name'] ?: 'N/A'); ?><br>
                            <?php echo sanitize($w['account_name'] ?: 'N/A'); ?> · <?php echo sanitize($w['account_number']); ?><br>
                            <?php echo sanitize($w['bank_district'] ?: 'N/A'); ?> · <?php echo sanitize($w['bank_branch'] ?: 'N/A'); ?>
                        <?php else: ?>
                            <?php echo strtoupper($w['method_type']); ?> - <?php echo sanitize($w['account_number']); ?>
                        <?php endif; ?>
                    </small>
                    <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($w['created_at'])); ?></small>
                </div>
                <div class="text-end">
                    <span class="status-badge status-<?php echo $w['status']; ?>">
                        <?php
                        $statusLabels = [
                            'pending' => 'পেন্ডিং',
                            'processing' => 'প্রসেসিং',
                            'completed' => 'সম্পন্ন',
                            'rejected' => 'প্রত্যাখ্যাত'
                        ];
                        echo $statusLabels[$w['status']] ?? $w['status'];
                        ?>
                    </span>
                    <?php if ($w['transaction_id']): ?>
                    <small class="d-block text-muted mt-1">TXN: <?php echo $w['transaction_id']; ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const method = document.getElementById('withdrawMethod');
    if (!method) return;

    const bankDetails = document.getElementById('bankDetails');
    const accountNumber = document.getElementById('accountNumber');
    const accountName = document.getElementById('accountName');
    const accountNameLabel = document.getElementById('accountNameLabel');

    function syncWithdrawalFields(prefillWallet = true) {
        const selected = method.options[method.selectedIndex];
        const isBank = method.value === 'bank';

        bankDetails.style.display = isBank ? 'block' : 'none';
        bankDetails.querySelectorAll('.bank-required').forEach(input => {
            input.required = isBank;
        });
        accountName.required = isBank;
        accountNameLabel.textContent = isBank ? 'অ্যাকাউন্ট হোল্ডার নাম *' : 'অ্যাকাউন্ট হোল্ডার নাম';

        if (!isBank && prefillWallet) {
            accountNumber.value = selected.dataset.number || '';
            accountName.value = selected.dataset.name || '';
        }
    }

    method.addEventListener('change', function() {
        syncWithdrawalFields(true);
    });

    // Keep submitted values visible after a validation error.
    syncWithdrawalFields(false);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
