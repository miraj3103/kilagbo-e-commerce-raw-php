<?php
/**
 * Kilagbo Reseller Portal - Login Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'রিসেলার লগইন';

// Redirect if already logged in
if (isResellerLoggedIn()) {
    $reseller = getResellerData();
    if ($reseller && $reseller['status'] === 'approved') {
        redirect(baseUrl('resell/'));
    } elseif ($reseller && $reseller['status'] === 'pending') {
        redirect(baseUrl('resell/pending.php'));
    }
}

// Check remember me cookie
if (!isResellerLoggedIn() && isset($_COOKIE['reseller_remember'])) {
    $token = $_COOKIE['reseller_remember'];
    $stmt = $pdo->prepare("SELECT * FROM resellers WHERE remember_token = ? AND status = 'approved'");
    $stmt->execute([$token]);
    $reseller = $stmt->fetch();
    
    if ($reseller) {
        $_SESSION['reseller_id'] = $reseller['id'];
        $pdo->prepare("UPDATE resellers SET last_login = NOW() WHERE id = ?")->execute([$reseller['id']]);
        redirect(baseUrl('resell/'));
    }
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'সব ফিল্ড পূরণ করুন';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM resellers WHERE email = ?");
        $stmt->execute([$email]);
        $reseller = $stmt->fetch();
        
        if ($reseller && password_verify($password, $reseller['password'])) {
            if ($reseller['status'] === 'rejected') {
                $error = 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে। নতুন তথ্য দিয়ে নতুন অ্যাকাউন্ট তৈরি করুন।';
            } else {
                $_SESSION['reseller_id'] = $reseller['id'];
                $pdo->prepare("UPDATE resellers SET last_login = NOW() WHERE id = ?")->execute([$reseller['id']]);
                
                // Set remember cookie
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE resellers SET remember_token = ? WHERE id = ?")->execute([$token, $reseller['id']]);
                    setcookie('reseller_remember', $token, time() + (86400 * 30), '/', '', false, true);
                }
                
                if ($reseller['status'] === 'pending') {
                    redirect(baseUrl('resell/pending.php'));
                } else {
                    redirect(baseUrl('resell/'));
                }
            }
        } else {
            $error = 'ইমেইল বা পাসওয়ার্ড সঠিক নয়';
        }
    }
}

// Get social login settings
$googleActive = getSettings('google_login_active') == '1';
$facebookActive = getSettings('facebook_login_active') == '1';

include __DIR__ . '/includes/header-auth.php';
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
    
    .auth-container {
        width: 100%;
        max-width: 420px;
    }
    
    .auth-card {
        background: #fff;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .auth-logo {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .auth-logo img {
        height: 50px;
    }
    
    .auth-logo h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin-top: 15px;
    }
    
    .auth-logo p {
        color: #666;
        font-size: 0.9rem;
    }
    
    .form-floating {
        margin-bottom: 15px;
    }
    
    .form-floating input {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        height: 55px;
    }
    
    .form-floating input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
    }
    
    .form-check {
        margin-bottom: 20px;
    }
    
    .btn-login {
        width: 100%;
        padding: 14px;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: #fff;
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(102,126,234,0.4);
        color: #fff;
    }
    
    .divider {
        display: flex;
        align-items: center;
        margin: 25px 0;
        color: #999;
    }
    
    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e9ecef;
    }
    
    .divider span {
        padding: 0 15px;
        font-size: 0.85rem;
    }
    
    .social-login {
        display: flex;
        gap: 10px;
    }
    
    .social-login a {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .social-login .btn-google {
        background: #fff;
        border: 2px solid #e9ecef;
        color: #333;
    }
    
    .social-login .btn-google:hover {
        background: #f8f9fa;
        border-color: #ddd;
    }
    
    .social-login .btn-facebook {
        background: #1877f2;
        color: #fff;
    }
    
    .social-login .btn-facebook:hover {
        background: #166fe5;
    }
    
    .auth-footer {
        text-align: center;
        margin-top: 25px;
        color: #666;
    }
    
    .auth-footer a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }
    
    .alert {
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .back-home {
        position: absolute;
        top: 20px;
        left: 20px;
        color: #fff;
        text-decoration: none;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .back-home:hover {
        color: #fff;
        opacity: 0.8;
    }
</style>

<div class="auth-page">
    <a href="<?php echo baseUrl(); ?>" class="back-home">
        <i class="bi bi-arrow-left"></i> হোমে ফিরুন
    </a>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <h1>রিসেলার লগইন</h1>
                <p>আপনার রিসেলার অ্যাকাউন্টে প্রবেশ করুন</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php showFlash(); ?>
            
            <form method="POST">
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="ইমেইল" required value="<?php echo $_POST['email'] ?? ''; ?>">
                    <label for="email"><i class="bi bi-envelope me-2"></i>ইমেইল</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="পাসওয়ার্ড" required>
                    <label for="password"><i class="bi bi-lock me-2"></i>পাসওয়ার্ড</label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">আমাকে মনে রাখুন</label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>লগইন করুন
                </button>
            </form>
            
            <?php if ($googleActive || $facebookActive): ?>
            <div class="divider"><span>অথবা</span></div>
            
            <div class="social-login">
                <?php if ($googleActive): ?>
                <a href="<?php echo baseUrl('resell/auth/google.php'); ?>" class="btn-google">
                    <i class="bi bi-google"></i> গুগল
                </a>
                <?php endif; ?>
                
                <?php if ($facebookActive): ?>
                <a href="<?php echo baseUrl('resell/auth/facebook.php'); ?>" class="btn-facebook">
                    <i class="bi bi-facebook"></i> ফেসবুক
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                অ্যাকাউন্ট নেই? <a href="<?php echo baseUrl('resell/register.php'); ?>">রেজিস্টার করুন</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
