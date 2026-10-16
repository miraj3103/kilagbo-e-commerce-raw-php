<?php
/**
 * Kilagbo - Affiliate Checkout Processing
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/affiliate/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(baseUrl());
}

$linkCode = sanitize($_POST['link_code'] ?? '');
$productId = intval($_POST['product_id'] ?? 0);
$customerName = sanitize($_POST['customer_name'] ?? '');
$customerPhone = sanitize($_POST['customer_phone'] ?? '');
$shippingAddress = sanitize($_POST['shipping_address'] ?? '');
$shippingArea = sanitize($_POST['shipping_area'] ?? 'inside_dhaka');
$orderNote = sanitize($_POST['order_note'] ?? '');
$variation = $_POST['variation'] ?? null;
$variationJson = !empty($variation) ? json_encode($variation, JSON_UNESCAPED_UNICODE) : null;
$qty = intval($_POST['quantity'] ?? 1);
if ($qty < 1) $qty = 1;

// Validate
$errors = [];

if (empty($linkCode)) $errors[] = 'অবৈধ রেফারেল লিংক';
if (empty($customerName)) $errors[] = 'নাম দিন';
if (empty($customerPhone)) $errors[] = 'ফোন নম্বর দিন';
if (empty($shippingAddress)) $errors[] = 'ঠিকানা দিন';

// Get link data
$linkData = getAffiliateLinkByCode($linkCode);
if (!$linkData) {
    $errors[] = 'অবৈধ অ্যাফিলিয়েট লিংক';
}

// Check product stock (NULL means unlimited stock)
if ($linkData && $linkData['stock_quantity'] !== null && $linkData['stock_quantity'] < $qty) {
    $errors[] = 'দুঃখিত, পর্যাপ্ত পণ্য স্টকে নেই (স্টক: ' . $linkData['stock_quantity'] . ')';
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    redirect(baseUrl('a/' . $linkCode));
}

// Calculate prices
$productPrice = $linkData['sale_price'] ?: $linkData['regular_price'];
$totalProductPrice = $productPrice * $qty;
$deliveryCharge = getAffiliateDeliveryCharge($shippingArea);
$totalAmount = $totalProductPrice + $deliveryCharge;

// Payment method (get early to apply discount)
$paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');

// Apply online payment discount if applicable
$onlinePaymentDiscount = 0;
if ($paymentMethod === 'online') {
    $discountType = getSettings('online_payment_discount_type_affiliate') ?? 'fixed';
    $discountValue = floatval(getSettings('online_payment_discount_value_affiliate') ?? 0);
    
    if ($discountValue > 0) {
        if ($discountType === 'percentage') {
            $onlinePaymentDiscount = ($totalAmount * $discountValue) / 100;
        } else {
            $onlinePaymentDiscount = $discountValue;
        }
        $totalAmount = max(0, $totalAmount - $onlinePaymentDiscount);
    }
}

// Payment details
$onlinePaymentEnabled = getSettings('online_payment_affiliate') == '1';
$paymentNumber = sanitize($_POST['payment_number'] ?? '');
$transactionId = sanitize($_POST['transaction_id'] ?? '');
$onlinePaymentType = sanitize($_POST['online_payment_type'] ?? '');

// Validate payment
if ($paymentMethod === 'online') {
    if (!$onlinePaymentEnabled) {
        $errors[] = 'অনলাইন পেমেন্ট আপাতত বন্ধ আছে';
    }
    if (empty($paymentNumber) || empty($transactionId) || empty($onlinePaymentType)) {
        $errors[] = 'অনলাইন পেমেন্টের তথ্য পূরণ করুন';
    }
}

// Calculate commission
$commissionAmount = 0;
if ($linkData['commission_type'] === 'percentage') {
    $commissionAmount = ($totalProductPrice * $linkData['commission_value']) / 100;
} else {
    $commissionAmount = $linkData['commission_value'] * $qty;
}

// Generate order number
$orderNumber = 'AFF' . date('ymd') . strtoupper(substr(uniqid(), -4));

try {
    $pdo->beginTransaction();
    
    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO affiliate_orders (
            order_number, affiliate_link_id, affiliator_id, product_id,
            customer_name, customer_phone, customer_email, shipping_address, shipping_area,
            product_price, delivery_charge, total_amount,
            commission_type, commission_value, commission_amount,
            payment_method, payment_number, transaction_id, order_note
        ) VALUES (?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderNumber,
        $linkData['id'],
        $linkData['affiliator_id'],
        $linkData['product_id'],
        $customerName,
        $customerPhone,
        $shippingAddress,
        $shippingArea,
        $totalProductPrice,
        $deliveryCharge,
        $totalAmount,
        $linkData['commission_type'],
        $linkData['commission_value'],
        $commissionAmount,
        $paymentMethod,
        $paymentNumber,
        $transactionId,
        $orderNote
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Create order item
    $stmt = $pdo->prepare("
        INSERT INTO affiliate_order_items (order_id, affiliate_product_id, product_name, product_image, quantity, unit_price, total_price, variation)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $linkData['product_id'],
        $linkData['name'],
        $linkData['image'],
        $qty,
        $productPrice,
        $totalProductPrice,
        $variationJson
    ]);
    
    // Update link conversions
    $pdo->prepare("UPDATE affiliate_links SET conversions = conversions + 1 WHERE id = ?")->execute([$linkData['link_id']]);
    
    // Update affiliator sales count
    $pdo->prepare("UPDATE affiliators SET total_sales = total_sales + 1 WHERE id = ?")->execute([$linkData['affiliator_id']]);
    
    // Reduce stock (only if not unlimited/NULL)
    if ($linkData['stock_quantity'] !== null) {
        $pdo->prepare("UPDATE affiliate_products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$qty, $linkData['product_id']]);
    }
    
    // Add notification
    $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('affiliate_order', ?, ?)")
        ->execute(["নতুন অ্যাফিলিয়েট অর্ডার #$orderNumber", "affiliate-orders"]);
    
    $pdo->commit();
    
    // Redirect to success page
    $_SESSION['affiliate_order_success'] = [
        'order_number' => $orderNumber,
        'customer_name' => $customerName,
        'product_name' => $linkData['name'],
        'total_amount' => $totalAmount
    ];
    
    redirect(baseUrl('affiliate-success.php'));
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Check if order_note column is missing
    if (strpos($e->getMessage(), 'order_note') !== false) {
        try {
            $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN order_note TEXT NULL AFTER transaction_id");
            // Retry once by redirecting back to the same POST handler
            // This is a simple way to retry the same data without re-writing the whole block
            // However, a real retry block is better.
        } catch (PDOException $e2) {
            // Column might already exist
        }
    }
    
    setFlash('error', 'অর্ডার প্রসেস করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।');
    redirect(baseUrl('a/' . $linkCode));
}
