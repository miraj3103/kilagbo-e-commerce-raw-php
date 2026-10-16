<?php
/**
 * Kilagbo Reseller - Google OAuth Callback
 */

require_once __DIR__ . '/../config.php';

if (getSettings('google_login_active') !== '1') {
    setFlash('error', 'Google লগইন সক্রিয় নেই');
    redirect(baseUrl('resell/login.php'));
}

$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    setFlash('error', 'Google লগইন বাতিল করা হয়েছে');
    redirect(baseUrl('resell/login.php'));
}

if (!$code) {
    redirect(baseUrl('resell/login.php'));
}

$googleClientId = getSettings('google_client_id');
$googleClientSecret = getSettings('google_client_secret');

if (!$googleClientId || !$googleClientSecret) {
    setFlash('error', 'Google লগইন কনফিগার করা হয়নি');
    redirect(baseUrl('resell/login.php'));
}

$redirectUri = baseUrl('resell/auth/google-callback.php');

try {
    // Exchange code for access token
    $tokenData = [
        'code' => $code,
        'client_id' => $googleClientId,
        'client_secret' => $googleClientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    $tokenInfo = json_decode($response, true);
    if (!isset($tokenInfo['access_token'])) {
        throw new Exception('Unable to fetch Google access token');
    }

    // Fetch user info
    $userInfoResponse = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenInfo['access_token']);
    if ($userInfoResponse === false) {
        throw new Exception('Failed to fetch Google user info');
    }
    $userInfo = json_decode($userInfoResponse, true);

    $email = $userInfo['email'] ?? '';
    $name = $userInfo['name'] ?? '';
    $googleId = $userInfo['id'] ?? '';

    if (empty($email) || empty($googleId)) {
        throw new Exception('Google থেকে ইমেইল পাওয়া যায়নি');
    }

    // Find existing reseller
    $stmt = $pdo->prepare("SELECT * FROM resellers WHERE google_id = ? OR email = ? LIMIT 1");
    $stmt->execute([$googleId, $email]);
    $reseller = $stmt->fetch();

    if ($reseller) {
        if ($reseller['status'] === 'rejected') {
            setFlash('error', 'এই অ্যাকাউন্ট প্রত্যাখ্যাত হয়েছে');
            redirect(baseUrl('resell/login.php'));
        }

        if (empty($reseller['google_id'])) {
            $pdo->prepare("UPDATE resellers SET google_id = ? WHERE id = ?")->execute([$googleId, $reseller['id']]);
        }
        $resellerId = $reseller['id'];
    } else {
        $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO resellers (name, email, phone, password, address, status, google_id) VALUES (?, ?, '', ?, '', 'pending', ?)");
        $stmt->execute([$name ?: $email, $email, $randomPassword, $googleId]);
        $resellerId = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('reseller', ?, ?)")
            ->execute(["নতুন রিসেলার (Google) - " . ($name ?: $email), "resellers"]);

        $reseller = [
            'id' => $resellerId,
            'status' => 'pending'
        ];
    }

    $_SESSION['reseller_id'] = $resellerId;
    $pdo->prepare("UPDATE resellers SET last_login = NOW() WHERE id = ?")->execute([$resellerId]);

    if ($reseller['status'] === 'pending') {
        redirect(baseUrl('resell/pending.php'));
    }

    redirect(baseUrl('resell/'));

} catch (Exception $e) {
    setFlash('error', 'Google লগইন ব্যর্থ হয়েছে। (' . $e->getMessage() . ')');
    redirect(baseUrl('resell/login.php'));
}
