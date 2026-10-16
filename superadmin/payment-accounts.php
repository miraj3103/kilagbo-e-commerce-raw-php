<?php
/**
 * Kilagbo - Admin Payment Accounts Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পেমেন্ট একাউন্ট';
$currentPage = 'payment-accounts';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        // Update online payment settings for each section
        $sections = ['retail', 'wholesale', 'resell', 'affiliate'];
        foreach ($sections as $section) {
            $value = isset($_POST['online_payment_' . $section]) ? '1' : '0';
            $incentive = sanitize($_POST['online_payment_incentive_' . $section] ?? '');
            $discountType = sanitize($_POST['online_payment_discount_type_' . $section] ?? 'fixed');
            $discountValue = floatval($_POST['online_payment_discount_value_' . $section] ?? 0);
            
            // Update online payment toggle
            $exists = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $exists->execute(['online_payment_' . $section]);
            
            if ($exists->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, 'online_payment_' . $section]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute(['online_payment_' . $section, $value]);
            }
            
            // Update incentive text
            $existsIncentive = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $existsIncentive->execute(['online_payment_incentive_' . $section]);
            
            if ($existsIncentive->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$incentive, 'online_payment_incentive_' . $section]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute(['online_payment_incentive_' . $section, $incentive]);
            }
            
            // Update discount type
            $existsDiscountType = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $existsDiscountType->execute(['online_payment_discount_type_' . $section]);
            
            if ($existsDiscountType->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$discountType, 'online_payment_discount_type_' . $section]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute(['online_payment_discount_type_' . $section, $discountType]);
            }
            
            // Update discount value
            $existsDiscountValue = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $existsDiscountValue->execute(['online_payment_discount_value_' . $section]);
            
            if ($existsDiscountValue->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$discountValue, 'online_payment_discount_value_' . $section]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute(['online_payment_discount_value_' . $section, $discountValue]);
            }
        }
        
        setFlash('success', 'পেমেন্ট সেটিংস আপডেট হয়েছে');
        redirect(adminUrl('payment-accounts'));
    }
    
    if ($action === 'update') {
        $id = intval($_POST['id']);
        $accountNumber = sanitize($_POST['account_number']);
        $accountName = sanitize($_POST['account_name'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $pdo->prepare("UPDATE payment_accounts SET account_number = ?, account_name = ?, is_active = ? WHERE id = ?")->execute([$accountNumber, $accountName, $isActive, $id]);
        setFlash('success', 'পেমেন্ট একাউন্ট আপডেট হয়েছে');
        redirect(adminUrl('payment-accounts'));
    }
    
    if ($action === 'add') {
        $paymentType = sanitize($_POST['payment_type']);
        $accountNumber = sanitize($_POST['account_number']);
        $accountName = sanitize($_POST['account_name'] ?? '');
        
        $pdo->prepare("INSERT INTO payment_accounts (payment_type, account_number, account_name, is_active) VALUES (?, ?, ?, 1)")->execute([$paymentType, $accountNumber, $accountName]);
        setFlash('success', 'নতুন পেমেন্ট একাউন্ট যোগ হয়েছে');
        redirect(adminUrl('payment-accounts'));
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM payment_accounts WHERE id = ?")->execute([$id]);
        setFlash('success', 'পেমেন্ট একাউন্ট মুছে ফেলা হয়েছে');
        redirect(adminUrl('payment-accounts'));
    }
}

// Fetch payment accounts
$accounts = $pdo->query("SELECT * FROM payment_accounts ORDER BY payment_type, created_at DESC")->fetchAll();

// Get current settings
$onlinePaymentRetail = getSettings('online_payment_retail') == '1';
$onlinePaymentWholesale = getSettings('online_payment_wholesale') == '1';
$onlinePaymentResell = getSettings('online_payment_resell') == '1';
$onlinePaymentAffiliate = getSettings('online_payment_affiliate') == '1';

// Get incentive texts
$incentiveRetail = getSettings('online_payment_incentive_retail') ?? '';
$incentiveWholesale = getSettings('online_payment_incentive_wholesale') ?? '';
$incentiveResell = getSettings('online_payment_incentive_resell') ?? '';
$incentiveAffiliate = getSettings('online_payment_incentive_affiliate') ?? '';

// Get discount settings
$discountTypeRetail = getSettings('online_payment_discount_type_retail') ?? 'fixed';
$discountValueRetail = floatval(getSettings('online_payment_discount_value_retail') ?? 0);
$discountTypeWholesale = getSettings('online_payment_discount_type_wholesale') ?? 'fixed';
$discountValueWholesale = floatval(getSettings('online_payment_discount_value_wholesale') ?? 0);
$discountTypeResell = getSettings('online_payment_discount_type_resell') ?? 'fixed';
$discountValueResell = floatval(getSettings('online_payment_discount_value_resell') ?? 0);
$discountTypeAffiliate = getSettings('online_payment_discount_type_affiliate') ?? 'fixed';
$discountValueAffiliate = floatval(getSettings('online_payment_discount_value_affiliate') ?? 0);

require_once 'includes/header.php';
?>

<style>
.payment-card {
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}
.payment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.payment-card.bkash {
    border-color: #e2136e;
}
.payment-card.nagad {
    border-color: #f05a28;
}
.payment-header {
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.payment-header.bkash {
    background: linear-gradient(135deg, #e2136e 0%, #d41b65 100%);
}
.payment-header.nagad {
    background: linear-gradient(135deg, #f05a28 0%, #e04a18 100%);
}
.payment-logo {
    height: 28px;
    filter: brightness(100);
}
.payment-body {
    padding: 20px;
    background: #fff;
}
.payment-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 5px;
    font-family: 'Courier New', monospace;
}
.settings-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 25px;
}
.toggle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}
.toggle-item:last-child {
    border-bottom: none;
}
.toggle-label {
    display: flex;
    align-items: center;
    gap: 12px;
}
.toggle-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.toggle-icon.retail { background: #e3f2fd; color: #1976d2; }
.toggle-icon.wholesale { background: #e8f5e9; color: #388e3c; }
.toggle-icon.resell { background: #fff3e0; color: #f57c00; }
.form-switch .form-check-input {
    width: 50px;
    height: 26px;
    cursor: pointer;
}
.form-switch .form-check-input:checked {
    background-color: #1a1a1a;
    border-color: #1a1a1a;
}
</style>

<?php showFlash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary fs-6"><?php echo count($accounts); ?> টি পেমেন্ট একাউন্ট</span>
    </div>
    <button type="button" class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>নতুন একাউন্ট
    </button>
</div>

<!-- Online Payment Settings -->
<div class="settings-card mb-4">
    <h5 class="mb-1"><i class="bi bi-gear me-2"></i>অনলাইন পেমেন্ট সেটিংস</h5>
    <p class="text-muted mb-4">কোন সেকশনে অনলাইন পেমেন্ট অপশন দেখাতে চান নির্বাচন করুন</p>
    
    <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        
        <div class="toggle-item">
            <div class="toggle-label">
                <div class="toggle-icon retail">
                    <i class="bi bi-cart3"></i>
                </div>
                <div>
                    <strong>রিটেইল চেকআউট</strong>
                    <p class="mb-0 text-muted small">খুচরা বিক্রয়ের চেকআউটে অনলাইন পেমেন্ট</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" name="online_payment_incentive_retail" class="form-control form-control-sm" style="width: 150px;" placeholder="অফার টেক্সট" value="<?php echo sanitize($incentiveRetail); ?>">
                <div class="input-group input-group-sm" style="width: 180px;">
                    <select name="online_payment_discount_type_retail" class="form-select form-select-sm" style="width: 70px;">
                        <option value="fixed" <?php echo $discountTypeRetail === 'fixed' ? 'selected' : ''; ?>>৳</option>
                        <option value="percentage" <?php echo $discountTypeRetail === 'percentage' ? 'selected' : ''; ?>>%</option>
                    </select>
                    <input type="number" name="online_payment_discount_value_retail" class="form-control form-control-sm" placeholder="ছাড়" step="0.01" min="0" value="<?php echo $discountValueRetail > 0 ? $discountValueRetail : ''; ?>">
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="online_payment_retail" id="retailPayment" <?php echo $onlinePaymentRetail ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>
        
        <div class="toggle-item">
            <div class="toggle-label">
                <div class="toggle-icon wholesale">
                    <i class="bi bi-shop-window"></i>
                </div>
                <div>
                    <strong>পাইকারি চেকআউট</strong>
                    <p class="mb-0 text-muted small">পাইকারি বিক্রয়ের চেকআউটে অনলাইন পেমেন্ট</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" name="online_payment_incentive_wholesale" class="form-control form-control-sm" style="width: 150px;" placeholder="অফার টেক্সট" value="<?php echo sanitize($incentiveWholesale); ?>">
                <div class="input-group input-group-sm" style="width: 180px;">
                    <select name="online_payment_discount_type_wholesale" class="form-select form-select-sm" style="width: 70px;">
                        <option value="fixed" <?php echo $discountTypeWholesale === 'fixed' ? 'selected' : ''; ?>>৳</option>
                        <option value="percentage" <?php echo $discountTypeWholesale === 'percentage' ? 'selected' : ''; ?>>%</option>
                    </select>
                    <input type="number" name="online_payment_discount_value_wholesale" class="form-control form-control-sm" placeholder="ছাড়" step="0.01" min="0" value="<?php echo $discountValueWholesale > 0 ? $discountValueWholesale : ''; ?>">
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="online_payment_wholesale" id="wholesalePayment" <?php echo $onlinePaymentWholesale ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>
        
        <div class="toggle-item">
            <div class="toggle-label">
                <div class="toggle-icon resell">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <strong>রিসেল চেকআউট</strong>
                    <p class="mb-0 text-muted small">রিসেলার অর্ডারে অনলাইন পেমেন্ট</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" name="online_payment_incentive_resell" class="form-control form-control-sm" style="width: 150px;" placeholder="অফার টেক্সট" value="<?php echo sanitize($incentiveResell); ?>">
                <div class="input-group input-group-sm" style="width: 180px;">
                    <select name="online_payment_discount_type_resell" class="form-select form-select-sm" style="width: 70px;">
                        <option value="fixed" <?php echo $discountTypeResell === 'fixed' ? 'selected' : ''; ?>>৳</option>
                        <option value="percentage" <?php echo $discountTypeResell === 'percentage' ? 'selected' : ''; ?>>%</option>
                    </select>
                    <input type="number" name="online_payment_discount_value_resell" class="form-control form-control-sm" placeholder="ছাড়" step="0.01" min="0" value="<?php echo $discountValueResell > 0 ? $discountValueResell : ''; ?>">
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="online_payment_resell" id="resellPayment" <?php echo $onlinePaymentResell ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <div class="toggle-icon affiliate" style="background: #e0f2fe; color: #0ea5e9;">
                    <i class="bi bi-share"></i>
                </div>
                <div>
                    <strong>অ্যাফিলিয়েট চেকআউট</strong>
                    <p class="mb-0 text-muted small">অ্যাফিলিয়েট ক্রেতাদের জন্য অনলাইন পেমেন্ট</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" name="online_payment_incentive_affiliate" class="form-control form-control-sm" style="width: 150px;" placeholder="অফার টেক্সট" value="<?php echo sanitize($incentiveAffiliate); ?>">
                <div class="input-group input-group-sm" style="width: 180px;">
                    <select name="online_payment_discount_type_affiliate" class="form-select form-select-sm" style="width: 70px;">
                        <option value="fixed" <?php echo $discountTypeAffiliate === 'fixed' ? 'selected' : ''; ?>>৳</option>
                        <option value="percentage" <?php echo $discountTypeAffiliate === 'percentage' ? 'selected' : ''; ?>>%</option>
                    </select>
                    <input type="number" name="online_payment_discount_value_affiliate" class="form-control form-control-sm" placeholder="ছাড়" step="0.01" min="0" value="<?php echo $discountValueAffiliate > 0 ? $discountValueAffiliate : ''; ?>">
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="online_payment_affiliate" id="affiliatePayment" <?php echo $onlinePaymentAffiliate ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info mt-3 mb-0">
            <i class="bi bi-info-circle me-2"></i>
            <small><strong>ডিসকাউন্ট/ক্যাশব্যাক:</strong> অফার টেক্সট = চেকআউটে দেখানো হবে। ছাড় = প্রকৃত ডিসকাউন্ট যা টোটাল থেকে কমবে (৳ বা %)। অনলাইন পেমেন্ট সিলেক্ট করলে রিয়েলটাইমে ডিসকাউন্ট দেখাবে।</small>
        </div>
        
        <div class="mt-4">
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-check-circle me-1"></i>সেটিংস সংরক্ষণ করুন
            </button>
        </div>
    </form>
</div>

<div class="admin-card">
    <h5 class="card-title">পেমেন্ট একাউন্ট তালিকা</h5>
    <p class="text-muted mb-4">ডিজিটাল প্রোডাক্ট ক্রয় ও অনলাইন পেমেন্টের জন্য বিকাশ/নগদ একাউন্ট</p>
    
    <?php if (empty($accounts)): ?>
    <div class="text-center py-5">
        <i class="bi bi-wallet2 display-1 text-muted"></i>
        <p class="text-muted mt-3">কোন পেমেন্ট একাউন্ট নেই</p>
    </div>
    <?php else: ?>
    
    <div class="row g-4">
        <?php foreach ($accounts as $account): ?>
        <div class="col-md-6">
            <div class="payment-card <?php echo $account['payment_type']; ?>">
                <div class="payment-header <?php echo $account['payment_type']; ?>">
                    <?php if ($account['payment_type'] == 'bkash'): ?>
                    <img src="https://www.logo.wine/a/logo/BKash/BKash-bKash-Logo.wine.svg" alt="bKash" class="payment-logo">
                    <?php else: ?>
                    <img src="https://download.logo.wine/logo/Nagad/Nagad-Logo.wine.png" alt="Nagad" class="payment-logo">
                    <?php endif; ?>
                    <span class="badge <?php echo $account['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo $account['is_active'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                    </span>
                </div>
                <div class="payment-body">
                    <div class="payment-number"><?php echo sanitize($account['account_number']); ?></div>
                    <?php if ($account['account_name']): ?>
                    <p class="text-muted mb-3">নাম: <?php echo sanitize($account['account_name']); ?></p>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $account['id']; ?>">
                            <i class="bi bi-pencil me-1"></i>এডিট
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteAccount(<?php echo $account['id']; ?>)">
                            <i class="bi bi-trash me-1"></i>মুছুন
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?php echo $account['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">একাউন্ট এডিট করুন</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo $account['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">একাউন্ট নম্বর</label>
                                <input type="text" name="account_number" class="form-control" value="<?php echo sanitize($account['account_number']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">একাউন্ট নাম (ঐচ্ছিক)</label>
                                <input type="text" name="account_name" class="form-control" value="<?php echo sanitize($account['account_name']); ?>">
                            </div>
                            
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_active" class="form-check-input" id="active<?php echo $account['id']; ?>" <?php echo $account['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active<?php echo $account['id']; ?>">সক্রিয়</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                            <button type="submit" class="btn btn-admin">আপডেট করুন</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">নতুন পেমেন্ট একাউন্ট</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">পেমেন্ট টাইপ</label>
                        <select name="payment_type" class="form-select" required>
                            <option value="bkash">বিকাশ</option>
                            <option value="nagad">নগদ</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">একাউন্ট নম্বর</label>
                        <input type="text" name="account_number" class="form-control" required placeholder="01XXXXXXXXX">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">একাউন্ট নাম (ঐচ্ছিক)</label>
                        <input type="text" name="account_name" class="form-control" placeholder="যেমন: Kilagbo">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-admin">যোগ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteAccount(id) {
    if (confirm('এই পেমেন্ট একাউন্টটি মুছে ফেলতে চান?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
