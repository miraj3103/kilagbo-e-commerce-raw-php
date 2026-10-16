<?php
/**
 * Kilagbo Reseller - Add Balance
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'এড ব্যালেন্স';
$currentPage = 'add-balance';
$resellerId = getResellerId();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $method = sanitize($_POST['payment_method'] ?? '');
    $sender = sanitize($_POST['sender_number'] ?? '');
    $trxid = sanitize($_POST['transaction_id'] ?? '');
    $bankDetails = sanitize($_POST['bank_details'] ?? '');
    
    if ($amount <= 0) {
        $error = 'সঠিক টাকা পরিমাণ দিন';
    } elseif (empty($method)) {
        $error = 'পেমেন্ট মেথড নির্বাচন করুন';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO resell_balance_requests (reseller_id, amount, payment_method, sender_number, transaction_id, bank_details) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$resellerId, $amount, $method, $sender, $trxid, $bankDetails]);
        setFlash('success', 'ব্যালেন্স রিকুয়েস্ট পাঠানো হয়েছে। অ্যাডমিন চেক করে ব্যালেন্স যোগ করবেন।');
        redirect(baseUrl('resell/add-balance.php'));
    }
}

// Get Active Methods
$paymentMethods = $pdo->query("SELECT * FROM resell_payment_methods WHERE is_active = 1")->fetchAll();
// Get History
$stmt = $pdo->prepare("SELECT * FROM resell_balance_requests WHERE reseller_id = ? ORDER BY requested_at DESC LIMIT 10");
$stmt->execute([$resellerId]);
$history = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">এড ব্যালেন্স রিকুয়েস্ট</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php showFlash(); ?>

                <div class="alert alert-warning border-0 shadow-sm mb-4" style="background: #fffbeb; color: #92400e;">
                    <h6 class="fw-bold mb-2"><i class="bi bi-shield-check me-1"></i> সিকিউরিটি ডিপোজিট ও রিটার্ন পলিসি</h6>
                    <p class="small mb-0" style="line-height: 1.6;">
                        নিরাপদ লেনদেনের জন্য আপনার একাউন্টে মিনিমাম <strong>৳২০০</strong> ব্যালেন্স রাখতে হবে, এই টাকা আপনার একাউন্টেই থাকবে। যদি কখনো আপনার প্রদান করা অর্ডারের ডেলিভারী করা প্রোডাক্ট রিটার্ন আসে, সেক্ষেত্রে আপনার একাউন্ট থেকে সেই পণ্যের ডেলিভারী চার্জ স্বয়ংক্রিয়ভাবে কেটে নেয়া হবে।
                    </p>
                </div>

                <form method="POST" id="balanceForm">
                    <div class="mb-3">
                        <label class="form-label">টাকার পরিমাণ (৳) *</label>
                        <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">পেমেন্ট মেথড নির্বাচন করুন *</label>
                        <div class="row g-2">
                            <?php foreach ($paymentMethods as $pm): ?>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="payment_method" id="pm<?php echo $pm['id']; ?>" 
                                       value="<?php echo $pm['method_name']; ?>" autocomplete="off" required
                                       data-details="<?php echo htmlspecialchars($pm['account_details']); ?>">
                                <label class="btn btn-outline-primary w-100 text-uppercase" for="pm<?php echo $pm['id']; ?>">
                                    <?php echo $pm['method_name']; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="paymentInstructions" class="alert alert-info d-none">
                        <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> পেমেন্ট ইন্সট্রাকশন:</h6>
                        <div id="instructionContent" style="white-space: pre-line;"></div>
                    </div>

                    <div id="formFields" class="d-none">
                        <div class="mb-3 field-mobile">
                            <label class="form-label">ব্যক্তিগত নাম্বার (যে নাম্বর থেকে টাকা পাঠিয়েছেন) *</label>
                            <input type="text" name="sender_number" class="form-control" placeholder="017xxxxxxxx">
                        </div>
                        <div class="mb-3 field-mobile">
                            <label class="form-label">ট্রানজেকশন আইডি (TrxID) *</label>
                            <input type="text" name="transaction_id" class="form-control" placeholder="XXXXXX">
                        </div>
                        <div class="mb-3 field-bank d-none">
                            <label class="form-label">ব্যাংক ট্রান্সফার ডিটেইলস (রেফারেন্স/হিসাব তথ্য) *</label>
                            <textarea name="bank_details" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" name="submit_request" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-send me-1"></i> রিকুয়েস্ট পাঠান
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">এড ব্যালেন্স ইতিহাস</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>পরিমাণ</th>
                                <th>মেথড</th>
                                <th>তারিখ</th>
                                <th>স্ট্যাটাস</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="4" class="text-center py-4">কোনো তথ্য পাওয়া যায়নি</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                <tr class="<?php echo ($h['status'] === 'rejected') ? 'table-light text-muted' : ''; ?>">
                                    <td>
                                        <strong><?php echo formatPrice($h['amount']); ?></strong>
                                    </td>
                                    <td><span class="badge bg-secondary text-uppercase"><?php echo $h['payment_method']; ?></span></td>
                                    <td>
                                        <small><?php echo date('d M Y, h:i A', strtotime($h['requested_at'])); ?></small>
                                        <?php if ($h['status'] !== 'pending' && $h['processed_at']): ?>
                                            <div class="text-muted" style="font-size: 0.7rem;">সম্পন্ন: <?php echo date('d M Y, h:i A', strtotime($h['processed_at'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($h['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">পেন্ডিং</span>
                                        <?php elseif ($h['status'] === 'approved'): ?>
                                            <span class="badge bg-success">সফল</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">বাতিল</span>
                                            <?php if ($h['admin_note']): ?>
                                                <div class="small text-danger mt-1">কারন: <?php echo sanitize($h['admin_note']); ?></div>
                                            <?php endif; ?>
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
</div>

<script>
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const details = this.getAttribute('data-details');
        const method = this.value;
        const instructions = document.getElementById('paymentInstructions');
        const content = document.getElementById('instructionContent');
        const formFields = document.getElementById('formFields');
        const mobileFields = document.querySelectorAll('.field-mobile');
        const bankFields = document.querySelector('.field-bank');
        
        content.innerText = details;
        instructions.classList.remove('d-none');
        formFields.classList.remove('d-none');
        
        if (method === 'bank') {
            mobileFields.forEach(f => {
                f.classList.add('d-none');
                f.querySelector('input').required = false;
            });
            bankFields.classList.remove('d-none');
            bankFields.querySelector('textarea').required = true;
        } else {
            mobileFields.forEach(f => {
                f.classList.remove('d-none');
                f.querySelector('input').required = true;
            });
            bankFields.classList.add('d-none');
            bankFields.querySelector('textarea').required = false;
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
