<?php
/**
 * Kilagbo Reseller - Place Order Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/resell-shortfall.php';

$pageTitle = 'অর্ডার করুন';
$currentPage = 'products';

$productId = intval($_GET['product'] ?? 0);
$variationsParam = $_GET['variations'] ?? '';

if (!$productId) {
    redirect(baseUrl('resell/products.php'));
}

// Minimum balance check
$stmt = $pdo->prepare("SELECT balance FROM resellers WHERE id = ?");
$stmt->execute([getResellerId()]);
$resellerRow = $stmt->fetch();
$currentBalance = $resellerRow['balance'] ?? 0;

$minBalance = (float)(getSettings('resell_min_balance') ?: 200);
if ($currentBalance < $minBalance) {
    setFlash('error', 'অর্ডার করতে হলে আপনার ব্যালেন্স নূন্যতম ' . number_format($minBalance) . ' টাকা হতে হবে।');
    redirect(baseUrl('resell/products.php'));
}

// Get product
$stmt = $pdo->prepare("SELECT * FROM resell_products WHERE id = ? AND status = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product || ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0)) {
    setFlash('error', 'পণ্যটি পাওয়া যায়নি বা স্টক নেই');
    redirect(baseUrl('resell/products.php'));
}

$resellerId = getResellerId();
$error = '';

// Check if product has custom delivery charge
$customDeliveryCharge = getResellProductDeliveryCharge($productId);
$hasCustomCharge = $customDeliveryCharge !== null;

// Get default delivery charges
$deliveryCharges = getResellDeliveryCharges();

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerPhone = sanitize($_POST['customer_phone'] ?? '');
    $customerEmail = sanitize($_POST['customer_email'] ?? '');
    $shippingAddress = sanitize($_POST['shipping_address'] ?? '');
    $deliveryChargeId = intval($_POST['delivery_charge_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $resellerPriceRaw = $_POST['reseller_price'] ?? null;
    $resellerPrice = ($resellerPriceRaw === null || trim((string)$resellerPriceRaw) === '') ? 0.0 : floatval($resellerPriceRaw);
    $notes = sanitize($_POST['notes'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
    $variations = sanitize($_POST['variations'] ?? '');

    // Online payment details
    $onlinePaymentType = sanitize($_POST['online_payment_type'] ?? '');
    $paymentNumber = sanitize($_POST['payment_number'] ?? '');
    $transactionId = sanitize($_POST['transaction_id'] ?? '');

    // NOTE 08: zero selling price is valid. Only negative values are invalid.
    if ($paymentMethod === 'online' && (empty($paymentNumber) || empty($transactionId))) {
        $error = 'অনলাইন পেমেন্টের জন্য পেমেন্ট নম্বর এবং Transaction ID আবশ্যক';
    } elseif (empty($customerName) || empty($customerPhone) || empty($shippingAddress)) {
        $error = 'সব প্রয়োজনীয় ফিল্ড পূরণ করুন';
    } elseif ($resellerPriceRaw === null || trim((string)$resellerPriceRaw) === '') {
        $error = 'আপনার বিক্রয় মূল্য দিন';
    } elseif ($resellerPrice < 0) {
        $error = 'বিক্রয় মূল্য ঋণাত্মক হতে পারবে না';
    } elseif ($product['stock_quantity'] !== null && $quantity > $product['stock_quantity']) {
        $error = 'স্টকে এত পণ্য নেই। স্টক: ' . $product['stock_quantity'];
    } else {
        try {
            $pdo->beginTransaction();

            // Lock reseller balance so a below-cost order cannot race another deduction.
            $balanceStmt = $pdo->prepare("SELECT balance FROM resellers WHERE id = ? FOR UPDATE");
            $balanceStmt->execute([$resellerId]);
            $lockedBalance = (float)($balanceStmt->fetchColumn() ?: 0);
            if ($lockedBalance < $minBalance) {
                throw new Exception('অর্ডার করতে হলে আপনার ব্যালেন্স নূন্যতম ' . formatPrice($minBalance) . ' টাকা হতে হবে।');
            }

            // Lock and re-read the product price/stock before calculating the order.
            $productStmt = $pdo->prepare("SELECT * FROM resell_products WHERE id = ? AND status = 1 FOR UPDATE");
            $productStmt->execute([$productId]);
            $lockedProduct = $productStmt->fetch();
            if (!$lockedProduct) {
                throw new Exception('পণ্যটি আর উপলব্ধ নেই');
            }
            if ($lockedProduct['stock_quantity'] !== null && $quantity > (int)$lockedProduct['stock_quantity']) {
                throw new Exception('স্টকে এত পণ্য নেই। স্টক: ' . $lockedProduct['stock_quantity']);
            }

            // Delivery charge - current custom charge or selected active area.
            $lockedCustomDeliveryCharge = getResellProductDeliveryCharge($productId);
            if ($lockedCustomDeliveryCharge !== null) {
                $deliveryCharge = (float)$lockedCustomDeliveryCharge;
                $shippingArea = 'custom';
            } else {
                $deliveryCharge = (float)getResellDeliveryCharge($deliveryChargeId);
                $dcStmt = $pdo->prepare("SELECT name FROM resell_delivery_charges WHERE id = ? AND status = 1");
                $dcStmt->execute([$deliveryChargeId]);
                $dcInfo = $dcStmt->fetch();
                if (!$dcInfo) {
                    throw new Exception('ডেলিভারি চার্জ নির্বাচন করুন');
                }
                $shippingArea = $dcInfo['name'];
            }

            $originalPrice = (float)$lockedProduct['reseller_price'] * $quantity;
            // NOTE17: the submitted value is the TOTAL selling price for all pieces.
            $totalResellerPrice = round($resellerPrice, 2);
            // Legacy resell_order_items.reseller_price represents a per-piece price.
            $unitResellerPrice = round($totalResellerPrice / $quantity, 2);
            $totalCost = $originalPrice + $deliveryCharge;

            // Apply existing online payment discount before final profit/shortfall.
            $onlinePaymentDiscount = 0;
            if ($paymentMethod === 'online') {
                $discountType = getSettings('online_payment_discount_type_resell') ?? 'fixed';
                $discountValue = floatval(getSettings('online_payment_discount_value_resell') ?? 0);

                if ($discountValue > 0) {
                    if ($discountType === 'percentage') {
                        $onlinePaymentDiscount = ($totalCost * $discountValue) / 100;
                    } else {
                        $onlinePaymentDiscount = $discountValue;
                    }
                    $onlinePaymentDiscount = min($totalCost, max(0, $onlinePaymentDiscount));
                    $totalCost -= $onlinePaymentDiscount;
                }
            }

            // NOTE16: item selling price excludes delivery. The customer's total bill
            // includes it once, so delivery is not removed from reseller profit.
            $customerBill = $totalResellerPrice + $deliveryCharge;
            $profit = round($customerBill - $totalCost, 2);
            $shortfall = round(max(0, $totalCost - $customerBill), 2);
            if ($shortfall > $lockedBalance) {
                throw new Exception('এই কম/শূন্য বিক্রয় মূল্যের জন্য ব্যালেন্সে পর্যাপ্ত টাকা নেই। ঘাটতি: ' . formatPrice($shortfall) . ', বর্তমান ব্যালেন্স: ' . formatPrice($lockedBalance));
            }

            // Generate order number
            $orderNumber = 'RS' . date('Ymd') . strtoupper(substr(uniqid(), -4));
            $paymentStatus = $paymentMethod === 'online' ? 'pending_verification' : 'pending';

            // Ensure variations is valid JSON or null
            $variationsJson = null;
            if (!empty($variations)) {
                $decoded = json_decode($variations);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $variationsJson = $variations;
                } else {
                    $variationsJson = json_encode($variations);
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO resell_orders
                (order_number, reseller_id, customer_name, customer_phone, customer_email, shipping_address, shipping_area,
                 original_price, delivery_charge, reseller_price, profit, shortfall_amount, shortfall_deducted, shortfall_refunded,
                 notes, payment_method, payment_type, payment_number, transaction_id, payment_status,
                 pricing_version, checkout_discount)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderNumber,
                $resellerId,
                $customerName,
                $customerPhone,
                $customerEmail,
                $shippingAddress,
                $shippingArea,
                $originalPrice,
                $deliveryCharge,
                $totalResellerPrice,
                $profit,
                $shortfall,
                $shortfall > 0 ? 1 : 0,
                0,
                $notes,
                $paymentMethod,
                $onlinePaymentType,
                $paymentNumber,
                $transactionId,
                $paymentStatus,
                2,
                $onlinePaymentDiscount
            ]);
            $orderId = (int)$pdo->lastInsertId();

            if ($shortfall > 0) {
                resellDeductOrderShortfall($pdo, [
                    'id' => $orderId,
                    'reseller_id' => $resellerId,
                    'order_number' => $orderNumber,
                ], $shortfall, 'order');
            }

            // Add order item
            $stmt = $pdo->prepare("
                INSERT INTO resell_order_items
                (order_id, resell_product_id, product_name, product_image, variation, quantity, original_price, reseller_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $lockedProduct['id'],
                $lockedProduct['name'],
                $lockedProduct['image'],
                $variationsJson,
                $quantity,
                $lockedProduct['reseller_price'],
                $unitResellerPrice
            ]);

            $pdo->prepare("UPDATE resellers SET total_orders = total_orders + 1 WHERE id = ?")->execute([$resellerId]);

            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_type, type, title, link, user_id, is_read) VALUES ('admin', 'resell_order', ?, ?, NULL, 0)");
            $notifStmt->execute(["নতুন রিসেল অর্ডার #$orderNumber", "resell-orders"]);

            $pdo->commit();
            setFlash('success', "অর্ডার সফল! অর্ডার নম্বর: $orderNumber" . ($shortfall > 0 ? ' | ব্যালেন্স থেকে কর্তন: ' . formatPrice($shortfall) : ''));

            if (headers_sent()) {
                echo "<script>window.location.href='" . baseUrl('resell/orders.php') . "';</script>";
                exit;
            }
            redirect(baseUrl('resell/orders.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "অর্ডার প্লেস করার সময় সমস্যা হয়েছে: " . $e->getMessage();
            error_log("Order Error: " . $e->getMessage());
        }
    }
}

// Get first delivery charge for initial display
$firstDeliveryCharge = !empty($deliveryCharges) ? $deliveryCharges[0]['charge'] : 0;
$initialDeliveryCharge = $hasCustomCharge ? $customDeliveryCharge : $firstDeliveryCharge;

include __DIR__ . '/includes/header.php';
?>

<style>
    .order-page {
        max-width: 900px;
        margin: 0 auto;
    }

    .order-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }

    .order-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .product-preview {
        display: flex;
        gap: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .product-preview img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 10px;
    }

    .product-preview .info h6 {
        font-weight: 600;
        margin-bottom: 10px;
    }

    .product-preview .info .price {
        font-size: 1.2rem;
        color: var(--primary);
        font-weight: 700;
    }

    .form-label {
        font-weight: 500;
    }

    .price-calculator {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 20px;
        color: #fff;
    }

    .price-calculator h5 {
        border-bottom-color: rgba(255,255,255,0.2);
        color: #fff;
    }

    .calc-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .calc-row:last-child {
        border-bottom: none;
    }

    .calc-row.profit {
        font-size: 1.2rem;
        font-weight: 700;
        padding-top: 15px;
        margin-top: 10px;
        border-top: 2px solid rgba(255,255,255,0.3);
    }

    .profit-positive { color: #2ecc71; }
    .profit-negative { color: #e74c3c; }

    .btn-submit {
        width: 100%;
        padding: 15px;
        font-size: 1.1rem;
        font-weight: 600;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        border-radius: 10px;
        color: #fff;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        color: #fff;
    }

    /* NOTE17 UX revision: scoped visual polish; no change to pricing formulas. */
    .order-card {
        border: 1px solid #e9edf5;
        border-radius: 18px;
        box-shadow: 0 10px 32px rgba(26, 38, 74, .055);
        padding: 26px;
    }
    .order-card h5 { font-size: 1.16rem; letter-spacing: -.01em; }
    .product-preview {
        align-items: center;
        gap: 16px;
        background: #f7f9fe;
        padding: 18px;
        border: 1px solid #edf1f8;
        border-radius: 14px;
    }
    .product-preview img { width: 100px; height: 100px; object-fit: contain; background:#fff; }
    .order-page .form-label { font-weight: 600; margin-bottom: 8px; }
    .order-page .form-control, .order-page .form-select {
        min-height: 45px;
        border-radius: 10px;
        border-color: #d7e0ed;
    }
    .order-page .form-control:focus, .order-page .form-select:focus {
        border-color: #677bea;
        box-shadow: 0 0 0 .2rem rgba(102,126,234,.12);
    }
    .order-page textarea.form-control { min-height: 78px; }
    .price-calculator { border: 0; border-radius: 18px; padding: 26px; }
    .price-calculator .calc-row { gap: 15px; align-items: baseline; }
    .price-calculator .calc-row span:last-child {
        margin-left: auto;
        white-space: nowrap;
        text-align: right;
        font-weight: 600;
    }
    .price-calculator .calc-row span:first-child { min-width: 0; }
    .price-calculator .calc-row.profit { align-items: center; }
    .btn-submit { border-radius: 12px; min-height: 54px; }
    @media (max-width: 767.98px) {
        .order-page { padding: 0 10px 32px; }
        .order-card, .price-calculator { padding: 18px; border-radius: 16px; }
        .product-preview { padding: 12px; gap: 12px; }
        .product-preview img { width: 78px; height: 78px; flex-shrink: 0; }
        .product-preview .info { min-width: 0; }
        .product-preview .info h6 { overflow-wrap: anywhere; }
        .order-page .row.g-3 > .col-md-6 { min-width: 0; }
        .price-calculator .calc-row { font-size: .93rem; }
        .price-calculator .calc-row.profit { font-size: 1.08rem; }
        .order-page .btn-submit { min-height: 52px; }
    }

</style>

<div class="order-page">
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="orderForm">
        <input type="hidden" name="variations" value="<?php echo htmlspecialchars($variationsParam); ?>">
        <div class="row">
            <div class="col-lg-7">
                <div class="order-card">
                    <h5><i class="bi bi-box-seam me-2"></i>পণ্য তথ্য</h5>

                    <div class="product-preview">
                        <img src="<?php echo imageUrl($product['image']); ?>" alt="">
                        <div class="info">
                            <h6><?php echo sanitize($product['name']); ?></h6>
                            <p class="price"><?php echo formatPrice($product['reseller_price']); ?> / পিস</p>
                            <?php if (!empty($variationsParam)): ?>
                            <div class="mb-1">
                                <?php
                                $vars = explode(',', $variationsParam);
                                foreach($vars as $var) {
                                    $parts = explode(':', $var);
                                    if(count($parts) == 2) {
                                        echo '<span class="badge bg-light text-dark border me-1">'.htmlspecialchars(urldecode($parts[0])).': '.htmlspecialchars(urldecode($parts[1])).'</span>';
                                    }
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            <small class="text-muted">স্টক: <?php echo $product['stock_quantity'] !== null ? $product['stock_quantity'] . ' পিস' : 'আনলিমিটেড'; ?></small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">পরিমাণ *</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" value="1" min="1" <?php echo $product['stock_quantity'] !== null ? 'max="' . $product['stock_quantity'] . '"' : ''; ?> required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">সব পিসের মোট বিক্রয় মূল্য *</label>
                            <input type="number" class="form-control" name="reseller_price" id="resellerPrice" autocomplete="off" placeholder="মোট বিক্রয়মূল্য" value="<?php echo htmlspecialchars((string)($_POST['reseller_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" min="0" step="0.01" required>
                        </div>
                    </div>
                </div>

                <div class="order-card">
                    <h5><i class="bi bi-person me-2"></i>কাস্টমার তথ্য</h5>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">কাস্টমারের নাম *</label>
                            <input type="text" class="form-control" name="customer_name" required value="<?php echo $_POST['customer_name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">মোবাইল নম্বর *</label>
                            <input type="tel" class="form-control" name="customer_phone" required value="<?php echo $_POST['customer_phone'] ?? ''; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">ইমেইল (ঐচ্ছিক)</label>
                            <input type="email" class="form-control" name="customer_email" value="<?php echo $_POST['customer_email'] ?? ''; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">ডেলিভারি ঠিকানা *</label>
                            <textarea class="form-control" name="shipping_address" rows="2" required><?php echo $_POST['shipping_address'] ?? ''; ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ডেলিভারি চার্জ *</label>
                            <?php if ($hasCustomCharge): ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                এই পণ্যের জন্য নির্ধারিত ডেলিভারি চার্জ: <strong><?php echo formatPrice($customDeliveryCharge); ?></strong>
                            </div>
                            <input type="hidden" name="delivery_charge_id" value="0">
                            <?php elseif (!empty($deliveryCharges)): ?>
                            <select class="form-select" name="delivery_charge_id" id="deliveryChargeSelect" required>
                                <?php foreach ($deliveryCharges as $dc): ?>
                                <option value="<?php echo $dc['id']; ?>" data-charge="<?php echo $dc['charge']; ?>">
                                    <?php echo sanitize($dc['name']); ?> (<?php echo formatPrice($dc['charge']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                কোনো ডেলিভারি চার্জ সেট করা নেই। অ্যাডমিনের সাথে যোগাযোগ করুন।
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label">নোট (ঐচ্ছিক)</label>
                            <textarea class="form-control" name="notes" rows="2"><?php echo $_POST['notes'] ?? ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="order-card">
                    <?php
                    $sectionType = 'resell';
                    include __DIR__ . '/../includes/payment-method.php';
                    ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="order-card price-calculator">
                    <h5><i class="bi bi-calculator me-2"></i>হিসাব</h5>

                    <div class="calc-row">
                        <span>পণ্য মূল্য:</span>
                        <span id="productTotal"><?php echo formatPrice($product['reseller_price']); ?></span>
                    </div>
                    <div class="calc-row">
                        <span>ডেলিভারি চার্জ:</span>
                        <span id="deliveryCharge"><?php echo formatPrice($initialDeliveryCharge); ?></span>
                    </div>
                    <div class="calc-row">
                        <span>মোট খরচ:</span>
                        <span id="totalCost"><?php echo formatPrice($product['reseller_price'] + $initialDeliveryCharge); ?></span>
                    </div>
                    <div class="calc-row">
                        <span>কাস্টমারের মোট বিক্রয় মূল্য:</span>
                        <span id="resellerTotal">—</span>
                    </div>
                    <div class="calc-row">
                        <span>ব্যালেন্স থেকে কর্তন:</span>
                        <span id="shortfallAmount">—</span>
                    </div>
                    <div class="calc-row profit">
                        <span>আপনার প্রফিট:</span>
                        <span id="profitAmount">—</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-submit mt-3">
                    <i class="bi bi-check-circle me-2"></i>অর্ডার কনফার্ম করুন
                </button>

                <a href="<?php echo baseUrl('resell/products.php'); ?>" class="btn btn-outline-secondary w-100 mt-2">
                    <i class="bi bi-arrow-left me-2"></i>পণ্যে ফিরুন
                </a>
            </div>
        </div>
    </form>
</div>

<script>
const note16DiscountType = <?php echo json_encode(getSettings('online_payment_discount_type_resell') ?? 'fixed'); ?>;
const note16DiscountValue = <?php echo json_encode((float)(getSettings('online_payment_discount_value_resell') ?? 0)); ?>;
function note16EffectiveCost(cost) {
    if (document.querySelector('input[name="payment_method"]:checked')?.value !== 'online') return cost;
    const raw = note16DiscountType === 'percentage' ? cost * note16DiscountValue / 100 : note16DiscountValue;
    return cost - Math.min(cost, Math.max(0, raw));
}
const productPrice = <?php echo $product['reseller_price']; ?>;
const hasCustomCharge = <?php echo $hasCustomCharge ? 'true' : 'false'; ?>;
const customDeliveryCharge = <?php echo $hasCustomCharge ? $customDeliveryCharge : 0; ?>;

function formatPrice(price) {
    return '৳' + price.toLocaleString('bn-BD');
}

function getSelectedDeliveryCharge() {
    if (hasCustomCharge) {
        return customDeliveryCharge;
    }

    const select = document.getElementById('deliveryChargeSelect');
    if (select) {
        const selectedOption = select.options[select.selectedIndex];
        return parseFloat(selectedOption.dataset.charge) || 0;
    }
    return 0;
}

function calculate() {
    const quantity = parseInt(document.getElementById('quantity').value) || 1;
    const resellerPrice = parseFloat(document.getElementById('resellerPrice').value) || 0;
    const deliveryCharge = getSelectedDeliveryCharge();

    const productTotal = productPrice * quantity;
    const totalCost = note16EffectiveCost(productTotal + deliveryCharge);
    const resellerTotal = resellerPrice; // NOTE17: total for all pieces, not per piece
    const customerBill = resellerTotal + deliveryCharge;
    const profit = customerBill - totalCost;
    const shortfall = Math.max(0, totalCost - customerBill);

    document.getElementById('productTotal').textContent = formatPrice(productTotal);
    document.getElementById('deliveryCharge').textContent = formatPrice(deliveryCharge);
    document.getElementById('totalCost').textContent = formatPrice(totalCost);
    const priceInput = document.getElementById('resellerPrice').value.trim();
    const isValid = priceInput !== '' && Number.isFinite(Number(priceInput)) && Number(priceInput) >= 0;
    document.getElementById('resellerTotal').textContent = isValid ? formatPrice(customerBill) : '—';
    document.getElementById('shortfallAmount').textContent = isValid ? formatPrice(shortfall) : '—';
    document.getElementById('profitAmount').textContent = isValid ? formatPrice(profit) : '—';
    document.getElementById('profitAmount').className = isValid ? (profit >= 0 ? 'profit-positive' : 'profit-negative') : '';
}

document.getElementById('quantity').addEventListener('input', calculate);
document.getElementById('resellerPrice').addEventListener('input', calculate);
document.querySelectorAll('input[name="payment_method"]').forEach(input => input.addEventListener('change', calculate));

// Add listener for delivery charge select if it exists
const deliverySelect = document.getElementById('deliveryChargeSelect');
if (deliverySelect) {
    deliverySelect.addEventListener('change', calculate);
}

// Initial calculation
calculate();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
