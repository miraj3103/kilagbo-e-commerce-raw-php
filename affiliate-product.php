<?php
/**
 * Kilagbo - Affiliate Product Page
 * When customers click on affiliate links, they land here
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config/config.php';

// Function to get affiliate link by code
function getAffiliateLinkByCodeLocal($code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT al.*, ap.*, a.name as affiliator_name, a.id as affiliator_id,
                   ap.id as product_id, al.id as link_id,
                   c.name as category_name
            FROM affiliate_links al
            JOIN affiliate_products ap ON al.product_id = ap.id
            JOIN affiliators a ON al.affiliator_id = a.id
            LEFT JOIN categories c ON ap.category_id = c.id
            WHERE al.link_code = ? AND al.status = 1 AND ap.status = 1 AND a.status = 'approved'
        ");
        $stmt->execute([$code]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

// Function to track affiliate click
function trackAffiliateClickLocal($linkId, $affiliatorId, $productId) {
    global $pdo;
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Check for duplicate click within 1 hour
        $stmt = $pdo->prepare("
            SELECT id FROM affiliate_clicks 
            WHERE link_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$linkId, $ip]);
        
        if (!$stmt->fetch()) {
            // Insert new click
            $pdo->prepare("
                INSERT INTO affiliate_clicks (link_id, affiliator_id, product_id, ip_address, user_agent, referrer)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$linkId, $affiliatorId, $productId, $ip, $userAgent, $referer]);
            
            // Update click counts
            $pdo->prepare("UPDATE affiliate_links SET clicks = clicks + 1 WHERE id = ?")->execute([$linkId]);
            $pdo->prepare("UPDATE affiliators SET total_clicks = total_clicks + 1 WHERE id = ?")->execute([$affiliatorId]);
        }
        
        // Set cookie for attribution (30 days)
        setcookie('affiliate_ref', $linkId, time() + (30 * 24 * 60 * 60), '/');
    } catch (Exception $e) {
        // Silently fail
    }
}

// Function to get delivery charges
function getAffiliateDeliveryChargesLocal() {
    global $pdo;
    try {
        return $pdo->query("SELECT * FROM affiliate_delivery_charges WHERE status = 1 ORDER BY charge ASC")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get affiliate link code from URL
$linkCode = sanitize($_GET['ref'] ?? '');

if (empty($linkCode)) {
    redirect(baseUrl());
}

// Get link and product data
$linkData = getAffiliateLinkByCodeLocal($linkCode);

if (!$linkData) {
    setFlash('error', 'এই লিংকটি বৈধ নয় বা পণ্যটি পাওয়া যায়নি');
    redirect(baseUrl());
}

// Track the click
trackAffiliateClickLocal($linkData['link_id'], $linkData['affiliator_id'], $linkData['product_id']);

$product = $linkData;
$productTitle = html_entity_decode($product['name'] ?? '', ENT_QUOTES | ENT_HTML5);
$pageTitle = $productTitle;

// Get delivery charges
$deliveryCharges = getAffiliateDeliveryChargesLocal();

$gallery = [];
if ($product['gallery']) {
    $gallery = json_decode($product['gallery'], true) ?: [];
}

// Get variations if retail product
$groupedVariations = [];
if (!empty($product['retail_product_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY id ASC");
        $stmt->execute([$product['retail_product_id']]);
        $variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($variations as $v) {
            $groupedVariations[$v['attribute_name']][] = $v;
        }
    } catch (Exception $e) {
        // Ignore error
    }
}

// Get minimum quantity for wholesale products
$minQty = 1;
if (!empty($product['wholesale_product_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT min_order_qty FROM wholesale_products WHERE id = ?");
        $stmt->execute([$product['wholesale_product_id']]);
        $wp = $stmt->fetch();
        if ($wp && $wp['min_order_qty'] > 1) {
            $minQty = $wp['min_order_qty'];
        }
    } catch (Exception $e) {
        // Ignore
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .product-page {
        padding: 40px 0;
    }
    
    .product-gallery {
        position: sticky;
        top: 100px;
    }
    
    .main-image {
        width: 100%;
        aspect-ratio: 1;
        background: #f8f9fa;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 15px;
    }
    
    .main-image img {
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
    
    .thumbnail {
        width: 70px;
        height: 70px;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid transparent;
        background: #f8f9fa;
    }
    
    .thumbnail.active {
        border-color: #10b981;
    }
    
    .thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    
    .product-info h1 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .product-price {
        margin-bottom: 20px;
    }
    
    .product-price .current {
        font-size: 2rem;
        font-weight: 700;
        color: #10b981;
    }
    
    .product-price .original {
        font-size: 1.2rem;
        color: #999;
        text-decoration: line-through;
        margin-left: 10px;
    }
    
    .product-price .discount {
        background: #fef3c7;
        color: #92400e;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 10px 0 20px;
    }
    .btn-order-now {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        padding: 12px 18px;
        border-radius: 10px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-order-now:hover { color: #fff; box-shadow: 0 8px 20px rgba(16,185,129,0.25); }
    .btn-visit {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
        padding: 12px 18px;
        border-radius: 10px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-visit:hover { color: #312e81; box-shadow: 0 6px 18px rgba(99,102,241,0.15); }
    
    .details-collapse {
        margin-top: 15px;
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
    
    .delivery-label {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 12px;
        border-radius: 10px;
        margin-top: 15px;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .product-gallery {
            position: static;
            margin-bottom: 25px;
        }
        .product-price .current {
            font-size: 1.6rem;
        }
        .product-info h1 {
            font-size: 1.4rem;
        }
    }
    
    .product-description {
        color: #666;
        line-height: 1.8;
        margin-bottom: 25px;
    }
    
    .order-form {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 25px;
        margin-top: 25px;
    }
    
    .order-form h4 {
        font-weight: 600;
        margin-bottom: 20px;
        color: #10b981;
    }
    
    .form-label {
        font-weight: 500;
    }
    
    .delivery-options {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .delivery-option {
        flex: 1;
        padding: 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .delivery-option:hover {
        border-color: #10b981;
    }
    
    .delivery-option.active {
        border-color: #10b981;
        background: #ecfdf5;
    }
    
    .delivery-option input {
        display: none;
    }
    
    .delivery-option .area {
        font-weight: 600;
        display: block;
    }
    
    .delivery-option .charge {
        color: #10b981;
        font-weight: 700;
    }
    
    .order-summary {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .order-summary .row {
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    
    .order-summary .row:last-child {
        border-bottom: none;
        font-weight: 700;
        font-size: 1.1rem;
        color: #10b981;
    }
    
    .btn-order {
        width: 100%;
        padding: 15px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 10px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
        color: #fff;
        transition: all 0.3s ease;
        margin-top: 20px;
    }
    
    .btn-order:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(16,185,129,0.3);
        color: #fff;
    }
    
    .stock-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        margin-bottom: 15px;
    }
    
    .stock-badge.in-stock {
        background: #d1fae5;
        color: #065f46;
    }
    
    .stock-badge.out-stock {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .affiliate-badge {
        background: #ecfdf5;
        border: 1px solid #10b981;
        border-radius: 8px;
        padding: 10px 15px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        color: #065f46;
    }
    
    .affiliate-badge i {
        color: #10b981;
    }
</style>

<section class="product-page">
    <div class="container">
        <div class="row g-5">
            <!-- Product Gallery -->
            <div class="col-lg-5">
                <div class="product-gallery">
                    <div class="main-image">
                        <img src="<?php echo imageUrl($product['image']); ?>" alt="<?php echo sanitize($product['name']); ?>" id="mainImage">
                    </div>
                    
                    <?php if (!empty($gallery)): ?>
                    <div class="thumbnail-list">
                        <div class="thumbnail active" onclick="changeImage('<?php echo imageUrl($product['image']); ?>', this)">
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="">
                        </div>
                        <?php foreach ($gallery as $img): ?>
                        <div class="thumbnail" onclick="changeImage('<?php echo imageUrl($img); ?>', this)">
                            <img src="<?php echo imageUrl($img); ?>" alt="">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="col-lg-7">
                <div class="product-info">
                    <?php if ($product['category_name']): ?>
                    <small class="text-muted"><?php echo sanitize($product['category_name']); ?></small>
                    <?php endif; ?>
                    
                    <h1><?php echo sanitize($productTitle); ?></h1>
                    
                    <?php if ($product['stock_quantity'] === null || $product['stock_quantity'] > 0): ?>
                    <span class="stock-badge in-stock"><i class="bi bi-check-circle me-1"></i>স্টকে আছে</span>
                    <?php else: ?>
                    <span class="stock-badge out-stock"><i class="bi bi-x-circle me-1"></i>স্টকে নেই</span>
                    <?php endif; ?>
                    
                    <div class="product-price">
                        <span class="current"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <span class="original"><?php echo formatPrice($product['regular_price']); ?></span>
                        <?php 
                        $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100);
                        ?>
                        <span class="discount"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="#orderForm" class="btn-order-now">
                            <i class="bi bi-bag-check"></i> অর্ডার করুন
                        </a>
                        <a href="<?php echo baseUrl(); ?>" class="btn-visit">
                            <i class="bi bi-globe2"></i> ওয়েবসাইট ভিজিট করুন
                        </a>
                    </div>
                    
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


                    
                    <!-- Order Form -->
                    <?php if ($product['stock_quantity'] === null || $product['stock_quantity'] > 0): ?>
                    <div class="order-form" id="orderForm">
                        <h4><i class="bi bi-bag-check me-2"></i>অর্ডার করুন</h4>
                        
                        <form action="<?php echo baseUrl('affiliate-checkout.php'); ?>" method="POST">
                            <input type="hidden" name="link_code" value="<?php echo $linkCode; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            
                            <?php if (!empty($groupedVariations)): ?>
                                <?php foreach ($groupedVariations as $name => $vars): ?>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo sanitize($name); ?> *</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($vars as $index => $v): ?>
                                            <div>
                                                <input type="radio" class="btn-check variation-radio" 
                                                       name="variation[<?php echo sanitize($name); ?>]" 
                                                       id="var_<?php echo $v['id']; ?>" 
                                                       value="<?php echo sanitize($v['attribute_value']); ?>" 
                                                       <?php echo $index === 0 ? 'checked' : ''; ?>
                                                       required>
                                                <label class="btn btn-outline-secondary btn-sm" for="var_<?php echo $v['id']; ?>">
                                                    <?php echo sanitize($v['attribute_value']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">পরিমাণ <?php if($minQty > 1): ?><small class="text-danger">(সর্বনিম্ন <?php echo $minQty; ?> পিস)</small><?php endif; ?></label>
                                <div class="input-group" style="width: 150px;">
                                    <button class="btn btn-outline-secondary" type="button" id="btnMinus"><i class="bi bi-dash"></i></button>
                                    <input type="number" class="form-control text-center" name="quantity" id="quantity" value="<?php echo $minQty; ?>" min="<?php echo $minQty; ?>" required>
                                    <button class="btn btn-outline-secondary" type="button" id="btnPlus"><i class="bi bi-plus"></i></button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">আপনার নাম *</label>
                                <input type="text" class="form-control" name="customer_name" required placeholder="সম্পূর্ণ নাম লিখুন">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">মোবাইল নম্বর *</label>
                                <input type="tel" class="form-control" name="customer_phone" required placeholder="01XXXXXXXXX">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">সম্পূর্ণ ঠিকানা *</label>
                                <textarea class="form-control" name="shipping_address" rows="2" required placeholder="বাড়ি নং, রোড, এলাকা, জেলা"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">অর্ডার নোট (ঐচ্ছিক)</label>
                                <textarea class="form-control" name="order_note" rows="2" placeholder="অর্ডার সংক্রান্ত কোনো বিশেষ তথ্য থাকলে লিখুন"></textarea>
                            </div>
                            
                            <label class="form-label">ডেলিভারি এরিয়া *</label>
                            <div class="delivery-options">
                                <?php 
                                $first = true;
                                foreach ($deliveryCharges as $dc): 
                                ?>
                                <label class="delivery-option <?php echo $first ? 'active' : ''; ?>">
                                    <input type="radio" name="shipping_area" value="<?php echo $dc['area_type']; ?>" data-charge="<?php echo $dc['charge']; ?>" <?php echo $first ? 'checked' : ''; ?>>
                                    <span class="area"><?php echo sanitize($dc['name']); ?></span>
                                    <span class="charge">৳<?php echo number_format($dc['charge']); ?></span>
                                </label>
                                <?php 
                                $first = false;
                                endforeach; 
                                ?>
                            </div>
                            
                            <?php 
                            // Payment options
                            $sectionType = 'affiliate';
                            include __DIR__ . '/includes/payment-method.php';
                            ?>
                            
                            <div class="order-summary">
                                <div class="row">
                                    <div class="col-6">পণ্যের মূল্য:</div>
                                    <div class="col-6 text-end" id="productPrice"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-6">ডেলিভারি চার্জ:</div>
                                    <div class="col-6 text-end" id="deliveryCharge"><?php echo formatPrice($deliveryCharges[0]['charge'] ?? 0); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-6">সর্বমোট:</div>
                                    <div class="col-6 text-end" id="totalAmount">
                                        <?php 
                                        $productPrice = $product['sale_price'] ?: $product['regular_price'];
                                        $deliveryCharge = $deliveryCharges[0]['charge'] ?? 0;
                                        echo formatPrice($productPrice + $deliveryCharge); 
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary mb-2" onclick="addToCart()">
                                    <i class="bi bi-cart-plus me-2"></i>কার্টে যোগ করুন
                                </button>
                                <button type="submit" class="btn btn-order mt-0">
                                    <i class="bi bi-check2-circle me-2"></i>অর্ডার নিশ্চিত করুন
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>দুঃখিত, এই পণ্যটি বর্তমানে স্টকে নেই।
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">নোটিফিকেশন</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="toastMessage">
    </div>
  </div>
</div>

<script>
function changeImage(src, element) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
    element.classList.add('active');
}

// Delivery and Quantity Logic
const minQty = <?php echo $minQty; ?>;
const productPrice = <?php echo $product['sale_price'] ?: $product['regular_price']; ?>;

function updateTotals() {
    let qty = parseInt(document.getElementById('quantity').value) || minQty;
    if (qty < minQty) qty = minQty;
    
    // Get active delivery charge
    let deliveryCharge = 0;
    const activeOption = document.querySelector('.delivery-option.active input');
    if (activeOption) {
        deliveryCharge = parseFloat(activeOption.dataset.charge);
    }
    
    // Calculate total product price
    const totalProductPrice = productPrice * qty;
    const grandTotal = totalProductPrice + deliveryCharge;
    
    // Update UI
    document.getElementById('productPrice').textContent = '৳' + totalProductPrice.toLocaleString();
    document.getElementById('deliveryCharge').textContent = '৳' + deliveryCharge.toLocaleString();
    document.getElementById('totalAmount').textContent = '৳' + grandTotal.toLocaleString();
}

// Quantity Buttons
document.getElementById('btnMinus').addEventListener('click', function() {
    const input = document.getElementById('quantity');
    let val = parseInt(input.value) || minQty;
    if (val > minQty) {
        input.value = val - 1;
        updateTotals();
    }
});

document.getElementById('btnPlus').addEventListener('click', function() {
    const input = document.getElementById('quantity');
    let val = parseInt(input.value) || minQty;
    input.value = val + 1;
    updateTotals();
});

document.getElementById('quantity').addEventListener('change', function() {
    if (this.value < minQty) this.value = minQty;
    updateTotals();
});

// Delivery option selection
document.querySelectorAll('.delivery-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.delivery-option').forEach(o => o.classList.remove('active'));
        this.classList.add('active');
        this.querySelector('input').checked = true;
        updateTotals();
    });
});


// Toggle product details
function toggleDetails() {
    const content = document.getElementById('detailsContent');
    const icon = document.getElementById('detailsIcon');
    if (!content || !icon) return;
    content.classList.toggle('show');
    icon.classList.toggle('bi-chevron-down');
    icon.classList.toggle('bi-chevron-up');
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('liveToast');
    const toastBody = document.getElementById('toastMessage');
    toastBody.textContent = message;
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

function addToCart() {
    const productId = <?php echo $product['product_id']; ?>; // This is affiliate_product_id
    const qty = parseInt(document.getElementById('quantity').value) || 1;
    const affiliateLinkId = <?php echo $linkData['link_id']; ?>;
    const affiliatorId = <?php echo $linkData['affiliator_id']; ?>;
    
    // Get variations
    let variation = {};
    let missingVariation = false;
    
    document.querySelectorAll('.variation-radio').forEach(r => {
        const name = r.name.replace('variation[', '').replace(']', '');
        if (r.checked) {
            variation[name] = r.value;
        }
    });

    // Check if checkable variations exist but none selected
    // Since we used radio buttons with 'required' attribute and iterate over groups, we can check if we captured all groups
    <?php if (!empty($groupedVariations)): ?>
    const variationGroups = <?php echo json_encode(array_keys($groupedVariations)); ?>;
    variationGroups.forEach(group => {
        if (!variation[group]) missingVariation = true;
    });
    <?php endif; ?>
    
    if (missingVariation) {
        showToast('অনুগ্রহ করে ভেরিয়েশন সিলেক্ট করুন', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', qty);
    formData.append('section', 'affiliate');
    formData.append('affiliate_link_id', affiliateLinkId);
    formData.append('affiliator_id', affiliatorId);
    if (Object.keys(variation).length > 0) {
        formData.append('variation', JSON.stringify(variation));
    }
    
    fetch('<?php echo baseUrl("ajax/cart.php"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            // Update cart count in header
            const badge = document.querySelector('#cart-icon .badge');
            if (badge) {
                badge.textContent = data.cartCount;
            } else {
                const icon = document.getElementById('cart-icon');
                if (icon) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge';
                    newBadge.textContent = data.cartCount;
                    icon.appendChild(newBadge);
                }
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('সমস্যা হয়েছে, আবার চেষ্টা করুন', 'error');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
