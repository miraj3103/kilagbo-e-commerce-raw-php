<?php
/**
 * Kilagbo Reseller Portal - Configuration
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';

// Reseller session helpers
function isResellerLoggedIn() {
    return isset($_SESSION['reseller_id']) && !empty($_SESSION['reseller_id']);
}

function getResellerId() {
    return $_SESSION['reseller_id'] ?? null;
}

function getResellerData() {
    global $pdo;
    if (!isResellerLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM resellers WHERE id = ?");
    $stmt->execute([getResellerId()]);
    return $stmt->fetch();
}

function requireResellerLogin() {
    if (!isResellerLoggedIn()) {
        redirect(baseUrl('resell/login.php'));
    }
    
    // Check if approved
    $reseller = getResellerData();
    if (!$reseller) {
        unset($_SESSION['reseller_id']);
        redirect(baseUrl('resell/login.php'));
    }
    
    if ($reseller['status'] === 'pending') {
        redirect(baseUrl('resell/pending.php'));
    }
    
    if ($reseller['status'] === 'rejected') {
        unset($_SESSION['reseller_id']);
        setFlash('error', 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে।');
        redirect(baseUrl('resell/login.php'));
    }
    
    return $reseller;
}

function resellerLogout() {
    unset($_SESSION['reseller_id']);
    session_regenerate_id(true);
}

// Check if email/phone is rejected
function isCredentialRejected($email, $phone = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reseller_rejected_credentials WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) return true;
    
    if ($phone) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reseller_rejected_credentials WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() > 0) return true;
    }
    
    return false;
}

// Get delivery charge for resell by charge ID
function getResellDeliveryCharge($chargeId = null) {
    global $pdo;
    
    if ($chargeId) {
        $stmt = $pdo->prepare("SELECT charge FROM resell_delivery_charges WHERE id = ? AND status = 1");
        $stmt->execute([$chargeId]);
        $result = $stmt->fetch();
        return $result ? (float)$result['charge'] : 0;
    }
    
    // Return first active charge as default
    $stmt = $pdo->query("SELECT charge FROM resell_delivery_charges WHERE status = 1 ORDER BY charge ASC LIMIT 1");
    $result = $stmt->fetch();
    return $result ? (float)$result['charge'] : 0;
}


// NOTE 05: reseller cart count. Returns 0 before the migration is installed.
function getResellerCartCount($resellerId = null) {
    global $pdo;
    $resellerId = $resellerId ?: getResellerId();
    if (!$resellerId) return 0;

    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM resell_cart WHERE reseller_id = ?");
        $stmt->execute([$resellerId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
