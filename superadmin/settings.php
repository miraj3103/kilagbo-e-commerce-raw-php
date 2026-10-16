<?php
/**
 * Kilagbo - Admin Settings
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'সেটিংস';
$currentPage = 'settings';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        // Verify current password if changing password
        if ($newPassword) {
            $adminStmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $adminStmt->execute([$_SESSION['admin_id']]);
            $admin = $adminStmt->fetch();
            
            if (!password_verify($currentPassword, $admin['password'])) {
                setFlash('error', 'বর্তমান পাসওয়ার্ড সঠিক নয়');
                redirect(adminUrl('settings'));
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admins SET username = ?, email = ?, password = ? WHERE id = ?")
                ->execute([$username, $email, $hashedPassword, $_SESSION['admin_id']]);
        } else {
            $pdo->prepare("UPDATE admins SET username = ?, email = ? WHERE id = ?")
                ->execute([$username, $email, $_SESSION['admin_id']]);
        }
        
        // Handle profile picture
        if (!empty($_FILES['profile_picture']['name'])) {
            $upload = uploadImage($_FILES['profile_picture'], 'profile');
            if ($upload['success']) {
                $pdo->prepare("UPDATE admins SET profile_picture = ? WHERE id = ?")
                    ->execute([$upload['filename'], $_SESSION['admin_id']]);
                $_SESSION['admin_picture'] = $upload['filename'];
            }
        }
        
        $_SESSION['admin_name'] = $username;
        $_SESSION['admin_email'] = $email;
        
        setFlash('success', 'প্রোফাইল আপডেট করা হয়েছে');
        redirect(adminUrl('settings'));
    }
    
    if ($action === 'update_site') {
        $siteTitle = sanitize($_POST['site_title'] ?? '');
        $siteTagline = sanitize($_POST['site_tagline'] ?? '');
        
        updateSetting('site_title', $siteTitle);
        updateSetting('site_tagline', $siteTagline);
        
        // Handle logo
        if (!empty($_FILES['site_logo']['name'])) {
            $upload = uploadImage($_FILES['site_logo'], 'site');
            if ($upload['success']) {
                updateSetting('site_logo', $upload['filename']);
            }
        }
        
        // Handle favicon
        if (!empty($_FILES['site_favicon']['name'])) {
            $upload = uploadImage($_FILES['site_favicon'], 'site');
            if ($upload['success']) {
                updateSetting('site_favicon', $upload['filename']);
            }
        }
        
        setFlash('success', 'সাইট সেটিংস আপডেট করা হয়েছে');
        redirect(adminUrl('settings'));
    }
}

// Fetch admin data
$adminStmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$adminStmt->execute([$_SESSION['admin_id']]);
$admin = $adminStmt->fetch();

$siteTitle = getSettings('site_title') ?? '';
$siteTagline = getSettings('site_tagline') ?? '';
$siteLogo = getSettings('site_logo') ?? '';
$siteFavicon = getSettings('site_favicon') ?? '';

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <!-- Profile Settings -->
    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h5 class="card-title">
                <i class="bi bi-person-circle me-2"></i>প্রোফাইল সেটিংস
            </h5>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="text-center mb-4">
                    <div class="profile-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <?php if ($admin['profile_picture']): ?>
                        <img src="<?php echo uploadUrl($admin['profile_picture']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                        <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="profile_picture" class="form-control" accept="image/*" style="max-width: 300px; margin: 0 auto;">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ইউজারনেম</label>
                    <input type="text" name="username" class="form-control" value="<?php echo sanitize($admin['username']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ইমেইল</label>
                    <input type="email" name="email" class="form-control" value="<?php echo sanitize($admin['email']); ?>" required>
                </div>
                
                <hr>
                <h6 class="mb-3">পাসওয়ার্ড পরিবর্তন (ঐচ্ছিক)</h6>
                
                <div class="mb-3">
                    <label class="form-label">বর্তমান পাসওয়ার্ড</label>
                    <input type="password" name="current_password" class="form-control">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">নতুন পাসওয়ার্ড</label>
                    <input type="password" name="new_password" class="form-control">
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>আপডেট করুন
                </button>
            </form>
        </div>
    </div>
    
    <!-- Site Settings -->
    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h5 class="card-title">
                <i class="bi bi-globe me-2"></i>সাইট সেটিংস
            </h5>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_site">
                
                <div class="mb-3">
                    <label class="form-label">সাইট টাইটেল</label>
                    <input type="text" name="site_title" class="form-control" value="<?php echo sanitize($siteTitle); ?>" placeholder="সাইটের নাম">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সাইট ট্যাগলাইন</label>
                    <input type="text" name="site_tagline" class="form-control" value="<?php echo sanitize($siteTagline); ?>" placeholder="সাইটের স্লোগান">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সাইট লোগো</label>
                    <?php if ($siteLogo): ?>
                    <div class="mb-2">
                        <img src="<?php echo uploadUrl($siteLogo); ?>" alt="Logo" style="max-height: 50px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="site_logo" class="form-control" accept="image/*">
                    <small class="text-muted">প্রস্তাবিত সাইজ: 200x50px</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">সাইট আইকন (Favicon)</label>
                    <?php if ($siteFavicon): ?>
                    <div class="mb-2">
                        <img src="<?php echo uploadUrl($siteFavicon); ?>" alt="Favicon" style="max-height: 32px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="site_favicon" class="form-control" accept="image/*">
                    <small class="text-muted">প্রস্তাবিত সাইজ: 32x32px</small>
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
