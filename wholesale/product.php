<?php
/**
 * Kilagbo - Wholesale Product Detail
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/access.php';
wholesaleRequireApproval($pdo);

$slug = sanitize($_GET['slug'] ?? '');
if (!$slug) {
    redirect(baseUrl('wholesale'));
}

// Get product
$stmt = $pdo->prepare("
    SELECT wp.*, c.name as category_name, c.slug as category_slug
    FROM wholesale_products wp 
    LEFT JOIN categories c ON wp.category_id = c.id 
    WHERE wp.slug = ? AND wp.status = 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    redirect(baseUrl('wholesale'));
}

$currentPage = 'wholesale';
$pageTitle = $product['name'];

// Get related products
$relatedStmt = $pdo->prepare("
    SELECT wp.* FROM wholesale_products wp 
    WHERE wp.category_id = ? AND wp.id != ? AND wp.status = 1 
    ORDER BY RAND() 
    LIMIT 4
");
$relatedStmt->execute([$product['category_id'], $product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

// Parse gallery
$gallery = json_decode($product['gallery'] ?? '[]', true) ?: [];

// Fetch variations
$variations = [];
if (!empty($product['has_variations'])) {
    $vStmt = $pdo->prepare("SELECT attribute_name, attribute_value FROM wholesale_product_variations WHERE wholesale_product_id = ?");
    $vStmt->execute([$product['id']]);
    $rawVariations = $vStmt->fetchAll();
    
    foreach ($rawVariations as $var) {
        $variations[$var['attribute_name']][] = $var['attribute_value'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .product-detail {
        padding: 40px 0;
    }
    
    .product-gallery {
        position: sticky;
        top: 100px;
    }

    .gallery-stage {
        position: relative;
        touch-action: pan-y;
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
        color: #667eea;
        transform: translateY(-50%) scale(1.05);
    }

    .gallery-nav.prev { left: 12px; }
    .gallery-nav.next { right: 12px; }
    
    .main-image {
        width: 100%;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 15px;
        position: relative;
        aspect-ratio: 1;
        background: #f8f9fa;
    }
    
    .main-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    
    .gallery-thumbs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .gallery-thumbs img {
        width: 70px;
        height: 70px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }
    
    .gallery-thumbs img:hover,
    .gallery-thumbs img.active {
        border-color: #667eea;
    }
    
    .product-info h1 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .product-price {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .product-price .current {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .product-price .original {
        font-size: 1.2rem;
        color: #999;
        text-decoration: line-through;
    }
    
    .product-price .discount {
        background: var(--primary-color);
        color: var(--secondary-color);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .product-meta {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .product-meta .meta-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .product-meta .meta-item:last-child {
        border-bottom: none;
    }
    
    .product-meta .meta-label {
        color: #666;
    }
    
    .product-meta .meta-value {
        font-weight: 600;
    }
    
    .quantity-selector {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .quantity-selector label {
        font-weight: 600;
        margin-bottom: 0;
        min-width: 80px;
    }
    
    .quantity-selector button {
        width: 34px;
        height: 34px;
        border: 1px solid #ddd;
        background: #fff;
        border-radius: 6px;
        font-size: 1.1rem;
    }
    
    .quantity-selector input {
        width: 50px;
        height: 34px;
        text-align: center;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1rem;
    }
    
    .btn-wholesale-lg {
        background: var(--primary-color);
        color: var(--secondary-color);
        border: none;
        padding: 15px 30px;
        border-radius: 6px;
        font-size: 1.1rem;
        font-weight: 600;
        width: 100%;
        transition: all 0.3s ease;
    }
    
    .btn-wholesale-lg:hover {
        color: var(--secondary-color);
        background: var(--accent-color);
    }
    
    .btn-wishlist-inline {
        margin-top: 15px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 16px;
        border: none;
        border-radius: 6px;
        background: var(--primary-color);
        color: var(--secondary-color);
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-wishlist-inline:hover {
        background: var(--accent-color);
        color: var(--secondary-color);
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
        background: #f8f9fa;
        padding: 12px;
        border-radius: 8px;
        margin-top: 15px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .gallery-nav {
            width: 38px;
            height: 38px;
        }
        .gallery-nav.prev { left: 8px; }
        .gallery-nav.next { right: 8px; }

        .product-price .current {
            font-size: 1.5rem;
        }
        .quantity-selector label {
            min-width: 60px;
            font-size: 0.9rem;
        }
    }
    
    .product-description {
        margin-top: 30px;
        padding-top: 30px;
        border-top: 1px solid var(--border-color);
    }
    
    .related-products {
        padding: 50px 0;
        background: #f8f9fa;
    }
    
    .product-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .product-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .product-card .product-image {
        padding-top: 100%;
        position: relative;
    }
    
    .product-card .product-image img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .product-card .product-info {
        padding: 15px;
    }
    
    .product-card .product-title {
        font-weight: 600;
        color: var(--text-color);
        text-decoration: none;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-card .current-price {
        font-weight: 700;
        color: var(--primary-color);
    }
</style>

<div class="product-detail">
    <div class="container">
        <div class="row">
            <!-- Gallery -->
            <div class="col-lg-5 mb-4">
                <div class="product-gallery">
                    <?php $wholesaleGalleryCount = ($product['image'] ? 1 : 0) + count($gallery); ?>
                    <div class="gallery-stage" id="wholesaleGalleryStage" tabindex="0" aria-label="পাইকারি পণ্যের গ্যালারি">
                        <div class="main-image" id="mainImageContainer">
                            <?php if ($product['image']): ?>
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="<?php echo $product['name']; ?>" id="mainImage" data-zoom-image="<?php echo imageUrl($product['image']); ?>">
                            <?php else: ?>
                            <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo $product['name']; ?>" id="mainImage" data-zoom-image="<?php echo baseUrl('assets/images/placeholder.png'); ?>">
                            <?php endif; ?>
                        </div>
                        <?php if ($wholesaleGalleryCount > 1): ?>
                        <button type="button" class="gallery-nav prev" onclick="wholesaleGalleryPrev()" aria-label="আগের ছবি">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="gallery-nav next" onclick="wholesaleGalleryNext()" aria-label="পরের ছবি">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($gallery) || $product['image']): ?>
                    <div class="gallery-thumbs">
                        <?php $galleryIndex = 0; ?>
                        <?php if ($product['image']): ?>
                        <img src="<?php echo imageUrl($product['image']); ?>" alt="" class="active" onclick="changeImage(this)" data-gallery-index="<?php echo $galleryIndex++; ?>">
                        <?php endif; ?>
                        <?php foreach ($gallery as $img): ?>
                        <img src="<?php echo imageUrl($img); ?>" alt="" onclick="changeImage(this)" data-gallery-index="<?php echo $galleryIndex++; ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Info -->
            <div class="col-lg-7">
                <div class="product-info">
                    <?php if ($product['category_name']): ?>
                    <a href="<?php echo baseUrl('wholesale/shop?category=' . $product['category_slug']); ?>" class="text-muted text-decoration-none mb-2 d-block">
                        <i class="bi bi-folder me-1"></i><?php echo $product['category_name']; ?>
                    </a>
                    <?php endif; ?>
                    
                    <h1><?php echo $product['name']; ?></h1>
                    
                    <div class="product-price">
                        <span class="current"><?php echo formatPrice($product['wholesale_price']); ?></span>
                        <?php if ($product['regular_price'] > $product['wholesale_price']): ?>
                        <span class="original"><?php echo formatPrice($product['regular_price']); ?></span>
                        <?php 
                        $discount = round((($product['regular_price'] - $product['wholesale_price']) / $product['regular_price']) * 100);
                        ?>
                        <span class="discount"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-meta">
                        <div class="meta-item">
                            <span class="meta-label">ন্যূনতম অর্ডার</span>
                            <span class="meta-value"><?php echo $product['min_order_qty']; ?> পিস</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">স্টক</span>
                            <span class="meta-value">
                                <?php 
                                if ($product['stock'] === null) echo 'আনলিমিটেড';
                                elseif ($product['stock'] > 0) echo $product['stock'] . ' পিস';
                                else echo 'স্টক আউট';
                                ?>
                            </span>
                        </div>
                        <?php if ($product['sku']): ?>
                        <div class="meta-item">
                            <span class="meta-label">SKU</span>
                            <span class="meta-value"><?php echo $product['sku']; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <form action="<?php echo baseUrl('wholesale/checkout'); ?>" method="GET">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        
                        <?php if (!empty($variations)): ?>
                        <div class="product-variations mb-4">
                            <?php foreach ($variations as $attrName => $attrValues): ?>
                            <div class="variation-group mb-3">
                                <label class="form-label fw-bold mb-2"><?php echo $attrName; ?>:</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($attrValues as $val): ?>
                                    <div class="variation-option">
                                        <input type="radio" class="btn-check" name="variation[<?php echo $attrName; ?>]" id="var_<?php echo $attrName; ?>_<?php echo $val; ?>" value="<?php echo $val; ?>" required>
                                        <label class="btn btn-outline-secondary" for="var_<?php echo $attrName; ?>_<?php echo $val; ?>"><?php echo $val; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="quantity-selector">
                            <label>পরিমাণ:</label>
                            <button type="button" onclick="decreaseQty()">-</button>
                            <input type="number" name="qty" id="qty" value="<?php echo $product['min_order_qty']; ?>" min="<?php echo $product['min_order_qty']; ?>">
                            <button type="button" onclick="increaseQty()">+</button>
                        </div>
                        
                        <div class="mb-3 d-flex align-items: center; gap: 15px;">
                            <strong style="min-width: 80px;">মোট:</strong>
                            <strong id="totalPrice" class="text-primary" style="font-size: 1.2rem;"><?php echo formatPrice($product['wholesale_price'] * $product['min_order_qty']); ?></strong>
                        </div>
                        
                        <?php if ($product['stock'] !== null && $product['stock'] <= 0): ?>
                        <button type="button" class="btn-wholesale-lg" style="background: #6c757d;" disabled>
                            <i class="bi bi-x-circle me-2"></i>স্টক শেষ
                        </button>
                        <?php else: ?>
                        <button type="submit" class="btn-wholesale-lg">
                            <i class="bi bi-bag-check me-2"></i>এখনই অর্ডার করুন
                        </button>
                        
                        <button type="button" class="btn-wholesale-lg mt-3" style="background: white; color: var(--primary-color); border: 2px solid var(--primary-color);" onclick="addToCartWholesale()">
                            <i class="bi bi-cart-plus me-2"></i>কার্টে যোগ করুন
                        </button>
                        <?php endif; ?>
                    </form>
                    
                    <button type="button" class="btn-wishlist-inline" onclick="addToWishlist(<?php echo $product['id']; ?>, 'wholesale')">
                        <i class="bi bi-heart me-2"></i>উইশলিস্টে যোগ করুন
                    </button>

                    <!-- Details Dropdown -->
                    <?php if ($product['description'] || $product['short_description']): ?>
                    <div class="details-collapse">
                        <button type="button" class="details-toggle" onclick="toggleDetails()">
                            বিবরণ দেখুন
                            <i class="bi bi-chevron-down" id="detailsIcon"></i>
                        </button>
                        <div class="details-content" id="detailsContent">
                            <?php if ($product['short_description']): ?>
                                <p><strong>সংক্ষিপ্ত বিবরণ:</strong></p>
                                <p><?php echo nl2br($product['short_description']); ?></p>
                                <hr>
                            <?php endif; ?>
                            <?php if ($product['description']): ?>
                                <div><?php echo $product['description']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>


                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($relatedProducts)): ?>
<div class="related-products">
    <div class="container">
        <h3 class="mb-4"><i class="bi bi-box-seam me-2"></i>সম্পর্কিত পণ্য</h3>
        <div class="row g-3">
            <?php foreach ($relatedProducts as $rp): ?>
            <div class="col-6 col-md-3">
                <div class="product-card">
                    <a href="<?php echo baseUrl('wholesale/product/' . $rp['slug']); ?>" class="product-image">
                        <?php if ($rp['image']): ?>
                        <img src="<?php echo imageUrl($rp['image']); ?>" alt="<?php echo $rp['name']; ?>">
                        <?php else: ?>
                        <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo $rp['name']; ?>">
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <a href="<?php echo baseUrl('wholesale/product/' . $rp['slug']); ?>" class="product-title"><?php echo $rp['name']; ?></a>
                        <div class="mt-2">
                            <span class="current-price"><?php echo formatPrice($rp['wholesale_price']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/elevatezoom/3.0.8/jquery.elevatezoom.min.js"></script>
<script>
const minQty = <?php echo $product['min_order_qty']; ?>;
const price = <?php echo $product['wholesale_price']; ?>;
let currentWholesaleGalleryIndex = 0;

function changeImage(el) {
    const main = document.getElementById('mainImage');
    if (!main) return;
    main.src = el.src;
    main.setAttribute('data-zoom-image', el.src);
    document.querySelectorAll('.gallery-thumbs img').forEach(img => img.classList.remove('active'));
    el.classList.add('active');
    const index = parseInt(el.dataset.galleryIndex, 10);
    if (!Number.isNaN(index)) currentWholesaleGalleryIndex = index;
    initElevateZoom();
}

function wholesaleGalleryGoTo(index) {
    const items = Array.from(document.querySelectorAll('.gallery-thumbs img[data-gallery-index]'));
    if (items.length < 2) return;
    const normalizedIndex = (index + items.length) % items.length;
    const item = items[normalizedIndex];
    if (!item) return;
    changeImage(item);
    item.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
}

function wholesaleGalleryPrev() {
    wholesaleGalleryGoTo(currentWholesaleGalleryIndex - 1);
}

function wholesaleGalleryNext() {
    wholesaleGalleryGoTo(currentWholesaleGalleryIndex + 1);
}

function initWholesaleGalleryNavigation() {
    const stage = document.getElementById('wholesaleGalleryStage');
    const activeItem = document.querySelector('.gallery-thumbs img.active[data-gallery-index]');
    if (activeItem) {
        const index = parseInt(activeItem.dataset.galleryIndex, 10);
        if (!Number.isNaN(index)) currentWholesaleGalleryIndex = index;
    }
    if (!stage) return;

    let touchStartX = 0;
    let touchStartY = 0;

    stage.addEventListener('touchstart', function(event) {
        const touch = event.changedTouches[0];
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
    }, { passive: true });

    stage.addEventListener('touchend', function(event) {
        const touch = event.changedTouches[0];
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;
        if (Math.abs(deltaX) < 45 || Math.abs(deltaX) <= Math.abs(deltaY)) return;
        deltaX < 0 ? wholesaleGalleryNext() : wholesaleGalleryPrev();
    }, { passive: true });

    stage.addEventListener('keydown', function(event) {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            wholesaleGalleryPrev();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            wholesaleGalleryNext();
        }
    });
}

// Image Zoom Functionality
function initElevateZoom() {
    const img = document.getElementById('mainImage');
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

document.addEventListener('DOMContentLoaded', function() {
    initElevateZoom();
    initWholesaleGalleryNavigation();
});

function increaseQty() {
    const input = document.getElementById('qty');
    input.value = parseInt(input.value) + 1;
    updateTotal();
}

function decreaseQty() {
    const input = document.getElementById('qty');
    if (parseInt(input.value) > minQty) {
        input.value = parseInt(input.value) - 1;
        updateTotal();
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

function updateTotal() {
    const qty = parseInt(document.getElementById('qty').value);
    const total = qty * price;
    document.getElementById('totalPrice').textContent = '৳' + total.toLocaleString('bn-BD');
}

document.getElementById('qty').addEventListener('change', function() {
    if (parseInt(this.value) < minQty) {
        this.value = minQty;
    }
    updateTotal();
});

function addToCartWholesale() {
    const qty = parseInt(document.getElementById('qty').value);
    
    // Collect variations
    let variation = {};
    const varInputs = document.querySelectorAll('input[name^="variation["]:checked');
    
    if (document.querySelectorAll('.variation-group').length > 0) {
        let allSelected = true;
        document.querySelectorAll('.variation-group').forEach(group => {
            if (!group.querySelector('input:checked')) {
                allSelected = false;
            }
        });
        
        if (!allSelected) {
            alert('অনুগ্রহ করে পণ্যের ভেরিয়েশন নির্বাচন করুন');
            return;
        }
        
        varInputs.forEach(input => {
            const name = input.name.match(/\[(.*?)\]/)[1];
            variation[name] = input.value;
        });
    }

    addToCart(<?php echo $product['id']; ?>, qty, Object.keys(variation).length ? variation : null, 'wholesale');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
