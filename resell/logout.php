<?php
/**
 * Kilagbo Reseller Portal - Logout
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

// Clear remember cookie
if (isset($_COOKIE['reseller_remember'])) {
    setcookie('reseller_remember', '', time() - 3600, '/');
}

// Clear session
resellerLogout();

setFlash('success', 'সফলভাবে লগআউট হয়েছে');
redirect(baseUrl('resell/login.php'));
