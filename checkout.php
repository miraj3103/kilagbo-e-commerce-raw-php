<?php
/**
 * Kilagbo - Checkout Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$pageTitle = 'চেকআউট';

$sessionId = getSessionId();
$userId = $_SESSION['user_id'] ?? null;

// Fetch cart items with custom delivery info
// Fetch cart items with custom delivery info
// Fetch cart items with custom delivery info
$sql = "SELECT c.*, 
        COALESCE(p.name, wp.name, ap.name) as name, 
        COALESCE(p.slug, wp.slug) as slug, 
        COALESCE(p.image, wp.image, ap.image) as image, 
        p.regular_price as retail_regular_price, 
        p.sale_price as retail_sale_price,
        p.custom_delivery_name, 
        p.custom_delivery_cost,
        wp.wholesale_price,
        ap.regular_price as affiliate_regular_price,
        ap.sale_price as affiliate_sale_price
        FROM cart c 
        LEFT JOIN products p ON c.product_id = p.id AND c.section = 'retail'
        LEFT JOIN wholesale_products wp ON c.product_id = wp.id AND c.section = 'wholesale'
        LEFT JOIN affiliate_products ap ON c.product_id = ap.id AND c.section = 'affiliate'
        WHERE ";

if ($userId) {
    $stmt = $pdo->prepare($sql . "c.user_id = ?");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->prepare($sql . "c.session_id = ?");
    $stmt->execute([$sessionId]);
}
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    redirect(baseUrl('cart'));
}

// Calculate totals
$subtotal = 0;
$hasCustomDelivery = false;
$customDeliveryCharge = 0;
$customDeliveryName = '';

foreach ($cartItems as $item) {
    if ($item['section'] === 'wholesale') {
        $price = $item['wholesale_price'];
    } elseif ($item['section'] === 'affiliate') {
        $price = $item['affiliate_sale_price'] ?: $item['affiliate_regular_price'];
    } else {
        $price = $item['retail_sale_price'] ?: $item['retail_regular_price'];
    }
    $subtotal += $price * $item['quantity'];
    
    // Check for custom delivery (use the first product's custom delivery if any)
    if (!empty($item['custom_delivery_name']) && !$hasCustomDelivery) {
        $hasCustomDelivery = true;
        $customDeliveryCharge = floatval($item['custom_delivery_cost']);
        $customDeliveryName = $item['custom_delivery_name'];
    }
}

$deliveryType = getSettings('delivery_type') ?: 'paid';
$insideDhakaCharge = floatval(getSettings('inside_dhaka_charge') ?: 60);
$outsideDhakaCharge = floatval(getSettings('outside_dhaka_charge') ?: 130);
$insideDhakaLabel = getSettings('inside_dhaka_label') ?: 'ঢাকার ভেতরে';
$outsideDhakaLabel = getSettings('outside_dhaka_label') ?: 'ঢাকার বাহিরে';

// If free delivery is set globally
$isFreeDelivery = ($deliveryType == 'free');

// Fetch user saved addresses
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

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $deliveryArea = sanitize($_POST['delivery_area'] ?? 'inside_dhaka');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
    
    // Online payment details
    $onlinePaymentType = sanitize($_POST['online_payment_type'] ?? '');
    $paymentNumber = sanitize($_POST['payment_number'] ?? '');
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    
    // Validate online payment fields
    if ($paymentMethod === 'online' && (empty($paymentNumber) || empty($transactionId))) {
        setFlash('error', 'অনলাইন পেমেন্টের জন্য পেমেন্ট নম্বর এবং Transaction ID আবশ্যক');
    } elseif (empty($name) || empty($phone) || empty($address)) {
        setFlash('error', 'সকল তথ্য পূরণ করুন');
    } else {
        // Determine delivery charge
        if ($hasCustomDelivery) {
            $deliveryCharge = $customDeliveryCharge;
        } elseif ($isFreeDelivery) {
            $deliveryCharge = 0;
        } else {
            $deliveryCharge = $deliveryArea == 'inside_dhaka' ? $insideDhakaCharge : $outsideDhakaCharge;
        }
        $total = $subtotal + $deliveryCharge;
        
        // Apply online payment discount if applicable
        $onlinePaymentDiscount = 0;
        if ($paymentMethod === 'online') {
            $discountType = getSettings('online_payment_discount_type_retail') ?? 'fixed';
            $discountValue = floatval(getSettings('online_payment_discount_value_retail') ?? 0);
            
            if ($discountValue > 0) {
                if ($discountType === 'percentage') {
                    $onlinePaymentDiscount = ($total * $discountValue) / 100;
                } else {
                    $onlinePaymentDiscount = $discountValue;
                }
                $total = max(0, $total - $onlinePaymentDiscount);
            }
        }
        
        $orderNumber = generateOrderNumber();
        
        // Determine payment status
        $paymentStatus = $paymentMethod === 'online' ? 'pending_verification' : 'pending';
        
        // Create order with payment info
        $stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, customer_name, customer_phone, customer_address, delivery_area, delivery_charge, subtotal, total, payment_method, payment_type, payment_number, transaction_id, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$orderNumber, $userId, $name, $phone, $address, $deliveryArea, $deliveryCharge, $subtotal, $total, $paymentMethod, $onlinePaymentType, $paymentNumber, $transactionId, $paymentStatus]);
        $orderId = $pdo->lastInsertId();
        
        // Add order items
        // Add order items
        foreach ($cartItems as $item) {
            if ($item['section'] === 'wholesale') {
                $price = $item['wholesale_price'];
            } elseif ($item['section'] === 'affiliate') {
                $price = $item['affiliate_sale_price'] ?: $item['affiliate_regular_price'];
            } else {
                $price = $item['retail_sale_price'] ?: $item['retail_regular_price'];
            }
            $itemTotal = $price * $item['quantity'];
            
            // Inject section into variation to avoid ID collision and identify product type
            $variationData = json_decode($item['variation'], true) ?: [];
            $variationData['section'] = $item['section'];
            $variationJson = json_encode($variationData);

            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, variation, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$orderId, $item['product_id'], $item['name'], $variationJson, $item['quantity'], $price, $itemTotal]);
        }
        
        // Clear cart
        if ($userId) {
            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
        } else {
            $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([$sessionId]);
        }
        
        // Add notification
        addNotification('order', 'নতুন অর্ডার!', $name . ' একটি অর্ডার দিয়েছেন - ' . formatPrice($total), adminUrl('orders/view/' . $orderId));
        
        // Store order info for success page
        $_SESSION['last_order'] = [
            'order_number' => $orderNumber,
            'total' => $total
        ];
        
        redirect(baseUrl('order-success'));
    }
}

include 'includes/header.php';
?>

<style>
    .checkout-page {
        padding: 40px 0;
    }
    
    .checkout-form {
        background: #fff;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .checkout-form h5 {
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .delivery-options {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .delivery-option {
        display: none;
    }
    
    .delivery-option + label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 20px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .delivery-option:checked + label {
        border-color: var(--primary-color);
        background: #f8f9fa;
    }
    
    .delivery-option + label .delivery-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .delivery-option + label .delivery-info i {
        font-size: 1.5rem;
        color: var(--primary-color);
    }
    
    .delivery-option + label .delivery-price {
        font-weight: 600;
    }
    
    .order-summary {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .order-summary h5 {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .order-item {
        display: flex;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .order-item img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .order-item-info {
        flex: 1;
    }
    
    .order-item-info h6 {
        margin: 0 0 5px;
        font-size: 0.9rem;
    }
    
    .order-item-info small {
        color: var(--text-light);
    }
    
    .order-totals {
        padding-top: 15px;
    }
    
    .order-total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    
    .order-total-row.final {
        font-size: 1.3rem;
        font-weight: 700;
        padding-top: 15px;
        border-top: 2px solid var(--border-color);
        margin-top: 10px;
    }
    
    .payment-method {
        background: var(--bg-light);
        padding: 15px 20px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 20px;
    }
    
    .payment-method i {
        font-size: 1.5rem;
        color: var(--primary-color);
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
        .checkout-page h1 {
            display: none;
        }
        .checkout-form h5 {
            display: none;
        }
        .form-label {
            display: none;
        }
        .checkout-form {
            padding: 15px;
        }
        .delivery-option + label {
            padding: 8px 12px;
        }
        .delivery-option + label .delivery-info i {
            font-size: 1.1rem;
        }
        .delivery-option + label .delivery-info strong {
            font-size: 0.9rem;
        }
        .delivery-option + label .delivery-info .text-muted {
            display: none !important;
        }
        .delivery-price {
            font-size: 0.9rem;
        }

        .payment-incentive-banner {
            padding: 8px 12px !important;
            gap: 8px !important;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2) !important;
        }
        .payment-incentive-banner i { font-size: 1.2rem !important; }
        .payment-incentive-banner .incentive-text strong { 
            font-size: 0.85rem !important; 
            margin-bottom: 0 !important;
            display: inline-block !important;
        }
        .payment-incentive-banner .incentive-text small { 
            font-size: 0.75rem !important;
            display: inline-block !important;
            margin-left: 5px;
        }

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
        .order-item-info small {
            font-size: 0.75rem;
        }
        .order-item strong {
            font-size: 0.85rem;
        }
        .order-total-row span {
            font-size: 0.85rem;
        }
        .order-total-row.final {
            font-size: 1.1rem;
        }
    }
</style>

<?php showFlash(); ?>

<div class="checkout-page">
    <div class="container">
        <h1 class="mb-4">চেকআউট</h1>
        
        <form method="POST">
            <div class="row row-mobile-order">
                <div class="col-lg-7 mb-4 mobile-billing-delivery">
                    <div class="mobile-instruction d-none">
                        <i class="bi bi-info-circle me-1"></i>অর্ডার কনফার্ম করতে সকল ডিটেইলস পূরন করুন
                    </div>
                    <div class="checkout-form">
                        <h5><i class="bi bi-person me-2"></i>বিলিং তথ্য</h5>
                        
                        <?php if (!empty($savedAddresses)): ?>
                        <!-- Saved Addresses -->
                        <div class="mb-4">
                            <label class="form-label">সংরক্ষিত ঠিকানা থেকে বাছাই করুন</label>
                            <div class="saved-addresses">
                                <?php foreach ($savedAddresses as $addr): ?>
                                <div class="form-check saved-address-option mb-2">
                                    <input class="form-check-input" type="radio" name="saved_address" 
                                           id="addr_<?php echo $addr['id']; ?>" 
                                           value="<?php echo $addr['id']; ?>"
                                           data-name="<?php echo sanitize($addr['recipient_name']); ?>"
                                           data-phone="<?php echo sanitize($addr['phone']); ?>"
                                           data-address="<?php echo sanitize($addr['address']); ?>"
                                           data-area="<?php echo $addr['area']; ?>"
                                           <?php echo $addr['is_default'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="addr_<?php echo $addr['id']; ?>">
                                        <div class="p-2 border rounded <?php echo $addr['is_default'] ? 'border-dark' : ''; ?>">
                                            <div class="d-flex justify-content-between">
                                                <strong><?php echo $addr['recipient_name']; ?></strong>
                                                <span class="badge <?php echo $addr['address_type'] === 'home' ? 'bg-primary' : 'bg-secondary'; ?> small">
                                                    <?php echo $addr['address_type'] === 'home' ? 'বাড়ি' : ($addr['label'] ?: 'কাস্টম'); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted"><?php echo $addr['phone']; ?> | <?php echo mb_substr($addr['address'], 0, 50); ?>...</small>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <div class="form-check saved-address-option mb-2">
                                    <input class="form-check-input" type="radio" name="saved_address" id="addr_new" value="new">
                                    <label class="form-check-label" for="addr_new">
                                        <div class="p-2 border rounded">
                                            <i class="bi bi-plus-circle me-1"></i>নতুন ঠিকানা লিখুন
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div id="manualAddressForm" <?php echo !empty($savedAddresses) ? 'style="display:none;"' : ''; ?>>
                            <div class="mb-3">
                                <label class="form-label">আপনার নাম *</label>
                                <div class="input-with-icon">
                                    <div class="icon-box"><i class="bi bi-person-fill"></i></div>
                                    <input type="text" name="name" id="inputName" class="form-control" placeholder="আপনার নাম" value="<?php echo isset($_SESSION['user_name']) ? sanitize($_SESSION['user_name']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ফোন নম্বর *</label>
                                <div class="input-with-icon">
                                    <div class="icon-box"><i class="bi bi-telephone-fill"></i></div>
                                    <input type="tel" name="phone" id="inputPhone" class="form-control" placeholder="ফোন নাম্বার">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">সম্পূর্ণ ঠিকানা *</label>
                                <div class="input-with-icon">
                                    <div class="icon-box"><i class="bi bi-geo-alt-fill"></i></div>
                                    <textarea name="address" id="inputAddress" class="form-control" rows="1" placeholder="এড্রেস (থানা+জেলা) লিখুন"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <h5><i class="bi bi-truck me-2"></i>ডেলিভারি অপশন</h5>
                        
                        <?php if ($hasCustomDelivery): ?>
                        <!-- Custom Delivery -->
                        <div class="delivery-options">
                            <input type="radio" name="delivery_area" id="customDelivery" value="custom" class="delivery-option" checked>
                            <label for="customDelivery">
                                <div class="delivery-info">
                                    <i class="bi bi-truck"></i>
                                    <div>
                                        <strong><?php echo sanitize($customDeliveryName); ?></strong>
                                        <br><small class="text-muted">এই পণ্যের জন্য বিশেষ ডেলিভারি</small>
                                    </div>
                                </div>
                                <span class="delivery-price"><?php echo $customDeliveryCharge > 0 ? formatPrice($customDeliveryCharge) : 'ফ্রী'; ?></span>
                            </label>
                        </div>
                        <?php elseif ($isFreeDelivery): ?>
                        <!-- Free Delivery -->
                        <div class="delivery-options">
                            <input type="radio" name="delivery_area" id="freeDelivery" value="free" class="delivery-option" checked>
                            <label for="freeDelivery">
                                <div class="delivery-info">
                                    <i class="bi bi-gift"></i>
                                    <div>
                                        <strong>ফ্রী ডেলিভারি</strong>
                                        <br><small class="text-muted">সারা বাংলাদেশে বিনামূল্যে ডেলিভারি</small>
                                    </div>
                                </div>
                                <span class="delivery-price text-success">ফ্রী</span>
                            </label>
                        </div>
                        <?php else: ?>
                        <!-- Paid Delivery Options -->
                        <div class="delivery-options">
                            <input type="radio" name="delivery_area" id="insideDhaka" value="inside_dhaka" class="delivery-option">
                            <label for="insideDhaka">
                                <div class="delivery-info">
                                    <i class="bi bi-geo-alt"></i>
                                    <div>
                                        <strong><?php echo $insideDhakaLabel; ?></strong>
                                        <br><small class="text-muted">২-৩ দিনের মধ্যে ডেলিভারি</small>
                                    </div>
                                </div>
                                <span class="delivery-price"><?php echo formatPrice($insideDhakaCharge); ?></span>
                            </label>
                            
                            <input type="radio" name="delivery_area" id="outsideDhaka" value="outside_dhaka" class="delivery-option" checked>
                            <label for="outsideDhaka">
                                <div class="delivery-info">
                                    <i class="bi bi-geo"></i>
                                    <div>
                                        <strong><?php echo $outsideDhakaLabel; ?></strong>
                                        <br><small class="text-muted">৩-৫ দিনের মধ্যে ডেলিভারি</small>
                                    </div>
                                </div>
                                <span class="delivery-price"><?php echo formatPrice($outsideDhakaCharge); ?></span>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-5 mobile-order-summary">
                    <div class="order-summary">
                        <h5>আপনার অর্ডার</h5>
                        
                        <?php foreach ($cartItems as $item): ?>
                        <?php 
                        if ($item['section'] === 'wholesale') {
                            $price = $item['wholesale_price'];
                        } elseif ($item['section'] === 'affiliate') {
                            $price = $item['affiliate_sale_price'] ?: $item['affiliate_regular_price'];
                        } else {
                            $price = $item['retail_sale_price'] ?: $item['retail_regular_price'];
                        }
                        ?>
                        <div class="order-item">
                            <img src="<?php echo imageUrl($item['image']); ?>" alt="">
                            <div class="order-item-info">
                                <h6><?php echo sanitize($item['name']); ?></h6>
                                <small><?php echo $item['quantity']; ?> x <?php echo formatPrice($price); ?></small>
                                <?php if ($item['section'] === 'wholesale'): ?>
                                <span class="badge bg-secondary p-1" style="font-size: 0.6rem;">পাইকারি</span>
                                <?php endif; ?>
                            </div>
                            <strong><?php echo formatPrice($price * $item['quantity']); ?></strong>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="order-totals">
                            <div class="order-total-row">
                                <span>সাবটোটাল</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            <div class="order-total-row">
                                <span>ডেলিভারি চার্জ</span>
                                <?php 
                                $initialDelivery = $hasCustomDelivery ? $customDeliveryCharge : ($isFreeDelivery ? 0 : $insideDhakaCharge);
                                ?>
                                <span id="deliveryCharge"><?php echo $initialDelivery > 0 ? formatPrice($initialDelivery) : 'ফ্রী'; ?></span>
                            </div>
                            <div class="order-total-row final">
                                <span>সর্বমোট</span>
                                <span id="grandTotal"><?php echo formatPrice($subtotal + $initialDelivery); ?></span>
                            </div>
                        </div>
                        
                        <div class="d-none d-lg-block">
                            <button type="submit" class="btn btn-primary-custom w-100 mt-4" style="padding: 15px; font-size: 1.1rem;">
                                <i class="bi bi-check-circle me-2"></i>অর্ডার কনফার্ম করুন
                            </button>
                            
                            <p class="text-center text-muted mt-3 mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-shield-check me-1"></i>আপনার তথ্য সম্পূর্ণ নিরাপদ
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 mobile-payment">
                    <div class="checkout-form">
                        <?php 
                        $sectionType = 'retail';
                        include 'includes/payment-method.php';
                        ?>
                    </div>
                    
                    <div class="d-block d-lg-none mt-3">
                        <button type="submit" class="btn btn-primary-custom w-100" style="padding: 15px; font-size: 1.1rem;">
                            <i class="bi bi-check-circle me-2"></i>অর্ডার কনফার্ম করুন
                        </button>
                        <p class="text-center text-muted mt-3 mb-0" style="font-size: 0.8rem;">
                            <i class="bi bi-shield-check me-1"></i>আপনার তথ্য সম্পূর্ণ নিরাপদ
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const subtotal = <?php echo $subtotal; ?>;
    const insideDhaka = <?php echo $insideDhakaCharge; ?>;
    const outsideDhaka = <?php echo $outsideDhakaCharge; ?>;
    const hasCustomDelivery = <?php echo $hasCustomDelivery ? 'true' : 'false'; ?>;
    const customDeliveryCharge = <?php echo $customDeliveryCharge; ?>;
    const isFreeDelivery = <?php echo $isFreeDelivery ? 'true' : 'false'; ?>;
    
    // Handle saved address selection
    document.querySelectorAll('input[name="saved_address"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const manualForm = document.getElementById('manualAddressForm');
            if (this.value === 'new') {
                manualForm.style.display = 'block';
                document.getElementById('inputName').required = true;
                document.getElementById('inputPhone').required = true;
                document.getElementById('inputAddress').required = true;
            } else {
                manualForm.style.display = 'none';
                document.getElementById('inputName').required = false;
                document.getElementById('inputPhone').required = false;
                document.getElementById('inputAddress').required = false;
                // Fill hidden fields with saved address data
                document.getElementById('inputName').value = this.dataset.name;
                document.getElementById('inputPhone').value = this.dataset.phone;
                document.getElementById('inputAddress').value = this.dataset.address;
                // Update delivery area if available
                const area = this.dataset.area;
                if (area && !hasCustomDelivery && !isFreeDelivery) {
                    const areaRadio = document.querySelector(`input[name="delivery_area"][value="${area}"]`);
                    if (areaRadio) {
                        areaRadio.checked = true;
                        areaRadio.dispatchEvent(new Event('change'));
                    }
                }
            }
        });
    });
    
    // Trigger initial selection
    const defaultAddr = document.querySelector('input[name="saved_address"]:checked');
    if (defaultAddr && defaultAddr.value !== 'new') {
        document.getElementById('inputName').value = defaultAddr.dataset.name;
        document.getElementById('inputPhone').value = defaultAddr.dataset.phone;
        document.getElementById('inputAddress').value = defaultAddr.dataset.address;
    }
    
    document.querySelectorAll('.delivery-option').forEach(option => {
        option.addEventListener('change', function() {
            let charge = 0;
            if (this.value === 'custom') {
                charge = customDeliveryCharge;
            } else if (this.value === 'free') {
                charge = 0;
            } else if (this.value === 'inside_dhaka') {
                charge = insideDhaka;
            } else {
                charge = outsideDhaka;
            }
            document.getElementById('deliveryCharge').textContent = charge > 0 ? '৳' + charge.toLocaleString() : 'ফ্রী';
            document.getElementById('grandTotal').textContent = '৳' + (subtotal + charge).toLocaleString();
        });
    });
    
    // DataLayer - Begin Checkout
    pushDataLayer('begin_checkout', {
        currency: 'BDT',
        value: <?php echo $subtotal; ?>,
        items: [
            <?php foreach ($cartItems as $item): ?>
            <?php 
                $price = ($item['section'] === 'wholesale') ? $item['wholesale_price'] : ($item['retail_sale_price'] ?: $item['retail_regular_price']);
                $price = $price ?: 0;
            ?>
            {
                item_id: '<?php echo $item['product_id']; ?>',
                item_name: '<?php echo addslashes($item['name']); ?>',
                price: <?php echo $price; ?>,
                quantity: <?php echo $item['quantity']; ?>
            },
            <?php endforeach; ?>
        ]
    });
    
    // Facebook Pixel - InitiateCheckout
    // Facebook Pixel - InitiateCheckout
    <?php if (getSettings('facebook_pixel_id') && getSettings('facebook_pixel_active') == '1'): ?>
    if (typeof fbq === 'function') {
        fbq('track', 'InitiateCheckout', {
            value: <?php echo $subtotal; ?>,
            currency: 'BDT',
            num_items: <?php echo count($cartItems); ?>
        });
    }
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
