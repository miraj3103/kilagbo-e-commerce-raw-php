<?php
/**
 * Kilagbo Seller Portal - Login Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'সেলার লগইন';

// Redirect if already logged in
if (isSellerLoggedIn()) {
    $seller = getSellerData();
    if ($seller && $seller['status'] === 'approved') {
        redirect(baseUrl('seller/index.php'));
    } elseif ($seller && $seller['status'] === 'pending') {
        redirect(baseUrl('seller/pending.php'));
    }
}

// Check remember me cookie
if (!isSellerLoggedIn() && isset($_COOKIE['seller_remember'])) {
    $token = $_COOKIE['seller_remember'];
    $stmt = $pdo->prepare("SELECT * FROM sellers WHERE remember_token = ? AND status = 'approved'");
    $stmt->execute([$token]);
    $seller = $stmt->fetch();
    
    if ($seller) {
        $_SESSION['seller_id'] = $seller['id'];
        $pdo->prepare("UPDATE sellers SET last_login = NOW() WHERE id = ?")->execute([$seller['id']]);
        redirect(baseUrl('seller/index.php'));
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
        $stmt = $pdo->prepare("SELECT * FROM sellers WHERE email = ?");
        $stmt->execute([$email]);
        $seller = $stmt->fetch();
        
        if ($seller && password_verify($password, $seller['password'])) {
            if ($seller['status'] === 'rejected') {
                $error = 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে। নতুন তথ্য দিয়ে নতুন অ্যাকাউন্ট তৈরি করুন।';
            } else {
                $_SESSION['seller_id'] = $seller['id'];
                $pdo->prepare("UPDATE sellers SET last_login = NOW() WHERE id = ?")->execute([$seller['id']]);
                
                // Set remember cookie for auto-login
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE sellers SET remember_token = ? WHERE id = ?")->execute([$token, $seller['id']]);
                    setcookie('seller_remember', $token, time() + (86400 * 30), '/', '', false, true);
                    // Also save email and encrypted password for pre-filling login form
                    setcookie('seller_remember_email', $email, time() + (86400 * 30), '/', '', false, false);
                    setcookie('seller_remember_pass', base64_encode($password), time() + (86400 * 30), '/', '', false, true);
                }
                
                if ($seller['status'] === 'pending') {
                    redirect(baseUrl('seller/pending.php'));
                } else {
                    redirect(baseUrl('seller/index.php'));
                }
            }
        } else {
            $error = 'ইমেইল বা পাসওয়ার্ড সঠিক নয়';
        }
    }
}

include __DIR__ . '/includes/header-auth.php';
?>

<style>
    .auth-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
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
        border-color: #16a34a;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
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
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        border: none;
        color: #fff;
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(22, 163, 74, 0.4);
        color: #fff;
    }
    
    .auth-footer {
        text-align: center;
        margin-top: 25px;
        color: #666;
    }
    
    .auth-footer a {
        color: #16a34a;
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
                <?php if (getSettings('site_logo')): ?>
                <img src="<?php echo uploadUrl(getSettings('site_logo')); ?>" alt="<?php echo getSettings('site_name'); ?>">
                <?php endif; ?>
                <h1>সেলার লগইন</h1>
                <p>আপনার সেলার অ্যাকাউন্টে প্রবেশ করুন</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php showFlash(); ?>
            
            <form method="POST">
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="ইমেইল" required value="<?php echo $_POST['email'] ?? ($_COOKIE['seller_remember_email'] ?? ''); ?>">
                    <label for="email"><i class="bi bi-envelope me-2"></i>ইমেইল</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="পাসওয়ার্ড" required value="<?php echo isset($_COOKIE['seller_remember_pass']) ? base64_decode($_COOKIE['seller_remember_pass']) : ''; ?>">
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
            
            <div class="auth-footer">
                অ্যাকাউন্ট নেই? <a href="<?php echo baseUrl('seller/register.php'); ?>">রেজিস্টার করুন</a>
                <br><br>
                <a href="<?php echo baseUrl('seller/welcome.php'); ?>" class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>সেলার সম্পর্কে জানুন
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
