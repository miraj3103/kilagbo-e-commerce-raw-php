<?php
/**
 * Kilagbo - 404 Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';
http_response_code(404);

$pageTitle = 'পেজ পাওয়া যায়নি';

include 'includes/header.php';
?>

<style>
    .error-page {
        min-height: 60vh;
        display: flex;
        align-items: center;
        padding: 60px 0;
        text-align: center;
    }
    
    .error-code {
        font-size: 8rem;
        font-weight: 700;
        color: var(--border-color);
        line-height: 1;
        margin-bottom: 20px;
    }
    
    .error-title {
        font-size: 1.8rem;
        margin-bottom: 15px;
    }
</style>

<div class="error-page">
    <div class="container">
        <div class="error-code">৪০৪</div>
        <h1 class="error-title">পেজ পাওয়া যায়নি!</h1>
        <p class="text-muted mb-4">দুঃখিত, আপনি যে পেজটি খুঁজছেন সেটি বিদ্যমান নেই।</p>
        
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?php echo baseUrl(); ?>" class="btn btn-primary-custom">
                <i class="bi bi-house me-2"></i>হোমে যান
            </a>
            <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-outline-custom">
                <i class="bi bi-bag me-2"></i>শপিং করুন
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
