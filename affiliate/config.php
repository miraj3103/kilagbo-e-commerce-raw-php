<?php
/**
 * Kilagbo Affiliate Portal - Configuration
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';

// Affiliator session helpers
function isAffiliatorLoggedIn() {
    return isset($_SESSION['affiliator_id']) && !empty($_SESSION['affiliator_id']);
}

function getAffiliatorId() {
    return $_SESSION['affiliator_id'] ?? null;
}

function getAffiliatorData() {
    global $pdo;
    if (!isAffiliatorLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM affiliators WHERE id = ?");
    $stmt->execute([getAffiliatorId()]);
    return $stmt->fetch();
}

function requireAffiliatorLogin() {
    if (!isAffiliatorLoggedIn()) {
        redirect(baseUrl('affiliate/login.php'));
    }
    
    // Check if approved
    $affiliator = getAffiliatorData();
    if (!$affiliator) {
        unset($_SESSION['affiliator_id']);
        redirect(baseUrl('affiliate/login.php'));
    }
    
    if ($affiliator['status'] === 'pending') {
        redirect(baseUrl('affiliate/pending.php'));
    }
    
    if ($affiliator['status'] === 'rejected') {
        unset($_SESSION['affiliator_id']);
        setFlash('error', 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে।');
        redirect(baseUrl('affiliate/login.php'));
    }
    
    return $affiliator;
}

function affiliatorLogout() {
    unset($_SESSION['affiliator_id']);
    session_regenerate_id(true);
}

// Check if email/phone is rejected
function isAffiliatorCredentialRejected($email, $phone = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliator_rejected_credentials WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) return true;
    
    if ($phone) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliator_rejected_credentials WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() > 0) return true;
    }
    
    return false;
}

// Get delivery charge for affiliate
function getAffiliateDeliveryCharge($areaType = 'inside_dhaka') {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT charge FROM affiliate_delivery_charges WHERE area_type = ? AND status = 1");
    $stmt->execute([$areaType]);
    $result = $stmt->fetch();
    return $result ? (float)$result['charge'] : 0;
}

// Get all active delivery charges
function getAffiliateDeliveryCharges() {
    global $pdo;
    return $pdo->query("SELECT * FROM affiliate_delivery_charges WHERE status = 1 ORDER BY charge ASC")->fetchAll();
}

// Generate unique affiliate link code
function generateAffiliateLinkCode() {
    return bin2hex(random_bytes(8)); // 16 character unique code
}

// Get affiliate link by code
function getAffiliateLinkByCode($code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT al.*, ap.*, a.name as affiliator_name, a.id as affiliator_id,
                   ap.id as product_id, al.id as link_id, c.name as category_name
            FROM affiliate_links al
            JOIN affiliate_products ap ON al.product_id = ap.id
            JOIN affiliators a ON al.affiliator_id = a.id
            LEFT JOIN categories c ON ap.category_id = c.id
            WHERE al.link_code = ? AND al.status = 1 AND ap.status = 1 AND a.status = 'approved'
        ");
        $stmt->execute([$code]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

// Track affiliate link click
function trackAffiliateClick($linkId, $affiliatorId, $productId) {
    global $pdo;
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        
        // Check if this IP clicked recently (within 1 hour) to prevent duplicate counts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM affiliate_clicks 
            WHERE link_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$linkId, $ipAddress]);
        
        if ($stmt->fetchColumn() == 0) {
            // Insert click record
            $pdo->prepare("INSERT INTO affiliate_clicks (link_id, affiliator_id, product_id, ip_address, user_agent, referrer) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$linkId, $affiliatorId, $productId, $ipAddress, $userAgent, $referrer]);
            
            // Update link click count
            $pdo->prepare("UPDATE affiliate_links SET clicks = clicks + 1 WHERE id = ?")->execute([$linkId]);
            
            // Update affiliator total clicks
            $pdo->prepare("UPDATE affiliators SET total_clicks = total_clicks + 1 WHERE id = ?")->execute([$affiliatorId]);
        }
        
        // Set cookie for tracking
        $cookieDays = getSettings('affiliate_cookie_days') ?: 30;
        setcookie('affiliate_link', $linkId, time() + (86400 * $cookieDays), '/', '', false, true);
    } catch (Exception $e) {
        // Silently fail
    }
}

// Calculate commission for an order
function calculateAffiliateCommission($productPrice, $commissionType, $commissionValue) {
    if ($commissionType === 'percentage') {
        return ($productPrice * $commissionValue) / 100;
    }
    return $commissionValue; // Fixed amount
}

// Credit commission to affiliator
function creditAffiliatorCommission($affiliatorId, $orderId, $amount) {
    global $pdo;
    
    // Get current balance
    $stmt = $pdo->prepare("SELECT balance FROM affiliators WHERE id = ?");
    $stmt->execute([$affiliatorId]);
    $currentBalance = $stmt->fetchColumn();
    
    $newBalance = $currentBalance + $amount;
    
    // Update affiliator balance
    $pdo->prepare("UPDATE affiliators SET balance = ?, total_earned = total_earned + ? WHERE id = ?")
        ->execute([$newBalance, $amount, $affiliatorId]);
    
    // Add balance history
    $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'credit', ?, ?, 'order', ?, ?)")
        ->execute([$affiliatorId, $amount, "অর্ডার #$orderId থেকে কমিশন", $orderId, $newBalance]);
    
    // Mark order as credited
    $pdo->prepare("UPDATE affiliate_orders SET commission_credited = 1 WHERE id = ?")->execute([$orderId]);
}

// Get affiliator statistics
function getAffiliatorStats($affiliatorId) {
    global $pdo;
    
    $stats = [];
    
    // Total links
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_links WHERE affiliator_id = ?");
    $stmt->execute([$affiliatorId]);
    $stats['total_links'] = $stmt->fetchColumn();
    
    // Total clicks
    $stmt = $pdo->prepare("SELECT total_clicks FROM affiliators WHERE id = ?");
    $stmt->execute([$affiliatorId]);
    $stats['total_clicks'] = $stmt->fetchColumn();
    
    // Total orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_orders WHERE affiliator_id = ?");
    $stmt->execute([$affiliatorId]);
    $stats['total_orders'] = $stmt->fetchColumn();
    
    // Pending orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_orders WHERE affiliator_id = ? AND status = 'pending'");
    $stmt->execute([$affiliatorId]);
    $stats['pending_orders'] = $stmt->fetchColumn();
    
    // Delivered orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_orders WHERE affiliator_id = ? AND status = 'delivered'");
    $stmt->execute([$affiliatorId]);
    $stats['delivered_orders'] = $stmt->fetchColumn();
    
    // This month earnings
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_orders WHERE affiliator_id = ? AND status = 'delivered' AND commission_credited = 1 AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute([$affiliatorId]);
    $stats['monthly_earnings'] = $stmt->fetchColumn();
    
    return $stats;
}

// Get admin affiliate notifications
function getAdminAffiliateNotifications() {
    global $pdo;
    
    $notifs = [];
    
    // Pending affiliator approvals
    $notifs['affiliators'] = $pdo->query("SELECT COUNT(*) FROM affiliators WHERE status = 'pending'")->fetchColumn();
    
    // Pending orders
    $notifs['orders'] = $pdo->query("SELECT COUNT(*) FROM affiliate_orders WHERE status = 'pending'")->fetchColumn();
    
    // Pending withdrawals
    $notifs['payments'] = $pdo->query("SELECT COUNT(*) FROM affiliator_withdrawals WHERE status = 'pending'")->fetchColumn();
    
    $notifs['total'] = $notifs['affiliators'] + $notifs['orders'] + $notifs['payments'];
    
    return $notifs;
}
