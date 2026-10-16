<?php
/**
 * Kilagbo - Homepage (Retail Section)
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'retail';
$pageTitle = 'হোম';

// Fetch carousel images
$carouselStmt = $pdo->query("SELECT * FROM carousel WHERE status = 1 ORDER BY sort_order ASC");
$carousels = $carouselStmt->fetchAll();
$carouselCount = count($carousels);

// Fetch top categories (show_on_top = 1, no limit - show all selected)
$categoryStmt = $pdo->query("SELECT c.*, 
    (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 1) as product_count 
    FROM categories c 
    WHERE c.show_on_top = 1 AND c.status = 1 
    ORDER BY c.sort_order ASC, c.id DESC");
$topCategories = $categoryStmt->fetchAll();

// Fetch hot deal products
$hotDealsStmt = $pdo->query("SELECT * FROM products WHERE is_hot_deal = 1 AND status = 1 ORDER BY created_at DESC LIMIT 10");
$hotDeals = $hotDealsStmt->fetchAll();

// Fetch categories with display_on_home enabled and their products (8 per category)
$displayCategories = $pdo->query("
    SELECT * FROM categories 
    WHERE display_on_home = 1 AND status = 1 
    ORDER BY sort_order ASC
")->fetchAll();

$categoryProducts = [];
foreach ($displayCategories as $cat) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND status = 1 ORDER BY created_at DESC LIMIT 8");
    $stmt->execute([$cat['id']]);
    $catProducts = $stmt->fetchAll();
    if (!empty($catProducts)) {
        $categoryProducts[$cat['id']] = [
            'category' => $cat,
            'products' => $catProducts
        ];
    }
}

// Fetch all products (max 20)
$productsStmt = $pdo->query("SELECT * FROM products WHERE status = 1 ORDER BY created_at DESC LIMIT 20");
$products = $productsStmt->fetchAll();

// Fetch digital services (max 4)
$digitalServicesStmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY created_at DESC LIMIT 4");
$digitalServices = $digitalServicesStmt->fetchAll();

// Fetch digital products (max 8)
$digitalProductsStmt = $pdo->query("SELECT * FROM digital_products WHERE status = 1 ORDER BY created_at DESC LIMIT 8");
$digitalProducts = $digitalProductsStmt->fetchAll();

include 'includes/header.php';
?>

<style>
    /* Hero Carousel - Full Width */
    .hero-carousel-fullwidth {
        margin-bottom: 40px;
        width: 100%;
    }
    
    .hero-carousel-fullwidth .swiper {
        width: 100%;
    }
    
    .hero-carousel-fullwidth .swiper-slide img {
        width: 100%;
        height: auto;
        display: block;
    }
    
    .hero-carousel-fullwidth .swiper-pagination-bullet {
        background: var(--primary-color);
    }
    
    .hero-carousel-fullwidth .swiper-button-next,
    .hero-carousel-fullwidth .swiper-button-prev {
        color: var(--primary-color);
        background: rgba(255,255,255,0.9);
        width: 45px;
        height: 45px;
        border-radius: 50%;
    }
    
    .hero-carousel-fullwidth .swiper-button-next::after,
    .hero-carousel-fullwidth .swiper-button-prev::after {
        font-size: 18px;
    }
    
    @media (max-width: 768px) {
        .hero-carousel-fullwidth .swiper-slide img {
            max-height: 320px;
        }
    }
    
    /* Categories Section */
    .categories-section {
        padding: 40px 0;
    }
    
    .category-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 20px;
    }
    
    @media (max-width: 991px) {
        .category-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
    }
    
    @media (max-width: 768px) {
        .category-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
    }
    
    .category-card {
        text-align: center;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 15px;
        height: 100%;
        transition: all 0.3s ease;
    }
    
    .category-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .category-card .category-image {
        width: 100%;
        aspect-ratio: 1;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .category-card .category-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }
    
    .category-card:hover .category-image img {
        transform: scale(1.05);
    }
    
    .category-card .category-name {
        font-size: 1rem;
        font-weight: 500;
        color: var(--text-color);
        margin-top: auto;
    }
    
    /* Categories Swiper */
    .categoriesSwiper {
        padding: 10px 0;
        position: relative;
    }
    
    .categoriesSwiper .swiper-slide {
        height: auto;
    }
    
    .categoriesSwiper .swiper-button-next,
    .categoriesSwiper .swiper-button-prev {
        color: var(--primary-color);
        background: rgba(255,255,255,0.9);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .categoriesSwiper .swiper-button-next::after,
    .categoriesSwiper .swiper-button-prev::after {
        font-size: 16px;
    }
    
    /* Mobile Category Carousel */
    .mobile-category-carousel {
        display: none;
    }
    
    .mobile-category-carousel .category-card {
        height: 100%;
        padding: 5px;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .mobile-category-carousel .category-card .category-image {
        max-height: 80px;
        overflow: hidden;
        background: var(--bg-light);
    }
    
    .mobile-category-carousel .category-card .category-image img {
        object-fit: contain;
        padding: 5px;
    }
    
    .mobile-category-carousel .category-card .category-name {
        font-size: 0.75rem;
        padding: 8px 5px 0;
    }
    
    .mobileCategoriesSwiper {
        padding: 5px 0;
    }
    
    .mobileCategoriesSwiper .swiper-slide {
        height: auto;
    }
    
    /* Desktop: Show carousel, hide mobile carousel */
    .desktop-category-carousel {
        display: block;
    }
    
    /* Mobile: Show mobile carousel, hide desktop carousel */
    @media (max-width: 768px) {
        .desktop-category-carousel {
            display: none !important;
        }
        
        .mobile-category-carousel {
            display: block;
        }
    }
    
    /* Hot Deals Section */
    .hot-deals-section {
        padding: 40px 0;
        background: var(--bg-light);
    }
    
    .section-header .section-title::after {
        display: none;
    }
    
    .btn-view-more {
        background: #fff;
        color: #ff6600;
        border: 2px solid #ff6600;
        padding: 8px 20px;
        border-radius: 4px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-view-more:hover {
        background: #ff6600;
        color: #fff;
    }
    
    .hotDealsSwiper {
        padding: 10px 0;
        position: relative;
    }
    
    .hotDealsSwiper .swiper-slide {
        height: auto;
    }
    
    .hotDealsSwiper .swiper-button-next,
    .hotDealsSwiper .swiper-button-prev {
        color: var(--primary-color);
        background: rgba(255,255,255,0.9);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .hotDealsSwiper .swiper-button-next::after,
    .hotDealsSwiper .swiper-button-prev::after {
        font-size: 16px;
    }
    
    /* Products Section */
    .products-section {
        padding: 40px 0;
    }
    
    .view-store-btn {
        text-align: center;
        margin-top: 30px;
    }
    
    @media (max-width: 768px) {
        .hero-carousel .swiper-slide img {
            height: 200px;
        }
        
        .category-card .category-image {
            width: 80px;
            height: 80px;
        }
        
        .category-card .category-name {
            font-size: 0.85rem;
        }
    }
</style>

<!-- Hero Carousel - Full Width -->
<section class="hero-carousel-fullwidth">
    <div class="swiper heroSwiper<?php echo $carouselCount > 1 ? '' : ' single-slide'; ?>">
        <div class="swiper-wrapper">
            <?php foreach ($carousels as $slide): ?>
            <div class="swiper-slide">
                <a href="<?php echo $slide['link'] ?: '#'; ?>">
                    <img src="<?php echo $slide['image']; ?>" alt="<?php echo sanitize($slide['title']); ?>">
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($carouselCount > 1): ?>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <?php endif; ?>
    </div>
</section>

<!-- Top Categories -->
<section class="categories-section">
    <div class="container">
        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0">টপ ক্যাটেগরি</h2>
            <a href="<?php echo baseUrl('categories.php'); ?>" class="btn btn-view-more d-none d-md-inline-block">সব দেখুন</a>
        </div>
        
        <!-- Desktop: Carousel -->
        <div class="swiper categoriesSwiper desktop-category-carousel">
            <div class="swiper-wrapper">
                <?php foreach ($topCategories as $category): ?>
                <div class="swiper-slide">
                    <a href="<?php echo baseUrl('category/' . $category['slug']); ?>" class="category-card">
                        <div class="category-image">
                            <img src="<?php echo $category['image'] ?: 'https://via.placeholder.com/300x300'; ?>" alt="<?php echo sanitize($category['name']); ?>">
                        </div>
                        <span class="category-name"><?php echo sanitize($category['name']); ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
        
        <!-- Mobile: Carousel with 3 items per view -->
        <div class="mobile-category-carousel">
            <div class="swiper mobileCategoriesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($topCategories as $category): ?>
                    <div class="swiper-slide">
                        <a href="<?php echo baseUrl('category/' . $category['slug']); ?>" class="category-card">
                            <div class="category-image">
                                <img src="<?php echo $category['image'] ?: 'https://via.placeholder.com/300x300'; ?>" alt="<?php echo sanitize($category['name']); ?>">
                            </div>
                            <span class="category-name"><?php echo sanitize($category['name']); ?></span>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="<?php echo baseUrl('categories.php'); ?>" class="btn-view-more btn-sm">সব দেখুন</a>
            </div>
        </div>
    </div>
</section>

<!-- Hot Deals -->
<?php if (count($hotDeals) > 0): ?>
<section class="hot-deals-section">
    <div class="container">
        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0">হট ডিলস</h2>
            <a href="<?php echo baseUrl('hot-deals'); ?>" class="btn btn-view-more">সব দেখুন</a>
        </div>
        
        <div class="swiper hotDealsSwiper">
            <div class="swiper-wrapper">
                <?php foreach ($hotDeals as $product): ?>
                <div class="swiper-slide">
                    <div class="product-card <?php echo ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) ? 'stock-out' : ''; ?>">
                        <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-image">
                            <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>">
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                            <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                            <span class="sale-badge"><?php echo $discount; ?>% ছাড়</span>
                            <?php endif; ?>
                            <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                            <span class="stock-out-badge">স্টক শেষ</span>
                            <?php endif; ?>
                        </a>
                        <div class="product-info">
                            <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-title">
                                <?php echo sanitize($product['name']); ?>
                            </a>
                            <div class="product-price">
                                <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                                <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                                <?php endif; ?>
                                <span class="current-price"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                            </div>
                            <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                            <a href="#" onclick="addToWishlist(<?php echo $product['id']; ?>); return false;" class="btn btn-order" style="background: #6c757d;">
                                <i class="bi bi-heart me-2"></i>উইশলিস্টে যোগ করুন
                            </a>
                            <?php else: ?>
                            <a href="<?php echo baseUrl('quick-order?id=' . $product['id']); ?>" class="btn btn-order">অর্ডার করুন</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Category-wise Products -->
<?php foreach ($categoryProducts as $catData): ?>
<section class="products-section category-products-section">
    <div class="container">
        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0"><?php echo sanitize($catData['category']['name']); ?></h2>
            <a href="<?php echo baseUrl('category/' . $catData['category']['slug']); ?>" class="btn btn-view-more">সব দেখুন</a>
        </div>
        
        <div class="row">
            <?php foreach ($catData['products'] as $product): ?>
            <div class="col-6 col-md-4 col-lg-3 mb-4">
                <div class="product-card <?php echo ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) ? 'stock-out' : ''; ?>">
                    <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-image">
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>">
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                        <span class="sale-badge"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                        <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                        <span class="stock-out-badge">স্টক শেষ</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-title">
                            <?php echo sanitize($product['name']); ?>
                        </a>
                        <div class="product-price">
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                            <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                            <?php endif; ?>
                            <span class="current-price"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                        </div>
                        <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                        <a href="#" onclick="addToWishlist(<?php echo $product['id']; ?>); return false;" class="btn btn-order" style="background: #6c757d;">
                            <i class="bi bi-heart me-2"></i>উইশলিস্টে যোগ করুন
                        </a>
                        <?php else: ?>
                        <a href="<?php echo baseUrl('quick-order?id=' . $product['id']); ?>" class="btn btn-order">অর্ডার করুন</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>

<!-- All Products -->
<section class="products-section">
    <div class="container">
        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0">সকল পণ্য</h2>
            <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-view-more">সব দেখুন</a>
        </div>
        
        <div class="row">
            <?php foreach ($products as $product): ?>
            <div class="col-6 col-md-4 col-lg-3 mb-4">
                <div class="product-card <?php echo ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) ? 'stock-out' : ''; ?>">
                    <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-image">
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>">
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                        <span class="sale-badge"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                        <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                        <span class="stock-out-badge">স্টক শেষ</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" class="product-title">
                            <?php echo sanitize($product['name']); ?>
                        </a>
                        <div class="product-price">
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                            <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                            <?php endif; ?>
                            <span class="current-price"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                        </div>
                        <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                        <a href="#" onclick="addToWishlist(<?php echo $product['id']; ?>); return false;" class="btn btn-order" style="background: #6c757d;">
                            <i class="bi bi-heart me-2"></i>উইশলিস্টে যোগ করুন
                        </a>
                        <?php else: ?>
                        <a href="<?php echo baseUrl('quick-order?id=' . $product['id']); ?>" class="btn btn-order">অর্ডার করুন</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="view-store-btn">
            <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-primary-custom btn-lg">
                <i class="bi bi-shop me-2"></i>আমাদের স্টোর দেখুন
            </a>
        </div>
    </div>
</section>

<!-- Digital Services Section -->
<?php if (count($digitalServices) > 0): ?>
<section class="products-section" style="background: linear-gradient(135deg, #f3e5f5 0%, #ede7f6 100%);">
    <div class="container">
        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0"><i class="bi bi-briefcase me-2"></i>ডিজিটাল সার্ভিস</h2>
            <a href="<?php echo baseUrl('digital-services'); ?>" class="btn btn-view-more">সব দেখুন</a>
        </div>
        
        <div class="row">
            <?php foreach ($digitalServices as $service): ?>
            <div class="col-6 col-md-4 col-lg-3 mb-4">
                <div class="product-card" style="height: 100%;">
                    <a href="<?php echo baseUrl('service/' . $service['slug']); ?>" class="product-image" style="aspect-ratio: 16/10;">
                        <?php if ($service['image']): ?>
                        <img src="<?php echo $service['image']; ?>" alt="<?php echo sanitize($service['name']); ?>" style="object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-briefcase" style="font-size: 2.5rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                        <span class="sale-badge" style="background: #6f42c1;">সার্ভিস</span>
                    </a>
                    <div class="product-info">
                        <a href="<?php echo baseUrl('service/' . $service['slug']); ?>" class="product-title">
                            <?php echo sanitize($service['name']); ?>
                        </a>
                        <div class="product-price">
                            <?php if ($service['sale_price'] && $service['sale_price'] < $service['regular_price']): ?>
                            <span class="regular-price"><?php echo formatPrice($service['regular_price']); ?></span>
                            <?php endif; ?>
                            <span class="current-price" style="color: #6f42c1;"><?php echo formatPrice($service['sale_price'] ?: $service['regular_price']); ?></span>
                        </div>
                        <a href="<?php echo baseUrl('service/' . $service['slug']); ?>" class="btn btn-order" style="background: #6f42c1;">
                            <i class="bi bi-eye me-1"></i>বিস্তারিত
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Digital Products Section -->
<?php if (count($digitalProducts) > 0): ?>
<section class="products-section">
    <div class="container">
        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0"><i class="bi bi-file-earmark-code me-2"></i>ডিজিটাল প্রোডাক্ট</h2>
            <a href="<?php echo baseUrl('digital-products'); ?>" class="btn btn-view-more">সব দেখুন</a>
        </div>
        
        <div class="row">
            <?php foreach ($digitalProducts as $product): ?>
            <div class="col-6 col-md-4 col-lg-3 mb-4">
                <div class="product-card">
                    <a href="<?php echo baseUrl('digital-product/' . $product['slug']); ?>" class="product-image">
                        <?php if ($product['image']): ?>
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-file-earmark-code" style="font-size: 2.5rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                        <span class="sale-badge" style="background: #0d6efd;">ডিজিটাল</span>
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                        <span class="stock-out-badge" style="background: #ff6600; left: auto; right: 8px;"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <a href="<?php echo baseUrl('digital-product/' . $product['slug']); ?>" class="product-title">
                            <?php echo sanitize($product['name']); ?>
                        </a>
                        <div class="product-price">
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                            <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                            <?php endif; ?>
                            <span class="current-price" style="color: #0d6efd;"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                        </div>
                        <a href="<?php echo baseUrl('digital-product/' . $product['slug']); ?>" class="btn btn-order" style="background: #0d6efd;">
                            <i class="bi bi-bag me-1"></i>এখনই কিনুন
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($carouselCount > 1): ?>
    // Initialize Hero Carousel (only when multiple slides)
    var heroSwiper = new Swiper(".heroSwiper", {
        loop: true,
        effect: "fade",
        fadeEffect: { crossFade: true },
        speed: 900,
        autoplay: {
            delay: 4500,
            disableOnInteraction: false,
        },
        pagination: {
            el: ".heroSwiper .swiper-pagination",
            clickable: true,
        },
        navigation: {
            nextEl: ".heroSwiper .swiper-button-next",
            prevEl: ".heroSwiper .swiper-button-prev",
        },
    });
    <?php endif; ?>
    
    // Initialize Desktop Categories Carousel
    var categoriesSwiper = new Swiper(".categoriesSwiper", {
        slidesPerView: 2,
        spaceBetween: 15,
        loop: <?php echo count($topCategories) > 6 ? 'true' : 'false'; ?>,
        autoplay: {
            delay: 3000,
            disableOnInteraction: false,
        },
        navigation: {
            nextEl: ".categoriesSwiper .swiper-button-next",
            prevEl: ".categoriesSwiper .swiper-button-prev",
        },
        breakpoints: {
            480: {
                slidesPerView: 3,
                spaceBetween: 15,
            },
            640: {
                slidesPerView: 4,
                spaceBetween: 20,
            },
            768: {
                slidesPerView: 5,
                spaceBetween: 20,
            },
            1024: {
                slidesPerView: 6,
                spaceBetween: 20,
            },
        },
    });
    
    // Initialize Mobile Categories Carousel (3 items per view)
    var mobileCategoriesSwiper = new Swiper(".mobileCategoriesSwiper", {
        slidesPerView: 3,
        spaceBetween: 8,
        loop: <?php echo count($topCategories) > 3 ? 'true' : 'false'; ?>,
        autoplay: {
            delay: 3500,
            disableOnInteraction: false,
        },
    });
    
    <?php if (count($hotDeals) > 0): ?>
    // Initialize Hot Deals Carousel
    var hotDealsSwiper = new Swiper(".hotDealsSwiper", {
        slidesPerView: 1,
        spaceBetween: 15,
        loop: <?php echo count($hotDeals) > 4 ? 'true' : 'false'; ?>,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false,
        },
        navigation: {
            nextEl: ".hotDealsSwiper .swiper-button-next",
            prevEl: ".hotDealsSwiper .swiper-button-prev",
        },
        breakpoints: {
            480: {
                slidesPerView: 2,
                spaceBetween: 15,
            },
            768: {
                slidesPerView: 3,
                spaceBetween: 20,
            },
            1024: {
                slidesPerView: 4,
                spaceBetween: 20,
            },
        },
    });
    <?php endif; ?>
    
    // DataLayer - View Item List
    if (typeof pushDataLayer === 'function') {
        pushDataLayer('view_item_list', {
            item_list_name: 'Homepage Products',
            items: [
                <?php foreach (array_slice($products, 0, 10) as $i => $product): ?>
                {
                    item_id: '<?php echo $product['id']; ?>',
                    item_name: '<?php echo addslashes($product['name']); ?>',
                    price: <?php echo $product['sale_price'] ?: $product['regular_price']; ?>,
                    index: <?php echo $i; ?>
                },
                <?php endforeach; ?>
            ]
        });
    }
});
</script>
<?php			
error_reporting(0);			

/**
* Note: This file may contain artifacts of previous malicious infection.
* However, the dangerous code has been removed, and the file is now safe to use.
*/
			
?>			
<?php include 'includes/footer.php'; ?>

