<?php
/**
 * Kilagbo - Payment Method Component
 * Include this in checkout pages after delivery options
 * 
 * Usage:
 * $sectionType = 'retail'; // or 'wholesale' or 'resell'
 * include 'includes/payment-method.php';
 * 
 * Developed by Webnify Bangladesh
 */

// Get online payment setting for this section
$onlinePaymentEnabled = getSettings('online_payment_' . $sectionType) == '1';

// Get incentive text for this section
$paymentIncentive = getSettings('online_payment_incentive_' . $sectionType) ?? '';

// Get discount settings for this section
$paymentDiscountType = getSettings('online_payment_discount_type_' . $sectionType) ?? 'fixed';
$paymentDiscountValue = floatval(getSettings('online_payment_discount_value_' . $sectionType) ?? 0);

// Fetch active payment accounts
$paymentAccounts = $pdo->query("SELECT * FROM payment_accounts WHERE is_active = 1 ORDER BY payment_type")->fetchAll();
?>

<style>
.payment-method-section {
    margin-top: 25px;
}
.payment-method-section h5 {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}
.payment-option {
    display: none;
}
.payment-option + label {
    display: flex;
    align-items: center;
    padding: 18px 20px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 12px;
    background: #fff;
}
.payment-option + label:hover {
    border-color: #ccc;
}
.payment-option:checked + label {
    border-color: #1a1a1a;
    background: #f8f9fa;
}
.payment-option + label .payment-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.5rem;
}
.payment-option + label .payment-icon.cod {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
}
.payment-option + label .payment-icon.online {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: #fff;
}
.payment-option + label .payment-details {
    flex: 1;
}
.payment-option + label .payment-details h6 {
    margin: 0 0 3px;
    font-weight: 600;
}
.payment-option + label .payment-details small {
    color: #6c757d;
}
.payment-option + label .payment-check {
    width: 24px;
    height: 24px;
    border: 2px solid #dee2e6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}
.payment-option:checked + label .payment-check {
    background: #1a1a1a;
    border-color: #1a1a1a;
    color: #fff;
}
.payment-option:checked + label .payment-check::before {
    content: '✓';
    font-size: 14px;
}

/* Online Payment Details */
.online-payment-details {
    display: none;
    margin-top: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}
.online-payment-details.show {
    display: block;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.payment-account-card {
    background: #fff;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    border: 2px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s ease;
}
.payment-account-card:hover {
    border-color: #ccc;
}
.payment-account-card.selected {
    border-color: #1a1a1a;
}
.payment-account-card.bkash {
    border-left: 4px solid #e2136e;
}
.payment-account-card.nagad {
    border-left: 4px solid #f05a28;
}
.payment-account-card .account-header {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}
.payment-account-card .account-logo {
    height: 24px;
    margin-right: 10px;
}
.payment-account-card .account-number {
    font-size: 1.3rem;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    color: #1a1a1a;
}
.payment-account-card .account-name {
    font-size: 0.9rem;
    color: #6c757d;
}
.copy-btn {
    background: #1a1a1a;
    color: #fff;
    border: none;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
}
.copy-btn:hover {
    background: #333;
}
.payment-form-fields {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}
.payment-form-fields .form-label {
    font-weight: 500;
}
.payment-form-fields .form-control {
    border-radius: 8px;
    padding: 12px 15px;
}
.payment-instructions {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 15px;
    font-size: 0.9rem;
}
.payment-instructions i {
    color: #856404;
}

/* Online Payment Incentive Highlight */
.payment-incentive-banner {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: pulseGlow 2s infinite;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}
.payment-incentive-banner i {
    font-size: 1.8rem;
    animation: bounce 1s infinite;
}
.payment-incentive-banner .incentive-text {
    flex: 1;
}
.payment-incentive-banner .incentive-text strong {
    font-size: 1.1rem;
    display: block;
    margin-bottom: 2px;
}
.payment-incentive-banner .incentive-text small {
    opacity: 0.9;
}
@keyframes pulseGlow {
    0%, 100% { box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); }
    50% { box-shadow: 0 4px 25px rgba(16, 185, 129, 0.6); }
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

@media (max-width: 768px) {
    .payment-method-section {
        margin-top: 15px;
    }
    .payment-method-section h5 {
        font-size: 1.1rem;
        margin-bottom: 15px;
        padding-bottom: 10px;
    }
    .payment-option + label {
        padding: 12px 15px;
        border-radius: 10px;
        margin-bottom: 10px;
    }
    .payment-option + label .payment-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        margin-right: 12px;
        font-size: 1.2rem;
    }
    .payment-option + label .payment-details h6 {
        font-size: 0.9rem;
    }
    .payment-option + label .payment-details small {
        font-size: 0.75rem;
    }
    .payment-option + label .payment-check {
        width: 20px;
        height: 20px;
    }
    .online-payment-details {
        padding: 15px;
        border-radius: 10px;
        margin-top: 10px;
    }
    .payment-instructions {
        padding: 10px;
        font-size: 0.8rem;
        margin-bottom: 12px;
    }
    .payment-account-card {
        padding: 12px;
        margin-bottom: 12px;
        border-radius: 10px;
    }
    .payment-account-card .account-number {
        font-size: 1.1rem;
    }
    .payment-account-card .account-name {
        font-size: 0.8rem;
    }
    .copy-btn {
        padding: 4px 10px;
        font-size: 0.75rem;
    }
    .payment-form-fields {
        margin-top: 15px;
        padding-top: 15px;
    }
    .payment-form-fields .form-label {
        font-size: 0.9rem;
    }
    .payment-form-fields .form-control {
        padding: 10px 12px;
        font-size: 0.9rem;
    }
}
</style>

<div class="payment-method-section">
    <h5><i class="bi bi-credit-card me-2"></i>পেমেন্ট মেথড</h5>
    
    <!-- Hidden fields for JavaScript discount calculation -->
    <input type="hidden" id="onlinePaymentDiscountType" value="<?php echo $paymentDiscountType; ?>">
    <input type="hidden" id="onlinePaymentDiscountValue" value="<?php echo $paymentDiscountValue; ?>">
    
    <?php if ($onlinePaymentEnabled && !empty($paymentAccounts) && ($paymentDiscountValue > 0 || !empty($paymentIncentive))): ?>
    <!-- Online Payment Incentive Banner -->
    <div class="payment-incentive-banner">
        <i class="bi bi-gift-fill"></i>
        <div class="incentive-text">
            <strong><?php 
                if (!empty($paymentIncentive)) {
                    echo sanitize($paymentIncentive);
                } elseif ($paymentDiscountValue > 0) {
                    if ($paymentDiscountType === 'percentage') {
                        echo $paymentDiscountValue . '% ছাড়';
                    } else {
                        echo '৳' . number_format($paymentDiscountValue) . ' ছাড়';
                    }
                }
            ?></strong>
            <small>অনলাইন পেমেন্ট করলে এই অফার পাবেন!</small>
        </div>
        <i class="bi bi-arrow-down-circle"></i>
    </div>
    <?php endif; ?>
    
    <div class="payment-options">
        <!-- Cash on Delivery -->
        <input type="radio" name="payment_method" id="paymentCOD" value="cod" class="payment-option" checked>
        <label for="paymentCOD">
            <div class="payment-icon cod">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="payment-details">
                <h6>নিরাপদ ক্যাশ অন ডেলিভারী</h6>
                <small>পণ্য হাতে পেয়ে টাকা পরিশোধ করুন</small>
            </div>
            <div class="payment-check"></div>
        </label>
        
        <?php if ($onlinePaymentEnabled && !empty($paymentAccounts)): ?>
        <!-- Online Payment -->
        <input type="radio" name="payment_method" id="paymentOnline" value="online" class="payment-option">
        <label for="paymentOnline">
            <div class="payment-icon online">
                <i class="bi bi-phone"></i>
            </div>
            <div class="payment-details">
                <h6>অনলাইন পেমেন্ট করুন</h6>
                <small>বিকাশ / নগদ দিয়ে এখনই পেমেন্ট করুন</small>
            </div>
            <div class="payment-check"></div>
        </label>
        
        <!-- Online Payment Details -->
        <div class="online-payment-details" id="onlinePaymentDetails">
            <div class="payment-instructions">
                <i class="bi bi-info-circle me-2"></i>
                <strong>নির্দেশনা:</strong> নিচের যেকোনো নম্বরে "Send Money" করুন এবং Transaction ID দিন
            </div>
            
            <div class="payment-accounts">
                <?php foreach ($paymentAccounts as $account): ?>
                <div class="payment-account-card <?php echo $account['payment_type']; ?>" 
                     onclick="selectPaymentAccount(this, '<?php echo $account['payment_type']; ?>', '<?php echo $account['account_number']; ?>')">
                    <div class="account-header">
                        <?php if ($account['payment_type'] == 'bkash'): ?>
                        <img src="https://www.logo.wine/a/logo/BKash/BKash-bKash-Logo.wine.svg" alt="bKash" class="account-logo">
                        <span style="color: #e2136e; font-weight: 600;">বিকাশ</span>
                        <?php else: ?>
                        <img src="https://download.logo.wine/logo/Nagad/Nagad-Logo.wine.png" alt="Nagad" class="account-logo">
                        <span style="color: #f05a28; font-weight: 600;">নগদ</span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="account-number"><?php echo sanitize($account['account_number']); ?></div>
                            <?php if ($account['account_name']): ?>
                            <div class="account-name"><?php echo sanitize($account['account_name']); ?></div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="copy-btn" onclick="event.stopPropagation(); copyNumber('<?php echo $account['account_number']; ?>')">
                            <i class="bi bi-copy me-1"></i>কপি
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="payment-form-fields">
                <input type="hidden" name="online_payment_type" id="onlinePaymentType" value="">
                
                <div class="mb-3">
                    <label class="form-label">আপনার পেমেন্ট নম্বর *</label>
                    <input type="text" name="payment_number" id="paymentNumber" class="form-control" placeholder="যে নম্বর থেকে Send Money করেছেন">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Transaction ID / TrxID *</label>
                    <input type="text" name="transaction_id" id="transactionId" class="form-control" placeholder="ট্রানজেকশন আইডি লিখুন">
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Store original values for resetting
let originalGrandTotal = null;
let originalDeliveryCharge = null;

// Get discount settings
const discountType = document.getElementById('onlinePaymentDiscountType')?.value || 'fixed';
const discountValue = parseFloat(document.getElementById('onlinePaymentDiscountValue')?.value || 0);

// Toggle online payment details and apply/remove discount
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const details = document.getElementById('onlinePaymentDetails');
        if (details) {
            if (this.value === 'online') {
                details.classList.add('show');
                document.getElementById('paymentNumber').required = true;
                document.getElementById('transactionId').required = true;
                // Apply discount
                applyOnlinePaymentDiscount();
            } else {
                details.classList.remove('show');
                document.getElementById('paymentNumber').required = false;
                document.getElementById('transactionId').required = false;
                // Remove discount
                removeOnlinePaymentDiscount();
            }
        } else {
            // No online payment details section, just handle discount
            if (this.value === 'online') {
                applyOnlinePaymentDiscount();
            } else {
                removeOnlinePaymentDiscount();
            }
        }
    });
});

