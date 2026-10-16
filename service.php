<?php
/**
 * Kilagbo - Single Service Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'digital-services';
$slug = sanitize($_GET['slug'] ?? '');

if (!$slug) {
    redirect(baseUrl('digital-services'));
}

// Fetch service
$stmt = $pdo->prepare("SELECT * FROM services WHERE slug = ? AND status = 1");
$stmt->execute([$slug]);
$service = $stmt->fetch();

if (!$service) {
    redirect(baseUrl('digital-services'));
}

$pageTitle = $service['name'];

// Handle service request form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_service'])) {
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerPhone = sanitize($_POST['customer_phone'] ?? '');
    $serviceDetails = sanitize($_POST['service_details'] ?? '');
    
    $errors = [];
    if (empty($customerName)) $errors[] = 'আপনার নাম দিন';
    if (empty($customerPhone)) $errors[] = 'ফোন নম্বর দিন';
    if (empty($serviceDetails)) $errors[] = 'সার্ভিস সম্পর্কে বিস্তারিত লিখুন';
    
    if (empty($errors)) {
        // Generate order number
        $orderNumber = 'SRV' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $price = $service['sale_price'] ?: $service['regular_price'];
        
        $stmt = $pdo->prepare("
            INSERT INTO service_orders (order_number, service_id, customer_name, customer_phone, service_details, price, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$orderNumber, $service['id'], $customerName, $customerPhone, $serviceDetails, $price]);
        
        // Add notification
        addNotification('service_order', 'নতুন সার্ভিস অর্ডার', 'অর্ডার নং: ' . $orderNumber . ' - ' . $service['name'], adminUrl('service-orders'));
        
        setFlash('success', 'আপনার সার্ভিস রিকোয়েস্ট সফলভাবে জমা হয়েছে! অর্ডার নম্বর: ' . $orderNumber);
        redirect(baseUrl('service/' . $service['slug']));
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

// Parse gallery
$gallery = json_decode($service['gallery'], true) ?: [];

// Related services
$relatedStmt = $pdo->prepare("SELECT * FROM services WHERE id != ? AND status = 1 ORDER BY RAND() LIMIT 4");
$relatedStmt->execute([$service['id']]);
$relatedServices = $relatedStmt->fetchAll();

include 'includes/header.php';
?>

<style>
    .service-page {
        padding: 40px 0;
    }
    
    .service-gallery {
        position: sticky;
        top: 100px;
    }
    
    .main-image-container {
        width: 100%;
        aspect-ratio: 16/10;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 15px;
        border: 1px solid var(--border-color);
        position: relative;
        background: #f8f9fa;
    }
    
    .main-image-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .thumbnail-list {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .thumbnail-item {
        width: 80px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .thumbnail-item:hover, .thumbnail-item.active {
        border-color: #6f42c1;
    }
    
    .thumbnail-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .service-details {
        padding-left: 30px;
    }
    
    .service-badge {
        display: inline-block;
        background: #6f42c1;
        color: #fff;
        padding: 4px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        margin-bottom: 15px;
    }
    
    .service-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 20px;
        line-height: 1.4;
    }
    
    .service-price-box {
        background: linear-gradient(135deg, #f3e5f5 0%, #ede7f6 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        border: 1px solid #ce93d8;
    }
    
    .service-price-box .current-price {
        font-size: 2rem;
        font-weight: 700;
        color: #6f42c1;
    }
    
    .service-price-box .regular-price {
        font-size: 1.2rem;
        color: var(--text-light);
        text-decoration: line-through;
        margin-left: 15px;
    }
    
    .service-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 25px;
        padding: 15px;
        background: var(--bg-light);
        border-radius: 10px;
    }
    
    .service-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-color);
    }
    
    .service-meta-item i {
        color: #6f42c1;
        font-size: 1.2rem;
    }
    
    .btn-request-service {
        width: 100%;
        padding: 16px 30px;
        font-size: 1.1rem;
        font-weight: 600;
        background: #6f42c1;
        color: #fff;
        border: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .btn-request-service:hover {
        background: #5a32a3;
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(111, 66, 193, 0.3);
    }
    
    .service-description {
        padding: 40px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 40px;
    }
    
    .request-modal .modal-header {
        background: #6f42c1;
        color: #fff;
        border-radius: 12px 12px 0 0;
    }
    
    .request-modal .modal-content {
        border-radius: 12px;
        border: none;
    }
    
    .request-modal .btn-close {
        filter: invert(1);
    }
    
    .related-services {
        padding: 40px 0;
        background: var(--bg-light);
        margin-top: 40px;
    }
    
    @media (max-width: 991px) {
        .service-details {
            padding-left: 0;
            margin-top: 30px;
        }
        
        .service-title {
            font-size: 1.4rem;
        }
        
        .service-price-box .current-price {
            font-size: 1.6rem;
        }
    }
</style>

<?php showFlash(); ?>

<div class="service-page">
    <div class="container">
        <div class="row">
            <!-- Service Gallery -->
            <div class="col-lg-6">
                <div class="service-gallery">
                    <div class="main-image-container">
                        <?php if ($service['image']): ?>
                        <img src="<?php echo $service['image']; ?>" alt="<?php echo sanitize($service['name']); ?>" id="mainImg">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-briefcase" style="font-size: 5rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($service['image'] || !empty($gallery)): ?>
                    <div class="thumbnail-list">
                        <?php if ($service['image']): ?>
                        <div class="thumbnail-item active" onclick="changeImage('<?php echo $service['image']; ?>', this)">
                            <img src="<?php echo $service['image']; ?>" alt="">
                        </div>
                        <?php endif; ?>
                        <?php foreach ($gallery as $img): ?>
                        <div class="thumbnail-item" onclick="changeImage('<?php echo $img; ?>', this)">
                            <img src="<?php echo $img; ?>" alt="">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Service Details -->
            <div class="col-lg-6">
                <div class="service-details">
                    <span class="service-badge"><i class="bi bi-briefcase me-1"></i>ডিজিটাল সার্ভিস</span>
                    
                    <h1 class="service-title"><?php echo sanitize($service['name']); ?></h1>
                    
                    <div class="service-price-box">
                        <span class="current-price"><?php echo formatPrice($service['sale_price'] ?: $service['regular_price']); ?></span>
                        <?php if ($service['sale_price'] && $service['sale_price'] < $service['regular_price']): ?>
                        <span class="regular-price"><?php echo formatPrice($service['regular_price']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($service['duration'] || $service['delivery_time']): ?>
                    <div class="service-meta">
                        <?php if ($service['duration']): ?>
                        <div class="service-meta-item">
                            <i class="bi bi-clock"></i>
                            <span>সময়কাল: <?php echo sanitize($service['duration']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($service['delivery_time']): ?>
                        <div class="service-meta-item">
                            <i class="bi bi-truck"></i>
                            <span>ডেলিভারি: <?php echo sanitize($service['delivery_time']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($service['short_description']): ?>
                    <p class="mb-4"><?php echo nl2br(sanitize($service['short_description'])); ?></p>
                    <?php endif; ?>
                    
                    <!-- Request Service Button -->
                    <button type="button" class="btn-request-service" data-bs-toggle="modal" data-bs-target="#requestModal">
                        <i class="bi bi-send me-2"></i>সার্ভিস নিতে চাই
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Description -->
        <?php if ($service['description']): ?>
        <div class="service-description">
            <h3 class="section-title">বিবরণ</h3>
            <div class="description-content">
                <?php echo nl2br(sanitize($service['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Request Service Modal -->
<div class="modal fade request-modal" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send me-2"></i>সার্ভিস নিতে চাই</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>সার্ভিস:</strong> <?php echo sanitize($service['name']); ?><br>
                        <strong>মূল্য:</strong> <?php echo formatPrice($service['sale_price'] ?: $service['regular_price']); ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">আপনার নাম <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" class="form-control" required placeholder="পুরো নাম লিখুন">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ফোন নম্বর <span class="text-danger">*</span></label>
                        <input type="tel" name="customer_phone" class="form-control" required placeholder="01XXXXXXXXX">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">সার্ভিস সম্পর্কে বিস্তারিত <span class="text-danger">*</span></label>
                        <textarea name="service_details" class="form-control" rows="4" required placeholder="আপনার কী ধরনের সার্ভিস প্রয়োজন তা বিস্তারিত লিখুন..."></textarea>
                        <small class="text-muted">আপনার প্রয়োজনীয়তা, সময়সীমা, বিশেষ কোন চাহিদা থাকলে উল্লেখ করুন।</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="request_service" class="btn" style="background: #6f42c1; color: #fff;">
                        <i class="bi bi-check-circle me-1"></i>অর্ডার করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Related Services -->
<?php if (!empty($relatedServices)): ?>
<section class="related-services">
    <div class="container">
        <h3 class="section-title">সম্পর্কিত সার্ভিস</h3>
        <div class="row g-4">
            <?php foreach ($relatedServices as $related): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="service-card" style="background: #fff; border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                    <a href="<?php echo baseUrl('service/' . $related['slug']); ?>" style="display: block; aspect-ratio: 16/10; overflow: hidden;">
                        <?php if ($related['image']): ?>
                        <img src="<?php echo $related['image']; ?>" alt="<?php echo sanitize($related['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-briefcase" style="font-size: 2rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                    </a>
                    <div style="padding: 15px;">
                        <a href="<?php echo baseUrl('service/' . $related['slug']); ?>" style="font-weight: 600; color: var(--text-color); text-decoration: none; display: block; margin-bottom: 10px;">
                            <?php echo sanitize($related['name']); ?>
                        </a>
                        <div style="font-weight: 700; color: #6f42c1;">
                            <?php echo formatPrice($related['sale_price'] ?: $related['regular_price']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
function changeImage(src, el) {
    const img = document.getElementById('mainImg');
    if (img) {
        img.src = src;
    }
    document.querySelectorAll('.thumbnail-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
}
</script>

<?php include 'includes/footer.php'; ?>
