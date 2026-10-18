<?php
/**
 * Kilagbo - Admin Social Login Settings
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'সোশ্যাল লগইন';
$currentPage = 'social-login';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_google') {
        $clientId = sanitize($_POST['google_client_id'] ?? '');
        $clientSecret = sanitize($_POST['google_client_secret'] ?? '');
        $isActive = isset($_POST['google_login_active']) ? '1' : '0';
        
        updateSetting('google_client_id', $clientId);
        updateSetting('google_client_secret', $clientSecret);
        updateSetting('google_login_active', $isActive);
        
        setFlash('success', 'Google লগইন সেটিংস আপডেট হয়েছে');
        redirect(adminUrl('social-login'));
    }
    
    if ($action === 'update_facebook') {
        $appId = sanitize($_POST['facebook_app_id'] ?? '');
        $appSecret = sanitize($_POST['facebook_app_secret'] ?? '');
        $isActive = isset($_POST['facebook_login_active']) ? '1' : '0';
        
        updateSetting('facebook_app_id', $appId);
        updateSetting('facebook_app_secret', $appSecret);
        updateSetting('facebook_login_active', $isActive);
        
        setFlash('success', 'Facebook লগইন সেটিংস আপডেট হয়েছে');
        redirect(adminUrl('social-login'));
    }
}

// Get current settings
$googleClientId = getSettings('google_client_id') ?? '';
$googleClientSecret = getSettings('google_client_secret') ?? '';
$googleLoginActive = getSettings('google_login_active') === '1';
$facebookAppId = getSettings('facebook_app_id') ?? '';
$facebookAppSecret = getSettings('facebook_app_secret') ?? '';
$facebookLoginActive = getSettings('facebook_login_active') === '1';

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <!-- Google Login -->
    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h5 class="card-title">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 24px; height: 24px; margin-right: 10px; vertical-align: middle;">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Google লগইন
            </h5>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_google">
                
                <div class="mb-3">
                    <label class="form-label">Client ID</label>
                    <input type="text" name="google_client_id" class="form-control" value="<?php echo sanitize($googleClientId); ?>" placeholder="Google OAuth Client ID">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Client Secret</label>
                    <input type="password" name="google_client_secret" class="form-control" value="<?php echo sanitize($googleClientSecret); ?>" placeholder="Google OAuth Client Secret">
                </div>
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="google_login_active" name="google_login_active" <?php echo $googleLoginActive ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="google_login_active">Google লগইন সক্রিয় করুন</label>
                </div>

                <div class="alert alert-info mb-4">
                    <h6><i class="bi bi-info-circle me-2"></i>সেটআপ গাইড:</h6>
                    <ol class="mb-0 small">
                        <li><a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> এ যান</li>
                        <li>নতুন প্রজেক্ট তৈরি করুন বা বিদ্যমান প্রজেক্ট সিলেক্ট করুন</li>
                        <li>APIs & Services > Credentials এ যান</li>
                        <li>Create Credentials > OAuth client ID সিলেক্ট করুন</li>
                        <li>Application type: Web application সিলেক্ট করুন</li>
                        <li>Authorized redirect URIs এ যোগ করুন:<br>
                            <code><?php echo baseUrl('auth-callback?provider=google'); ?></code>
                            <br>অথবা আপনার লাইভ ডোমেইনের জন্য:<br>
                            <code>https://Domain-Name/auth-callback?provider=google</code>
                        </li>
                        <li>Client ID এবং Client Secret কপি করে এখানে পেস্ট করুন</li>
                    </ol>
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
                
                <span class="badge <?php echo $googleLoginActive ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                    <i class="bi bi-<?php echo $googleLoginActive ? 'check-circle' : 'slash-circle'; ?> me-1"></i>
                    <?php echo $googleLoginActive ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                </span>
            </form>
        </div>
    </div>
    
    <!-- Facebook Login -->
    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h5 class="card-title">
                <i class="bi bi-facebook me-2" style="color: #1877f2; font-size: 1.3rem;"></i>
                Facebook লগইন
            </h5>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_facebook">
                
                <div class="mb-3">
                    <label class="form-label">App ID</label>
                    <input type="text" name="facebook_app_id" class="form-control" value="<?php echo sanitize($facebookAppId); ?>" placeholder="Facebook App ID">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">App Secret</label>
                    <input type="password" name="facebook_app_secret" class="form-control" value="<?php echo sanitize($facebookAppSecret); ?>" placeholder="Facebook App Secret">
                </div>
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="facebook_login_active" name="facebook_login_active" <?php echo $facebookLoginActive ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="facebook_login_active">Facebook লগইন সক্রিয় করুন</label>
                </div>

                <div class="alert alert-info mb-4">
                    <h6><i class="bi bi-info-circle me-2"></i>সেটআপ গাইড:</h6>
                    <ol class="mb-0 small">
                        <li><a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> এ যান</li>
                        <li>My Apps > Create App সিলেক্ট করুন</li>
                        <li>Consumer বা Business টাইপ সিলেক্ট করুন</li>
                        <li>App Settings > Basic এ যান</li>
                        <li>App ID এবং App Secret কপি করুন</li>
                        <li>Facebook Login > Settings এ যান</li>
                        <li>Valid OAuth Redirect URIs এ যোগ করুন:<br>
                            <code><?php echo baseUrl('auth-callback?provider=facebook'); ?></code>
                            <br>অথবা আপনার লাইভ ডোমেইনের জন্য:<br>
                            <code>https://Domain-Name/auth-callback?provider=facebook</code>
                        </li>
                        <li>App Mode কে Live করুন (Settings > Basic > App Mode)</li>
                    </ol>
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
                
                <span class="badge <?php echo $facebookLoginActive ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                    <i class="bi bi-<?php echo $facebookLoginActive ? 'check-circle' : 'slash-circle'; ?> me-1"></i>
                    <?php echo $facebookLoginActive ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                </span>
            </form>
        </div>
    </div>
</div>

<div class="admin-card">
    <h5 class="card-title"><i class="bi bi-question-circle me-2"></i>সাহায্য</h5>
    <p class="mb-2">সোশ্যাল লগইন সেটআপ করলে আপনার কাস্টমাররা Google বা Facebook অ্যাকাউন্ট দিয়ে সহজে লগইন/রেজিস্ট্রেশন করতে পারবে।</p>
    <ul class="mb-0">
        <li>Google এবং Facebook উভয়ই সেটআপ করা বাধ্যতামূলক নয়। যেকোনো একটি বা দুটোই সেটআপ করতে পারেন।</li>
        <li>টগল অফ রাখলে সেই সোশ্যাল লগইন বাটন লুকানো থাকবে। অন রাখতে চাইলে অবশ্যই সঠিক Credential দিন।</li>
        <li>প্রোডাকশনে যাওয়ার আগে অবশ্যই টেস্ট করুন।</li>
    </ul>
</div>

<?php require_once 'includes/footer.php'; ?>
