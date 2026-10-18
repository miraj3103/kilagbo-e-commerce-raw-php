<?php
/**
 * Kilagbo - Social Auth Callback Handler
 * Developed by Webnify Bangladesh
 * 
 * Handles Google and Facebook OAuth callbacks
 */

require_once 'config/config.php';

$provider = $_GET['provider'] ?? '';
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';
$state = $_GET['state'] ?? '';

$isReseller = ($state === 'resell');
$isWholesale = ($state === 'wholesale');

if ($error) {
    setFlash('error', 'লগইন বাতিল করা হয়েছে');
    redirect(baseUrl($isReseller ? 'resell/login.php' : 'login'));
}

if (!$code || !in_array($provider, ['google', 'facebook'])) {
    redirect(baseUrl($isReseller ? 'resell/login.php' : 'login'));
}

// Get OAuth settings
$googleClientId = getSettings('google_client_id');
$googleClientSecret = getSettings('google_client_secret');
$facebookAppId = getSettings('facebook_app_id');
$facebookAppSecret = getSettings('facebook_app_secret');

$redirectUri = baseUrl('auth-callback?provider=' . $provider);

try {
    if ($provider === 'google') {
        // Exchange code for access token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $tokenData = [
            'code' => $code,
            'client_id' => $googleClientId,
            'client_secret' => $googleClientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $tokenInfo = json_decode($response, true);
        
        if (!isset($tokenInfo['access_token'])) {
            throw new Exception('Failed to get access token');
        }
        
        // Get user info
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenInfo['access_token'];
        $userInfo = json_decode(file_get_contents($userInfoUrl), true);
        
        $email = $userInfo['email'] ?? '';
        $name = $userInfo['name'] ?? '';
        $socialId = $userInfo['id'] ?? '';
        $picture = $userInfo['picture'] ?? '';
        
    } elseif ($provider === 'facebook') {
        // Exchange code for access token
        $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id' => $facebookAppId,
            'client_secret' => $facebookAppSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code
        ]);
        
        $tokenInfo = json_decode(file_get_contents($tokenUrl), true);
        
        if (!isset($tokenInfo['access_token'])) {
            throw new Exception('Failed to get access token');
        }
        
        // Get user info
        $userInfoUrl = 'https://graph.facebook.com/me?fields=id,name,email,picture&access_token=' . $tokenInfo['access_token'];
        $userInfo = json_decode(file_get_contents($userInfoUrl), true);
        
        $email = $userInfo['email'] ?? '';
        $name = $userInfo['name'] ?? '';
        $socialId = $userInfo['id'] ?? '';
        $picture = $userInfo['picture']['data']['url'] ?? '';
    }
    
    if (empty($email)) {
        throw new Exception('Email not provided by ' . $provider);
    }
    
    // Check if user exists with this email (reseller or regular user)
    if ($isReseller) {
        // For resellers, only look in resellers table
        $stmt = $pdo->prepare("SELECT * FROM resellers WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Update social login info if not set
            if (empty($user[$provider . '_id'])) {
                $pdo->prepare("UPDATE resellers SET {$provider}_id = ? WHERE id = ?")
                    ->execute([$socialId, $user['id']]);
            }
            $userId = $user['id'];
            $userName = $user['name'];
        } else {
            // Create new reseller
            $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO resellers (name, email, {$provider}_id, password, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$name, $email, $socialId, $randomPassword]);
            $userId = $pdo->lastInsertId();
            $userName = $name;
            
            // Add notification for admin
            $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('reseller', ?, ?)")
                ->execute(["নতুন রিসেলার (Social) - $name", "resellers"]);
        }
    } else {
        // Regular retail user logic
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Update social login info if not set
            if (empty($user[$provider . '_id'])) {
                $pdo->prepare("UPDATE users SET {$provider}_id = ? WHERE id = ?")
                    ->execute([$socialId, $user['id']]);
            }
            $userId = $user['id'];
            $userName = $user['name'];
        } else {
            // Create new user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, {$provider}_id, password) VALUES (?, ?, ?, ?)");
            // Generate random password for social login users
            $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt->execute([$name, $email, $socialId, $randomPassword]);
            $userId = $pdo->lastInsertId();
            $userName = $name;
        }
    }
    
    // Set session and redirect
    if ($isReseller) {
        // Reseller session setup
        $_SESSION['reseller_id'] = $userId;
        $pdo->prepare("UPDATE resellers SET last_login = NOW() WHERE id = ?")->execute([$userId]);
        
        // Get reseller data for status check
        $stmt = $pdo->prepare("SELECT * FROM resellers WHERE id = ?");
        $stmt->execute([$userId]);
        $resellerData = $stmt->fetch();
        
        if ($resellerData['status'] === 'pending') {
            redirect(baseUrl('resell/pending.php'));
        } elseif ($resellerData['status'] === 'rejected') {
            unset($_SESSION['reseller_id']);
            setFlash('error', 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে।');
            redirect(baseUrl('resell/login.php'));
        } else {
            // Approved reseller
            redirect(baseUrl('resell/'));
        }
    } else {
        // Retail user session setup
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $userName;
        $_SESSION['user_email'] = $email;
        
        // Merge session cart to user cart
        $sessionId = getSessionId();
        $pdo->prepare("UPDATE cart SET user_id = ?, session_id = NULL WHERE session_id = ? AND user_id IS NULL")
            ->execute([$userId, $sessionId]);
        $pdo->prepare("UPDATE wishlist SET user_id = ?, session_id = NULL WHERE session_id = ? AND user_id IS NULL")
            ->execute([$userId, $sessionId]);
        
        if ($isWholesale) {
            require_once __DIR__ . '/wholesale/includes/access.php';
            if (wholesaleSubmitRequest($pdo, (int)$userId)) {
                wholesaleNotifyNewRequest((int)$userId);
            }
        }
        setFlash('success', $isWholesale ? 'পাইকারি আবেদনের অবস্থা দেখুন' : 'সফলভাবে লগইন হয়েছে');
        redirect(baseUrl($isWholesale ? 'wholesale/access.php' : 'account'));
    }
    
} catch (Exception $e) {
    setFlash('error', 'লগইন করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।');
    redirect(baseUrl($isReseller ? 'resell/login.php' : 'login'));
}
