<?php
/**
 * Kilagbo Affiliate Portal - Profile Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'প্রোফাইল';
$currentPage = 'profile';
$affiliatorId = getAffiliatorId();
$affiliator = getAffiliatorData();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        
        if (empty($name) || empty($phone)) {
            $error = 'নাম এবং ফোন নম্বর আবশ্যক';
        } else {
            // Check if phone exists for another user
            $stmt = $pdo->prepare("SELECT id FROM affiliators WHERE phone = ? AND id != ?");
            $stmt->execute([$phone, $affiliatorId]);
            if ($stmt->fetch()) {
                $error = 'এই ফোন নম্বর অন্য কারো অ্যাকাউন্টে ব্যবহৃত';
            } else {
                $pdo->prepare("UPDATE affiliators SET name = ?, phone = ?, address = ? WHERE id = ?")
                    ->execute([$name, $phone, $address, $affiliatorId]);
                $success = 'প্রোফাইল আপডেট হয়েছে!';
                $affiliator = getAffiliatorData();
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'সব পাসওয়ার্ড ফিল্ড পূরণ করুন';
        } elseif (!password_verify($currentPassword, $affiliator['password'])) {
            $error = 'বর্তমান পাসওয়ার্ড সঠিক নয়';
        } elseif (strlen($newPassword) < 6) {
            $error = 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'নতুন পাসওয়ার্ড মিলছে না';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE affiliators SET password = ? WHERE id = ?")->execute([$hashedPassword, $affiliatorId]);
            $success = 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!';
        }
    }
}

// Get stats
$stats = getAffiliatorStats($affiliatorId);

include __DIR__ . '/includes/header.php';
?>

<style>
    .profile-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 16px;
        padding: 30px;
        color: #fff;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 25px;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
    }
    
    .profile-info h2 {
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .profile-info p {
        opacity: 0.9;
        margin: 3px 0;
    }
    
    .profile-stats {
        display: flex;
        gap: 30px;
        margin-top: 15px;
    }
    
    .profile-stats .stat-item span {
        display: block;
    }
    
    .profile-stats .stat-item .value {
        font-size: 1.3rem;
        font-weight: 600;
    }
    
    .profile-stats .stat-item .label {
        font-size: 0.8rem;
        opacity: 0.8;
    }
    
    .profile-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }
    
    .profile-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .btn-save {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16,185,129,0.3);
        color: #fff;
    }
    
    .account-info {
        background: #f3f4f6;
        border-radius: 10px;
        padding: 20px;
    }
    
    .account-info p {
        margin: 8px 0;
        display: flex;
        align-items: center;
    }
    
    .account-info i {
        width: 30px;
        color: #10b981;
    }
    
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
        }
        
        .profile-stats {
            justify-content: center;
        }
    }
</style>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php showFlash(); ?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-avatar">
        <?php echo strtoupper(substr($affiliator['name'], 0, 1)); ?>
    </div>
    <div class="profile-info">
        <h2><?php echo sanitize($affiliator['name']); ?></h2>
        <p><i class="bi bi-envelope me-2"></i><?php echo $affiliator['email']; ?></p>
        <p><i class="bi bi-phone me-2"></i><?php echo $affiliator['phone']; ?></p>
        
        <div class="profile-stats">
            <div class="stat-item">
                <span class="value"><?php echo $stats['total_links']; ?></span>
                <span class="label">লিংক</span>
            </div>
            <div class="stat-item">
                <span class="value"><?php echo $stats['total_clicks']; ?></span>
                <span class="label">ক্লিক</span>
            </div>
            <div class="stat-item">
                <span class="value"><?php echo $stats['total_orders']; ?></span>
                <span class="label">অর্ডার</span>
            </div>
            <div class="stat-item">
                <span class="value"><?php echo formatPrice($affiliator['total_earned']); ?></span>
                <span class="label">মোট আয়</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="profile-card">
            <h5><i class="bi bi-person me-2"></i>প্রোফাইল তথ্য আপডেট</h5>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">পুরো নাম *</label>
                    <input type="text" class="form-control" name="name" value="<?php echo sanitize($affiliator['name']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ইমেইল</label>
                    <input type="email" class="form-control" value="<?php echo $affiliator['email']; ?>" disabled>
                    <small class="text-muted">ইমেইল পরিবর্তন করা যাবে না</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">মোবাইল নম্বর *</label>
                    <input type="tel" class="form-control" name="phone" value="<?php echo $affiliator['phone']; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ঠিকানা</label>
                    <textarea class="form-control" name="address" rows="3"><?php echo sanitize($affiliator['address']); ?></textarea>
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-save">
                    <i class="bi bi-check-lg me-2"></i>আপডেট করুন
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="profile-card">
            <h5><i class="bi bi-lock me-2"></i>পাসওয়ার্ড পরিবর্তন</h5>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">বর্তমান পাসওয়ার্ড *</label>
                    <input type="password" class="form-control" name="current_password" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">নতুন পাসওয়ার্ড *</label>
                    <input type="password" class="form-control" name="new_password" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">নতুন পাসওয়ার্ড নিশ্চিত করুন *</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-save">
                    <i class="bi bi-shield-lock me-2"></i>পাসওয়ার্ড পরিবর্তন করুন
                </button>
            </form>
        </div>
        
        <div class="profile-card">
            <h5><i class="bi bi-info-circle me-2"></i>অ্যাকাউন্ট তথ্য</h5>
            
            <div class="account-info">
                <p><i class="bi bi-calendar-check"></i><strong>যোগদান:</strong> <?php echo date('d M Y', strtotime($affiliator['created_at'])); ?></p>
                <p><i class="bi bi-clock"></i><strong>সর্বশেষ লগইন:</strong> <?php echo $affiliator['last_login'] ? date('d M Y, h:i A', strtotime($affiliator['last_login'])) : 'N/A'; ?></p>
                <p><i class="bi bi-patch-check"></i><strong>স্ট্যাটাস:</strong> 
                    <span class="badge bg-success">অনুমোদিত</span>
                </p>
                <p><i class="bi bi-wallet2"></i><strong>বর্তমান ব্যালেন্স:</strong> <?php echo formatPrice($affiliator['balance']); ?></p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
