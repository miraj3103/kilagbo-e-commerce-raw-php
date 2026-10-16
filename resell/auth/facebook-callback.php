<?php
/**
 * Kilagbo Reseller - Facebook OAuth Callback
 */

require_once __DIR__ . '/../config.php';

if (getSettings('facebook_login_active') !== '1') {
    setFlash('error', 'Facebook লগইন সক্রিয় নেই');
    redirect(baseUrl('resell/login.php'));
}

$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    setFlash('error', 'Facebook লগইন বাতিল করা হয়েছে');
    redirect(baseUrl('resell/login.php'));
}

if (!$code) {
    redirect(baseUrl('resell/login.php'));
}

$facebookAppId = getSettings('facebook_app_id');
$facebookAppSecret = getSettings('facebook_app_secret');

$redirectUri = baseUrl('resell/auth/facebook-callback.php');

try {
    $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
        'client_id' => $facebookAppId,
        'client_secret' => $facebookAppSecret,
        'redirect_uri' => $redirectUri,
        'code' => $code
    ]);

    $tokenResponse = file_get_contents($tokenUrl);
    if ($tokenResponse === false) {
        throw new Exception('Failed to fetch Facebook token');
    }
    $tokenInfo = json_decode($tokenResponse, true);

    if (!isset($tokenInfo['access_token'])) {
        throw new Exception('Facebook access token missing');
    }

    $userInfoUrl = 'https://graph.facebook.com/me?fields=id,name,email,picture&access_token=' . $tokenInfo['access_token'];
    $userInfoResponse = file_get_contents($userInfoUrl);
    if ($userInfoResponse === false) {
        throw new Exception('Failed to fetch Facebook user info');
    }
    $userInfo = json_decode($userInfoResponse, true);

    $email = $userInfo['email'] ?? '';
    $name = $userInfo['name'] ?? '';
    $facebookId = $userInfo['id'] ?? '';

    if (empty($facebookId)) {
        throw new Exception('Facebook থেকে তথ্য পাওয়া যায়নি');
    }

    $stmt = $pdo->prepare("SELECT * FROM resellers WHERE facebook_id = ? OR email = ? LIMIT 1");
    $stmt->execute([$facebookId, $email]);
    $reseller = $stmt->fetch();

    if ($reseller) {
        if ($reseller['status'] === 'rejected') {
            setFlash('error', 'এই অ্যাকাউন্ট প্রত্যাখ্যাত হয়েছে');
            redirect(baseUrl('resell/login.php'));
        }

        if (empty($reseller['facebook_id'])) {
            $pdo->prepare("UPDATE resellers SET facebook_id = ? WHERE id = ?")->execute([$facebookId, $reseller['id']]);
        }
        $resellerId = $reseller['id'];
    } else {
        $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO resellers (name, email, phone, password, address, status, facebook_id) VALUES (?, ?, '', ?, '', 'pending', ?)");
        $stmt->execute([$name ?: $email, $email ?: $facebookId . '@fb.local', $randomPassword, $facebookId]);
        $resellerId = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('reseller', ?, ?)")
            ->execute(["নতুন রিসেলার (Facebook) - " . ($name ?: $email ?: $facebookId), "resellers"]);

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
    setFlash('error', 'Facebook লগইন ব্যর্থ হয়েছে। (' . $e->getMessage() . ')');
    redirect(baseUrl('resell/login.php'));
}
