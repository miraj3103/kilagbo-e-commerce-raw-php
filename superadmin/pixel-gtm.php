<?php
/**
 * Kilagbo - Pixel & GTM Setup
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পিক্সেল ও GTM';
$currentPage = 'pixel-gtm';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_pixel') {
        $pixelId = sanitize($_POST['pixel_id'] ?? '');
        $conversionApi = sanitize($_POST['conversion_api'] ?? '');
        $testEvent = sanitize($_POST['test_event'] ?? '');
        $pixelActive = isset($_POST['pixel_active']) ? '1' : '0';
        
        updateSetting('facebook_pixel_id', $pixelId);
        updateSetting('facebook_conversion_api', $conversionApi);
        updateSetting('facebook_test_event', $testEvent);
        updateSetting('facebook_pixel_active', $pixelActive);
        
        setFlash('success', 'Facebook Pixel সেটিংস সেভ করা হয়েছে');
        redirect(adminUrl('pixel-gtm'));
    }
    
    if ($action === 'save_gtm') {
        $gtmId = sanitize($_POST['gtm_id'] ?? '');
        $gtmActive = isset($_POST['gtm_active']) ? '1' : '0';
        
        updateSetting('gtm_id', $gtmId);
        updateSetting('gtm_active', $gtmActive);
        
        setFlash('success', 'Google Tag Manager সেটিংস সেভ করা হয়েছে');
        redirect(adminUrl('pixel-gtm'));
    }
}

$pixelId = getSettings('facebook_pixel_id') ?? '';
$conversionApi = getSettings('facebook_conversion_api') ?? '';
$testEvent = getSettings('facebook_test_event') ?? '';
$pixelActive = getSettings('facebook_pixel_active') == '1';
$gtmId = getSettings('gtm_id') ?? '';
$gtmActive = getSettings('gtm_active') == '1';

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <!-- Facebook Pixel -->
    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h5 class="card-title">
                <i class="bi bi-facebook text-primary me-2"></i>
                Facebook Pixel
            </h5>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_pixel">
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="pixel_active" class="form-check-input" id="pixelActive" <?php echo $pixelActive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="pixelActive"><strong>Facebook Pixel সক্রিয় করুন</strong></label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Pixel ID *</label>
                    <input type="text" name="pixel_id" class="form-control" value="<?php echo $pixelId; ?>" placeholder="যেমন: 123456789012345">
                    <small class="text-muted">Facebook Events Manager থেকে Pixel ID সংগ্রহ করুন</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Conversions API Token (ঐচ্ছিক)</label>
                    <input type="text" name="conversion_api" class="form-control" value="<?php echo $conversionApi; ?>" placeholder="Conversions API Access Token">
                    <small class="text-muted">সার্ভার-সাইড ট্র্যাকিং এর জন্য প্রয়োজন</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Test Event Code (ঐচ্ছিক)</label>
                    <input type="text" name="test_event" class="form-control" value="<?php echo $testEvent; ?>" placeholder="যেমন: TEST12345">
                    <small class="text-muted">টেস্ট মোডে ইভেন্ট পাঠাতে এই কোড ব্যবহার হবে</small>
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
            </form>
            
            <?php if ($pixelId): ?>
            <hr>
            <h6>সক্রিয় ইভেন্ট সমূহ</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ইভেন্ট</th>
                            <th>স্ট্যাটাস</th>
                            <th>পেজ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>PageView</td>
                            <td><span class="badge bg-success">সক্রিয়</span></td>
                            <td>সকল পেজ</td>
                        </tr>
                        <tr>
                            <td>ViewContent</td>
                            <td><span class="badge bg-success">সক্রিয়</span></td>
                            <td>পণ্য পেজ</td>
                        </tr>
                        <tr>
                            <td>AddToCart</td>
                            <td><span class="badge bg-success">সক্রিয়</span></td>
                            <td>কার্ট যোগ করলে</td>
                        </tr>
                        <tr>
                            <td>InitiateCheckout</td>
                            <td><span class="badge bg-success">সক্রিয়</span></td>
                            <td>চেকআউট পেজ</td>
                        </tr>
                        <tr>
                            <td>Purchase</td>
                            <td><span class="badge bg-success">সক্রিয়</span></td>
                            <td>অর্ডার সফল পেজ</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Google Tag Manager -->
    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h5 class="card-title">
                <i class="bi bi-google text-danger me-2"></i>
                Google Tag Manager
            </h5>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_gtm">
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="gtm_active" class="form-check-input" id="gtmActive" <?php echo $gtmActive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="gtmActive"><strong>Google Tag Manager সক্রিয় করুন</strong></label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">GTM Container ID *</label>
                    <input type="text" name="gtm_id" class="form-control" value="<?php echo $gtmId; ?>" placeholder="যেমন: GTM-XXXXXXX">
                    <small class="text-muted">Google Tag Manager থেকে Container ID সংগ্রহ করুন</small>
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
            </form>
            
            <?php if ($gtmId): ?>
            <hr>
            <h6>DataLayer ইভেন্ট সমূহ</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ইভেন্ট</th>
                            <th>ডাটা</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>view_item_list</code></td>
                            <td>হোমপেজ ও শপ পেজে পণ্য তালিকা দেখলে</td>
                        </tr>
                        <tr>
                            <td><code>view_item</code></td>
                            <td>পণ্য বিস্তারিত পেজ দেখলে</td>
                        </tr>
                        <tr>
                            <td><code>add_to_cart</code></td>
                            <td>কার্টে পণ্য যোগ করলে</td>
                        </tr>
                        <tr>
                            <td><code>view_cart</code></td>
                            <td>কার্ট পেজ দেখলে</td>
                        </tr>
                        <tr>
                            <td><code>begin_checkout</code></td>
                            <td>চেকআউট শুরু করলে</td>
                        </tr>
                        <tr>
                            <td><code>purchase</code></td>
                            <td>অর্ডার সম্পন্ন হলে</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="admin-card">
            <h5 class="card-title">ডকুমেন্টেশন</h5>
            <ul class="list-unstyled mb-0">
                <li class="mb-2">
                    <a href="https://developers.facebook.com/docs/facebook-pixel" target="_blank">
                        <i class="bi bi-link-45deg me-2"></i>Facebook Pixel ডকুমেন্টেশন
                    </a>
                </li>
                <li class="mb-2">
                    <a href="https://developers.google.com/tag-manager" target="_blank">
                        <i class="bi bi-link-45deg me-2"></i>Google Tag Manager ডকুমেন্টেশন
                    </a>
                </li>
                <li>
                    <a href="https://developers.google.com/analytics/devguides/collection/ga4/ecommerce" target="_blank">
                        <i class="bi bi-link-45deg me-2"></i>GA4 Ecommerce Events
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
