<?php
/**
 * DEBUG VERSION OF CHECKOUT.PHP
 */
require_once 'config/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function debug_log($msg) {
    file_put_contents('checkout_debug.log', date('Y-m-d H:i:s') . ': ' . $msg . "\n", FILE_APPEND);
}

$currentPage = 'retail';
$pageTitle = 'চেকআউট';

$sessionId = getSessionId();
$userId = $_SESSION['user_id'] ?? null;

debug_log("Starting checkout for User: " . ($userId ?: 'Guest') . " Session: " . $sessionId);

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

debug_log("Found " . count($cartItems) . " items in cart");

if (empty($cartItems)) {
    debug_log("Cart is empty, redirecting to cart page");
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
$isFreeDelivery = ($deliveryType == 'free');

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("POST Request received");
    try {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $deliveryArea = sanitize($_POST['delivery_area'] ?? 'inside_dhaka');
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
        
        $onlinePaymentType = sanitize($_POST['online_payment_type'] ?? '');
        $paymentNumber = sanitize($_POST['payment_number'] ?? '');
        $transactionId = sanitize($_POST['transaction_id'] ?? '');
        
        debug_log("POST Data: Name=$name, Phone=$phone, Method=$paymentMethod, Area=$deliveryArea");

        if ($paymentMethod === 'online' && (empty($paymentNumber) || empty($transactionId))) {
            setFlash('error', 'অনলাইন পেমেন্টের জন্য পেমেন্ট নম্বর এবং Transaction ID আবশ্যক');
            debug_log("Validation failed: Online payment details missing");
        } elseif (empty($name) || empty($phone) || empty($address)) {
            setFlash('error', 'সকল তথ্য পূরণ করুন');
            debug_log("Validation failed: Billing info missing");
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
            $paymentStatus = $paymentMethod === 'online' ? 'pending_verification' : 'pending';
            
            debug_log("Creating order: $orderNumber, Total: $total");

            $stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, customer_name, customer_phone, customer_address, delivery_area, delivery_charge, subtotal, total, payment_method, payment_type, payment_number, transaction_id, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$orderNumber, $userId, $name, $phone, $address, $deliveryArea, $deliveryCharge, $subtotal, $total, $paymentMethod, $onlinePaymentType, $paymentNumber, $transactionId, $paymentStatus]);
            $orderId = $pdo->lastInsertId();
            
            debug_log("Order created ID: $orderId");

            foreach ($cartItems as $item) {
                if ($item['section'] === 'wholesale') {
                    $price = $item['wholesale_price'];
                } elseif ($item['section'] === 'affiliate') {
                    $price = $item['affiliate_sale_price'] ?: $item['affiliate_regular_price'];
                } else {
                    $price = $item['retail_sale_price'] ?: $item['retail_regular_price'];
                }
                $itemTotal = $price * $item['quantity'];
                $variationData = json_decode($item['variation'], true) ?: [];
                $variationData['section'] = $item['section'];
                $variationJson = json_encode($variationData);

                debug_log("Inserting item: Product ID " . $item['product_id'] . " Name: " . $item['name']);

                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, variation, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$orderId, $item['product_id'], $item['name'], $variationJson, $item['quantity'], $price, $itemTotal]);
            }
            
            debug_log("Clearing cart");
            if ($userId) {
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
            } else {
                $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([$sessionId]);
            }
            
            debug_log("Sending notification");
            addNotification('order', 'নতুন অর্ডার!', $name . ' একটি অর্ডার দিয়েছেন - ' . formatPrice($total), adminUrl('orders/view/' . $orderId));
            
            $_SESSION['last_order'] = [
                'order_number' => $orderNumber,
                'total' => $total
            ];
            
            debug_log("Redirecting to success page");
            redirect(baseUrl('order-success'));
        }
    } catch (Exception $e) {
        debug_log("FATAL ERROR: " . $e->getMessage());
        die("Error: " . $e->getMessage());
    } catch (Error $e) {
        debug_log("FATAL ERROR: " . $e->getMessage());
        die("Error: " . $e->getMessage());
    }
}

// ... rest of the file (HTML)
// (I will just include the original content here via secondary tool or assume it stays)
include 'includes/header.php';
?>
<!-- Rest of the HTML from checkout.php - I'll just use the original content for the view -->
<?php
// Since I can't easily copy 600 lines of HTML here, I'll use replace_file_content to wrap the PHP part
?>
