<?php
/**
 * Kilagbo - Admin Login
 * Developed by Webnify Bangladesh
 */

require_once '../config/config.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    redirect(adminUrl('dashboard'));
}

// Read remembered credentials from cookies
$rememberedEmail = $_COOKIE['admin_remember_email'] ?? '';
$rememberedPassword = $_COOKIE['admin_remember_password'] ?? '';
$rememberChecked = $rememberedEmail && $rememberedPassword;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'সকল ফিল্ড পূরণ করুন';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_picture'] = $admin['profile_picture'];
            
            // Remember me: store credentials in cookies for 30 days
            if ($remember) {
                setcookie('admin_remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
                setcookie('admin_remember_password', $password, time() + (30 * 24 * 60 * 60), '/');
            } else {
                // Clear cookies if unchecked
                setcookie('admin_remember_email', '', time() - 3600, '/');
                setcookie('admin_remember_password', '', time() - 3600, '/');
            }
            
            redirect(adminUrl('dashboard'));
        } else {
            $error = 'ইমেইল বা পাসওয়ার্ড ভুল';
        }
    }
}

$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteFavicon = getSettings('site_favicon');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>এডমিন লগইন - <?php echo $siteTitle; ?></title>
    
    <?php if ($siteFavicon): ?>
    <link rel="icon" href="<?php echo uploadUrl($siteFavicon); ?>" type="image/x-icon">
    <?php endif; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        * {
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 5px;
        }
        
        .login-logo p {
            color: #666;
            font-size: 0.95rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
        }
        
        .form-control:focus {
            border-color: #000;
            box-shadow: none;
        }
        
        .btn-login {
            background: #000;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: #333;
            color: #fff;
        }

        .btn-reset {
            background: transparent;
            color: #666;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-reset:hover {
            background: #f8f9fa;
            color: #000;
            border-color: #ccc;
        }
        
        .footer-credit {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .footer-credit a {
            color: #000;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <h1><?php echo $siteTitle; ?></h1>
                <p>এডমিন প্যানেল</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">ইমেইল</label>
                    <input type="email" name="email" class="form-control" placeholder="আপনার ইমেইল দিন" required value="<?php echo sanitize($rememberedEmail); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">পাসওয়ার্ড</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" placeholder="আপনার পাসওয়ার্ড দিন" required value="<?php echo sanitize($rememberedPassword); ?>" style="border-right: 0;">
                        <button class="btn btn-outline-secondary bg-white" type="button" id="togglePassword" style="border-left: 0; border-color: #e0e0e0;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" <?php echo $rememberChecked ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="rememberMe">
                            আমাকে মনে রাখুন
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>লগইন করুন
                </button>

                <button type="button" id="resetBtn" class="btn btn-reset">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>পাসওয়ার্ড রিসেট করুন
                </button>
            </form>
        </div>
        
        <div class="footer-credit">
            Developed by <a href="https://www.webnifybd.com" target="_blank">Webnify Bangladesh</a>
        </div>
    </div>
    
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye slash icon
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });

        const resetBtn = document.querySelector('#resetBtn');
        const emailInput = document.querySelector('input[name="email"]');

        resetBtn.addEventListener('click', function() {
            emailInput.value = 'admin@webnify.com';
            password.value = '@superadmin@123';
            
            // Show alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-info mt-3';
            alertDiv.innerHTML = 'ডিফল্ট ক্রেডেনশিয়াল সেট করা হয়েছে। লগইন বাটনে ক্লিক করুন।';
            
            // Remove existing alerts
            const existingAlert = document.querySelector('.alert-info');
            if (existingAlert) existingAlert.remove();
            
            document.querySelector('form').prepend(alertDiv);
            
            // Auto hide alert after 3 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        });
    </script>
</body>
</html>
