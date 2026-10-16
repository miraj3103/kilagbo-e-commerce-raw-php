<?php
/**
 * Kilagbo - Single Digital Product Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'digital-products';
$slug = sanitize($_GET['slug'] ?? '');

if (!$slug) {
    redirect(baseUrl('digital-products'));
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM digital_products WHERE slug = ? AND status = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    redirect(baseUrl('digital-products'));
}

$pageTitle = $product['name'];

// Fetch variations
$variationsStmt = $pdo->prepare("SELECT * FROM digital_product_variations WHERE product_id = ? ORDER BY attribute_name, id");
$variationsStmt->execute([$product['id']]);
$variations = $variationsStmt->fetchAll();

// Group variations by attribute name
$groupedVariations = [];
foreach ($variations as $var) {
    if (!isset($groupedVariations[$var['attribute_name']])) {
        $groupedVariations[$var['attribute_name']] = [];
    }
    $groupedVariations[$var['attribute_name']][] = $var;
}

// Get payment accounts
$bkashAccount = $pdo->query("SELECT * FROM payment_accounts WHERE payment_type = 'bkash' AND is_active = 1 LIMIT 1")->fetch();
$nagadAccount = $pdo->query("SELECT * FROM payment_accounts WHERE payment_type = 'nagad' AND is_active = 1 LIMIT 1")->fetch();

// Handle purchase form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerPhone = sanitize($_POST['customer_phone'] ?? '');
    $customerEmail = sanitize($_POST['customer_email'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'bkash');
    $paymentNumber = sanitize($_POST['payment_number'] ?? '');
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    $variationId = !empty($_POST['variation_id']) ? intval($_POST['variation_id']) : null;
    
    $errors = [];
    if (empty($customerName)) $errors[] = 'আপনার নাম দিন';
    if (empty($customerPhone)) $errors[] = 'ফোন নম্বর দিন';
    if (empty($customerEmail)) $errors[] = 'ইমেইল দিন';
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'সঠিক ইমেইল দিন';
    if (empty($paymentNumber)) $errors[] = 'পেমেন্ট নম্বর দিন';
    if (empty($transactionId)) $errors[] = 'ট্রানজেকশন আইডি দিন';
    
    if (empty($errors)) {
        // Calculate price
        $price = $product['sale_price'] ?: $product['regular_price'];
        if ($variationId) {
            $varStmt = $pdo->prepare("SELECT price_adjustment FROM digital_product_variations WHERE id = ?");
            $varStmt->execute([$variationId]);
            $varData = $varStmt->fetch();
            if ($varData) {
                $price += $varData['price_adjustment'];
            }
        }
        
        // Generate order number
        $orderNumber = 'DGT' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("
            INSERT INTO digital_orders (order_number, product_id, variation_id, customer_name, customer_phone, customer_email, price, payment_method, payment_number, transaction_id, payment_status, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
        ");
        $stmt->execute([$orderNumber, $product['id'], $variationId, $customerName, $customerPhone, $customerEmail, $price, $paymentMethod, $paymentNumber, $transactionId]);
        
        // Add notification
        addNotification('digital_order', 'নতুন ডিজিটাল অর্ডার', 'অর্ডার নং: ' . $orderNumber . ' - ' . $product['name'], adminUrl('digital-orders'));
        
        setFlash('success', 'আপনার অর্ডার সফলভাবে জমা হয়েছে! অর্ডার নম্বর: ' . $orderNumber . '. পেমেন্ট যাচাই করার পর আপনার ইমেইলে ডাউনলোড লিংক পাঠানো হবে।');
        redirect(baseUrl('digital-product/' . $product['slug']));
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

// Parse gallery
$gallery = json_decode($product['gallery'], true) ?: [];

// Related products
$relatedStmt = $pdo->prepare("SELECT * FROM digital_products WHERE id != ? AND status = 1 ORDER BY RAND() LIMIT 4");
$relatedStmt->execute([$product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

include 'includes/header.php';
?>

<style>
    .product-page {
        padding: 40px 0;
    }
    
    .product-gallery {
        position: sticky;
        top: 100px;
    }
    
    .main-image-container {
        width: 100%;
        aspect-ratio: 1;
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
        object-fit: contain;
        padding: 20px;
    }
    
    .thumbnail-list {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .thumbnail-item {
        width: 70px;
        height: 70px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .thumbnail-item:hover, .thumbnail-item.active {
        border-color: #0d6efd;
    }
    
    .thumbnail-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .product-details {
        padding-left: 30px;
    }
    
    .product-badge {
        display: inline-block;
        background: #0d6efd;
        color: #fff;
        padding: 4px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        margin-bottom: 15px;
    }
    
    .file-badge {
        display: inline-block;
        background: #333;
        color: #fff;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .product-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 20px;
        line-height: 1.4;
    }
    
    .product-price-box {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        border: 1px solid #90caf9;
    }
    
    .product-price-box .current-price {
        font-size: 2rem;
        font-weight: 700;
        color: #0d6efd;
    }
    
    .product-price-box .regular-price {
        font-size: 1.2rem;
        color: var(--text-light);
        text-decoration: line-through;
        margin-left: 15px;
    }
    
    .product-price-box .discount-badge {
        background: #ff6600;
        color: #fff;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
        margin-left: 15px;
    }
    
    .product-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 25px;
        padding: 15px;
        background: var(--bg-light);
        border-radius: 10px;
    }
    
    .product-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-color);
    }
    
    .product-meta-item i {
        color: #0d6efd;
        font-size: 1.2rem;
    }
    
    .variation-group {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .variation-label {
        font-weight: 600;
        margin-bottom: 0;
        min-width: 80px;
        font-size: 0.95rem;
    }
    
    .variation-options {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .variation-option {
        display: none;
    }
    
    .variation-option + label {
        padding: 6px 14px;
        border: 1.5px solid var(--border-color);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 0.85rem;
        background: #f8f9fa;
    }
    
    .variation-option:checked + label {
        background: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
    }
    
    .variation-option + label:hover {
        border-color: #0d6efd;
    }
    
    .details-collapse {
        margin-top: 20px;
        margin-bottom: 20px;
    }
    
    .details-toggle {
        width: 100%;
        text-align: left;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 12px 14px;
        border-radius: 10px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
    }
    
    .details-content {
        display: none;
        padding: 15px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-top: 8px;
        color: #555;
        line-height: 1.8;
    }
    
    .details-content.show { display: block; }
    
    .btn-buy-now {
        width: 100%;
        padding: 16px 30px;
        font-size: 1.1rem;
        font-weight: 600;
        background: #0d6efd;
        color: #fff;
        border: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .btn-buy-now:hover {
        background: #0b5ed7;
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(13, 110, 253, 0.3);
    }
    
    .product-description {
        padding: 40px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 40px;
    }
    
    .purchase-modal .modal-header {
        background: #0d6efd;
        color: #fff;
        border-radius: 12px 12px 0 0;
    }
    
    .purchase-modal .modal-content {
        border-radius: 12px;
        border: none;
    }
    
    .purchase-modal .btn-close {
        filter: invert(1);
    }
    
    .payment-method-card {
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .payment-method-card:hover {
        border-color: #0d6efd;
    }
    
    .payment-method-card.active {
        border-color: #0d6efd;
        background: #e3f2fd;
    }
    
    .payment-method-card img {
        height: 40px;
        margin-bottom: 10px;
    }
    
    .payment-info {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .related-products {
        padding: 40px 0;
        background: var(--bg-light);
        margin-top: 40px;
    }
    
    @media (max-width: 991px) {
        .product-details {
            padding-left: 0;
            margin-top: 30px;
        }
        
        .product-title {
            font-size: 1.4rem;
        }
        
        .product-price-box .current-price {
            font-size: 1.6rem;
        }
    }

    @media (max-width: 768px) {
        .product-price-box {
            padding: 15px;
            margin-bottom: 15px;
        }
        .product-price-box .current-price {
            font-size: 1.5rem;
        }
        .variation-label {
            min-width: 60px;
            font-size: 0.9rem;
        }
        .variation-option + label {
            padding: 4px 10px;
            font-size: 0.8rem;
        }
    }
</style>

<?php showFlash(); ?>

<div class="product-page">
    <div class="container">
        <div class="row">
            <!-- Product Gallery -->
            <div class="col-lg-6">
                <div class="product-gallery">
                    <div class="main-image-container">
                        <?php if ($product['image']): ?>
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>" id="mainImg">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-file-earmark-code" style="font-size: 5rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($product['image'] || !empty($gallery)): ?>
                    <div class="thumbnail-list">
                        <?php if ($product['image']): ?>
                        <div class="thumbnail-item active" onclick="changeImage('<?php echo $product['image']; ?>', this)">
                            <img src="<?php echo $product['image']; ?>" alt="">
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
            
            <!-- Product Details -->
            <div class="col-lg-6">
                <div class="product-details">
                    <span class="product-badge"><i class="bi bi-file-earmark-code me-1"></i>ডিজিটাল প্রোডাক্ট</span>
                    <?php if ($product['file_type']): ?>
                    <span class="file-badge"><?php echo sanitize($product['file_type']); ?></span>
                    <?php endif; ?>
                    
                    <h1 class="product-title"><?php echo sanitize($product['name']); ?></h1>
                    
                    <div class="product-price-box">
                        <span class="current-price" id="displayPrice"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                        <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                        <span class="discount-badge"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($product['file_size'] || $product['access_type']): ?>
                    <div class="product-meta">
                        <?php if ($product['file_size']): ?>
                        <div class="product-meta-item">
                            <i class="bi bi-hdd"></i>
                            <span>সাইজ: <?php echo sanitize($product['file_size']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($product['access_type']): ?>
                        <div class="product-meta-item">
                            <i class="bi bi-download"></i>
                            <span>অ্যাক্সেস: 
                                <?php 
                                $accessLabels = ['download' => 'ডাউনলোড', 'streaming' => 'স্ট্রিমিং', 'both' => 'উভয়'];
                                echo $accessLabels[$product['access_type']] ?? $product['access_type'];
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($product['short_description']): ?>
                    <p class="mb-4"><?php echo nl2br(sanitize($product['short_description'])); ?></p>
                    <?php endif; ?>
                    
                    <!-- Variations -->
                    <?php if ($product['has_variations'] && !empty($groupedVariations)): ?>
                    <form id="variationForm">
                        <?php foreach ($groupedVariations as $attrName => $vars): ?>
                        <div class="variation-group">
                            <span class="variation-label"><?php echo sanitize($attrName); ?>:</span>
                            <div class="variation-options">
                                <?php foreach ($vars as $index => $var): ?>
                                <input type="radio" name="variation" id="var_<?php echo $var['id']; ?>" value="<?php echo $var['id']; ?>" 
                                       class="variation-option" data-price="<?php echo $var['price_adjustment']; ?>" <?php echo $index == 0 ? 'checked' : ''; ?>>
                                <label for="var_<?php echo $var['id']; ?>">
                                    <?php echo sanitize($var['attribute_value']); ?>
                                    <?php if ($var['price_adjustment'] > 0): ?>
                                    (+৳<?php echo number_format($var['price_adjustment']); ?>)
                                    <?php endif; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </form>
                    <?php endif; ?>
                    
                    <!-- Buy Now Button -->
                    <button type="button" class="btn-buy-now" data-bs-toggle="modal" data-bs-target="#purchaseModal">
                        <i class="bi bi-bag-check me-2"></i>এখনই কিনুন
                    </button>
                    
                    <div class="mt-3 text-center text-muted small">
                        <i class="bi bi-shield-check me-1"></i>পেমেন্ট যাচাইয়ের পর তাৎক্ষণিক ডেলিভারি
                    </div>
                </div>
            </div>
        </div>
        
                    <!-- Description Dropdown -->
                    <?php if ($product['description']): ?>
                    <div class="details-collapse">
                        <button type="button" class="details-toggle" onclick="toggleDetails()">
                            বিবরণ দেখুন
                            <i class="bi bi-chevron-down" id="detailsIcon"></i>
                        </button>
                        <div class="details-content" id="detailsContent">
                            <?php echo nl2br(sanitize($product['description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
    </div>
</div>

<!-- Purchase Modal -->
<div class="modal fade purchase-modal" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bag-check me-2"></i>ক্রয় করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">আপনার তথ্য</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">আপনার নাম <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" required placeholder="পুরো নাম লিখুন">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ফোন নম্বর <span class="text-danger">*</span></label>
                                <input type="tel" name="customer_phone" class="form-control" required placeholder="01XXXXXXXXX">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ইমেইল <span class="text-danger">*</span></label>
                                <input type="email" name="customer_email" class="form-control" required placeholder="your@email.com">
                                <small class="text-muted">এই ইমেইলে ডাউনলোড লিংক পাঠানো হবে</small>
                            </div>
                            
                            <?php if ($product['has_variations'] && !empty($groupedVariations)): ?>
                            <input type="hidden" name="variation_id" id="selectedVariation" value="">
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="mb-3">পেমেন্ট</h6>
                            
                            <!-- Payment Method Selection -->
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="payment-method-card active" data-method="bkash" onclick="selectPaymentMethod('bkash', this)">
                                        <img src="https://www.logo.wine/a/logo/BKash/BKash-bKash-Logo.wine.svg" alt="bKash" style="height: 35px;">
                                        <div class="small mt-1" style="color: #e2136e; font-weight: 600;">বিকাশ</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="payment-method-card" data-method="nagad" onclick="selectPaymentMethod('nagad', this)">
                                        <img src="https://download.logo.wine/logo/Nagad/Nagad-Logo.wine.png" alt="Nagad" style="height: 35px;">
                                        <div class="small mt-1" style="color: #f05a28; font-weight: 600;">নগদ</div>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="payment_method" id="paymentMethod" value="bkash">
                            
                            <!-- Payment Info -->
                            <div class="payment-info" id="paymentInfo">
                                <div id="bkashInfo">
                                    <strong><i class="bi bi-phone me-1"></i>বিকাশ নম্বর:</strong> 
                                    <span class="fs-5 fw-bold"><?php echo $bkashAccount['account_number'] ?? '01XXXXXXXXX'; ?></span>
                                    <br><small>Send Money করে নিচে ট্রানজেকশন আইডি দিন</small>
                                </div>
                                <div id="nagadInfo" style="display: none;">
                                    <strong><i class="bi bi-phone me-1"></i>নগদ নম্বর:</strong> 
                                    <span class="fs-5 fw-bold"><?php echo $nagadAccount['account_number'] ?? '01XXXXXXXXX'; ?></span>
                                    <br><small>Send Money করে নিচে ট্রানজেকশন আইডি দিন</small>
                                </div>
                            </div>
                            
                            <div class="alert alert-primary py-2">
                                <strong>পরিমাণ:</strong> <span id="modalPrice"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">আপনার নম্বর (যেটা থেকে পাঠিয়েছেন) <span class="text-danger">*</span></label>
                                <input type="tel" name="payment_number" class="form-control" required placeholder="01XXXXXXXXX">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ট্রানজেকশন আইডি (TrxID) <span class="text-danger">*</span></label>
                                <input type="text" name="transaction_id" class="form-control" required placeholder="যেমন: 8KL7XXXX">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="buy_now" class="btn" style="background: #0d6efd; color: #fff;">
                        <i class="bi bi-check-circle me-1"></i>অর্ডার সম্পন্ন করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Related Products -->
<?php if (!empty($relatedProducts)): ?>
<section class="related-products">
    <div class="container">
        <h3 class="section-title">সম্পর্কিত প্রোডাক্ট</h3>
        <div class="row g-4">
            <?php foreach ($relatedProducts as $related): ?>
            <div class="col-6 col-lg-3">
                <div class="product-card" style="background: #fff; border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                    <a href="<?php echo baseUrl('digital-product/' . $related['slug']); ?>" style="display: block; aspect-ratio: 1; overflow: hidden; background: #f8f9fa;">
                        <?php if ($related['image']): ?>
                        <img src="<?php echo $related['image']; ?>" alt="<?php echo sanitize($related['name']); ?>" style="width: 100%; height: 100%; object-fit: contain; padding: 15px;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-file-earmark-code" style="font-size: 2rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                    </a>
                    <div style="padding: 15px;">
                        <a href="<?php echo baseUrl('digital-product/' . $related['slug']); ?>" style="font-weight: 600; color: var(--text-color); text-decoration: none; display: block; margin-bottom: 10px; font-size: 0.9rem;">
                            <?php echo sanitize($related['name']); ?>
                        </a>
                        <div style="font-weight: 700; color: #0d6efd;">
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
const basePrice = <?php echo $product['sale_price'] ?: $product['regular_price']; ?>;

function toggleDetails() {
    const content = document.getElementById('detailsContent');
    const icon = document.getElementById('detailsIcon');
    if (!content || !icon) return;
    if (content.style.display === 'block') {
        content.style.display = 'none';
        icon.classList.remove('bi-chevron-up');
        icon.classList.add('bi-chevron-down');
    } else {
        content.style.display = 'block';
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-up');
    }
}

function changeImage(src, el) {
    const img = document.getElementById('mainImg');
    if (img) {
        img.src = src;
    }
    document.querySelectorAll('.thumbnail-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
}

function selectPaymentMethod(method, el) {
    document.querySelectorAll('.payment-method-card').forEach(card => card.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('paymentMethod').value = method;
    
    if (method === 'bkash') {
        document.getElementById('bkashInfo').style.display = 'block';
        document.getElementById('nagadInfo').style.display = 'none';
    } else {
        document.getElementById('bkashInfo').style.display = 'none';
        document.getElementById('nagadInfo').style.display = 'block';
    }
}

// Handle variation selection
document.querySelectorAll('.variation-option').forEach(input => {
    input.addEventListener('change', function() {
        const priceAdj = parseFloat(this.dataset.price) || 0;
        const totalPrice = basePrice + priceAdj;
        document.getElementById('displayPrice').textContent = '৳' + totalPrice.toLocaleString('en-BD');
        document.getElementById('modalPrice').textContent = '৳' + totalPrice.toLocaleString('en-BD');
        document.getElementById('selectedVariation').value = this.value;
    });
});

// Set initial variation
<?php if ($product['has_variations'] && !empty($groupedVariations)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const firstVar = document.querySelector('.variation-option:checked');
    if (firstVar) {
        document.getElementById('selectedVariation').value = firstVar.value;
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
