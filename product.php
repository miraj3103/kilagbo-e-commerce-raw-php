<?php
/**
 * Kilagbo - Single Product Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$slug = sanitize($_GET['slug'] ?? '');

if (!$slug) {
    redirect(baseUrl('shop'));
}

// Fetch product
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.status = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    redirect(baseUrl('shop'));
}

$pageTitle = $product['name'];

// Fetch variations
$variationsStmt = $pdo->prepare("SELECT attribute_name, GROUP_CONCAT(DISTINCT attribute_value) as values_list FROM product_variations WHERE product_id = ? GROUP BY attribute_name");
$variationsStmt->execute([$product['id']]);
$variations = $variationsStmt->fetchAll();

// Parse gallery - Video first if exists, then main image, then gallery
$gallery = json_decode($product['gallery'], true) ?: [];
$mediaItems = [];

// Check for video
$videoId = '';
if ($product['video_url']) {
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $product['video_url'], $matches)) {
        $videoId = $matches[1];
        $mediaItems[] = ['type' => 'video', 'id' => $videoId, 'thumb' => 'https://img.youtube.com/vi/' . $videoId . '/0.jpg'];
    }
}

// Add main image
$mediaItems[] = ['type' => 'image', 'src' => $product['image']];

// Add gallery images
foreach ($gallery as $img) {
    if ($img && $img !== $product['image']) {
        $mediaItems[] = ['type' => 'image', 'src' => $img];
    }
}

// Related products
$relatedStmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND status = 1 ORDER BY RAND() LIMIT 8");
$relatedStmt->execute([$product['category_id'], $product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

// Handle add to cart / direct checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $action = $_POST['add_to_cart'] === 'buy' ? 'buy' : 'cart';
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $variation = [];
    
    if ($product['has_variations']) {
        foreach ($variations as $var) {
            $attrName = $var['attribute_name'];
            if (isset($_POST['variation_' . $attrName])) {
                $variation[$attrName] = sanitize($_POST['variation_' . $attrName]);
            }
        }
    }
    
    $sessionId = getSessionId();
    $userId = $_SESSION['user_id'] ?? null;
    $variationJson = !empty($variation) ? json_encode($variation) : null;
    
    if ($action === 'cart') {
        // Only add/update cart entry
        if ($userId) {
            $checkStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND section = 'retail' AND product_id = ? AND (variation = ? OR (variation IS NULL AND ? IS NULL))");
            $checkStmt->execute([$userId, $product['id'], $variationJson, $variationJson]);
        } else {
            $checkStmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND section = 'retail' AND product_id = ? AND (variation = ? OR (variation IS NULL AND ? IS NULL))");
            $checkStmt->execute([$sessionId, $product['id'], $variationJson, $variationJson]);
        }
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")->execute([$quantity, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, session_id, product_id, section, variation, quantity) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $sessionId, $product['id'], 'retail', $variationJson, $quantity]);
        }
        
        setFlash('success', 'পণ্য কার্টে যোগ হয়েছে');
        redirect(baseUrl('product/' . $product['slug']));
    } else {
        // Direct checkout with only this product
        if ($userId) {
            $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND section = 'retail'")->execute([$userId]);
        } else {
            $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND section = 'retail'")->execute([$sessionId]);
        }
        
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, session_id, product_id, section, variation, quantity) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $sessionId, $product['id'], 'retail', $variationJson, $quantity]);
        
        redirect(baseUrl('checkout'));
    }
}

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

    .gallery-stage {
        position: relative;
        touch-action: pan-y;
        -webkit-user-select: none;
        user-select: none;
        -webkit-touch-callout: none;
    }
    .gallery-stage img { -webkit-user-drag: none; user-select: none; }
    @media (hover: none), (pointer: coarse) {
        .gallery-stage .gallery-nav { display: none; }
        .zoomContainer { display: none !important; pointer-events: none !important; }
    }

    .gallery-stage:focus {
        outline: none;
    }

    .gallery-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 1px solid rgba(0, 0, 0, 0.12);
        background: rgba(255, 255, 255, 0.92);
        color: #333;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 5;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        transition: all 0.2s ease;
    }

    .gallery-nav:hover {
        background: #fff;
        color: #ff6600;
        transform: translateY(-50%) scale(1.05);
    }

    .gallery-nav.prev { left: 12px; }
    .gallery-nav.next { right: 12px; }
    
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
    }
    
    .main-image-container .video-container {
        width: 100%;
        height: 100%;
        position: relative;
    }
    
    .main-image-container iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
    
    .video-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.3);
        cursor: pointer;
    }
    
    .video-overlay i {
        font-size: 4rem;
        color: #fff;
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
        position: relative;
    }
    
    .thumbnail-item:hover, .thumbnail-item.active {
        border-color: #ff6600;
    }
    
    .thumbnail-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .thumbnail-item.video::after {
        content: '▶';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #fff;
        font-size: 1rem;
        background: rgba(0,0,0,0.5);
        width: 25px;
        height: 25px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding-left: 3px;
    }
    
    .product-details {
        padding-left: 30px;
        display: flex;
        flex-direction: column;
    }
    
    .product-meta-top {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    
    .product-meta-top .sku {
        color: var(--text-light);
        font-size: 0.9rem;
    }
    
    .product-meta-top .category-link {
        color: #ff6600;
        text-decoration: none;
        font-size: 0.9rem;
    }
    
    .product-meta-top .category-link:hover {
        text-decoration: underline;
    }
    
    .product-meta-top .stock-status {
        font-size: 0.85rem;
        padding: 4px 12px;
        border-radius: 20px;
    }
    
    .stock-status.in-stock {
        background: #d4edda;
        color: #155724;
    }
    
    .stock-status.out-of-stock {
        background: #f8d7da;
        color: #721c24;
    }
    
    .product-title {
        font-size: 1.6rem;
        font-weight: 600;
        margin-bottom: 20px;
        line-height: 1.4;
    }
    
    .product-price-box {
        background: #fff5ee;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        border: 1px solid #ffe0cc;
    }
    
    .product-price-box .current-price {
        font-size: 2rem;
        font-weight: 700;
        color: #ff6600;
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
    
    .variation-group {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
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
        padding: 5px 12px;
        border: 1.5px solid var(--border-color);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 0.85rem;
        background: #f8f9fa;
    }
    
    .variation-option:checked + label {
        background: #ff6600;
        color: #fff;
        border-color: #ff6600;
    }
    
    .variation-option + label:hover {
        border-color: #ff6600;
    }
    
    .quantity-selector {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .quantity-input {
        display: flex;
        align-items: center;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .quantity-input button {
        width: 34px;
        height: 34px;
        border: none;
        background: var(--bg-light);
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .quantity-input button:hover {
        background: #ff6600;
        color: #fff;
    }
    
    .quantity-input input {
        width: 45px;
        height: 34px;
        border: none;
        text-align: center;
        font-size: 1rem;
        font-weight: 500;
    }
    
    .quantity-input input:focus {
        outline: none;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 25px;
    }
    
    .btn-add-cart {
        width: 100%;
        padding: 14px 30px;
        font-size: 1rem;
        font-weight: 600;
        background: #fff;
        color: #ff6600;
        border: 2px solid #ff6600;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-add-cart:hover {
        background: #fff5ee;
    }
    
    .btn-order-now {
        width: 100%;
        padding: 14px 30px;
        font-size: 1rem;
        font-weight: 600;
        background: #ff6600;
        color: #fff;
        border: none;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-order-now:hover {
        background: #e55a00;
        color: #fff;
    }
    
    .btn-wishlist-inline {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 30px;
        border: none;
        border-radius: 8px;
        background: #ff6600;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .btn-wishlist-inline:hover {
        background: #e55a00;
        color: #fff;
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
    
    .delivery-info {
        background: var(--bg-light);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .delivery-info p {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .delivery-info i {
        color: #ff6600;
    }
    
    .product-description {
        padding: 40px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 40px;
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
            font-size: 1.3rem;
        }
        
        .product-price-box .current-price {
            font-size: 1.6rem;
        }
    }

    @media (max-width: 768px) {
        .gallery-nav {
            width: 38px;
            height: 38px;
        }
        .gallery-nav.prev { left: 8px; }
        .gallery-nav.next { right: 8px; }

        .product-price-box {
            padding: 10px;
            margin-bottom: 15px;
        }
        .product-price-box .current-price {
            font-size: 1.4rem;
        }
        .product-price-box .regular-price {
            font-size: 1rem;
        }
        .quantity-selector {
            margin-bottom: 15px;
        }
        .quantity-input button {
            width: 30px;
            height: 30px;
        }
        .quantity-input input {
            width: 40px;
            height: 30px;
            font-size: 0.9rem;
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
                    <div class="gallery-stage" id="retailGalleryStage" tabindex="0" aria-label="পণ্যের গ্যালারি">
                        <div class="main-image-container" id="mainImageContainer">
                            <?php if ($videoId && count($mediaItems) > 0 && $mediaItems[0]['type'] === 'video'): ?>
                            <div class="video-container" id="videoContainer">
                                <img src="<?php echo $mediaItems[0]['thumb']; ?>" alt="Video Thumbnail" id="videoThumb">
                                <div class="video-overlay" onclick="playVideo()">
                                    <i class="bi bi-play-circle-fill"></i>
                                </div>
                            </div>
                            <?php else: ?>
                            <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>" id="mainImg" data-zoom-image="<?php echo $product['image']; ?>">
                            <?php endif; ?>
                        </div>
                        <?php if (count($mediaItems) > 1): ?>
                        <button type="button" class="gallery-nav prev" onclick="galleryPrev()" aria-label="আগের ছবি">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="gallery-nav next" onclick="galleryNext()" aria-label="পরের ছবি">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="thumbnail-list">
                        <?php foreach ($mediaItems as $index => $item): ?>
                        <?php if ($item['type'] === 'video'): ?>
                        <div class="thumbnail-item video <?php echo $index == 0 ? 'active' : ''; ?>" onclick="showVideo(this)" data-media-index="<?php echo $index; ?>" data-media-type="video" data-video-id="<?php echo $item['id']; ?>">
                            <img src="<?php echo $item['thumb']; ?>" alt="Video">
                        </div>
                        <?php else: ?>
                        <div class="thumbnail-item <?php echo $index == 0 && !$videoId ? 'active' : ''; ?>" onclick="changeImage('<?php echo $item['src']; ?>', this)" data-media-index="<?php echo $index; ?>" data-media-type="image" data-src="<?php echo $item['src']; ?>">
                            <img src="<?php echo $item['src']; ?>" alt="">
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Product Details -->
            <div class="col-lg-6">
                <div class="product-details">
                    <!-- Meta Top -->
                    <div class="product-meta-top">
                        <?php if ($product['category_name']): ?>
                        <a href="<?php echo baseUrl('shop?category=' . $product['category_slug']); ?>" class="category-link">
                            <i class="bi bi-tag me-1"></i><?php echo sanitize($product['category_name']); ?>
                        </a>
                        <?php endif; ?>
                        <span class="sku">SKU: <?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        <span class="stock-status <?php echo ($product['stock_quantity'] === null || $product['stock_quantity'] > 0) ? 'in-stock' : 'out-of-stock'; ?>">
                            <?php echo ($product['stock_quantity'] === null || $product['stock_quantity'] > 0) ? 'স্টকে আছে' : 'স্টক শেষ'; ?>
                        </span>
                    </div>
                    
                    <h1 class="product-title"><?php echo sanitize($product['name']); ?></h1>
                    
                    <div class="product-price-box">
                        <span class="current-price"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                        <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                        <span class="discount-badge"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST">
                        <!-- Variations -->
                        <?php if ($product['has_variations'] && !empty($variations)): ?>
                        <?php foreach ($variations as $var): ?>
                        <div class="variation-group">
                            <span class="variation-label"><?php echo sanitize($var['attribute_name']); ?>:</span>
                            <div class="variation-options">
                                <?php 
                                $values = explode(',', $var['values_list']);
                                foreach ($values as $index => $value): 
                                    $value = trim($value);
                                    $inputId = 'var_' . $var['attribute_name'] . '_' . $index;
                                ?>
                                <input type="radio" name="variation_<?php echo $var['attribute_name']; ?>" id="<?php echo $inputId; ?>" value="<?php echo sanitize($value); ?>" class="variation-option" <?php echo $index == 0 ? 'checked' : ''; ?> required>
                                <label for="<?php echo $inputId; ?>"><?php echo sanitize($value); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Quantity -->
                        <div class="quantity-selector">
                            <span class="variation-label">পরিমাণ:</span>
                            <div class="quantity-input">
                                <button type="button" onclick="changeQuantity(-1)">-</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="99">
                                <button type="button" onclick="changeQuantity(1)">+</button>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                            <button type="button" class="btn btn-add-cart" style="background: #6c757d; border-color: #6c757d;" disabled>
                                <i class="bi bi-x-circle me-2"></i>স্টক শেষ
                            </button>
                            <?php else: ?>
                            <button type="submit" name="add_to_cart" value="cart" class="btn btn-add-cart">
                                <i class="bi bi-cart-plus me-2"></i>কার্টে যোগ করুন
                            </button>
                            <button type="submit" name="add_to_cart" value="buy" class="btn btn-order-now">
                                <i class="bi bi-bag me-2"></i>এখনই কিনুন
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-wishlist-inline" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                <i class="bi bi-heart me-2"></i>উইশলিস্টে যোগ করুন
                            </button>
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

                        <!-- Delivery Info -->
                        <div class="delivery-info">
                            <p><i class="bi bi-truck"></i> ঢাকায় <?php echo formatPrice(getSettings('inside_dhaka_charge') ?: 60); ?>, ঢাকার বাইরে <?php echo formatPrice(getSettings('outside_dhaka_charge') ?: 130); ?></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        

    </div>
</div>

<!-- Related Products -->
<?php if (!empty($relatedProducts)): ?>
<section class="related-products">
    <div class="container">
        <h3 class="section-title">সম্পর্কিত পণ্য</h3>
        <div class="row g-3 g-md-4">
            <?php foreach ($relatedProducts as $related): ?>
            <div class="col-lg-3 col-md-4 col-6">
                <div class="product-card">
                    <a href="<?php echo baseUrl('product/' . $related['slug']); ?>" class="product-image">
                        <img src="<?php echo $related['image']; ?>" alt="<?php echo sanitize($related['name']); ?>">
                        <?php if ($related['sale_price'] && $related['sale_price'] < $related['regular_price']): ?>
                        <?php $discount = round((($related['regular_price'] - $related['sale_price']) / $related['regular_price']) * 100); ?>
                        <span class="sale-badge"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <a href="<?php echo baseUrl('product/' . $related['slug']); ?>" class="product-title">
                            <?php echo sanitize($related['name']); ?>
                        </a>
                        <div class="product-price">
                            <?php if ($related['sale_price'] && $related['sale_price'] < $related['regular_price']): ?>
                            <span class="regular-price"><?php echo formatPrice($related['regular_price']); ?></span>
                            <?php endif; ?>
                            <span class="current-price"><?php echo formatPrice($related['sale_price'] ?: $related['regular_price']); ?></span>
                        </div>
                        <a href="<?php echo baseUrl('quick-order?id=' . $related['id']); ?>" class="btn btn-order">অর্ডার করুন</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/elevatezoom/3.0.8/jquery.elevatezoom.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image Zoom
    initElevateZoom();
    initGalleryNavigation();
});

let isVideoPlaying = false;
let currentGalleryIndex = 0;

function syncGalleryIndex(el) {
    if (!el) return;
    const index = parseInt(el.dataset.mediaIndex, 10);
    if (!Number.isNaN(index)) currentGalleryIndex = index;
}

function changeImage(src, el) {
    const container = document.getElementById('mainImageContainer');
    if (!container) return;
    container.innerHTML = '<img src="' + src + '" alt="" id="mainImg" data-zoom-image="' + src + '">';
    document.querySelectorAll('.thumbnail-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
    syncGalleryIndex(el);
    isVideoPlaying = false;
    initElevateZoom();
}

function showVideo(el) {
    const videoId = el.dataset.videoId;
    const container = document.getElementById('mainImageContainer');
    container.innerHTML = '<div class="video-container"><img src="https://img.youtube.com/vi/' + videoId + '/0.jpg" alt="Video Thumbnail" id="videoThumb"><div class="video-overlay" onclick="playVideo()"><i class="bi bi-play-circle-fill"></i></div></div>';
    document.querySelectorAll('.thumbnail-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
    syncGalleryIndex(el);
    isVideoPlaying = false;
    if (typeof $ !== 'undefined' && $.fn.elevateZoom) {
        $('.zoomContainer').remove();
    }
    
    // Store video ID for playVideo function
    container.dataset.videoId = videoId;
}

function playVideo() {
    const container = document.getElementById('mainImageContainer');
    const videoId = container.dataset.videoId || '<?php echo $videoId; ?>';
    container.innerHTML = '<iframe src="https://www.youtube.com/embed/' + videoId + '?autoplay=1&enablejsapi=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    isVideoPlaying = true;
}

function galleryGoTo(index) {
    const items = Array.from(document.querySelectorAll('.thumbnail-item[data-media-index]'));
    if (items.length < 2) return;

    const normalizedIndex = (index + items.length) % items.length;
    const item = items[normalizedIndex];
    if (!item) return;

    if (item.dataset.mediaType === 'video') {
        showVideo(item);
    } else {
        changeImage(item.dataset.src || item.querySelector('img')?.src || '', item);
    }
    item.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
}

function galleryPrev() {
    galleryGoTo(currentGalleryIndex - 1);
}

function galleryNext() {
    galleryGoTo(currentGalleryIndex + 1);
}

function initGalleryNavigation() {
    const stage = document.getElementById('retailGalleryStage');
    const activeItem = document.querySelector('.thumbnail-item.active[data-media-index]');
    syncGalleryIndex(activeItem);
    if (!stage) return;

    let touchStartX = 0;
    let touchStartY = 0;

    stage.querySelectorAll('img').forEach(img => img.draggable = false);
    stage.addEventListener('dragstart', function(event) { event.preventDefault(); });

    stage.addEventListener('touchstart', function(event) {
        if (event.touches.length !== 1) return;
        const touch = event.changedTouches[0];
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
    }, { passive: true });

    stage.addEventListener('touchend', function(event) {
        if (event.changedTouches.length !== 1) return;
        const touch = event.changedTouches[0];
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;
        if (Math.abs(deltaX) < 40 || Math.abs(deltaX) <= Math.abs(deltaY) * 1.15) return;
        deltaX < 0 ? galleryNext() : galleryPrev();
    }, { passive: true });

    stage.addEventListener('keydown', function(event) {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            galleryPrev();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            galleryNext();
        }
    });
}

function changeQuantity(delta) {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value) + delta;
    if (value < 1) value = 1;
    if (value > 99) value = 99;
    input.value = value;
}

function initElevateZoom() {
    const img = document.getElementById('mainImg');
    // ElevateZoom inserts a positioned overlay that intercepts mobile swipes.
    // Keep zoom on desktop pointer devices only.
    if (window.matchMedia('(hover: none), (pointer: coarse)').matches) {
        if (typeof $ !== 'undefined') $('.zoomContainer').remove();
        return;
    }
    if (!img || typeof $ === 'undefined' || !$.fn.elevateZoom) return;
    
    function startZoom() {
        $('.zoomContainer').remove();
        $(img).removeData('elevateZoom');
        $(img).elevateZoom({
            zoomType: 'lens',
            lensShape: 'round',
            lensSize: 200,
            borderSize: 2,
            borderColour: '#ccc',
            containLensZoom: true
        });
    }

    if (img.complete) {
        startZoom();
    } else {
        img.onload = startZoom;
    }
}

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

// DataLayer - View Item
if (typeof pushDataLayer === 'function') {
    pushDataLayer('view_item', {
        currency: 'BDT',
        value: <?php echo $product['sale_price'] ?: $product['regular_price']; ?>,
        items: [{
            item_id: '<?php echo $product['id']; ?>',
            item_name: '<?php echo addslashes($product['name']); ?>',
            price: <?php echo $product['sale_price'] ?: $product['regular_price']; ?>
        }]
    });
}

// Facebook Pixel - ViewContent
<?php if (getSettings('facebook_pixel_id') && getSettings('facebook_pixel_active') == '1'): ?>
if (typeof fbq === 'function') {
    fbq('track', 'ViewContent', {
        content_name: '<?php echo addslashes($product['name']); ?>',
        content_ids: ['<?php echo $product['id']; ?>'],
        content_type: 'product',
        value: <?php echo $product['sale_price'] ?: $product['regular_price']; ?>,
        currency: 'BDT'
    });
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