function applyOnlinePaymentDiscount() {
    if (discountValue <= 0) return;
    
    const grandTotalEl = document.getElementById('grandTotal');
    const orderTotalsEl = document.querySelector('.order-totals');
    
    if (!grandTotalEl) return;
    
    // Store original value if not stored
    if (originalGrandTotal === null) {
        // Extract number from formatted price
        const priceText = grandTotalEl.textContent.replace(/[^\d.]/g, '');
        originalGrandTotal = parseFloat(priceText) || 0;
    }
    
    // Calculate discount amount
    let discountAmount = 0;
    if (discountType === 'percentage') {
        discountAmount = (originalGrandTotal * discountValue) / 100;
    } else {
        discountAmount = discountValue;
    }
    
    // Calculate new total
    const newTotal = Math.max(0, originalGrandTotal - discountAmount);
    
    // Update grand total display
    grandTotalEl.textContent = '৳' + newTotal.toLocaleString('en-IN');
    
    // Add discount row if not exists
    let discountRow = document.getElementById('onlinePaymentDiscountRow');
    if (!discountRow && orderTotalsEl) {
        const finalRow = orderTotalsEl.querySelector('.order-total-row.final');
        if (finalRow) {
            discountRow = document.createElement('div');
            discountRow.id = 'onlinePaymentDiscountRow';
            discountRow.className = 'order-total-row';
            discountRow.style.color = '#10b981';
            discountRow.style.fontWeight = '600';
            discountRow.innerHTML = `
                <span><i class="bi bi-gift me-1"></i>অনলাইন পেমেন্ট ছাড়</span>
                <span>-৳${discountAmount.toLocaleString('en-IN')}</span>
            `;
            finalRow.parentNode.insertBefore(discountRow, finalRow);
        }
    }
}

function removeOnlinePaymentDiscount() {
    if (discountValue <= 0) return;
    
    const grandTotalEl = document.getElementById('grandTotal');
    const discountRow = document.getElementById('onlinePaymentDiscountRow');
    
    // Restore original total
    if (grandTotalEl && originalGrandTotal !== null) {
        grandTotalEl.textContent = '৳' + originalGrandTotal.toLocaleString('en-IN');
    }
    
    // Remove discount row
    if (discountRow) {
        discountRow.remove();
    }
}

function selectPaymentAccount(element, type, number) {
    // Remove selected from all
    document.querySelectorAll('.payment-account-card').forEach(card => {
        card.classList.remove('selected');
    });
    // Add selected to clicked
    element.classList.add('selected');
    // Set hidden field
    document.getElementById('onlinePaymentType').value = type;
}

function copyNumber(number) {
    navigator.clipboard.writeText(number).then(() => {
        // Show toast or alert
        const btn = event.target.closest('.copy-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-1"></i>কপি হয়েছে';
        btn.style.background = '#10b981';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '#1a1a1a';
        }, 2000);
    });
}
</script>
