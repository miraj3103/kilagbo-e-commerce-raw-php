<?php
/**
 * Kilagbo Reseller - Cart Checkout
 * NOTE 05
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/resell-shortfall.php';

$pageTitle = 'কার্ট চেকআউট';
$currentPage = 'cart';
$reseller = requireResellerLogin();
$resellerId = (int)$reseller['id'];
$error = '';

function loadResellerCartCheckout(PDO $pdo, int $resellerId): array {
    $stmt = $pdo->prepare("\n        SELECT rc.*, rp.name, rp.image, rp.reseller_price, rp.suggested_selling_price, rp.stock_quantity, rp.status,\n               rpd.custom_charge\n        FROM resell_cart rc\n        JOIN resell_products rp ON rp.id = rc.resell_product_id\n        LEFT JOIN resell_product_delivery rpd ON rpd.product_id = rp.id\n        WHERE rc.reseller_id = ?\n        ORDER BY rc.created_at ASC\n    ");
    $stmt->execute([$resellerId]);
    return $stmt->fetchAll();
}

$cartItems = loadResellerCartCheckout($pdo, $resellerId);
if (!$cartItems) {
    setFlash('error', 'চেকআউট করার জন্য কার্টে পণ্য নেই');
    redirect(baseUrl('resell/cart.php'));
}

$deliveryCharges = getResellDeliveryCharges();
$customCharges = [];
foreach ($cartItems as $item) {
    if ($item['custom_charge'] !== null) {
        $customCharges[] = (float)$item['custom_charge'];
    }
}
$forcedCustomCharge = $customCharges ? max($customCharges) : null;

// Existing reseller minimum-balance rule remains unchanged in NOTE 05.
$stmt = $pdo->prepare("SELECT balance FROM resellers WHERE id = ?");
$stmt->execute([$resellerId]);
$currentBalance = (float)($stmt->fetchColumn() ?: 0);
$minBalance = (float)(getSettings('resell_min_balance') ?: 200);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerPhone = sanitize($_POST['customer_phone'] ?? '');
    $customerEmail = sanitize($_POST['customer_email'] ?? '');
    $shippingAddress = sanitize($_POST['shipping_address'] ?? '');
    $deliveryChargeId = (int)($_POST['delivery_charge_id'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
    $onlinePaymentType = sanitize($_POST['online_payment_type'] ?? '');
    $paymentNumber = sanitize($_POST['payment_number'] ?? '');
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    $sellingPrices = $_POST['selling_price'] ?? [];

    if ($currentBalance < $minBalance) {
        $error = 'অর্ডার করতে হলে আপনার ব্যালেন্স নূন্যতম ' . formatPrice($minBalance) . ' হতে হবে।';
    } elseif (empty($customerName) || empty($customerPhone) || empty($shippingAddress)) {
        $error = 'সব প্রয়োজনীয় কাস্টমার তথ্য পূরণ করুন';
    } elseif ($paymentMethod === 'online' && (empty($paymentNumber) || empty($transactionId))) {
        $error = 'অনলাইন পেমেন্টের জন্য পেমেন্ট নম্বর এবং Transaction ID আবশ্যক';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock reseller and all cart rows/products so checkout totals cannot change mid-request.
            $lockReseller = $pdo->prepare("SELECT balance FROM resellers WHERE id = ? FOR UPDATE");
            $lockReseller->execute([$resellerId]);
            $lockedBalance = (float)($lockReseller->fetchColumn() ?: 0);
            if ($lockedBalance < $minBalance) {
                throw new Exception('অর্ডার করতে হলে আপনার ব্যালেন্স নূন্যতম ' . formatPrice($minBalance) . ' হতে হবে।');
            }

            $cartItems = loadResellerCartCheckout($pdo, $resellerId);
            if (!$cartItems) {
                throw new Exception('কার্ট খালি');
            }

            $originalTotal = 0.0;
            $saleTotal = 0.0;
            $orderItemData = [];
            $customChargesNow = [];

            foreach ($cartItems as $item) {
                $pStmt = $pdo->prepare("SELECT id, name, image, reseller_price, suggested_selling_price, stock_quantity, status FROM resell_products WHERE id = ? FOR UPDATE");
                $pStmt->execute([(int)$item['resell_product_id']]);
                $product = $pStmt->fetch();

                if (!$product || (int)$product['status'] !== 1) {
                    throw new Exception('কার্টের একটি পণ্য আর উপলব্ধ নেই');
                }

                $quantity = max(1, (int)$item['quantity']);
                if ($product['stock_quantity'] !== null && $quantity > (int)$product['stock_quantity']) {
                    throw new Exception($product['name'] . ' - পর্যাপ্ত স্টক নেই');
                }

                if (!array_key_exists($item['id'], $sellingPrices) || trim((string)$sellingPrices[$item['id']]) === '') {
                    throw new Exception($product['name'] . ' - বিক্রয় মূল্য দিন');
                }
                if (!is_scalar($sellingPrices[$item['id']]) || !is_numeric($sellingPrices[$item['id']]) || !is_finite((float)$sellingPrices[$item['id']])) {
                    throw new Exception($product['name'] . ' - সঠিক বিক্রয় মূল্য দিন');
                }
                $sellingPrice = (float)$sellingPrices[$item['id']];
                if ($sellingPrice < 0) {
                    throw new Exception($product['name'] . ' - বিক্রয় মূল্য ঋণাত্মক হতে পারবে না');
                }

                $basePrice = (float)$product['reseller_price'];
                $originalTotal += $basePrice * $quantity;
                $saleTotal += $sellingPrice * $quantity;

                $cStmt = $pdo->prepare("SELECT custom_charge FROM resell_product_delivery WHERE product_id = ?");
                $cStmt->execute([(int)$product['id']]);
                $cc = $cStmt->fetchColumn();
                if ($cc !== false && $cc !== null) {
                    $customChargesNow[] = (float)$cc;
                }

                $orderItemData[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'variation' => $item['variation'],
                    'selling_price' => $sellingPrice,
                ];
            }

            if ($customChargesNow) {
                // A cart is one shipment. Preserve custom-product delivery rules by applying
                // the highest custom charge among selected products once to the shipment.
                $deliveryCharge = max($customChargesNow);
                $shippingArea = 'custom';
            } else {
                if (!$deliveryChargeId) {
                    throw new Exception('ডেলিভারি চার্জ নির্বাচন করুন');
                }
                $dcStmt = $pdo->prepare("SELECT name, charge FROM resell_delivery_charges WHERE id = ? AND status = 1");
                $dcStmt->execute([$deliveryChargeId]);
                $dc = $dcStmt->fetch();
                if (!$dc) {
                    throw new Exception('ডেলিভারি চার্জ পাওয়া যায়নি');
                }
                $deliveryCharge = (float)$dc['charge'];
                $shippingArea = $dc['name'];
            }

            $totalCost = $originalTotal + $deliveryCharge;

            $onlinePaymentDiscount = 0.0;
            if ($paymentMethod === 'online') {
                $discountType = getSettings('online_payment_discount_type_resell') ?? 'fixed';
                $discountValue = (float)(getSettings('online_payment_discount_value_resell') ?? 0);
                if ($discountValue > 0) {
                    $onlinePaymentDiscount = $discountType === 'percentage'
                        ? ($totalCost * $discountValue) / 100
                        : $discountValue;
                    $onlinePaymentDiscount = min($totalCost, max(0, $onlinePaymentDiscount));
                    $totalCost -= $onlinePaymentDiscount;
                }
            }

            // NOTE16: Customer pays item selling prices PLUS delivery once.
            // The courier delivery component is already part of the cost; therefore
            // it must also be included in the customer's revenue before profit.
            $customerBill = $saleTotal + $deliveryCharge;
            $profit = round($customerBill - $totalCost, 2);
            $shortfall = round(max(0, $totalCost - $customerBill), 2);
            if ($shortfall > $lockedBalance) {
                throw new Exception('এই কম/শূন্য বিক্রয় মূল্যের জন্য ব্যালেন্সে পর্যাপ্ত টাকা নেই। ঘাটতি: ' . formatPrice($shortfall) . ', বর্তমান ব্যালেন্স: ' . formatPrice($lockedBalance));
            }

            $orderNumber = 'RS' . date('Ymd') . strtoupper(substr(uniqid(), -4));
            $paymentStatus = $paymentMethod === 'online' ? 'pending_verification' : 'pending';

            $orderStmt = $pdo->prepare("\n                INSERT INTO resell_orders\n                (order_number, reseller_id, customer_name, customer_phone, customer_email, shipping_address, shipping_area,\n                 original_price, delivery_charge, reseller_price, profit, shortfall_amount, shortfall_deducted, shortfall_refunded,\n                 notes, payment_method, payment_type, payment_number, transaction_id, payment_status,
                 pricing_version, checkout_discount)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            $orderStmt->execute([
                $orderNumber, $resellerId, $customerName, $customerPhone, $customerEmail ?: null,
                $shippingAddress, $shippingArea, $originalTotal, $deliveryCharge, $saleTotal, $profit,
                $shortfall, $shortfall > 0 ? 1 : 0, 0,
                $notes ?: null, $paymentMethod, $onlinePaymentType ?: null, $paymentNumber ?: null,
                $transactionId ?: null, $paymentStatus, 2, $onlinePaymentDiscount
            ]);
            $orderId = (int)$pdo->lastInsertId();

            if ($shortfall > 0) {
                resellDeductOrderShortfall($pdo, [
                    'id' => $orderId,
                    'reseller_id' => $resellerId,
                    'order_number' => $orderNumber,
                ], $shortfall, 'order');
            }

            $itemStmt = $pdo->prepare("\n                INSERT INTO resell_order_items\n                (order_id, resell_product_id, product_name, product_image, variation, quantity, original_price, reseller_price)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            foreach ($orderItemData as $data) {
                $p = $data['product'];
                $itemStmt->execute([
                    $orderId,
                    $p['id'],
                    $p['name'],
                    $p['image'],
                    $data['variation'] ?: null,
                    $data['quantity'],
                    $p['reseller_price'],
                    $data['selling_price']
                ]);
            }

            $pdo->prepare("UPDATE resellers SET total_orders = total_orders + 1 WHERE id = ?")->execute([$resellerId]);
            $pdo->prepare("DELETE FROM resell_cart WHERE reseller_id = ?")->execute([$resellerId]);

            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_type, type, title, link, user_id, is_read) VALUES ('admin', 'resell_order', ?, ?, NULL, 0)");
            $notifStmt->execute(["নতুন রিসেল অর্ডার #$orderNumber", "resell-orders"]);

            $pdo->commit();
            setFlash('success', 'কার্ট অর্ডার সফল হয়েছে। অর্ডার নম্বর: ' . $orderNumber . ($shortfall > 0 ? ' | ব্যালেন্স থেকে কর্তন: ' . formatPrice($shortfall) : ''));
            redirect(baseUrl('resell/order-details.php?id=' . $orderId));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$baseTotal = 0.0;
$enteredSaleTotal = 0.0;
$allPricesEntered = ($_SERVER['REQUEST_METHOD'] === 'POST');
foreach ($cartItems as $item) {
    $qty = (int)$item['quantity'];
    $baseTotal += (float)$item['reseller_price'] * $qty;
    $enteredPrice = $_POST['selling_price'][$item['id']] ?? null;
    if ($enteredPrice === null || trim((string)$enteredPrice) === '' || !is_numeric($enteredPrice)) {
        $allPricesEntered = false;
    } else {
        $enteredSaleTotal += (float)$enteredPrice * $qty;
    }
}
$selectedDeliveryCharge = null;
foreach ($deliveryCharges as $dc) {
    if ((int)$dc['id'] === (int)($_POST['delivery_charge_id'] ?? 0)) {
        $selectedDeliveryCharge = (float)$dc['charge'];
        break;
    }
}
$initialDeliveryCharge = $forcedCustomCharge !== null
    ? $forcedCustomCharge
    : ($selectedDeliveryCharge ?? (!empty($deliveryCharges) ? (float)$deliveryCharges[0]['charge'] : 0.0));

include __DIR__ . '/includes/header.php';
?>

<style>
.checkout-wrap { max-width:1200px; margin:0 auto; }
.checkout-card { background:#fff; border-radius:14px; padding:22px; box-shadow:0 2px 12px rgba(0,0,0,.05); margin-bottom:20px; }
.checkout-card h5 { font-weight:700; padding-bottom:14px; border-bottom:1px solid #edf0f3; margin-bottom:18px; }
.checkout-item { display:grid; grid-template-columns:70px minmax(0,1fr) 120px 150px; gap:14px; align-items:center; padding:12px 0; border-bottom:1px solid #f0f2f4; }
.checkout-item:last-child { border-bottom:0; }
.checkout-item img { width:70px; height:70px; object-fit:contain; border-radius:8px; background:#f8f9fa; }
.checkout-name { font-weight:700; }
.checkout-variation { font-size:.78rem; color:#6c757d; }
.price-summary { position:sticky; top:95px; }
.summary-row { display:flex; justify-content:space-between; gap:15px; padding:8px 0; }
.summary-row.final { margin-top:8px; padding-top:14px; border-top:1px solid #ddd; font-size:1.15rem; font-weight:800; }
.profit-positive { color:#198754; font-weight:800; }
.profit-negative { color:#dc3545; font-weight:800; }
@media (max-width: 767.98px) {
    .checkout-item { grid-template-columns:58px 1fr; }
    .checkout-item img { width:58px; height:58px; }
    .checkout-item .qty-col, .checkout-item .sale-col { grid-column:2; }
}
</style>

<div class="checkout-wrap">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-bag-check me-2"></i>রিসেলার কার্ট চেকআউট</h3>
            <div class="text-muted">একটি অর্ডারে <?php echo count($cartItems); ?> ধরনের পণ্য</div>
        </div>
        <a href="<?php echo baseUrl('resell/cart.php'); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>কার্টে ফিরুন</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    <?php showFlash(); ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        শূন্য বা মোট খরচের কম বিক্রয় মূল্য দেওয়া যাবে। ঘাটতি থাকলে অর্ডার কনফার্মের সময় রিসেলার ব্যালেন্স থেকে স্বয়ংক্রিয়ভাবে কাটা হবে।
        বর্তমান ব্যালেন্স: <strong><?php echo formatPrice($currentBalance); ?></strong>
    </div>
    <?php if ($currentBalance < $minBalance): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        অর্ডার কনফার্ম করতে আপনার ব্যালেন্স নূন্যতম <strong><?php echo formatPrice($minBalance); ?></strong> হতে হবে।
        বর্তমান ব্যালেন্স <strong><?php echo formatPrice($currentBalance); ?></strong>।
        <a href="<?php echo baseUrl('resell/add-balance.php'); ?>" class="alert-link ms-1">ব্যালেন্স এড করুন</a>
    </div>
    <?php endif; ?>

    <form method="POST" id="resellCheckoutForm">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="checkout-card">
                    <h5><i class="bi bi-box-seam me-2"></i>পণ্য ও বিক্রয় মূল্য</h5>
                    <?php foreach ($cartItems as $item): ?>
                    <?php
                        $variation = json_decode($item['variation'] ?? '', true) ?: [];
                        $enteredPrice = $_POST['selling_price'][$item['id']] ?? '';
                    ?>
                    <div class="checkout-item" data-base-price="<?php echo (float)$item['reseller_price']; ?>" data-qty="<?php echo (int)$item['quantity']; ?>">
                        <img src="<?php echo imageUrl($item['image']); ?>" alt="">
                        <div>
                            <div class="checkout-name"><?php echo sanitize($item['name']); ?></div>
                            <small class="text-muted">রিসেলার মূল্য: <?php echo formatPrice($item['reseller_price']); ?> / পিস</small>
                            <?php if ($variation): ?>
                            <div class="checkout-variation mt-1">
                                <?php foreach ($variation as $key => $value): ?>
                                <span class="badge bg-light text-dark border me-1"><?php echo sanitize($key); ?>: <?php echo sanitize($value); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="qty-col"><strong><?php echo (int)$item['quantity']; ?> পিস</strong></div>
                        <div class="sale-col">
                            <label class="form-label small mb-1">বিক্রয় মূল্য / পিস</label>
                            <input type="number" step="0.01" min="0" name="selling_price[<?php echo $item['id']; ?>]" class="form-control selling-price" value="<?php echo htmlspecialchars((string)$enteredPrice, ENT_QUOTES, 'UTF-8'); ?>" placeholder="মূল্য লিখুন" inputmode="decimal" required>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="checkout-card">
                    <h5><i class="bi bi-person me-2"></i>কাস্টমার তথ্য</h5>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">কাস্টমারের নাম *</label><input type="text" name="customer_name" class="form-control" value="<?php echo sanitize($_POST['customer_name'] ?? ''); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">মোবাইল নম্বর *</label><input type="tel" name="customer_phone" class="form-control" value="<?php echo sanitize($_POST['customer_phone'] ?? ''); ?>" required></div>
                        <div class="col-12"><label class="form-label">ইমেইল (ঐচ্ছিক)</label><input type="email" name="customer_email" class="form-control" value="<?php echo sanitize($_POST['customer_email'] ?? ''); ?>"></div>
                        <div class="col-12"><label class="form-label">ডেলিভারি ঠিকানা *</label><textarea name="shipping_address" class="form-control" rows="3" required><?php echo sanitize($_POST['shipping_address'] ?? ''); ?></textarea></div>
                        <div class="col-12">
                            <label class="form-label">ডেলিভারি চার্জ *</label>
                            <?php if ($forcedCustomCharge !== null): ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-truck me-2"></i>কার্টে কাস্টম ডেলিভারি চার্জ যুক্ত পণ্য আছে। এই অর্ডারে প্রযোজ্য চার্জ: <strong><?php echo formatPrice($forcedCustomCharge); ?></strong>
                            </div>
                            <input type="hidden" name="delivery_charge_id" value="0">
                            <?php elseif ($deliveryCharges): ?>
                            <select class="form-select" name="delivery_charge_id" id="deliveryChargeSelect" required>
                                <?php foreach ($deliveryCharges as $dc): ?>
                                <option value="<?php echo $dc['id']; ?>" data-charge="<?php echo (float)$dc['charge']; ?>" <?php echo ((int)($_POST['delivery_charge_id'] ?? 0) === (int)$dc['id']) ? 'selected' : ''; ?>><?php echo sanitize($dc['name']); ?> (<?php echo formatPrice($dc['charge']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <div class="alert alert-warning mb-0">কোনো সক্রিয় ডেলিভারি চার্জ পাওয়া যায়নি।</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12"><label class="form-label">নোট (ঐচ্ছিক)</label><textarea name="notes" class="form-control" rows="2"><?php echo sanitize($_POST['notes'] ?? ''); ?></textarea></div>
                    </div>
                </div>

                <div class="checkout-card">
                    <?php $sectionType = 'resell'; include __DIR__ . '/../includes/payment-method.php'; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="checkout-card price-summary order-totals">
                    <h5><i class="bi bi-calculator me-2"></i>হিসাব</h5>
                    <div class="summary-row"><span>পণ্য মূল্য</span><strong id="productTotal"><?php echo formatPrice($baseTotal); ?></strong></div>
                    <div class="summary-row"><span>ডেলিভারি চার্জ</span><strong id="deliveryCharge"><?php echo formatPrice($initialDeliveryCharge); ?></strong></div>
                    <div class="summary-row"><span>মোট খরচ</span><strong id="totalCost"><?php echo formatPrice($baseTotal + $initialDeliveryCharge); ?></strong></div>
                    <div class="summary-row"><span>কাস্টমারের মোট বিক্রয় মূল্য</span><strong id="resellerTotal"><?php echo $allPricesEntered ? formatPrice($enteredSaleTotal + $initialDeliveryCharge) : '—'; ?></strong></div>
                    <div class="summary-row"><span>ব্যালেন্স থেকে কর্তন</span><strong id="shortfallAmount"><?php echo $allPricesEntered ? formatPrice(max(0, $baseTotal - $enteredSaleTotal)) : '—'; ?></strong></div>
                    <div class="summary-row final"><span>আপনার প্রফিট</span><strong id="profitAmount" class="<?php echo $allPricesEntered ? ($enteredSaleTotal >= $baseTotal ? 'profit-positive' : 'profit-negative') : ''; ?>"><?php echo $allPricesEntered ? formatPrice($enteredSaleTotal - $baseTotal) : '—'; ?></strong></div>
                    <button type="submit" class="btn btn-primary w-100 py-3 mt-3 fw-bold" <?php echo $currentBalance < $minBalance ? 'disabled' : ''; ?>><i class="bi bi-check-circle me-2"></i>অর্ডার কনফার্ম করুন</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const forcedCustomCharge = <?php echo $forcedCustomCharge !== null ? json_encode((float)$forcedCustomCharge) : 'null'; ?>;
const note16DiscountType = <?php echo json_encode(getSettings('online_payment_discount_type_resell') ?? 'fixed'); ?>;
const note16DiscountValue = <?php echo json_encode((float)(getSettings('online_payment_discount_value_resell') ?? 0)); ?>;
function note16EffectiveCost(cost) {
    if (document.querySelector('input[name="payment_method"]:checked')?.value !== 'online') return cost;
    const raw = note16DiscountType === 'percentage' ? cost * note16DiscountValue / 100 : note16DiscountValue;
    return cost - Math.min(cost, Math.max(0, raw));
}
function formatResellMoney(value) { return '৳' + Number(value).toLocaleString('bn-BD', {maximumFractionDigits: 2}); }
function currentDeliveryCharge() {
    if (forcedCustomCharge !== null) return Number(forcedCustomCharge) || 0;
    const select = document.getElementById('deliveryChargeSelect');
    if (!select || !select.options.length) return 0;
    return Number(select.options[select.selectedIndex].dataset.charge) || 0;
}
function recalculateCheckout() {
    let baseTotal = 0;
    let saleTotal = 0;
    let allPricesEntered = true;
    document.querySelectorAll('.checkout-item').forEach(row => {
        const qty = Number(row.dataset.qty) || 1;
        const base = Number(row.dataset.basePrice) || 0;
        const inputValue = row.querySelector('.selling-price')?.value.trim() ?? '';
        baseTotal += base * qty;
        if (inputValue === '' || !Number.isFinite(Number(inputValue)) || Number(inputValue) < 0) {
            allPricesEntered = false;
        } else {
            saleTotal += Number(inputValue) * qty;
        }
    });
    const delivery = currentDeliveryCharge();
    const cost = note16EffectiveCost(baseTotal + delivery);
    document.getElementById('productTotal').textContent = formatResellMoney(baseTotal);
    document.getElementById('deliveryCharge').textContent = formatResellMoney(delivery);
    document.getElementById('totalCost').textContent = formatResellMoney(cost);
    const profitEl = document.getElementById('profitAmount');
    if (!allPricesEntered) {
        ['resellerTotal', 'shortfallAmount'].forEach(id => {
            document.getElementById(id).textContent = '—';
        });
        profitEl.textContent = '—';
        profitEl.className = '';
        return;
    }
    // The customer pays for items + shipping once. Shipping therefore cancels
    // from both sides of the profit calculation; never subtract it twice.
    const customerBill = saleTotal + delivery;
    const profit = customerBill - cost;
    const shortfall = Math.max(0, cost - customerBill);
    document.getElementById('resellerTotal').textContent = formatResellMoney(customerBill);
    document.getElementById('shortfallAmount').textContent = formatResellMoney(shortfall);
    profitEl.textContent = formatResellMoney(profit);
    profitEl.className = profit >= 0 ? 'profit-positive' : 'profit-negative';
}
document.querySelectorAll('.selling-price').forEach(input => input.addEventListener('input', recalculateCheckout));
document.getElementById('deliveryChargeSelect')?.addEventListener('change', recalculateCheckout);
document.querySelectorAll('input[name="payment_method"]').forEach(input => input.addEventListener('change', recalculateCheckout));
recalculateCheckout();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
