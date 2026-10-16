<?php
/**
 * Kilagbo Affiliate Portal - Logout
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

// Clear remember cookie and token on explicit logout
if (isset($_COOKIE['affiliator_remember'])) {
    // Clear the token from database
    $token = $_COOKIE['affiliator_remember'];
    $pdo->prepare("UPDATE affiliators SET remember_token = NULL WHERE remember_token = ?")->execute([$token]);
    
    // Clear the cookie
    setcookie('affiliator_remember', '', time() - 3600, '/', '', false, true);
}

affiliatorLogout();

setFlash('success', 'সফলভাবে লগআউট হয়েছে');
redirect(baseUrl('affiliate/login.php'));
