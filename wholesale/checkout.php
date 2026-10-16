<?php
/**
 * Kilagbo - Wholesale Checkout
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';

$currentPage = 'wholesale';
$pageTitle = 'পাইকারি চেকআউট';

// Get product from query
$productId = (int)($_GET['product_id'] ?? 0);
$qty = (int)($_GET['qty'] ?? 1);
$variations_selected = $_GET['variation'] ?? [];

if (!$productId) {
    redirect(baseUrl('wholesale'));
}

// Get product
$stmt = $pdo->prepare("SELECT * FROM wholesale_products WHERE id = ? AND status = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect(baseUrl('wholesale'));
}

// Ensure minimum quantity
if ($qty < $product['min_order_qty']) {
    $qty = $product['min_order_qty'];
}

// Get wholesale delivery charges
$defaultDelivery = null;
$customDelivery = null;
try {
    // Check for product-specific delivery
    $customStmt = $pdo->prepare("SELECT * FROM wholesale_delivery_charges WHERE wholesale_product_id = ?");
    $customStmt->execute([$productId]);
    $customDelivery = $customStmt->fetch();
    
    // Get default delivery
    $defaultDeliveries = $pdo->query("SELECT * FROM wholesale_delivery_charges WHERE is_default = 1 AND wholesale_product_id IS NULL ORDER BY created_at ASC")->fetchAll();
    // Keep $defaultDelivery for backward compatibility/initial fallback
    $defaultDelivery = !empty($defaultDeliveries) ? $defaultDeliveries[0] : null;
} catch (PDOException $e) {
    // Table doesn't exist
}

// Determine delivery charge
$deliveryName = 'স্ট্যান্ডার্ড ডেলিভারি';
$deliveryCharge = 100; // Fallback

if ($customDelivery) {
    $deliveryName = $customDelivery['delivery_name'];
    $deliveryCharge = (float)$customDelivery['delivery_cost'];
} elseif (!empty($defaultDeliveries)) {
    // Check if a specific option is selected (via POST)
    $selectedId = isset($_POST['delivery_option']) ? (int)$_POST['delivery_option'] : 0;
    $selectedDelivery = null;
    
    if ($selectedId) {
        foreach ($defaultDeliveries as $dd) {
            if ($dd['id'] == $selectedId) {
                $selectedDelivery = $dd;
                break;
            }
        }
    }
    
    // If no valid selection or first load, use the first one
    if (!$selectedDelivery) {
        $selectedDelivery = $defaultDeliveries[0];
    }
    
    $deliveryName = $selectedDelivery['delivery_name'];
    $deliveryCharge = (float)$selectedDelivery['delivery_cost'];
}

// Calculate totals
$subtotal = $product['wholesale_price'] * $qty;
$total = $subtotal + $deliveryCharge;

// Fetch user saved addresses
$userId = $_SESSION['user_id'] ?? null;
$savedAddresses = [];
if ($userId) {
    try {
        $addrStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
        $addrStmt->execute([$userId]);
        $savedAddresses = $addrStmt->fetchAll();
    } catch (PDOException $e) {
        // Table doesn't exist
    }
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerPhone = sanitize($_POST['customer_phone'] ?? '');
    $customerEmail = sanitize($_POST['customer_email'] ?? '');
    $shippingAddress = sanitize($_POST['shipping_address'] ?? '');
    $shippingArea = sanitize($_POST['shipping_area'] ?? 'inside_dhaka');
    $notes = sanitize($_POST['notes'] ?? '');
    $orderQty = (int)($_POST['qty'] ?? $qty);
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
    
    // Online payment details
    $onlinePaymentType = sanitize($_POST['online_payment_type'] ?? '');
    $paymentNumber = sanitize($_POST['payment_number'] ?? '');
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    $orderVariations = $_POST['variations_json'] ?? '';
    
    // Validate
    if ($paymentMethod === 'online' && (empty($paymentNumber) || empty($transactionId))) {
        $error = 'অনলাইন পেমেন্টের জন্য পেমেন্ট নম্বর এবং Transaction ID আবশ্যক';
    } elseif (empty($customerName) || empty($customerPhone) || empty($shippingAddress)) {
        $error = 'সকল প্রয়োজনীয় ফিল্ড পূরণ করুন';
    } elseif ($orderQty < $product['min_order_qty']) {
        $error = 'ন্যূনতম অর্ডার পরিমাণ ' . $product['min_order_qty'] . ' পিস';
    } else {
        // Calculate final totals (delivery charge already determined above)
        $subtotal = $product['wholesale_price'] * $orderQty;
        $total = $subtotal + $deliveryCharge;
        
        // Apply online payment discount if applicable
        $onlinePaymentDiscount = 0;
        if ($paymentMethod === 'online') {
            $discountType = getSettings('online_payment_discount_type_wholesale') ?? 'fixed';
            $discountValue = floatval(getSettings('online_payment_discount_value_wholesale') ?? 0);
            
            if ($discountValue > 0) {
                if ($discountType === 'percentage') {
                    $onlinePaymentDiscount = ($total * $discountValue) / 100;
                } else {
                    $onlinePaymentDiscount = $discountValue;
                }
                $total = max(0, $total - $onlinePaymentDiscount);
            }
        }
        
        // Generate order number
        $orderNumber = 'WS' . date('Ymd') . strtoupper(substr(uniqid(), -4));
        
        // Determine payment status
        $paymentStatus = 'pending'; // Enum only supports 'pending', 'paid', 'failed', 'refunded'
        
        // Create order with payment info
        $stmt = $pdo->prepare("
            INSERT INTO wholesale_orders 
            (order_number, user_id, customer_name, customer_email, customer_phone, shipping_address, shipping_area, 
             subtotal, delivery_charge, total, notes, payment_method, payment_type, payment_number, transaction_id, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderNumber,
            isUserLoggedIn() ? $_SESSION['user_id'] : null,
            $customerName,
            $customerEmail,
            $customerPhone,
            $shippingAddress,
            $shippingArea,
            $subtotal,
            $deliveryCharge,
            $total,
            $notes,
            $paymentMethod,
            $onlinePaymentType,
            $paymentNumber,
            $transactionId,
            $paymentStatus
        ]);
        $orderId = $pdo->lastInsertId();
        
        // Add order item
        $stmt = $pdo->prepare("
            INSERT INTO wholesale_order_items 
            (order_id, wholesale_product_id, product_name, product_image, quantity, unit_price, total_price, variation) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderId,
            $product['id'],
            $product['name'],
            $product['image'],
            $orderQty,
            $product['wholesale_price'],
            $subtotal,
            $orderVariations
        ]);
        
        // Add notification
        $pdo->prepare("INSERT INTO notifications (user_type, type, title, message, link) VALUES ('admin', 'wholesale_order', 'নতুন পাইকারি অর্ডার', ?, ?)")
            ->execute(["নতুন পাইকারি অর্ডার #$orderNumber - " . formatPrice($total), "wholesale-order-view/$orderId"]);
        
        setFlash('success', 'অর্ডার সফলভাবে সম্পন্ন হয়েছে! অর্ডার নম্বর: ' . $orderNumber);
        redirect(baseUrl('wholesale'));
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Wholesale Checkout - Black & White Minimal Theme */
    .checkout-page {
        padding: 40px 0;
    }
    
    .checkout-card {
        background: var(--secondary-color);
        border-radius: 8px;
        padding: 25px;
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .checkout-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .order-summary {
        background: var(--bg-light);
        border-radius: 8px;
        padding: 20px;
        border: 1px solid var(--border-color);
    }
    
    .order-item {
        display: flex;
        gap: 15px;
        padding-bottom: 15px;
        margin-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .order-item img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        background: var(--bg-light);
    }
    
    .order-item-info h6 {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .order-totals .row {
        padding: 8px 0;
    }
    
    .order-totals .total-row {
        font-size: 1.2rem;
        font-weight: 700;
        padding-top: 15px;
        margin-top: 10px;
        border-top: 2px solid var(--primary-color);
    }
    
    .btn-wholesale-lg {
        background: var(--primary-color);
        color: var(--secondary-color);
        border: none;
        padding: 15px 30px;
        border-radius: 6px;
        font-size: 1.1rem;
        font-weight: 600;
        width: 100%;
        transition: all 0.3s ease;
    }
    
    .btn-wholesale-lg:hover {
        color: var(--secondary-color);
        background: var(--accent-color);
    }
    
    .delivery-info-box {
        background: var(--bg-light);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 12px 15px;
        margin-bottom: 15px;
    }

    /* Icon-based inputs */
    .input-with-icon {
        display: flex;
        align-items: stretch;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
        background: #fff;
    }

    .input-with-icon .icon-box {
        background: #e9ecef;
        padding: 10px 15px;
        border-right: 1px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #333;
        min-width: 55px;
    }

    .input-with-icon .icon-box i {
        font-size: 1.2rem;
    }

    .input-with-icon input, 
    .input-with-icon textarea {
        border: none !important;
        padding: 12px 15px !important;
        flex: 1;
        font-size: 1rem;
        box-shadow: none !important;
        outline: none !important;
        border-radius: 0 !important;
        min-height: 48px;
    }

    .input-with-icon textarea {
        resize: none;
        height: 48px;
    }

    @media (max-width: 768px) {
        .checkout-page h2 {
            display: none;
        }
        .checkout-card h5 {
            display: none;
        }
        .form-label {
            display: none;
        }
        .checkout-card {
            padding: 15px;
        }
        .delivery-info-box {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        .delivery-info-box .text-muted {
            display: none !important;
        }
        .payment-incentive-banner {
            padding: 10px 15px;
            gap: 8px;
        }
        .payment-incentive-banner i { font-size: 1.4rem; }
        .payment-incentive-banner .incentive-text strong { font-size: 0.9rem; }
        .payment-incentive-banner .incentive-text small { font-size: 0.75rem; }
        
        .row-mobile-order {
            display: flex;
            flex-direction: column;
        }
        .mobile-order-summary {
            order: 2;
        }
        .mobile-billing-delivery {
            order: 1;
        }
        .mobile-payment {
            order: 3;
        }
        .mobile-instruction {
            display: block !important;
            text-align: center;
            background: #fff3cd;
            color: #856404;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 15px;
            border: 1px solid #ffeeba;
        }
        .order-summary h5 {
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        .order-item-info h6 {
            font-size: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }
        .order-item-info p {
            font-size: 0.75rem;
            margin-bottom: 2px !important;
        }
        .order-item strong {
            font-size: 0.85rem;
        }
        .order-totals .row {
            font-size: 0.85rem;
            padding: 5px 0;
        }
        .order-totals .total-row {
            font-size: 1.1rem;
        }
    }
</style>

<div class="checkout-page">
    <div class="container">
        <h2 class="mb-4"><i class="bi bi-bag-check me-2"></i>পাইকারি চেকআউট</h2>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="qty" value="<?php echo $qty; ?>">
            <input type="hidden" name="variations_json" value='<?php echo json_encode($variations_selected); ?>'>
            
            <div class="row row-mobile-order">
                <div class="col-lg-7 mobile-billing-delivery">
                    <div class="mobile-instruction d-none">
                        <i class="bi bi-info-circle me-1"></i>অর্ডার কনফার্ম করতে সকল ডিটেইলস পূরন করুন
                    </div>
                    <!-- Customer Info -->
                    <div class="checkout-card">
                        <h5><i class="bi bi-person me-2"></i>কাস্টমার তথ্য</h5>
                        
                        <?php if (!empty($savedAddresses)): ?>
                        <!-- Saved Addresses -->
                        <div class="mb-4">
                            <label class="form-label">সংরক্ষিত ঠিকানা থেকে বাছাই করুন</label>
                            <div class="saved-addresses">
                                <?php foreach ($savedAddresses as $addr): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input saved-addr-radio" type="radio" name="saved_address" 
                                           id="waddr_<?php echo $addr['id']; ?>" 
                                           value="<?php echo $addr['id']; ?>"
                                           data-name="<?php echo sanitize($addr['recipient_name']); ?>"
                                           data-phone="<?php echo sanitize($addr['phone']); ?>"
                                           data-address="<?php echo sanitize($addr['address']); ?>"
                                           <?php echo $addr['is_default'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="waddr_<?php echo $addr['id']; ?>">
                                        <div class="p-2 border rounded <?php echo $addr['is_default'] ? 'border-dark' : ''; ?>">
                                            <strong><?php echo $addr['recipient_name']; ?></strong>
                                            <span class="badge bg-secondary small ms-2"><?php echo $addr['address_type'] === 'home' ? 'বাড়ি' : ($addr['label'] ?: 'কাস্টম'); ?></span>
                                            <br><small class="text-muted"><?php echo $addr['phone']; ?> | <?php echo mb_substr($addr['address'], 0, 40); ?>...</small>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input saved-addr-radio" type="radio" name="saved_address" id="waddr_new" value="new">
                                    <label class="form-check-label" for="waddr_new">
                                        <div class="p-2 border rounded"><i class="bi bi-plus-circle me-1"></i>নতুন ঠিকানা লিখুন</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div id="manualAddressForm" <?php echo !empty($savedAddresses) ? 'style="display:none;"' : ''; ?>>
                            <div class="mb-3">
                                <label class="form-label">নাম *</label>
                                <div class="input-with-icon">
                                    <div class="icon-box"><i class="bi bi-person-fill"></i></div>
                                    <input type="text" name="customer_name" id="wInputName" class="form-control" placeholder="আপনার নাম"
                                           value="<?php echo isUserLoggedIn() ? $_SESSION['user_name'] : ''; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ফোন নম্বর *</label>
                                <div class="input-with-icon">
                                    <div class="icon-box"><i class="bi bi-telephone-fill"></i></div>
                                    <input type="tel" name="customer_phone" id="wInputPhone" class="form-control" placeholder="ফোন নাম্বার">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">সম্পূর্ণ ঠিকানা *</label>
                                <div class="input-with-icon">
                                    <div class="icon-box"><i class="bi bi-geo-alt-fill"></i></div>
                                    <textarea name="shipping_address" id="wInputAddress" class="form-control" rows="1" placeholder="এড্রেস (থানা+জেলা) লিখুন"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delivery Info -->
                    <div class="checkout-card">
                        <h5><i class="bi bi-truck me-2"></i>ডেলিভারি তথ্য</h5>
                        
                        <?php if ($customDelivery || empty($defaultDeliveries)): ?>
                        <div class="delivery-info-box">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo $deliveryName; ?></strong>
                                    <br><small class="text-muted">পাইকারি ডেলিভারি</small>
                                </div>
                                <strong><?php echo formatPrice($deliveryCharge); ?></strong>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="delivery-methods">
                            <?php foreach ($defaultDeliveries as $index => $dd): ?>
                            <div class="form-check mb-2 ps-0">
                                <input class="form-check-input delivery-option visually-hidden" type="radio" name="delivery_option" 
                                       id="delivery_<?php echo $dd['id']; ?>" 
                                       value="<?php echo $dd['id']; ?>"
                                       data-cost="<?php echo $dd['delivery_cost']; ?>"
                                       <?php echo ($deliveryName == $dd['delivery_name']) ? 'checked' : ''; ?>>
                                <label class="form-check-label w-100 cursor-pointer" for="delivery_<?php echo $dd['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-center p-3 border rounded delivery-option-box <?php echo ($deliveryName == $dd['delivery_name']) ? 'border-primary bg-light' : ''; ?>">
                                        <div>
                                            <span class="fw-bold d-block"><?php echo sanitize($dd['delivery_name']); ?></span>
                                            <small class="text-muted">পাইকারি ডেলিভারি</small>
                                        </div>
                                        <strong class="text-primary"><?php echo formatPrice($dd['delivery_cost']); ?></strong>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-5 mobile-order-summary">
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h5><i class="bi bi-receipt me-2"></i>অর্ডার সারাংশ</h5>
                        
                        <div class="order-item">
                            <?php if ($product['image']): ?>
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="">
                            <?php else: ?>
                            <div style="width: 80px; height: 80px; background: #e9ecef; border-radius: 8px;"></div>
                            <?php endif; ?>
                            <div class="order-item-info">
                                <h6><?php echo $product['name']; ?></h6>
                                <?php if (!empty($variations_selected)): ?>
                                <p class="small text-muted mb-1">
                                    <?php 
                                    foreach ($variations_selected as $key => $val) {
                                        echo sanitize($key) . ': ' . sanitize($val) . ' ';
                                    }
                                    ?>
                                </p>
                                <?php endif; ?>
                                <p class="text-muted mb-1"><?php echo formatPrice($product['wholesale_price']); ?> × <?php echo $qty; ?> পিস</p>
                                <strong><?php echo formatPrice($subtotal); ?></strong>
                            </div>
                        </div>
                        
                        <div class="order-totals">
                            <div class="row">
                                <div class="col-6">সাবটোটাল</div>
                                <div class="col-6 text-end"><?php echo formatPrice($subtotal); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-6">ডেলিভারি চার্জ</div>
                                <div class="col-6 text-end" id="deliveryCharge"><?php echo formatPrice($deliveryCharge); ?></div>
                            </div>
                            <div class="row total-row">
                                <div class="col-6">মোট</div>
                                <div class="col-6 text-end" id="totalAmount"><?php echo formatPrice($total); ?></div>
                            </div>
                        </div>
                        
                        <div class="d-none d-lg-block">
                            <button type="submit" class="btn-wholesale-lg mt-4">
                                <i class="bi bi-check-circle me-2"></i>অর্ডার কনফার্ম করুন
                            </button>
                            
                            <p class="text-muted text-center mt-3 mb-0">
                                <i class="bi bi-shield-check me-1"></i>নিরাপদ পেমেন্ট
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 mobile-payment">
                    <!-- Payment Method -->
                    <div class="checkout-card">
                        <?php 
                        $sectionType = 'wholesale';
                        include __DIR__ . '/../includes/payment-method.php';
                        ?>
                    </div>

                    <div class="d-block d-lg-none mt-3">
                        <button type="submit" class="btn-wholesale-lg">
                            <i class="bi bi-check-circle me-2"></i>অর্ডার কনফার্ম করুন
                        </button>
                        <p class="text-muted text-center mt-3 mb-0" style="font-size: 0.8rem;">
                            <i class="bi bi-shield-check me-1"></i>নিরাপদ পেমেন্ট
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Handle saved address selection
document.querySelectorAll('.saved-addr-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        const manualForm = document.getElementById('manualAddressForm');
        if (this.value === 'new') {
            manualForm.style.display = 'block';
        } else {
            manualForm.style.display = 'none';
            // Fill hidden fields with saved address data
            document.getElementById('wInputName').value = this.dataset.name || '';
            document.getElementById('wInputPhone').value = this.dataset.phone || '';
            document.getElementById('wInputAddress').value = this.dataset.address || '';
        }
    });
});

// Trigger initial selection
const defaultAddr = document.querySelector('.saved-addr-radio:checked');
if (defaultAddr && defaultAddr.value !== 'new') {
    document.getElementById('wInputName').value = defaultAddr.dataset.name || '';
    document.getElementById('wInputPhone').value = defaultAddr.dataset.phone || '';
    document.getElementById('wInputAddress').value = defaultAddr.dataset.address || '';
}

// Handle delivery option change
document.querySelectorAll('.delivery-option').forEach(radio => {
    radio.addEventListener('change', function() {
        // Update visuals
        document.querySelectorAll('.delivery-option-box').forEach(box => {
            box.classList.remove('border-primary', 'bg-light');
        });
        this.nextElementSibling.querySelector('.delivery-option-box').classList.add('border-primary', 'bg-light');
        
        // Update totals
        const deliveryCost = parseFloat(this.dataset.cost);
        const subtotal = <?php echo $subtotal; ?>;
        const total = subtotal + deliveryCost;
        
        // Format price helper
        const formatPrice = (amount) => {
            return '৳' + amount.toLocaleString('en-US', {
                minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
                maximumFractionDigits: 2
            });
        };
        
        document.getElementById('deliveryCharge').innerText = formatPrice(deliveryCost);
        document.getElementById('totalAmount').innerText = formatPrice(total);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
