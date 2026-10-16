<?php
/**
 * Kilagbo Affiliate Portal - Login Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অ্যাফিলিয়েট লগইন';

// Redirect if already logged in
if (isAffiliatorLoggedIn()) {
    $affiliator = getAffiliatorData();
    if ($affiliator && $affiliator['status'] === 'approved') {
        redirect(baseUrl('affiliate/'));
    } elseif ($affiliator && $affiliator['status'] === 'pending') {
        redirect(baseUrl('affiliate/pending.php'));
    }
}

// Check remember me cookie
if (!isAffiliatorLoggedIn() && isset($_COOKIE['affiliator_remember'])) {
    $token = $_COOKIE['affiliator_remember'];
    $stmt = $pdo->prepare("SELECT * FROM affiliators WHERE remember_token = ? AND status = 'approved'");
    $stmt->execute([$token]);
    $affiliator = $stmt->fetch();
    
    if ($affiliator) {
        $_SESSION['affiliator_id'] = $affiliator['id'];
        $pdo->prepare("UPDATE affiliators SET last_login = NOW() WHERE id = ?")->execute([$affiliator['id']]);
        redirect(baseUrl('affiliate/'));
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
        $stmt = $pdo->prepare("SELECT * FROM affiliators WHERE email = ?");
        $stmt->execute([$email]);
        $affiliator = $stmt->fetch();
        
        if ($affiliator && password_verify($password, $affiliator['password'])) {
            if ($affiliator['status'] === 'rejected') {
                $error = 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে। নতুন তথ্য দিয়ে নতুন অ্যাকাউন্ট তৈরি করুন।';
            } else {
                $_SESSION['affiliator_id'] = $affiliator['id'];
                $pdo->prepare("UPDATE affiliators SET last_login = NOW() WHERE id = ?")->execute([$affiliator['id']]);
                
                // Set remember cookie for auto-login
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE affiliators SET remember_token = ? WHERE id = ?")->execute([$token, $affiliator['id']]);
                    setcookie('affiliator_remember', $token, time() + (86400 * 30), '/', '', false, true);
                    // Also save email and encrypted password for pre-filling login form
                    setcookie('affiliator_remember_email', $email, time() + (86400 * 30), '/', '', false, false);
                    setcookie('affiliator_remember_pass', base64_encode($password), time() + (86400 * 30), '/', '', false, true);
                }
                
                if ($affiliator['status'] === 'pending') {
                    redirect(baseUrl('affiliate/pending.php'));
                } else {
                    redirect(baseUrl('affiliate/'));
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
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16,185,129,0.15);
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
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
        color: #fff;
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(16,185,129,0.4);
        color: #fff;
    }
    
    .auth-footer {
        text-align: center;
        margin-top: 25px;
        color: #666;
    }
    
    .auth-footer a {
        color: #10b981;
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
                <h1>অ্যাফিলিয়েট লগইন</h1>
                <p>আপনার অ্যাফিলিয়েট অ্যাকাউন্টে প্রবেশ করুন</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php showFlash(); ?>
            
            <form method="POST">
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="ইমেইল" required value="<?php echo $_POST['email'] ?? ($_COOKIE['affiliator_remember_email'] ?? ''); ?>">
                    <label for="email"><i class="bi bi-envelope me-2"></i>ইমেইল</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="পাসওয়ার্ড" required value="<?php echo isset($_COOKIE['affiliator_remember_pass']) ? base64_decode($_COOKIE['affiliator_remember_pass']) : ''; ?>">
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
                অ্যাকাউন্ট নেই? <a href="<?php echo baseUrl('affiliate/register.php'); ?>">রেজিস্টার করুন</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
