<?php
/**
 * Kilagbo - Courier Setup (Steadfast)
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'কুরিয়ার সেটআপ';
$currentPage = 'courier';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

$balanceResult = null;
$balanceError = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $apiKey = sanitize($_POST['api_key'] ?? '');
        $secretKey = sanitize($_POST['secret_key'] ?? '');
        $isActive = isset($_POST['is_active']) ? '1' : '0';
        
        updateSetting('steadfast_api_key', $apiKey);
        updateSetting('steadfast_secret_key', $secretKey);
        updateSetting('steadfast_active', $isActive);
        
        setFlash('success', 'সেটিংস সেভ করা হয়েছে');
        redirect(adminUrl('courier'));
    }
    
    if ($action === 'check_balance') {
        $apiKey = getSettings('steadfast_api_key');
        $secretKey = getSettings('steadfast_secret_key');
        
        if ($apiKey && $secretKey) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, STEADFAST_BASE_URL . '/get_balance');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Api-Key: ' . $apiKey,
                'Secret-Key: ' . $secretKey
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode == 200 && isset($result['current_balance'])) {
                $balanceResult = $result['current_balance'];
            } else {
                $balanceError = $result['message'] ?? 'API কানেকশন ব্যর্থ হয়েছে';
            }
        } else {
            $balanceError = 'API Key এবং Secret Key দিন';
        }
    }
}

$currentApiKey = getSettings('steadfast_api_key') ?? '';
$currentSecretKey = getSettings('steadfast_secret_key') ?? '';
$isActive = getSettings('steadfast_active') == '1';

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <div class="col-lg-6">
        <div class="admin-card">
            <h5 class="card-title">
                <img src="https://steadfast.com.bd/images/logo.png" alt="Steadfast" style="height: 30px; margin-right: 10px;" onerror="this.style.display='none'">
                Steadfast Courier সেটআপ
            </h5>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="mb-3">
                    <label class="form-label">API Key *</label>
                    <input type="text" name="api_key" class="form-control" value="<?php echo $currentApiKey; ?>" placeholder="আপনার Steadfast API Key দিন">
                    <small class="text-muted">Steadfast ড্যাশবোর্ড থেকে API Key সংগ্রহ করুন</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Secret Key *</label>
                    <input type="password" name="secret_key" class="form-control" value="<?php echo $currentSecretKey; ?>" placeholder="আপনার Steadfast Secret Key দিন">
                </div>
                
                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?php echo $isActive ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isActive">কুরিয়ার সক্রিয় করুন</label>
                    </div>
                    <small class="text-muted">এটি সক্রিয় করলে অর্ডার পেজে "Send" বাটন দেখাবে</small>
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="admin-card">
            <h5 class="card-title">ব্যালেন্স চেক করুন</h5>
            
            <p class="text-muted mb-3">
                API সেটআপ সঠিকভাবে কাজ করছে কিনা যাচাই করতে ব্যালেন্স চেক করুন।
            </p>
            
            <?php if ($balanceResult !== null): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <strong>সফল!</strong> আপনার বর্তমান ব্যালেন্স: <strong>৳<?php echo number_format($balanceResult, 2); ?></strong>
            </div>
            <?php endif; ?>
            
            <?php if ($balanceError): ?>
            <div class="alert alert-danger">
                <i class="bi bi-x-circle me-2"></i>
                <strong>ত্রুটি!</strong> <?php echo $balanceError; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="check_balance">
                <button type="submit" class="btn btn-admin-outline" <?php echo (!$currentApiKey || !$currentSecretKey) ? 'disabled' : ''; ?>>
                    <i class="bi bi-wallet2 me-2"></i>ব্যালেন্স চেক করুন
                </button>
            </form>
            
            <?php if (!$currentApiKey || !$currentSecretKey): ?>
            <small class="text-danger mt-2 d-block">প্রথমে API Key এবং Secret Key সেভ করুন</small>
            <?php endif; ?>
        </div>
        
        <div class="admin-card">
            <h5 class="card-title">তথ্য</h5>
            
            <ul class="list-unstyled mb-0">
                <li class="mb-2">
                    <i class="bi bi-info-circle text-primary me-2"></i>
                    Base URL: <code><?php echo STEADFAST_BASE_URL; ?></code>
                </li>
                <li class="mb-2">
                    <i class="bi bi-info-circle text-primary me-2"></i>
                    স্ট্যাটাস: 
                    <?php if ($isActive): ?>
                    <span class="badge bg-success">সক্রিয়</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">নিষ্ক্রিয়</span>
                    <?php endif; ?>
                </li>
                <li>
                    <i class="bi bi-link-45deg text-primary me-2"></i>
                    <a href="https://steadfast.com.bd" target="_blank">Steadfast ওয়েবসাইট</a>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
