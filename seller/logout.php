<?php
/**
 * Kilagbo Seller Portal - Logout
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

// Clear remember cookie and token on explicit logout
if (isset($_COOKIE['seller_remember'])) {
    // Clear the token from database
    $token = $_COOKIE['seller_remember'];
    $pdo->prepare("UPDATE sellers SET remember_token = NULL WHERE remember_token = ?")->execute([$token]);
    
    // Clear the cookie
    setcookie('seller_remember', '', time() - 3600, '/', '', false, true);
}

sellerLogout();
redirect(baseUrl('seller/login.php'));
