<?php
/**
 * Kilagbo Seller Portal - Configuration
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';

// Seller session helpers
function isSellerLoggedIn() {
    return isset($_SESSION['seller_id']) && !empty($_SESSION['seller_id']);
}

function getSellerId() {
    return $_SESSION['seller_id'] ?? null;
}

function getSellerData() {
    global $pdo;
    if (!isSellerLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
    $stmt->execute([getSellerId()]);
    return $stmt->fetch();
}

function requireSellerLogin() {
    // Get current script name to prevent redirect loops
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    
    if (!isSellerLoggedIn()) {
        if ($currentScript !== 'login.php') {
            redirect(baseUrl('seller/login.php'));
        }
        return null;
    }
    
    // Check if approved
    $seller = getSellerData();
    if (!$seller) {
        unset($_SESSION['seller_id']);
        if ($currentScript !== 'login.php') {
            redirect(baseUrl('seller/login.php'));
        }
        return null;
    }
    
    if ($seller['status'] === 'pending') {
        if ($currentScript !== 'pending.php') {
            redirect(baseUrl('seller/pending.php'));
        }
    }
    
    if ($seller['status'] === 'rejected') {
        unset($_SESSION['seller_id']);
        setFlash('error', 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে।');
        if ($currentScript !== 'login.php') {
            redirect(baseUrl('seller/login.php'));
        }
        return null;
    }
    
    return $seller;
}

function sellerLogout() {
    unset($_SESSION['seller_id']);
    session_regenerate_id(true);
}

// Check if email/phone is rejected
function isSellerCredentialRejected($email, $phone = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_rejected_credentials WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) return true;
        
        if ($phone) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_rejected_credentials WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn() > 0) return true;
        }
    } catch (PDOException $e) {
        return false;
    }
    
    return false;
}

// Get seller stats
function getSellerStats($sellerId) {
    global $pdo;
    
    $stats = [
        'total_products' => 0,
        'pending_products' => 0,
        'approved_products' => 0,
        'total_orders' => 0,
        'pending_orders' => 0,
        'completed_orders' => 0,
        'total_earnings' => 0,
        'pending_earnings' => 0
    ];
    
    try {
        // Product counts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_products WHERE seller_id = ?");
        $stmt->execute([$sellerId]);
        $stats['total_products'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_products WHERE seller_id = ? AND status = 'pending'");
        $stmt->execute([$sellerId]);
        $stats['pending_products'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_products WHERE seller_id = ? AND status = 'approved'");
        $stmt->execute([$sellerId]);
        $stats['approved_products'] = $stmt->fetchColumn();
        
        // Order counts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_orders WHERE seller_id = ?");
        $stmt->execute([$sellerId]);
        $stats['total_orders'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_orders WHERE seller_id = ? AND status = 'pending'");
        $stmt->execute([$sellerId]);
        $stats['pending_orders'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM seller_orders WHERE seller_id = ? AND status = 'completed'");
        $stmt->execute([$sellerId]);
        $stats['completed_orders'] = $stmt->fetchColumn();
        
        // Earnings
        $stmt = $pdo->prepare("SELECT total_earnings, pending_earnings FROM sellers WHERE id = ?");
        $stmt->execute([$sellerId]);
        $earnings = $stmt->fetch();
        if ($earnings) {
            $stats['total_earnings'] = $earnings['total_earnings'];
            $stats['pending_earnings'] = $earnings['pending_earnings'];
        }
    } catch (PDOException $e) {
        // Tables may not exist yet
    }
    
    return $stats;
}

// Generate order number
function generateSellerOrderNumber() {
    return 'SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}
