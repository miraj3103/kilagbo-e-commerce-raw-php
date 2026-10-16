<?php
/**
 * Kilagbo Seller Portal - Registration Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'সেলার রেজিস্ট্রেশন';

// Redirect if already logged in
if (isSellerLoggedIn()) {
    redirect(baseUrl('seller/index.php'));
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
    $businessName = sanitize($_POST['business_name'] ?? '');
    $businessType = sanitize($_POST['business_type'] ?? '');
    $nidNumber = sanitize($_POST['nid_number'] ?? '');
    $tradeLicense = sanitize($_POST['trade_license'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $district = sanitize($_POST['district'] ?? '');
    $bankName = sanitize($_POST['bank_name'] ?? '');
    $bankAccountName = sanitize($_POST['bank_account_name'] ?? '');
    $bankAccountNumber = sanitize($_POST['bank_account_number'] ?? '');
    $bankBranch = sanitize($_POST['bank_branch'] ?? '');
    $bkashNumber = sanitize($_POST['bkash_number'] ?? '');
    $nagadNumber = sanitize($_POST['nagad_number'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($businessName)) {
        $error = 'সব প্রয়োজনীয় ফিল্ড পূরণ করুন';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'সঠিক ইমেইল দিন';
    } elseif (strlen($password) < 6) {
        $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে';
    } elseif ($password !== $confirmPassword) {
        $error = 'পাসওয়ার্ড মিলছে না';
    } elseif (isSellerCredentialRejected($email, $phone)) {
        $error = 'এই ইমেইল বা ফোন নম্বর দিয়ে আগে আবেদন প্রত্যাখ্যান করা হয়েছে। অন্য তথ্য দিয়ে চেষ্টা করুন।';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM sellers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'এই ইমেইল দিয়ে আগে অ্যাকাউন্ট তৈরি হয়েছে';
        } else {
            // Check if phone exists
            $stmt = $pdo->prepare("SELECT id FROM sellers WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'এই ফোন নম্বর দিয়ে আগে অ্যাকাউন্ট তৈরি হয়েছে';
            } else {
                // Create seller
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO sellers (
                        name, email, phone, password, business_name, business_type, 
                        nid_number, trade_license, address, district,
                        bank_name, bank_account_name, bank_account_number, bank_branch,
                        bkash_number, nagad_number, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $name, $email, $phone, $hashedPassword, $businessName, $businessType,
                    $nidNumber, $tradeLicense, $address, $district,
                    $bankName, $bankAccountName, $bankAccountNumber, $bankBranch,
                    $bkashNumber, $nagadNumber
                ]);
                
                $sellerId = $pdo->lastInsertId();
                
                // Add notification for admin
                $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('seller', ?, ?)")
                    ->execute(["নতুন সেলার আবেদন - $name", "sellers"]);
                
                $_SESSION['seller_id'] = $sellerId;
                redirect(baseUrl('seller/pending.php'));
            }
        }
    }
}

// Bangladesh districts
$districts = [
    'ঢাকা', 'চট্টগ্রাম', 'রাজশাহী', 'খুলনা', 'বরিশাল', 'সিলেট', 'রংপুর', 'ময়মনসিংহ',
    'কুমিল্লা', 'গাজীপুর', 'নারায়ণগঞ্জ', 'টাঙ্গাইল', 'কিশোরগঞ্জ', 'মানিকগঞ্জ', 'মুন্সীগঞ্জ',
    'নরসিংদী', 'শরীয়তপুর', 'মাদারীপুর', 'গোপালগঞ্জ', 'ফরিদপুর', 'রাজবাড়ী', 'ফেনী',
    'লক্ষ্মীপুর', 'নোয়াখালী', 'চাঁদপুর', 'ব্রাহ্মণবাড়িয়া', 'কক্সবাজার', 'রাঙ্গামাটি',
    'খাগড়াছড়ি', 'বান্দরবান', 'বগুড়া', 'জয়পুরহাট', 'নওগাঁ', 'নাটোর', 'চাঁপাইনবাবগঞ্জ',
    'পাবনা', 'সিরাজগঞ্জ', 'যশোর', 'সাতক্ষীরা', 'মেহেরপুর', 'নড়াইল', 'চুয়াডাঙ্গা',
    'কুষ্টিয়া', 'মাগুরা', 'ঝিনাইদহ', 'বাগেরহাট', 'পটুয়াখালী', 'পিরোজপুর', 'ঝালকাঠি',
    'বরগুনা', 'ভোলা', 'হবিগঞ্জ', 'মৌলভীবাজার', 'সুনামগঞ্জ', 'দিনাজপুর', 'ঠাকুরগাঁও',
    'পঞ্চগড়', 'নীলফামারী', 'লালমনিরহাট', 'কুড়িগ্রাম', 'গাইবান্ধা', 'জামালপুর',
    'শেরপুর', 'নেত্রকোণা'
];
sort($districts);

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
        max-width: 800px;
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
    
    .form-section {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .form-section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #16a34a;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-label {
        font-weight: 500;
        color: #333;
        margin-bottom: 5px;
    }
    
    .form-control, .form-select {
        border-radius: 8px;
        border: 2px solid #e9ecef;
        padding: 10px 15px;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
    }
    
    .btn-register {
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
    
    .btn-register:hover {
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
    
    .info-note {
        background: #e8f5e9;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        color: #2e7d32;
    }
    
    .info-note i {
        color: #16a34a;
    }
    
    .required-star {
        color: #dc3545;
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
                <h1>সেলার রেজিস্ট্রেশন</h1>
                <p>পাইকারি বিক্রেতা হিসেবে যোগ দিন</p>
            </div>
            
            <div class="info-note">
                <i class="bi bi-info-circle me-2"></i>
                <strong>শুধুমাত্র পাইকারি বিক্রেতাদের জন্য:</strong> এই সেকশন শুধুমাত্র তাদের জন্য যাদের পাইকারি পণ্য আছে এবং আমাদের প্ল্যাটফর্মে বিক্রি করতে চান। রেজিস্ট্রেশনের পর অ্যাডমিনের অনুমোদনের জন্য অপেক্ষা করতে হবে।
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php showFlash(); ?>
            
            <form method="POST">
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-person"></i> ব্যক্তিগত তথ্য
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">পুরো নাম <span class="required-star">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="আপনার পুরো নাম" required value="<?php echo $_POST['name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ইমেইল <span class="required-star">*</span></label>
                            <input type="email" class="form-control" name="email" placeholder="example@email.com" required value="<?php echo $_POST['email'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">মোবাইল নম্বর <span class="required-star">*</span></label>
                            <input type="tel" class="form-control" name="phone" placeholder="01XXXXXXXXX" required value="<?php echo $_POST['phone'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">জাতীয় পরিচয়পত্র নম্বর</label>
                            <input type="text" class="form-control" name="nid_number" placeholder="NID নম্বর" value="<?php echo $_POST['nid_number'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Business Information -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-shop"></i> ব্যবসার তথ্য
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্যবসার নাম <span class="required-star">*</span></label>
                            <input type="text" class="form-control" name="business_name" placeholder="আপনার ব্যবসার নাম" required value="<?php echo $_POST['business_name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্যবসার ধরন</label>
                            <select class="form-select" name="business_type">
                                <option value="">নির্বাচন করুন</option>
                                <option value="manufacturer" <?php echo ($_POST['business_type'] ?? '') == 'manufacturer' ? 'selected' : ''; ?>>ম্যানুফ্যাকচারার</option>
                                <option value="wholesaler" <?php echo ($_POST['business_type'] ?? '') == 'wholesaler' ? 'selected' : ''; ?>>পাইকারি বিক্রেতা</option>
                                <option value="importer" <?php echo ($_POST['business_type'] ?? '') == 'importer' ? 'selected' : ''; ?>>আমদানিকারক</option>
                                <option value="distributor" <?php echo ($_POST['business_type'] ?? '') == 'distributor' ? 'selected' : ''; ?>>ডিস্ট্রিবিউটর</option>
                                <option value="other" <?php echo ($_POST['business_type'] ?? '') == 'other' ? 'selected' : ''; ?>>অন্যান্য</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ট্রেড লাইসেন্স নম্বর</label>
                            <input type="text" class="form-control" name="trade_license" placeholder="ট্রেড লাইসেন্স নম্বর" value="<?php echo $_POST['trade_license'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">জেলা</label>
                            <select class="form-select" name="district">
                                <option value="">নির্বাচন করুন</option>
                                <?php foreach ($districts as $dist): ?>
                                <option value="<?php echo $dist; ?>" <?php echo ($_POST['district'] ?? '') == $dist ? 'selected' : ''; ?>><?php echo $dist; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">সম্পূর্ণ ঠিকানা</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="আপনার ব্যবসার সম্পূর্ণ ঠিকানা"><?php echo $_POST['address'] ?? ''; ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-wallet2"></i> পেমেন্ট তথ্য (যেকোনো একটি)
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">বিকাশ নম্বর</label>
                            <input type="tel" class="form-control" name="bkash_number" placeholder="01XXXXXXXXX" value="<?php echo $_POST['bkash_number'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">নগদ নম্বর</label>
                            <input type="tel" class="form-control" name="nagad_number" placeholder="01XXXXXXXXX" value="<?php echo $_POST['nagad_number'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্যাংকের নাম</label>
                            <input type="text" class="form-control" name="bank_name" placeholder="যেমন: Dutch Bangla Bank" value="<?php echo $_POST['bank_name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">অ্যাকাউন্ট হোল্ডারের নাম</label>
                            <input type="text" class="form-control" name="bank_account_name" placeholder="ব্যাংক অ্যাকাউন্টের নাম" value="<?php echo $_POST['bank_account_name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">অ্যাকাউন্ট নম্বর</label>
                            <input type="text" class="form-control" name="bank_account_number" placeholder="ব্যাংক অ্যাকাউন্ট নম্বর" value="<?php echo $_POST['bank_account_number'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্রাঞ্চ</label>
                            <input type="text" class="form-control" name="bank_branch" placeholder="ব্যাংক ব্রাঞ্চের নাম" value="<?php echo $_POST['bank_branch'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-shield-lock"></i> পাসওয়ার্ড সেট করুন
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">পাসওয়ার্ড <span class="required-star">*</span></label>
                            <input type="password" class="form-control" name="password" placeholder="কমপক্ষে ৬ অক্ষর" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">পাসওয়ার্ড নিশ্চিত করুন <span class="required-star">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="পাসওয়ার্ড আবার লিখুন" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-register">
                    <i class="bi bi-person-plus me-2"></i>রেজিস্ট্রেশন করুন
                </button>
            </form>
            
            <div class="auth-footer">
                অ্যাকাউন্ট আছে? <a href="<?php echo baseUrl('seller/login.php'); ?>">লগইন করুন</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
