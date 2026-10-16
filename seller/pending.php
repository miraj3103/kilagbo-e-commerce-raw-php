<?php
/**
 * Kilagbo Seller Portal - Pending Approval Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'অনুমোদন মুলতুবি';

// Redirect if not logged in
if (!isSellerLoggedIn()) {
    redirect(baseUrl('seller/login.php'));
}

$seller = getSellerData();

// Redirect if already approved
if ($seller && $seller['status'] === 'approved') {
    redirect(baseUrl('seller/index.php'));
}

// Redirect if rejected
if ($seller && $seller['status'] === 'rejected') {
    sellerLogout();
    setFlash('error', 'আপনার অ্যাকাউন্ট প্রত্যাখ্যান করা হয়েছে।');
    redirect(baseUrl('seller/login.php'));
}

include __DIR__ . '/includes/header-auth.php';
?>

<style>
    .pending-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
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
        border-radius: 50%;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        margin: 0 auto 25px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .pending-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 15px;
    }
    
    .pending-text {
        color: #666;
        line-height: 1.7;
        margin-bottom: 30px;
    }
    
    .pending-info {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        text-align: left;
        margin-bottom: 25px;
    }
    
    .pending-info h6 {
        font-weight: 600;
        margin-bottom: 10px;
        color: #333;
    }
    
    .pending-info p {
        margin: 0;
        color: #666;
        font-size: 0.9rem;
    }
    
    .btn-logout {
        padding: 12px 30px;
        font-weight: 600;
        border-radius: 10px;
        background: #f8f9fa;
        color: #333;
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-logout:hover {
        background: #e9ecef;
        color: #333;
    }
    
    .btn-home {
        padding: 12px 30px;
        font-weight: 600;
        border-radius: 10px;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        border: none;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .btn-home:hover {
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(22, 163, 74, 0.4);
    }
</style>

<div class="pending-page">
    <div class="pending-container">
        <div class="pending-card">
            <div class="pending-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            
            <h1 class="pending-title">অনুমোদনের অপেক্ষায়</h1>
            
            <p class="pending-text">
                প্রিয় <strong><?php echo $seller['name']; ?></strong>,<br>
                আপনার সেলার অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে এবং বর্তমানে অ্যাডমিনের অনুমোদনের জন্য অপেক্ষা করছে।
            </p>
            
            <div class="pending-info">
                <h6><i class="bi bi-info-circle me-2"></i>পরবর্তী কি হবে?</h6>
                <p>
                    আমাদের টিম আপনার আবেদন পর্যালোচনা করবে এবং যত দ্রুত সম্ভব অনুমোদন দিবে। অনুমোদন হলে আপনি আপনার ড্যাশবোর্ডে অ্যাক্সেস পাবেন এবং পণ্য যোগ করতে পারবেন।
                </p>
            </div>
            
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="<?php echo baseUrl(); ?>" class="btn-home">
                    <i class="bi bi-house me-2"></i>হোমে যান
                </a>
                <a href="<?php echo baseUrl('seller/logout.php'); ?>" class="btn btn-logout">
                    <i class="bi bi-box-arrow-right me-2"></i>লগআউট
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-auth.php'; ?>
