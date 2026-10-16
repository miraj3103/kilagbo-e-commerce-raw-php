<?php
/**
 * Kilagbo Reseller Portal - Pending Approval Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অনুমোদন অপেক্ষমাণ';

if (!isResellerLoggedIn()) {
    redirect(baseUrl('resell/login.php'));
}

$reseller = getResellerData();

if (!$reseller) {
    resellerLogout();
    redirect(baseUrl('resell/login.php'));
}

if ($reseller['status'] === 'approved') {
    redirect(baseUrl('resell/'));
}

if ($reseller['status'] === 'rejected') {
    resellerLogout();
    setFlash('error', 'আপনার আবেদন প্রত্যাখ্যান করা হয়েছে।');
    redirect(baseUrl('resell/login.php'));
}

include __DIR__ . '/includes/header-auth.php';
?>

<style>
    .pending-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    
    .pending-card {
        background: #fff;
        border-radius: 20px;
        padding: 60px 40px;
        text-align: center;
        max-width: 500px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .pending-icon {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30px;
    }
    
    .pending-icon i {
        font-size: 50px;
        color: #e17055;
    }
    
    .pending-card h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 15px;
    }
    
    .pending-card p {
        color: #666;
        line-height: 1.8;
        margin-bottom: 30px;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff3cd;
        color: #856404;
        padding: 12px 25px;
        border-radius: 50px;
        font-weight: 600;
        margin-bottom: 30px;
    }
    
    .info-box {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        text-align: left;
        margin-bottom: 25px;
    }
    
    .info-box h5 {
        font-weight: 600;
        margin-bottom: 15px;
        color: #333;
    }
    
    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .info-item:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: #666;
    }
    
    .info-value {
        font-weight: 600;
        color: #333;
    }
    
    .btn-logout {
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        color: #666;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .btn-logout:hover {
        background: #e9ecef;
        color: #333;
    }
    
    .btn-refresh {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: #fff;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        margin-left: 10px;
    }
    
    .btn-refresh:hover {
        color: #fff;
        box-shadow: 0 5px 20px rgba(102,126,234,0.4);
    }
</style>

<div class="pending-page">
    <div class="pending-card">
        <div class="pending-icon">
            <i class="bi bi-hourglass-split"></i>
        </div>
        
        <h1>অনুমোদন অপেক্ষমাণ</h1>
        
        <div class="status-badge">
            <i class="bi bi-clock"></i> আপনার আবেদন পর্যালোচনা করা হচ্ছে
        </div>
        
        <p>
            প্রিয় <strong><?php echo sanitize($reseller['name']); ?></strong>,<br>
            আপনার রিসেলার আবেদন সফলভাবে জমা হয়েছে। আমাদের টিম আপনার আবেদন পর্যালোচনা করছে। 
            অনুমোদনের পর আপনাকে ইমেইলে জানানো হবে।
        </p>
        
        <div class="info-box">
            <h5><i class="bi bi-person me-2"></i>আপনার তথ্য</h5>
            <div class="info-item">
                <span class="info-label">নাম</span>
                <span class="info-value"><?php echo sanitize($reseller['name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">ইমেইল</span>
                <span class="info-value"><?php echo sanitize($reseller['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">মোবাইল</span>
                <span class="info-value"><?php echo sanitize($reseller['phone']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">আবেদনের তারিখ</span>
                <span class="info-value"><?php echo date('d M Y, h:i A', strtotime($reseller['created_at'])); ?></span>
            </div>
        </div>
        
        <div>
            <a href="<?php echo baseUrl('resell/logout.php'); ?>" class="btn-logout">
                <i class="bi bi-box-arrow-left me-2"></i>লগআউট
            </a>
            <a href="<?php echo baseUrl('resell/pending.php'); ?>" class="btn-refresh">
                <i class="bi bi-arrow-clockwise me-2"></i>রিফ্রেশ
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
