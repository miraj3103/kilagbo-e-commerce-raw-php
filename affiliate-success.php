<?php
/**
 * Kilagbo - Affiliate Order Success Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config/config.php';

if (!isset($_SESSION['affiliate_order_success'])) {
    redirect(baseUrl());
}

$orderData = $_SESSION['affiliate_order_success'];
unset($_SESSION['affiliate_order_success']);

$pageTitle = 'অর্ডার সফল';

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .success-page {
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
    }
    
    .success-card {
        background: #fff;
        border-radius: 20px;
        padding: 50px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        max-width: 500px;
        width: 100%;
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
        70% { box-shadow: 0 0 0 20px rgba(16,185,129,0); }
        100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
    }
    
    .success-icon i {
        font-size: 3rem;
        color: #fff;
    }
    
    .success-card h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #10b981;
        margin-bottom: 15px;
    }
    
    .success-card p {
        color: #666;
        margin-bottom: 25px;
    }
    
    .order-details {
        background: #f3f4f6;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        text-align: left;
    }
    
    .order-details .row {
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .order-details .row:last-child {
        border-bottom: none;
    }
    
    .order-details .label {
        color: #666;
    }
    
    .order-details .value {
        font-weight: 600;
        text-align: right;
    }
    
    .order-number {
        font-size: 1.2rem;
        color: #10b981;
    }
    
    .btn-home {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-home:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16,185,129,0.3);
        color: #fff;
    }
</style>

<section class="success-page">
    <div class="success-card">
        <div class="success-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        
        <h1>অর্ডার সফল হয়েছে!</h1>
        <p>আপনার অর্ডার সফলভাবে গ্রহণ করা হয়েছে। শীঘ্রই আমরা আপনার সাথে যোগাযোগ করব।</p>
        
        <div class="order-details">
            <div class="row">
                <div class="col-5 label">অর্ডার নম্বর:</div>
                <div class="col-7 value order-number">#<?php echo $orderData['order_number']; ?></div>
            </div>
            <div class="row">
                <div class="col-5 label">গ্রাহক:</div>
                <div class="col-7 value"><?php echo sanitize($orderData['customer_name']); ?></div>
            </div>
            <div class="row">
                <div class="col-5 label">পণ্য:</div>
                <div class="col-7 value"><?php echo sanitize($orderData['product_name']); ?></div>
            </div>
            <div class="row">
                <div class="col-5 label">সর্বমোট:</div>
                <div class="col-7 value" style="color: #10b981;"><?php echo formatPrice($orderData['total_amount']); ?></div>
            </div>
        </div>
        
        <p class="mb-4"><small class="text-muted">পেমেন্ট: ক্যাশ অন ডেলিভারি</small></p>
        
        <a href="<?php echo baseUrl(); ?>" class="btn btn-home">
            <i class="bi bi-house me-2"></i>হোমে ফিরুন
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
