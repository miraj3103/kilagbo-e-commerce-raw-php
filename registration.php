<?php
/**
 * Kilagbo - User Registration
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';
require_once __DIR__ . '/wholesale/includes/access.php';
$wholesaleIntent = (($_GET['wholesale'] ?? $_POST['wholesale'] ?? '') === '1');
if ($wholesaleIntent && !wholesaleRequestsReady($pdo)) {
    http_response_code(503);
    exit('Wholesale approval migration is required before wholesale registration.');
}

if (isUserLoggedIn()) {
    redirect(baseUrl($wholesaleIntent ? 'wholesale/access.php' : 'account'));
}

$currentPage = 'retail';
$pageTitle = 'রেজিস্ট্রেশন';
$error = '';

// Normal Retail registration preserves its existing social signup options.
if (!$wholesaleIntent) {
// Get social login settings
$googleClientId = getSettings('google_client_id');
$googleClientSecret = getSettings('google_client_secret');
$googleLoginActive = getSettings('google_login_active') === '1';
$facebookAppId = getSettings('facebook_app_id');
$facebookAppSecret = getSettings('facebook_app_secret');
$facebookLoginActive = getSettings('facebook_login_active') === '1';
$showGoogleLogin = $googleLoginActive && !empty($googleClientId) && !empty($googleClientSecret);
$showFacebookLogin = $facebookLoginActive && !empty($facebookAppId) && !empty($facebookAppSecret);
$socialLoginEnabled = $showGoogleLogin || $showFacebookLogin;

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = $wholesaleIntent ? sanitize($_POST['address'] ?? '') : '';
    
    if (empty($name) || empty($email) || empty($password) || ($wholesaleIntent && empty($phone))) {
        $error = 'সকল প্রয়োজনীয় ফিল্ড পূরণ করুন';
    } elseif ($password !== $confirmPassword) {
        $error = 'পাসওয়ার্ড মিলছে না';
    } elseif (strlen($password) < 6) {
        $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষর হতে হবে';
    } else {
        // Check if email exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            $error = 'এই ইমেইল দিয়ে আগেই অ্যাকাউন্ট আছে';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
            if ($wholesaleIntent) {
                // Registration + approval request are atomic for wholesale signups.
                try {
                    $pdo->beginTransaction();
                    $wholesaleStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, address) VALUES (?, ?, ?, ?, ?)");
                    $wholesaleStmt->execute([$name, $email, $phone, $hashedPassword, $address]);
                    $userId = (int)$pdo->lastInsertId();
                    wholesaleSubmitRequest($pdo, $userId);
                    $pdo->commit();
                    wholesaleNotifyNewRequest($userId);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'পাইকারি রেজিস্ট্রেশন সম্পন্ন হয়নি। আবার চেষ্টা করুন।';
                }
            } else {
                $stmt->execute([$name, $email, $phone, $hashedPassword]);
                $userId = (int)$pdo->lastInsertId();
            }
            if (!$error) {
            // Auto login
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            // Merge session cart
            $sessionId = getSessionId();
            $pdo->prepare("UPDATE cart SET user_id = ?, session_id = NULL WHERE session_id = ?")
                ->execute([$userId, $sessionId]);
            $pdo->prepare("UPDATE wishlist SET user_id = ?, session_id = NULL WHERE session_id = ?")
                ->execute([$userId, $sessionId]);
            
            setFlash('success', 'অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে');
            redirect(baseUrl($wholesaleIntent ? 'wholesale/access.php' : 'account'));
            } // completed registration
        }
    }
}

// Wholesale registration uses the existing Reseller auth layout; regular Retail stays unchanged.
if ($wholesaleIntent) {
    $pageTitle = 'পাইকারি রেজিস্ট্রেশন';
    include __DIR__ . '/resell/includes/header-auth.php';
    ?>
<style>
    .auth-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    .auth-container { width: 100%; max-width: 480px; }
    .auth-card {
        background: #fff;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .auth-logo { text-align: center; margin-bottom: 30px; }
    .auth-logo img { height: 50px; max-width: 100%; object-fit: contain; }
    .auth-logo h1 { font-size: 1.5rem; font-weight: 700; color: #333; margin-top: 15px; }
    .auth-logo p { color: #666; font-size: .9rem; }
    .form-floating { margin-bottom: 15px; }
    .form-floating input, .form-floating textarea {
        border-radius: 10px;
        border: 2px solid #e9ecef;
    }
    .form-floating input { height: 55px; }
    .form-floating input:focus, .form-floating textarea:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,.15);
    }
    .btn-register {
        width: 100%; padding: 14px; font-size: 1rem; font-weight: 600;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none; color: #fff; margin-top: 10px;
        transition: all .3s ease;
    }
    .btn-register:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(102,126,234,.4);
        color: #fff;
    }
    .auth-footer { text-align: center; margin-top: 25px; color: #666; }
    .auth-footer a { color: #667eea; text-decoration: none; font-weight: 600; }
    .alert { border-radius: 10px; margin-bottom: 20px; }
    .back-home {
        position: absolute; top: 20px; left: 20px; color: #fff;
        text-decoration: none; font-weight: 500;
        display: flex; align-items: center; gap: 5px;
    }
    .back-home:hover { color: #fff; opacity: .8; }
    .info-note {
        background: #f8f9fa; border-radius: 10px;
        padding: 15px; margin-bottom: 20px;
        font-size: .9rem; color: #666;
    }
    .info-note i { color: #667eea; }
    @media (max-width: 575px) {
        .auth-page { padding: 65px 15px 30px; }
        .auth-card { padding: 25px 20px; }
    }
</style>
<div class="auth-page">
    <a href="<?php echo baseUrl(); ?>" class="back-home"><i class="bi bi-arrow-left"></i> হোমে ফিরুন</a>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <?php if (getSettings('site_logo')): ?>
                    <img src="<?php echo uploadUrl(getSettings('site_logo')); ?>" alt="Kilagbo">
                <?php endif; ?>
                <h1>পাইকারি রেজিস্ট্রেশন</h1>
                <p>নতুন পাইকারি অ্যাকাউন্ট তৈরি করুন</p>
            </div>
            <div class="info-note">
                <i class="bi bi-info-circle me-2"></i>
                রেজিস্ট্রেশনের পর আপনার পাইকারি অ্যাক্সেস সুপার অ্যাডমিনের অনুমোদনের জন্য অপেক্ষা করবে।
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php showFlash(); ?>
            <form method="POST" action="<?php echo baseUrl('registration.php?wholesale=1'); ?>">
                <input type="hidden" name="wholesale" value="1">
                <div class="form-floating">
                    <input type="text" class="form-control" id="wholesale_name" name="name" placeholder="পুরো নাম" required maxlength="100" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="wholesale_name"><i class="bi bi-person me-2"></i>পুরো নাম *</label>
                </div>
                <div class="form-floating">
                    <input type="email" class="form-control" id="wholesale_email" name="email" placeholder="ইমেইল" required maxlength="255" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="wholesale_email"><i class="bi bi-envelope me-2"></i>ইমেইল *</label>
                </div>
                <div class="form-floating">
                    <input type="tel" class="form-control" id="wholesale_phone" name="phone" placeholder="মোবাইল নম্বর" required maxlength="20" value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="wholesale_phone"><i class="bi bi-phone me-2"></i>মোবাইল নম্বর *</label>
                </div>
                <div class="form-floating">
                    <input type="password" class="form-control" id="wholesale_password" name="password" placeholder="পাসওয়ার্ড" required minlength="6" autocomplete="new-password">
                    <label for="wholesale_password"><i class="bi bi-lock me-2"></i>পাসওয়ার্ড *</label>
                </div>
                <div class="form-floating">
                    <input type="password" class="form-control" id="wholesale_confirm_password" name="confirm_password" placeholder="পাসওয়ার্ড নিশ্চিত করুন" required minlength="6" autocomplete="new-password">
                    <label for="wholesale_confirm_password"><i class="bi bi-lock-fill me-2"></i>পাসওয়ার্ড নিশ্চিত করুন *</label>
                </div>
                <div class="form-floating">
                    <textarea class="form-control" id="wholesale_address" name="address" placeholder="ঠিকানা" style="height: 80px"><?php echo htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <label for="wholesale_address"><i class="bi bi-geo-alt me-2"></i>ঠিকানা (ঐচ্ছিক)</label>
                </div>
                <button type="submit" class="btn btn-register"><i class="bi bi-person-plus me-2"></i>রেজিস্ট্রেশন করুন</button>
            </form>
            <div class="auth-footer">অ্যাকাউন্ট আছে? <a href="<?php echo baseUrl('login.php?wholesale=1'); ?>">লগইন করুন</a></div>
        </div>
    </div>
</div>
<?php
    include __DIR__ . '/resell/includes/footer-auth.php';
    exit;
}

include 'includes/header.php';
?>

<style>
    .auth-page {
        min-height: 60vh;
        display: flex;
        align-items: center;
        padding: 60px 0;
    }
    
    .auth-card {
        background: #fff;
        border-radius: 16px;
        padding: 40px;
        max-width: 480px;
        margin: 0 auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .auth-card h2 {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .auth-card .form-control {
        padding: 12px 16px;
    }
    
    .auth-card .btn {
        padding: 12px;
        font-size: 1rem;
    }
    
    .auth-footer {
        text-align: center;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }
    
    /* Social Login Styles */
    .social-divider {
        display: flex;
        align-items: center;
        margin: 25px 0;
    }
    
    .social-divider::before,
    .social-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-color);
    }
    
    .social-divider span {
        padding: 0 15px;
        color: #888;
        font-size: 0.9rem;
    }
    
    .social-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .btn-social {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 1px solid #ddd;
    }
    
    .btn-google {
        background: #fff;
        color: #333;
    }
    
    .btn-google:hover {
        background: #f5f5f5;
        color: #333;
        border-color: #ccc;
    }
    
    .btn-google svg {
        width: 20px;
        height: 20px;
    }
    
    .btn-facebook {
        background: #1877f2;
        color: #fff;
        border-color: #1877f2;
    }
    
    .btn-facebook:hover {
        background: #166fe5;
        color: #fff;
    }
    
    .btn-facebook i {
        font-size: 1.2rem;
    }
    
    .btn-social.disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<div class="auth-page">
    <div class="container">
        <div class="auth-card">
            <h2><i class="bi bi-person-plus me-2"></i><?php echo $wholesaleIntent ? 'পাইকারি রেজিস্ট্রেশন' : 'রেজিস্ট্রেশন'; ?></h2>
            <?php if ($wholesaleIntent): ?><p class="text-muted text-center">রেজিস্ট্রেশনের পর Super Admin অনুমোদন দিলে পাইকারি দাম দেখতে পারবেন।</p><?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($wholesaleIntent): ?><input type="hidden" name="wholesale" value="1"><?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">পুরো নাম *</label>
                    <input type="text" name="name" class="form-control" placeholder="আপনার নাম দিন" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ইমেইল *</label>
                    <input type="email" name="email" class="form-control" placeholder="আপনার ইমেইল দিন" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ফোন নম্বর</label>
                    <input type="tel" name="phone" class="form-control" placeholder="01XXXXXXXXX">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">পাসওয়ার্ড *</label>
                    <input type="password" name="password" class="form-control" placeholder="কমপক্ষে ৬ অক্ষর" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">পাসওয়ার্ড নিশ্চিত করুন *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="আবার পাসওয়ার্ড দিন" required>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100">
                    <i class="bi bi-person-check me-2"></i>রেজিস্ট্রেশন করুন
                </button>
            </form>
            
            <div class="social-divider">
                <span>অথবা</span>
            </div>
            
            <div class="social-buttons">
                <?php
                $googleAuthUrl = $googleClientId ? 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                    'client_id' => $googleClientId,
                    'redirect_uri' => baseUrl('auth-callback?provider=google'),
                    'response_type' => 'code',
                    'scope' => 'email profile',
                    'access_type' => 'online',
                    'state' => $wholesaleIntent ? 'wholesale' : ''
                ]) : '#';
                ?>
                <a href="<?php echo $googleAuthUrl; ?>" class="btn-social btn-google<?php echo !$googleClientId ? ' disabled' : ''; ?>" <?php echo !$googleClientId ? 'onclick="alert(\'Google লগইন এখনো সেটআপ করা হয়নি\'); return false;"' : ''; ?>>
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Google দিয়ে সাইন আপ করুন
                </a>
                
                <?php
                $facebookAuthUrl = $facebookAppId ? 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
                    'client_id' => $facebookAppId,
                    'redirect_uri' => baseUrl('auth-callback?provider=facebook'),
                    'scope' => 'email,public_profile',
                    'response_type' => 'code',
                    'state' => $wholesaleIntent ? 'wholesale' : ''
                ]) : '#';
                ?>
                <a href="<?php echo $facebookAuthUrl; ?>" class="btn-social btn-facebook<?php echo !$facebookAppId ? ' disabled' : ''; ?>" <?php echo !$facebookAppId ? 'onclick="alert(\'Facebook লগইন এখনো সেটআপ করা হয়নি\'); return false;"' : ''; ?>>
                    <i class="bi bi-facebook"></i>
                    Facebook দিয়ে সাইন আপ করুন
                </a>
            </div>
            
            <div class="auth-footer">
                <p class="mb-0">অ্যাকাউন্ট আছে? <a href="<?php echo baseUrl($wholesaleIntent ? 'login.php?wholesale=1' : 'login'); ?>">লগইন করুন</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
