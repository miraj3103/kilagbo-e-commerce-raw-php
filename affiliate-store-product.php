<?php
/**
 * Kilagbo - Affiliate Store Single Product Page
 * Single product checkout page for store products (same design as affiliate-product.php)
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config/config.php';

// Get store code and product ID from URL
$storeCode = sanitize($_GET['store'] ?? '');
$productId = intval($_GET['product'] ?? 0);

if (empty($storeCode) || empty($productId)) {
    redirect(baseUrl());
}

// Get store data
$store = null;
try {
    $stmt = $pdo->prepare("
        SELECT s.*, a.name as affiliator_name, a.id as affiliator_id, a.status as affiliator_status
        FROM affiliate_stores s
        JOIN affiliators a ON s.affiliator_id = a.id
        WHERE s.store_code = ? AND s.status = 1
    ");
    $stmt->execute([$storeCode]);
    $store = $stmt->fetch();
} catch (PDOException $e) {
    setFlash('error', 'স্টোর পাওয়া যায়নি');
    redirect(baseUrl());
}

if (!$store || $store['affiliator_status'] !== 'approved') {
    setFlash('error', 'এই স্টোরটি বৈধ নয় বা বন্ধ আছে');
    redirect(baseUrl());
}

// Get product
$product = null;
$stmt = $pdo->prepare("SELECT * FROM affiliate_products WHERE id = ? AND status = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'পণ্যটি পাওয়া যায়নি');
    redirect(baseUrl('store/' . $storeCode));
}

// Set cookie for attribution
setcookie('affiliate_store', $store['id'], time() + (30 * 24 * 60 * 60), '/');
setcookie('affiliate_store_code', $storeCode, time() + (30 * 24 * 60 * 60), '/');

$productTitle = html_entity_decode($product['name'] ?? '', ENT_QUOTES | ENT_HTML5);
$storeName = $store['store_name'] ?: $store['affiliator_name'] . "'s Store";
$storeType = $store['store_type'];
$siteTitle = getSettings('site_title') ?: 'Kilagbo';
$siteFavicon = getSettings('site_favicon');
$siteLogo = getSettings('site_logo');

// Get delivery charges
$deliveryCharges = [];
try {
    $deliveryCharges = $pdo->query("SELECT * FROM affiliate_delivery_charges WHERE status = 1 ORDER BY charge ASC")->fetchAll();
} catch (Exception $e) {
    $deliveryCharges = [];
}

// Parse gallery
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

$productPrice = $product['sale_price'] ?: $product['regular_price'];
$hasDiscount = $product['sale_price'] && $product['regular_price'] > $product['sale_price'];
$discountPercent = $hasDiscount ? round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($productTitle); ?> - <?php echo sanitize($storeName); ?></title>
    <?php if ($siteFavicon): ?>
    <link rel="icon" href="<?php echo uploadUrl($siteFavicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        * { font-family: 'Hind Siliguri', sans-serif; }
        
        body {
            background: #f8f9fa;
            padding-top: 70px;
        }
        
        .store-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #fff;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 12px 0;
        }
        
        .store-header .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        
        .store-header .logo {
            flex-shrink: 0;
        }
        
        .store-header .logo img {
            height: 40px;
            max-width: 150px;
            object-fit: contain;
        }
        
        .store-header .logo-text {
            font-size: 1.4rem;
            font-weight: 700;
            color: #10b981;
            text-decoration: none;
        }
        
        .store-header .btn-visit-site {
            flex-shrink: 0;
            background: #10b981;
            color: #fff;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .store-header .btn-visit-site:hover {
            background: #059669;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }
        
        @media (max-width: 768px) {
            .store-header .btn-visit-site span {
                display: none;
            }
            
            .store-header .btn-visit-site {
                padding: 10px 14px;
            }
        }
        
        .product-page {
            padding: 30px 0 50px;
        }
        
        .product-gallery {
            position: sticky;
            top: 90px;
        }
        
        .main-image {
            width: 100%;
            aspect-ratio: 1;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
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
            background: #fff;
        }
        
        .thumbnail.active {
            border-color: #10b981;
        }
        
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .product-info {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
        }
        
        .product-info h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
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
        
        .details-collapse {
            margin-top: 15px;
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
        
        .order-form {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
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
            flex-wrap: wrap;
        }
        
        .delivery-option {
            flex: 1;
            min-width: 140px;
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
        
        @media (max-width: 991px) {
            .product-gallery {
                position: static;
                margin-bottom: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Store Header -->
    <header class="store-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <?php if ($siteLogo): ?>
                    <img src="<?php echo uploadUrl($siteLogo); ?>" alt="<?php echo sanitize($siteTitle); ?>">
                    <?php else: ?>
                    <span class="logo-text"><?php echo sanitize($siteTitle); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <a href="<?php echo baseUrl('cart'); ?>" class="position-relative text-dark text-decoration-none" id="cart-icon">
                        <i class="bi bi-bag fs-4"></i>
                        <?php $cartCount = getCartCount(); if ($cartCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $cartCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="<?php echo baseUrl(); ?>" class="btn-visit-site">
                        <i class="bi bi-globe2"></i>
                        <span>মূল ওয়েবসাইট</span>
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Product Page -->
    <section class="product-page">
        <div class="container">
            <div class="row g-4">
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
                        <h1><?php echo sanitize($productTitle); ?></h1>
                        
                        <?php if ($product['stock_quantity'] === null || $product['stock_quantity'] > 0): ?>
                        <span class="stock-badge in-stock"><i class="bi bi-check-circle me-1"></i>স্টকে আছে</span>
                        <?php else: ?>
                        <span class="stock-badge out-stock"><i class="bi bi-x-circle me-1"></i>স্টকে নেই</span>
                        <?php endif; ?>
                        
                        <div class="product-price">
                            <span class="current"><?php echo formatPrice($productPrice); ?></span>
                            <?php if ($hasDiscount): ?>
                            <span class="original"><?php echo formatPrice($product['regular_price']); ?></span>
                            <span class="discount"><?php echo $discountPercent; ?>% ছাড়</span>
                            <?php endif; ?>
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
                            
                            <form action="<?php echo baseUrl('affiliate-store-checkout.php'); ?>" method="POST">
                                <input type="hidden" name="store_code" value="<?php echo $storeCode; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="product_price" value="<?php echo $productPrice; ?>">
                                
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
                                    if (!empty($deliveryCharges)):
                                        $first = true;
                                        foreach ($deliveryCharges as $dc): 
                                    ?>
                                    <label class="delivery-option <?php echo $first ? 'active' : ''; ?>">
                                        <input type="radio" name="shipping_area" value="<?php echo $dc['area_type'] ?? $dc['area_name']; ?>" data-charge="<?php echo $dc['charge']; ?>" <?php echo $first ? 'checked' : ''; ?>>
                                        <span class="area"><?php echo sanitize($dc['name'] ?? $dc['area_name']); ?></span>
                                        <span class="charge">৳<?php echo number_format($dc['charge']); ?></span>
                                    </label>
                                    <?php 
                                        $first = false;
                                        endforeach;
                                    else:
                                    ?>
                                    <label class="delivery-option active">
                                        <input type="radio" name="shipping_area" value="inside_dhaka" data-charge="60" checked>
                                        <span class="area">ঢাকার ভিতরে</span>
                                        <span class="charge">৳60</span>
                                    </label>
                                    <label class="delivery-option">
                                        <input type="radio" name="shipping_area" value="outside_dhaka" data-charge="120">
                                        <span class="area">ঢাকার বাইরে</span>
                                        <span class="charge">৳120</span>
                                    </label>
                                    <?php endif; ?>
                                </div>
                                
                                <?php 
                                // Payment options - same as affiliate product page
                                $sectionType = 'affiliate';
                                include __DIR__ . '/includes/payment-method.php';
                                ?>
                                
                                <div class="order-summary">
                                    <div class="row">
                                        <div class="col-6">পণ্যের মূল্য:</div>
                                        <div class="col-6 text-end" id="productPriceDisplay"><?php echo formatPrice($productPrice); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">ডেলিভারি চার্জ:</div>
                                        <div class="col-6 text-end" id="deliveryCharge"><?php echo formatPrice($deliveryCharges[0]['charge'] ?? 60); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">সর্বমোট:</div>
                                        <div class="col-6 text-end" id="totalAmount">
                                            <?php 
                                            $deliveryCharge = $deliveryCharges[0]['charge'] ?? 60;
                                            echo formatPrice($productPrice + $deliveryCharge); 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
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
                        <div class="alert alert-warning mt-4">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('liveToast');
            const toastBody = document.getElementById('toastMessage');
            if(toastBody) toastBody.textContent = message;
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }

        function addToCart() {
            const productId = <?php echo $product['id']; ?>; // This is affiliate_product_id
            const qty = parseInt(document.getElementById('quantity').value) || 1;
            const storeId = <?php echo $store['id']; ?>;
            const affiliatorId = <?php echo $store['affiliator_id']; ?>;
            
            // Get variations
            let variation = {};
            let missingVariation = false;
            
            document.querySelectorAll('.variation-radio').forEach(r => {
                const name = r.name.replace('variation[', '').replace(']', '');
                if (r.checked) {
                    variation[name] = r.value;
                }
            });

            // Check variations
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
            formData.append('store_id', storeId);
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
                    // Update header cart count
                    const badge = document.querySelector('#cart-icon .badge');
                    if (badge) {
                        badge.textContent = data.cartCount;
                    } else {
                        const icon = document.getElementById('cart-icon');
                        if (icon) {
                            // Find the icon element and append badge
                            icon.innerHTML += `
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                ${data.cartCount}
                            </span>`;
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

        function changeImage(src, element) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            element.classList.add('active');
        }
        
// Delivery and Quantity Logic
const minQty = <?php echo $minQty; ?>;
const productPrice = <?php echo $productPrice; ?>; // PHP variable used here

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
    // Note: ID productPriceDisplay used in store page
    const priceDisplay = document.getElementById('productPriceDisplay'); 
    if(priceDisplay) priceDisplay.textContent = '৳' + totalProductPrice.toLocaleString();
    
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
    </script>
</body>
</html>
