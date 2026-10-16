<?php
/**
 * Kilagbo Affiliate Portal - Registration Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অ্যাফিলিয়েট রেজিস্ট্রেশন';

// Redirect if already logged in
if (isAffiliatorLoggedIn()) {
    redirect(baseUrl('affiliate/'));
}

$error = '';
$success = '';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = sanitize($_POST['address'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'সব প্রয়োজনীয় ফিল্ড পূরণ করুন';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'সঠিক ইমেইল দিন';
    } elseif (strlen($password) < 6) {
        $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে';
    } elseif ($password !== $confirmPassword) {
        $error = 'পাসওয়ার্ড মিলছে না';
    } elseif (isAffiliatorCredentialRejected($email, $phone)) {
        $error = 'এই ইমেইল বা ফোন নম্বর দিয়ে আগে আবেদন প্রত্যাখ্যান করা হয়েছে। অন্য তথ্য দিয়ে চেষ্টা করুন।';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM affiliators WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'এই ইমেইল দিয়ে আগে অ্যাকাউন্ট তৈরি হয়েছে';
        } else {
            // Check if phone exists
            $stmt = $pdo->prepare("SELECT id FROM affiliators WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'এই ফোন নম্বর দিয়ে আগে অ্যাকাউন্ট তৈরি হয়েছে';
            } else {
                // Create affiliator
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO affiliators (name, email, phone, password, address, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$name, $email, $phone, $hashedPassword, $address]);
                
                $affiliatorId = $pdo->lastInsertId();
                
                // Add notification for admin
                $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('affiliator', ?, ?)")
                    ->execute(["নতুন অ্যাফিলিয়েট আবেদন - $name", "affiliators"]);
                
                $_SESSION['affiliator_id'] = $affiliatorId;
                redirect(baseUrl('affiliate/pending.php'));
            }
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
        max-width: 480px;
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
    
    .form-floating input,
    .form-floating textarea {
        border-radius: 10px;
        border: 2px solid #e9ecef;
    }
    
    .form-floating input {
        height: 55px;
    }
    
    .form-floating input:focus,
    .form-floating textarea:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16,185,129,0.15);
    }
    
    .btn-register {
        width: 100%;
        padding: 14px;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 10px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
        color: #fff;
        transition: all 0.3s ease;
        margin-top: 10px;
    }
    
    .btn-register:hover {
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
    
    .info-note {
        background: #ecfdf5;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        color: #065f46;
    }
    
    .info-note i {
        color: #10b981;
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
                <h1>অ্যাফিলিয়েট রেজিস্ট্রেশন</h1>
                <p>নতুন অ্যাফিলিয়েট অ্যাকাউন্ট তৈরি করুন</p>
            </div>
            
            <div class="info-note">
                <i class="bi bi-info-circle me-2"></i>
                রেজিস্ট্রেশনের পর আপনার অ্যাকাউন্ট অ্যাডমিনের অনুমোদনের জন্য অপেক্ষা করবে।
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php showFlash(); ?>
            
            <form method="POST">
                <div class="form-floating">
                    <input type="text" class="form-control" id="name" name="name" placeholder="পুরো নাম" required value="<?php echo $_POST['name'] ?? ''; ?>">
                    <label for="name"><i class="bi bi-person me-2"></i>পুরো নাম *</label>
                </div>
                
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="ইমেইল" required value="<?php echo $_POST['email'] ?? ''; ?>">
                    <label for="email"><i class="bi bi-envelope me-2"></i>ইমেইল *</label>
                </div>
                
                <div class="form-floating">
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="মোবাইল নম্বর" required value="<?php echo $_POST['phone'] ?? ''; ?>">
                    <label for="phone"><i class="bi bi-phone me-2"></i>মোবাইল নম্বর *</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="পাসওয়ার্ড" required>
                    <label for="password"><i class="bi bi-lock me-2"></i>পাসওয়ার্ড *</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="পাসওয়ার্ড নিশ্চিত করুন" required>
                    <label for="confirm_password"><i class="bi bi-lock-fill me-2"></i>পাসওয়ার্ড নিশ্চিত করুন *</label>
                </div>
                
                <div class="form-floating">
                    <textarea class="form-control" id="address" name="address" placeholder="ঠিকানা" style="height: 80px"><?php echo $_POST['address'] ?? ''; ?></textarea>
                    <label for="address"><i class="bi bi-geo-alt me-2"></i>ঠিকানা (ঐচ্ছিক)</label>
                </div>
                
                <button type="submit" class="btn btn-register">
                    <i class="bi bi-person-plus me-2"></i>রেজিস্ট্রেশন করুন
                </button>
            </form>
            
            <div class="auth-footer">
                অ্যাকাউন্ট আছে? <a href="<?php echo baseUrl('affiliate/login.php'); ?>">লগইন করুন</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
