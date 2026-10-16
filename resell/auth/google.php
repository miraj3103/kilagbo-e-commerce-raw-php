<?php
/**
 * Kilagbo Reseller - Google OAuth Start
 */

require_once __DIR__ . '/../config.php';

$googleClientId = getSettings('google_client_id');

if (!$googleClientId || getSettings('google_login_active') !== '1') {
    setFlash('error', 'Google লগইন অনুপলব্ধ');
    redirect(baseUrl('resell/login.php'));
}

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $googleClientId,
    'redirect_uri' => baseUrl('auth-callback?provider=google'),
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => 'resell'
]);

header('Location: ' . $authUrl);
exit;
