<?php
/**
 * Kilagbo Reseller - Profile Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'প্রোফাইল';
$currentPage = 'profile';

$resellerId = getResellerId();
$reseller = getResellerData();

// Calculate correct totals and balance
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN profit_credited = 1 THEN profit ELSE 0 END), 0) as total_earned_correct
    FROM resell_orders WHERE reseller_id = ?
");
$stmt->execute([$resellerId]);
$orderTotals = $stmt->fetch();

// Get total withdrawn from completed withdrawals
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_withdrawn_correct
    FROM reseller_withdrawals 
    WHERE reseller_id = ? AND status = 'completed'
");
$stmt->execute([$resellerId]);
$withdrawalTotals = $stmt->fetch();

$totals = array_merge($orderTotals, $withdrawalTotals);

// Calculate correct current balance
$correctBalance = $totals['total_earned_correct'] - $totals['total_withdrawn_correct'];

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    
    if (empty($name) || empty($phone)) {
        $error = 'নাম এবং ফোন নম্বর দিন';
    } else {
        $pdo->prepare("UPDATE resellers SET name = ?, phone = ?, address = ? WHERE id = ?")
            ->execute([$name, $phone, $address, $resellerId]);
        $success = 'প্রোফাইল আপডেট হয়েছে';
        $reseller = getResellerData();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        $error = 'সব ফিল্ড পূরণ করুন';
    } elseif (!password_verify($currentPassword, $reseller['password'])) {
        $error = 'বর্তমান পাসওয়ার্ড সঠিক নয়';
    } elseif (strlen($newPassword) < 6) {
        $error = 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'পাসওয়ার্ড মিলছে না';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE resellers SET password = ? WHERE id = ?")->execute([$hashedPassword, $resellerId]);
        $success = 'পাসওয়ার্ড পরিবর্তন হয়েছে';
    }
}

// Handle deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_deletion'])) {
    $reason = sanitize($_POST['reason'] ?? '');
    
    if ($reseller['deletion_request']) {
        $error = 'আপনি ইতিমধ্যে একটি রিকুয়েস্ট পাঠিয়েছেন।';
    } else {
        $pdo->prepare("UPDATE resellers SET deletion_request = 1, deletion_request_reason = ?, deletion_requested_at = NOW() WHERE id = ?")
            ->execute([$reason, $resellerId]);
        $success = 'অ্যাকাউন্ট ডিলিট করার রিকুয়েস্ট অ্যাডমিনের কাছে পাঠানো হয়েছে';
        $reseller = getResellerData();
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
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
        margin: 0 0 5px;
        font-weight: 700;
    }
    
    .profile-info p {
        margin: 0;
        opacity: 0.9;
    }
    
    .profile-badge {
        background: rgba(255,255,255,0.2);
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        margin-top: 10px;
        display: inline-block;
    }
    
    .section-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    
    .section-card h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-box {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-box h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        margin: 0;
    }
    
    .stat-box p {
        margin: 5px 0 0;
        color: #666;
        font-size: 0.85rem;
    }
    
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?php echo $success; ?></div>
<?php endif; ?>

<div class="profile-header">
    <div class="profile-avatar">
        <?php echo strtoupper(substr($reseller['name'], 0, 1)); ?>
    </div>
    <div class="profile-info">
        <h2><?php echo sanitize($reseller['name']); ?></h2>
        <p><?php echo sanitize($reseller['email']); ?></p>
        <span class="profile-badge">
            <i class="bi bi-patch-check me-1"></i> অনুমোদিত রিসেলার
        </span>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-box">
        <h4><?php echo formatPrice($correctBalance); ?></h4>
        <p>ব্যালেন্স</p>
    </div>
    <div class="stat-box">
        <h4><?php echo formatPrice($totals['total_earned_correct']); ?></h4>
        <p>মোট আয়</p>
    </div>
    <div class="stat-box">
        <h4><?php echo $reseller['total_orders']; ?></h4>
        <p>মোট অর্ডার</p>
    </div>
    <div class="stat-box">
        <h4><?php echo date('d M Y', strtotime($reseller['created_at'])); ?></h4>
        <p>যোগদান</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="section-card">
            <h5><i class="bi bi-person me-2"></i>প্রোফাইল তথ্য</h5>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">নাম *</label>
                    <input type="text" class="form-control" name="name" value="<?php echo sanitize($reseller['name']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ইমেইল</label>
                    <input type="email" class="form-control" value="<?php echo sanitize($reseller['email']); ?>" disabled>
                    <small class="text-muted">ইমেইল পরিবর্তন করা যায় না</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">মোবাইল *</label>
                    <input type="tel" class="form-control" name="phone" value="<?php echo sanitize($reseller['phone']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ঠিকানা</label>
                    <textarea class="form-control" name="address" rows="2"><?php echo sanitize($reseller['address']); ?></textarea>
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="bi bi-check-lg me-2"></i>আপডেট করুন
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="section-card">
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
                
                <button type="submit" name="change_password" class="btn btn-warning">
                    <i class="bi bi-key me-2"></i>পাসওয়ার্ড পরিবর্তন করুন
                </button>
            </form>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="section-card border border-danger">
            <h5 class="text-danger"><i class="bi bi-trash me-2"></i>অ্যাকাউন্ট ডিলিট করুন</h5>
            
            <?php if ($reseller['deletion_request']): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-clock-history me-2"></i> আপনার অ্যাকাউন্ট ডিলিট করার রিকুয়েস্ট প্রসেসিং এ আছে। অ্যাডামিন এটি দেখার পর ব্যবস্থা নেবেন।
                    <p class="mt-2 mb-0 small">রেজিস্ট্রেশনের তারিখ: <?php echo date('d M Y, h:i A', strtotime($reseller['deletion_requested_at'])); ?></p>
                </div>
            <?php else: ?>
                <p class="text-muted mb-4">আপনি যদি আপনার অ্যাকাউন্ট স্থায়ীভাবে ডিলিট করতে চান তবে এখানে ক্লিক করুন। এটি করার পর আপনি আর এই অ্যাকাউন্টে লগইন করতে পারবেন না।</p>
                
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="bi bi-trash me-2"></i>ডিলিট রিকুয়েস্ট পাঠান
                </button>

                <!-- Delete Modal -->
                <div class="modal fade" id="deleteModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title text-danger">অ্যাকাউন্ট ডিলিট রিকুয়েস্ট</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>আপনি কি নিশ্চিত যে আপনার অ্যাকাউন্ট ডিলিট করতে চান? কোনো কারণ থাকলে নিচে লিখতে পারেন (ঐচ্ছিক):</p>
                                    <textarea class="form-control" name="reason" rows="4" placeholder="ডিলিট করার কারণ..."></textarea>
                                    <div class="alert alert-danger mt-3">
                                        <small><i class="bi bi-exclamation-triangle me-1"></i>সতর্কতা: অ্যাকাউন্ট একবার ডিলিট হলে আপনার সকল তথ্য, ব্যালেন্স এবং অর্ডার হিস্ট্রি স্থায়ীভাবে মুছে যাবে।</small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                    <button type="submit" name="request_deletion" class="btn btn-danger">কনফার্ম ডিলিট রিকুয়েস্ট</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
