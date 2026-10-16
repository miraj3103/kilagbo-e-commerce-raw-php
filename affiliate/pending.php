<?php
/**
 * Kilagbo Affiliate Portal - Pending Approval Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অনুমোদনের অপেক্ষায়';

// Check if logged in
if (!isAffiliatorLoggedIn()) {
    redirect(baseUrl('affiliate/login.php'));
}

$affiliator = getAffiliatorData();

// If approved, redirect to dashboard
if ($affiliator && $affiliator['status'] === 'approved') {
    redirect(baseUrl('affiliate/'));
}

// If rejected, show message and redirect
if ($affiliator && $affiliator['status'] === 'rejected') {
    unset($_SESSION['affiliator_id']);
    setFlash('error', 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে: ' . ($affiliator['rejection_reason'] ?: 'কোনো কারণ দেওয়া হয়নি'));
    redirect(baseUrl('affiliate/login.php'));
}

include __DIR__ . '/includes/header-auth.php';
?>

<style>
    .pending-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    
    .pending-container {
        width: 100%;
        max-width: 500px;
    }
    
    .pending-card {
        background: #fff;
        border-radius: 16px;
        padding: 50px 40px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        text-align: center;
    }
    
    .pending-icon {
        width: 100px;
        height: 100px;
        background: #fef3c7;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
    }
    
    .pending-icon i {
        font-size: 3rem;
        color: #f59e0b;
    }
    
    .pending-card h2 {
        font-weight: 700;
        margin-bottom: 15px;
        color: #333;
    }
    
    .pending-card p {
        color: #666;
        margin-bottom: 25px;
    }
    
    .user-info {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
        text-align: left;
    }
    
    .user-info p {
        margin: 8px 0;
        color: #333;
    }
    
    .user-info strong {
        color: #10b981;
    }
    
    .btn-logout {
        background: #f3f4f6;
        color: #374151;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-logout:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    
    .btn-home {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-home:hover {
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16,185,129,0.3);
    }
</style>

<div class="pending-page">
    <div class="pending-container">
        <div class="pending-card">
            <div class="pending-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            
            <h2>অনুমোদনের অপেক্ষায়</h2>
            <p>আপনার অ্যাফিলিয়েট অ্যাকাউন্ট অ্যাডমিনের অনুমোদনের জন্য অপেক্ষা করছে। অনুমোদন হলে আপনাকে জানানো হবে।</p>
            
            <div class="user-info">
                <p><i class="bi bi-person me-2"></i><strong>নাম:</strong> <?php echo sanitize($affiliator['name']); ?></p>
                <p><i class="bi bi-envelope me-2"></i><strong>ইমেইল:</strong> <?php echo $affiliator['email']; ?></p>
                <p><i class="bi bi-phone me-2"></i><strong>ফোন:</strong> <?php echo $affiliator['phone']; ?></p>
                <p><i class="bi bi-calendar me-2"></i><strong>আবেদনের তারিখ:</strong> <?php echo date('d M Y, h:i A', strtotime($affiliator['created_at'])); ?></p>
            </div>
            
            <div class="d-flex gap-3 justify-content-center">
                <a href="<?php echo baseUrl(); ?>" class="btn btn-home">
                    <i class="bi bi-house me-2"></i>হোমে যান
                </a>
                <a href="<?php echo baseUrl('affiliate/logout.php'); ?>" class="btn btn-logout">
                    <i class="bi bi-box-arrow-right me-2"></i>লগআউট
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
