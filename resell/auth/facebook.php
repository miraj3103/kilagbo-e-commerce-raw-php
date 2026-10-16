<?php
/**
 * Kilagbo Reseller - Facebook OAuth Start
 */

require_once __DIR__ . '/../config.php';

$facebookAppId = getSettings('facebook_app_id');

if (!$facebookAppId || getSettings('facebook_login_active') !== '1') {
    setFlash('error', 'Facebook লগইন অনুপলব্ধ');
    redirect(baseUrl('resell/login.php'));
}

$authUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
    'client_id' => $facebookAppId,
    'redirect_uri' => baseUrl('auth-callback?provider=facebook'),
    'scope' => 'email,public_profile',
    'response_type' => 'code',
    'state' => 'resell'
]);

header('Location: ' . $authUrl);
exit;
