<?php
/**
 * Kilagbo - Order Success Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$pageTitle = 'অর্ডার সফল';

if (!isset($_SESSION['last_order'])) {
    redirect(baseUrl());
}

$orderInfo = $_SESSION['last_order'];
unset($_SESSION['last_order']);

include 'includes/header.php';
?>

<style>
    .success-page {
        min-height: 60vh;
        display: flex;
        align-items: center;
        padding: 60px 0;
    }
    
    .success-card {
        background: #fff;
        border-radius: 16px;
        padding: 50px;
        text-align: center;
        max-width: 500px;
        margin: 0 auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: #28a745;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        margin: 0 auto 25px;
        animation: scaleIn 0.5s ease;
    }
    
    @keyframes scaleIn {
        0% { transform: scale(0); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .success-card h2 {
        margin-bottom: 15px;
        color: #28a745;
    }
    
    .order-number {
        background: var(--bg-light);
        padding: 15px 25px;
        border-radius: 10px;
        display: inline-block;
        margin: 20px 0;
    }
    
    .order-number span {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--primary-color);
    }
</style>

<div class="success-page">
    <div class="container">
        <div class="success-card">
            <div class="success-icon">
                <i class="bi bi-check-lg"></i>
            </div>
            
            <h2>অর্ডার সফল হয়েছে!</h2>
            <p class="text-muted mb-3">আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে। শীঘ্রই আমরা আপনার সাথে যোগাযোগ করব।</p>
            
            <div class="order-number">
                <small class="d-block text-muted mb-1">অর্ডার নম্বর</small>
                <span><?php echo $orderInfo['order_number']; ?></span>
            </div>
            
            <p class="mb-4">
                <strong>মোট পেমেন্ট:</strong> <?php echo formatPrice($orderInfo['total']); ?>
            </p>
            
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-primary-custom">
                    <i class="bi bi-bag me-2"></i>শপিং চালিয়ে যান
                </a>
                <a href="<?php echo baseUrl(); ?>" class="btn btn-outline-custom">
                    <i class="bi bi-house me-2"></i>হোমে যান
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // DataLayer - Purchase
    pushDataLayer('purchase', {
        transaction_id: '<?php echo $orderInfo['order_number']; ?>',
        value: <?php echo $orderInfo['total']; ?>,
        currency: 'BDT'
    });
    
    // Facebook Pixel - Purchase
    <?php if (getSettings('facebook_pixel_id') && getSettings('facebook_pixel_active') == '1'): ?>
    if (typeof fbq === 'function') {
        fbq('track', 'Purchase', {
            value: <?php echo $orderInfo['total']; ?>,
            currency: 'BDT'
        });
    }
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
