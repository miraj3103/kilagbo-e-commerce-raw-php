<?php
/**
 * Kilagbo Seller Portal - Profile Management
 * Update profile and payment information
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'প্রোফাইল';
$currentPage = 'profile';

$seller = requireSellerLogin();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $businessName = sanitize($_POST['business_name'] ?? '');
        $businessType = sanitize($_POST['business_type'] ?? '');
        $nidNumber = sanitize($_POST['nid_number'] ?? '');
        $tradeLicense = sanitize($_POST['trade_license'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        
        // Handle profile picture
        $profilePicture = $seller['profile_picture'];
        if (!empty($_FILES['profile_picture']['name'])) {
            $upload = uploadImage($_FILES['profile_picture'], 'sellers');
            if ($upload['success']) {
                $profilePicture = $upload['filename'];
            }
        }
        
        // Check if phone is unique
        $stmt = $pdo->prepare("SELECT id FROM sellers WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $seller['id']]);
        if ($stmt->fetch()) {
            $error = 'এই ফোন নম্বর অন্য কেউ ব্যবহার করছে';
        } else {
            $stmt = $pdo->prepare("
                UPDATE sellers SET 
                    name = ?, phone = ?, business_name = ?, business_type = ?,
                    nid_number = ?, trade_license = ?, address = ?, district = ?,
                    profile_picture = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $phone, $businessName, $businessType,
                $nidNumber, $tradeLicense, $address, $district,
                $profilePicture, $seller['id']
            ]);
            
            $success = 'প্রোফাইল সফলভাবে আপডেট হয়েছে';
            $seller = getSellerData(); // Refresh seller data
        }
    } elseif ($action === 'update_payment') {
        $bankName = sanitize($_POST['bank_name'] ?? '');
        $bankAccountName = sanitize($_POST['bank_account_name'] ?? '');
        $bankAccountNumber = sanitize($_POST['bank_account_number'] ?? '');
        $bankBranch = sanitize($_POST['bank_branch'] ?? '');
        $bkashNumber = sanitize($_POST['bkash_number'] ?? '');
        $nagadNumber = sanitize($_POST['nagad_number'] ?? '');
        
        $stmt = $pdo->prepare("
            UPDATE sellers SET 
                bank_name = ?, bank_account_name = ?, bank_account_number = ?,
                bank_branch = ?, bkash_number = ?, nagad_number = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $bankName, $bankAccountName, $bankAccountNumber,
            $bankBranch, $bkashNumber, $nagadNumber, $seller['id']
        ]);
        
        $success = 'পেমেন্ট তথ্য সফলভাবে আপডেট হয়েছে';
        $seller = getSellerData();
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPassword, $seller['password'])) {
            $error = 'বর্তমান পাসওয়ার্ড সঠিক নয়';
        } elseif (strlen($newPassword) < 6) {
            $error = 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'নতুন পাসওয়ার্ড মিলছে না';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE sellers SET password = ? WHERE id = ?")
                ->execute([$hashedPassword, $seller['id']]);
            $success = 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে';
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

require_once 'includes/header.php';
?>

<style>
    .profile-header {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 25px;
    }
    
    .profile-hero-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
        overflow: hidden;
        border: 4px solid rgba(255,255,255,0.3);
    }
    
    .profile-hero-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }
    
    .profile-info h3 {
        margin: 0 0 5px;
        font-weight: 700;
    }
    
    .profile-info p {
        margin: 0;
        opacity: 0.9;
    }
    
    .nav-tabs .nav-link {
        color: #666;
        border: none;
        padding: 12px 20px;
        font-weight: 500;
    }
    
    .nav-tabs .nav-link.active {
        color: #16a34a;
        border-bottom: 3px solid #16a34a;
        background: none;
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
</style>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-hero-avatar">
        <?php if ($seller['profile_picture']): ?>
        <img src="<?php echo uploadUrl($seller['profile_picture']); ?>" alt="<?php echo $seller['name']; ?>">
        <?php else: ?>
        <?php echo strtoupper(substr($seller['name'], 0, 1)); ?>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <h3><?php echo sanitize($seller['name']); ?></h3>
        <p><i class="bi bi-shop me-2"></i><?php echo sanitize($seller['business_name']); ?></p>
        <p><i class="bi bi-envelope me-2"></i><?php echo sanitize($seller['email']); ?></p>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="profileTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#personal">
            <i class="bi bi-person me-2"></i>ব্যক্তিগত তথ্য
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#payment">
            <i class="bi bi-wallet2 me-2"></i>পেমেন্ট তথ্য
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#security">
            <i class="bi bi-shield-lock me-2"></i>পাসওয়ার্ড
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Personal Info Tab -->
    <div class="tab-pane fade show active" id="personal">
        <div class="seller-card">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-camera"></i> প্রোফাইল ছবি
                    </div>
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                <?php if ($seller['profile_picture']): ?>
                                <img src="<?php echo uploadUrl($seller['profile_picture']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                <i class="bi bi-person" style="font-size: 2rem; color: #999;"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col">
                            <input type="file" name="profile_picture" class="form-control" accept="image/*">
                            <small class="text-muted">JPG, PNG - সর্বোচ্চ 2MB</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-person"></i> ব্যক্তিগত তথ্য
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">পুরো নাম *</label>
                            <input type="text" name="name" class="form-control" value="<?php echo sanitize($seller['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">মোবাইল নম্বর *</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo sanitize($seller['phone']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ইমেইল</label>
                            <input type="email" class="form-control" value="<?php echo sanitize($seller['email']); ?>" disabled>
                            <small class="text-muted">ইমেইল পরিবর্তন করা যাবে না</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">জাতীয় পরিচয়পত্র নম্বর</label>
                            <input type="text" name="nid_number" class="form-control" value="<?php echo sanitize($seller['nid_number']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-shop"></i> ব্যবসার তথ্য
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্যবসার নাম *</label>
                            <input type="text" name="business_name" class="form-control" value="<?php echo sanitize($seller['business_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্যবসার ধরন</label>
                            <select name="business_type" class="form-select">
                                <option value="">নির্বাচন করুন</option>
                                <option value="manufacturer" <?php echo $seller['business_type'] == 'manufacturer' ? 'selected' : ''; ?>>ম্যানুফ্যাকচারার</option>
                                <option value="wholesaler" <?php echo $seller['business_type'] == 'wholesaler' ? 'selected' : ''; ?>>পাইকারি বিক্রেতা</option>
                                <option value="importer" <?php echo $seller['business_type'] == 'importer' ? 'selected' : ''; ?>>আমদানিকারক</option>
                                <option value="distributor" <?php echo $seller['business_type'] == 'distributor' ? 'selected' : ''; ?>>ডিস্ট্রিবিউটর</option>
                                <option value="other" <?php echo $seller['business_type'] == 'other' ? 'selected' : ''; ?>>অন্যান্য</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ট্রেড লাইসেন্স</label>
                            <input type="text" name="trade_license" class="form-control" value="<?php echo sanitize($seller['trade_license']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">জেলা</label>
                            <select name="district" class="form-select">
                                <option value="">নির্বাচন করুন</option>
                                <?php foreach ($districts as $dist): ?>
                                <option value="<?php echo $dist; ?>" <?php echo $seller['district'] == $dist ? 'selected' : ''; ?>><?php echo $dist; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">সম্পূর্ণ ঠিকানা</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo sanitize($seller['address']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-seller">
                    <i class="bi bi-check-circle me-2"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>
    
    <!-- Payment Info Tab -->
    <div class="tab-pane fade" id="payment">
        <div class="seller-card">
            <form method="POST">
                <input type="hidden" name="action" value="update_payment">
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-phone"></i> মোবাইল ব্যাংকিং
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">বিকাশ নম্বর</label>
                            <input type="tel" name="bkash_number" class="form-control" value="<?php echo sanitize($seller['bkash_number']); ?>" placeholder="01XXXXXXXXX">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">নগদ নম্বর</label>
                            <input type="tel" name="nagad_number" class="form-control" value="<?php echo sanitize($seller['nagad_number']); ?>" placeholder="01XXXXXXXXX">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-bank"></i> ব্যাংক তথ্য
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্যাংকের নাম</label>
                            <input type="text" name="bank_name" class="form-control" value="<?php echo sanitize($seller['bank_name']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">অ্যাকাউন্ট হোল্ডারের নাম</label>
                            <input type="text" name="bank_account_name" class="form-control" value="<?php echo sanitize($seller['bank_account_name']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">অ্যাকাউন্ট নম্বর</label>
                            <input type="text" name="bank_account_number" class="form-control" value="<?php echo sanitize($seller['bank_account_number']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ব্রাঞ্চ</label>
                            <input type="text" name="bank_branch" class="form-control" value="<?php echo sanitize($seller['bank_branch']); ?>">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-seller">
                    <i class="bi bi-check-circle me-2"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>
    
    <!-- Security Tab -->
    <div class="tab-pane fade" id="security">
        <div class="seller-card">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="bi bi-shield-lock"></i> পাসওয়ার্ড পরিবর্তন
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">বর্তমান পাসওয়ার্ড *</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">নতুন পাসওয়ার্ড *</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small class="text-muted">কমপক্ষে ৬ অক্ষর</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">পাসওয়ার্ড নিশ্চিত করুন *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-seller">
                    <i class="bi bi-check-circle me-2"></i>পাসওয়ার্ড পরিবর্তন করুন
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
