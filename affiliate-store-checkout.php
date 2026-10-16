<?php
/**
 * Kilagbo - Affiliate Store Checkout Processing
 * Handles orders from affiliate store pages
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(baseUrl());
}

$storeCode = sanitize($_POST['store_code'] ?? '');
$productId = intval($_POST['product_id'] ?? 0);
$productPrice = floatval($_POST['product_price'] ?? 0);
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

if (empty($storeCode)) $errors[] = 'অবৈধ স্টোর';
if (empty($productId)) $errors[] = 'পণ্য সিলেক্ট করুন';
if (empty($customerName)) $errors[] = 'নাম দিন';
if (empty($customerPhone)) $errors[] = 'ফোন নম্বর দিন';
if (empty($shippingAddress)) $errors[] = 'ঠিকানা দিন';

// Get store data
$store = null;
try {
    $stmt = $pdo->prepare("
        SELECT s.*, a.name as affiliator_name, a.id as affiliator_id
        FROM affiliate_stores s
        JOIN affiliators a ON s.affiliator_id = a.id
        WHERE s.store_code = ? AND s.status = 1 AND a.status = 'approved'
    ");
    $stmt->execute([$storeCode]);
    $store = $stmt->fetch();
} catch (PDOException $e) {
    $errors[] = 'স্টোর পাওয়া যায়নি';
}

if (!$store) {
    $errors[] = 'অবৈধ স্টোর';
}

// Get product
$product = null;
if ($productId) {
    $stmt = $pdo->prepare("SELECT * FROM affiliate_products WHERE id = ? AND status = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $errors[] = 'পণ্যটি পাওয়া যায়নি';
    }
}

// Check product stock
if ($product && $product['stock_quantity'] !== null && $product['stock_quantity'] < $qty) {
    $errors[] = 'দুঃখিত, পর্যাপ্ত পণ্য স্টকে নেই (স্টক: ' . $product['stock_quantity'] . ')';
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    redirect(baseUrl('store/' . $storeCode));
}

// Calculate prices
$productPrice = $product['sale_price'] ?: $product['regular_price'];
$totalProductPrice = $productPrice * $qty;

// Get delivery charge
$deliveryCharge = 60; // Default
try {
    $stmt = $pdo->prepare("SELECT charge FROM affiliate_delivery_charges WHERE area_name = ? AND status = 1");
    $stmt->execute([$shippingArea]);
    $dc = $stmt->fetch();
    if ($dc) {
        $deliveryCharge = (float)$dc['charge'];
    } else {
        // Fallback to default areas
        $deliveryCharge = ($shippingArea === 'inside_dhaka' || $shippingArea === 'ঢাকার ভিতরে') ? 60 : 120;
    }
} catch (PDOException $e) {
    $deliveryCharge = ($shippingArea === 'inside_dhaka' || $shippingArea === 'ঢাকার ভিতরে') ? 60 : 120;
}

$totalAmount = $totalProductPrice + $deliveryCharge;

// Payment method handling
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

// Validate online payment
if ($paymentMethod === 'online') {
    if (!$onlinePaymentEnabled) {
        $errors[] = 'অনলাইন পেমেন্ট আপাতত বন্ধ আছে';
    }
    if (empty($paymentNumber) || empty($transactionId) || empty($onlinePaymentType)) {
        $errors[] = 'অনলাইন পেমেন্টের তথ্য পূরণ করুন';
    }
    
    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
        redirect(baseUrl('store-product/' . $storeCode . '/' . $productId));
    }
}

// Calculate commission
$commissionAmount = 0;
if ($product['commission_type'] === 'percentage') {
    $commissionAmount = ($totalProductPrice * $product['commission_value']) / 100;
} else {
    $commissionAmount = $product['commission_value'] * $qty;
}

// Generate order number
$orderNumber = 'AFS' . date('ymd') . strtoupper(substr(uniqid(), -4));

try {
    $pdo->beginTransaction();
    
    // Create order - use affiliate_orders table with store reference
    $stmt = $pdo->prepare("
        INSERT INTO affiliate_orders (
            order_number, affiliate_link_id, affiliator_id, product_id,
            customer_name, customer_phone, customer_email, shipping_address, shipping_area,
            product_price, delivery_charge, total_amount,
            commission_type, commission_value, commission_amount,
            order_source, store_id,
            payment_method, payment_number, transaction_id, order_note
        ) VALUES (?, NULL, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, 'store', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderNumber,
        $store['affiliator_id'],
        $product['id'],
        $customerName,
        $customerPhone,
        $shippingAddress,
        $shippingArea,
        $totalProductPrice,
        $deliveryCharge,
        $totalAmount,
        $product['commission_type'],
        $product['commission_value'],
        $commissionAmount,
        $store['id'],
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
        $product['id'],
        $product['name'],
        $product['image'],
        $qty,
        $productPrice,
        $totalProductPrice, 
        $variationJson
    ]);
    
    // Update store order count
    $pdo->prepare("UPDATE affiliate_stores SET orders = orders + 1 WHERE id = ?")->execute([$store['id']]);
    
    // Reduce stock if applicable
    if ($product['stock_quantity'] !== null) {
        $pdo->prepare("UPDATE affiliate_products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity > 0")
            ->execute([$qty, $product['id']]);
    }
    
    // Add notification
    $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('affiliate_order', ?, ?)")
        ->execute(["নতুন অ্যাফিলিয়েট স্টোর অর্ডার #$orderNumber - " . formatPrice($totalAmount), "affiliate-order-view/$orderId"]);
    
    $pdo->commit();
    
    // Store order info for success page
    $_SESSION['last_order'] = [
        'order_number' => $orderNumber,
        'total' => $totalAmount
    ];
    
    setFlash('success', 'অর্ডার সফলভাবে সম্পন্ন হয়েছে! অর্ডার নম্বর: ' . $orderNumber);
    redirect(baseUrl('order-success'));
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Check if columns are missing and add them
    if (strpos($e->getMessage(), 'order_source') !== false || strpos($e->getMessage(), 'store_id') !== false || strpos($e->getMessage(), 'order_note') !== false) {
        try {
            $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN order_source VARCHAR(20) DEFAULT 'link' AFTER commission_amount");
        } catch (PDOException $e2) {}
        
        try {
            $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN store_id INT(11) DEFAULT NULL AFTER order_source");
        } catch (PDOException $e2) {}
        
        try {
            $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN order_note TEXT NULL AFTER transaction_id");
        } catch (PDOException $e2) {}
        
        // Retry the insert
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO affiliate_orders (
                    order_number, affiliate_link_id, affiliator_id, product_id,
                    customer_name, customer_phone, customer_email, shipping_address, shipping_area,
                    product_price, delivery_charge, total_amount,
                    commission_type, commission_value, commission_amount,
                    order_source, store_id,
                    payment_method, payment_number, transaction_id, order_note
                ) VALUES (?, NULL, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, 'store', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderNumber,
                $store['affiliator_id'],
                $product['id'],
                $customerName,
                $customerPhone,
                $shippingAddress,
                $shippingArea,
                $totalProductPrice,
                $deliveryCharge,
                $totalAmount,
                $product['commission_type'],
                $product['commission_value'],
                $commissionAmount,
                $store['id'],
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
                $product['id'],
                $product['name'],
                $product['image'],
                $qty,
                $productPrice,
                $totalProductPrice,
                $variationJson
            ]);
            
            $pdo->prepare("UPDATE affiliate_stores SET orders = orders + 1 WHERE id = ?")->execute([$store['id']]);
            
            if ($product['stock_quantity'] !== null) {
                $pdo->prepare("UPDATE affiliate_products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity > 0")
                    ->execute([$qty, $product['id']]);
            }
            
            $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('affiliate_order', ?, ?)")
                ->execute(["নতুন অ্যাফিলিয়েট স্টোর অর্ডার #$orderNumber - " . formatPrice($totalAmount), "affiliate-order-view/$orderId"]);
            
            $pdo->commit();
            
            $_SESSION['last_order'] = [
                'order_number' => $orderNumber,
                'total' => $totalAmount
            ];
            
            setFlash('success', 'অর্ডার সফলভাবে সম্পন্ন হয়েছে! অর্ডার নম্বর: ' . $orderNumber);
            redirect(baseUrl('order-success'));
            
        } catch (PDOException $e3) {
            $pdo->rollBack();
            setFlash('error', 'অর্ডার প্রসেস করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।');
            redirect(baseUrl('store/' . $storeCode));
        }
    } else {
        setFlash('error', 'অর্ডার প্রসেস করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।');
        redirect(baseUrl('store/' . $storeCode));
    }
}
