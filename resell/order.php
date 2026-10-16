<?php
/**
 * Kilagbo Reseller - Place Order Page
 * Developed by Webnify Bangladesh
 */

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

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
    $resellerPrice = floatval($_POST['reseller_price'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
    $variations = sanitize($_POST['variations'] ?? '');
    
    // Online payment details
    $onlinePaymentType = sanitize($_POST['online_payment_type'] ?? '');
    $paymentNumber = sanitize($_POST['payment_number'] ?? '');
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    
    // Calculate costs
    $originalPrice = $product['reseller_price'] * $quantity;
    
    // Get delivery charge - custom or selected
    if ($hasCustomCharge) {
        $deliveryCharge = $customDeliveryCharge;
        $shippingArea = 'custom';
    } else {
        $deliveryCharge = getResellDeliveryCharge($deliveryChargeId);
        // Get delivery charge name for shipping_area
        $stmt = $pdo->prepare("SELECT name FROM resell_delivery_charges WHERE id = ?");
        $stmt->execute([$deliveryChargeId]);
        $dcInfo = $stmt->fetch();
        $shippingArea = $dcInfo ? $dcInfo['name'] : 'default';
    }
    
    $totalCost = $originalPrice + $deliveryCharge;
    $totalResellerPrice = $resellerPrice * $quantity;
    $profit = $totalResellerPrice - $totalCost;
    
    // Validation
    if ($paymentMethod === 'online' && (empty($paymentNumber) || empty($transactionId))) {
        $error = 'অনলাইন পেমেন্টের জন্য পেমেন্ট নম্বর এবং Transaction ID আবশ্যক';
    } elseif (empty($customerName) || empty($customerPhone) || empty($shippingAddress)) {
        $error = 'সব প্রয়োজনীয় ফিল্ড পূরণ করুন';
    } elseif ($resellerPrice <= 0) {
        $error = 'আপনার বিক্রয় মূল্য দিন';
    } elseif ($totalResellerPrice < $totalCost) {
        $minPrice = ceil($totalCost / $quantity);
        $error = "আপনার বিক্রয় মূল্য কম। ন্যূনতম মূল্য প্রতি পিস: " . formatPrice($minPrice) . " (পণ্য মূল্য + ডেলিভারি চার্জ)";
    } elseif ($product['stock_quantity'] !== null && $quantity > $product['stock_quantity']) {
        $error = 'স্টকে এত পণ্য নেই। স্টক: ' . $product['stock_quantity'];
    } else {
        try {
            // Apply online payment discount if applicable
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
                    $totalCost = max(0, $totalCost - $onlinePaymentDiscount);
                    $profit = $totalResellerPrice - $totalCost;
                }
            }
            
            // Generate order number
            $orderNumber = 'RS' . date('Ymd') . strtoupper(substr(uniqid(), -4));
            
            // Determine payment status
            $paymentStatus = $paymentMethod === 'online' ? 'pending_verification' : 'pending';
            
            // Ensure variations is valid JSON or null
            $variationsJson = null;
            if (!empty($variations)) {
                // Try to decode to check if it's already JSON
                $decoded = json_decode($variations);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $variationsJson = $variations;
                } else {
                    // It's a string, encode it as a JSON string
                    $variationsJson = json_encode($variations);
                }
            }
            
            // Create order with payment info
            $stmt = $pdo->prepare("
                INSERT INTO resell_orders 
                (order_number, reseller_id, customer_name, customer_phone, customer_email, shipping_address, shipping_area, 
                 original_price, delivery_charge, reseller_price, profit, notes, payment_method, payment_type, payment_number, transaction_id, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                INSERT INTO resell_order_items 
                (order_id, resell_product_id, product_name, product_image, variation, quantity, original_price, reseller_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $product['id'],
                $product['name'],
                $product['image'],
                $variationsJson,
                $quantity,
                $product['reseller_price'],
                $resellerPrice
            ]);
            
            // Update reseller stats
            $pdo->prepare("UPDATE resellers SET total_orders = total_orders + 1 WHERE id = ?")->execute([$resellerId]);
            
            // Add notification
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_type, type, title, link, user_id, is_read) VALUES ('admin', 'resell_order', ?, ?, NULL, 0)");
            $notifStmt->execute(["নতুন রিসেল অর্ডার #$orderNumber", "resell-orders"]);
            
            setFlash('success', "অর্ডার সফল! অর্ডার নম্বর: $orderNumber");
            
            // Debug redirection issues
            if (headers_sent()) {
                echo "<script>window.location.href='" . baseUrl('resell/orders.php') . "';</script>";
                exit;
            } else {
                redirect(baseUrl('resell/orders.php'));
            }

        } catch (Exception $e) {
            $error = "অর্ডার প্লেস করার সময় সমস্যা হয়েছে: " . $e->getMessage();
            // Log error for debugging
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
    
    .note-box {
        background: #fff3cd;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        color: #856404;
    }
</style>

<div class="order-page">
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <div class="note-box">
        <i class="bi bi-info-circle me-2"></i>
        <strong>মনে রাখুন:</strong> <?php echo getSettings('reselling_commission_note') ?: 'আপনার প্রফিট = আপনার বিক্রয় মূল্য - (পণ্যের মূল্য + ডেলিভারি চার্জ)'; ?>
    </div>
    
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
                            <label class="form-label">আপনার বিক্রয় মূল্য (প্রতি পিস) *</label>
                            <input type="number" class="form-control" name="reseller_price" id="resellerPrice" placeholder="যেমন: ৫০০" value="<?php echo $product['suggested_selling_price'] ?: ''; ?>" required>
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
                        <span>আপনার বিক্রয় মূল্য:</span>
                        <span id="resellerTotal">৳০</span>
                    </div>
                    <div class="calc-row profit">
                        <span>আপনার প্রফিট:</span>
                        <span id="profitAmount" class="profit-negative">৳০</span>
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
    const totalCost = productTotal + deliveryCharge;
    const resellerTotal = resellerPrice * quantity;
    const profit = resellerTotal - totalCost;
    
    document.getElementById('productTotal').textContent = formatPrice(productTotal);
    document.getElementById('deliveryCharge').textContent = formatPrice(deliveryCharge);
    document.getElementById('totalCost').textContent = formatPrice(totalCost);
    document.getElementById('resellerTotal').textContent = formatPrice(resellerTotal);
    document.getElementById('profitAmount').textContent = formatPrice(profit);
    document.getElementById('profitAmount').className = profit >= 0 ? 'profit-positive' : 'profit-negative';
}

document.getElementById('quantity').addEventListener('input', calculate);
document.getElementById('resellerPrice').addEventListener('input', calculate);

// Add listener for delivery charge select if it exists
const deliverySelect = document.getElementById('deliveryChargeSelect');
if (deliverySelect) {
    deliverySelect.addEventListener('change', calculate);
}

// Initial calculation
calculate();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
