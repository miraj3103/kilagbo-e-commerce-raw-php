<?php
/**
 * Kilagbo - Affiliate Store Page
 * Clean shop page for customers to browse affiliate products
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config/config.php';

// Get store code from URL
$storeCode = sanitize($_GET['code'] ?? '');

if (empty($storeCode)) {
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

// Track store click
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $pdo->prepare("
        SELECT id FROM affiliate_store_clicks 
        WHERE store_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$store['id'], $ip]);
    
    if (!$stmt->fetch()) {
        try {
            $pdo->prepare("INSERT INTO affiliate_store_clicks (store_id, affiliator_id, ip_address) VALUES (?, ?, ?)")
                ->execute([$store['id'], $store['affiliator_id'], $ip]);
        } catch (PDOException $e) {
            // Ignore
        }
        $pdo->prepare("UPDATE affiliate_stores SET clicks = clicks + 1 WHERE id = ?")->execute([$store['id']]);
    }
    
    // Set cookie for attribution (30 days)
    setcookie('affiliate_store', $store['id'], time() + (30 * 24 * 60 * 60), '/');
    setcookie('affiliate_store_code', $storeCode, time() + (30 * 24 * 60 * 60), '/');
} catch (Exception $e) {
    // Silently fail
}

// Get filters
$categoryFilter = intval($_GET['category'] ?? 0);

// Get products based on store type
$products = [];
$catWhere = "";
if ($store['store_type'] === 'retail') {
    $whereClause = "WHERE ap.status = 1 AND ap.retail_product_id IS NOT NULL AND (ap.wholesale_product_id IS NULL OR ap.wholesale_product_id = 0)";
    $catWhere = $whereClause;
} else {
    $whereClause = "WHERE ap.status = 1 AND ap.wholesale_product_id IS NOT NULL AND ap.wholesale_product_id > 0";
    $catWhere = $whereClause;
}

$params = [];
if ($categoryFilter === -1) {
    $whereClause .= " AND (ap.category_id = 0 OR ap.category_id IS NULL)";
} elseif ($categoryFilter > 0) {
    $whereClause .= " AND ap.category_id = ?";
    $params[] = $categoryFilter;
}

$stmt = $pdo->prepare("
    SELECT ap.* FROM affiliate_products ap
    $whereClause
    ORDER BY ap.created_at DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get unique categories for this store's products
$catStmt = $pdo->prepare("
    SELECT DISTINCT c.* 
    FROM categories c
    JOIN affiliate_products ap ON c.id = ap.category_id
    $catWhere
    ORDER BY c.name ASC
");
$catStmt->execute();
$allStoreCategories = $catStmt->fetchAll();

// Get 6 random categories for display
$displayCategories = $allStoreCategories;
shuffle($displayCategories);
$displayCategories = array_slice($displayCategories, 0, 6);

// Count total products for this store
$totalProductsStmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_products ap $catWhere");
$totalProductsStmt->execute();
$totalProductsCount = $totalProductsStmt->fetchColumn();

// Count uncategorized products
$uncatCountStmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_products ap $catWhere AND (ap.category_id = 0 OR ap.category_id IS NULL)");
$uncatCountStmt->execute();
$uncategorizedCount = $uncatCountStmt->fetchColumn();
$hasUncategorized = $uncategorizedCount > 0;

$storeName = $store['store_name'] ?: $store['affiliator_name'] . "'s Store";
$storeType = $store['store_type'];
$siteTitle = getSettings('site_title') ?: 'Kilagbo';
$siteFavicon = getSettings('site_favicon');
$siteLogo = getSettings('site_logo');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($storeName); ?></title>
    <?php if ($siteFavicon): ?>
    <link rel="icon" href="<?php echo uploadUrl($siteFavicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #ffffff;
            --accent-color: #333333;
            --text-color: #212121;
            --text-light: #666666;
            --border-color: #e0e0e0;
            --bg-light: #f8f9fa;
        }
        
        * { font-family: 'Hind Siliguri', sans-serif; }
        
        html, body {
            height: 100%;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            padding-top: 70px;
            display: flex;
            flex-direction: column;
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
        
        .store-header .search-box {
            flex: 1;
            max-width: 500px;
        }
        
        .store-header .search-box input {
            width: 100%;
            padding: 10px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 25px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .store-header .search-box input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
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
        
        /* Mobile Search Bar - Row below header */
        .mobile-search-row {
            display: none;
            background: #fff;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .mobile-search-row .search-box {
            max-width: 280px;
            margin: 0 auto;
        }
        
        .mobile-search-row .search-box input {
            width: 100%;
            padding: 8px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 20px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .mobile-search-row .search-box input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        
        @media (max-width: 768px) {
            /* Make header NOT sticky on mobile */
            .store-header {
                position: relative;
            }
            
            body {
                padding-top: 0;
            }
            
            /* Hide desktop search, show mobile search row */
            .store-header .search-box {
                display: none;
            }
            
            .mobile-search-row {
                display: block;
            }
            
            .store-header .btn-visit-site span {
                display: none;
            }
            
            .store-header .btn-visit-site {
                padding: 10px 14px;
            }
        }
        
        /* Category Grid Section */
        .category-section {
            background: #fff;
            padding: 25px 0;
            border-bottom: 1px solid #eee;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #10b981;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #333;
        }
        
        .section-header .view-all {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            background: #f0fdf4;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .section-header .view-all:hover {
            background: #10b981;
            color: #fff;
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 15px;
        }
        
        .category-card {
            text-align: center;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            height: 100%;
            padding-bottom: 10px;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #10b981;
        }

        .category-card.active {
            border-color: #10b981;
            box-shadow: 0 12px 32px rgba(16,185,129,0.25);
            background: #f0fdf4;
        }
        
        .category-card .category-image {
            width: 100%;
            aspect-ratio: 1;
            overflow: hidden;
            background: #f8f9fa;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .category-card .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .category-card:hover .category-image img {
            transform: scale(1.05);
        }
        
        .category-card .category-icon {
            font-size: 2.5rem;
            color: #10b981;
        }
        
        .category-card .category-name {
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
            padding: 10px 8px 4px;
            background: #fff;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
            min-height: 2.6em;
        }
        
        .category-card .product-count {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 2px;
        }
        
        .category-card.special-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: #86efac;
        }
        
        .category-card.special-card:hover {
            border-color: #10b981;
        }
        
        .category-card.uncategorized-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-color: #fcd34d;
        }
        
        .category-card.uncategorized-card:hover {
            border-color: #f59e0b;
        }
        
        .category-card.uncategorized-card .category-icon {
            color: #f59e0b;
        }
        
        /* Mobile Responsive for Category Grid */
        @media (max-width: 991px) {
            .category-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .category-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .category-card .category-name {
                font-size: 0.75rem;
                padding: 8px 5px 3px;
            }
            
            .category-card .product-count {
                font-size: 0.7rem;
            }
            
            .category-card .category-icon {
                font-size: 2rem;
            }
            
            .section-header h2 {
                font-size: 1.1rem;
            }
            
            .section-header .view-all {
                font-size: 0.8rem;
                padding: 6px 12px;
            }
        }
        
        .products-section {
            padding: 30px 0 50px;
            flex: 1;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            color: inherit;
        }
        
        .product-card .product-image {
            aspect-ratio: 1;
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        
        .product-card .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 15px;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-card .discount-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ef4444;
            color: #fff;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .product-card .product-info {
            padding: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .product-card .product-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.4;
            height: 2.8em;
            color: #333;
        }
        
        .product-card .product-price {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .product-card .current-price {
            font-size: 1.15rem;
            font-weight: 700;
            color: #10b981;
        }
        
        .product-card .regular-price {
            font-size: 0.85rem;
            color: #999;
            text-decoration: line-through;
        }
        
        .product-card .order-btn {
            display: block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            text-align: center;
            padding: 12px;
            font-weight: 600;
            margin-top: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .product-card:hover .order-btn {
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            border-radius: 12px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
        }
        
        /* Main Footer - Same as main website */
        .main-footer {
            background: var(--primary-color);
            color: var(--secondary-color);
            padding: 40px 0 20px;
            margin-top: auto;
        }
        
        .main-footer h5, .main-footer h6 {
            color: var(--secondary-color);
        }
        
        .main-footer a {
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .main-footer a:hover {
            text-decoration: underline;
        }
        
        .footer-credit {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.9rem;
        }
        
        .footer-credit a {
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .footer-credit a:hover {
            text-decoration: underline;
        }
        
        /* Footer Contact Icons */
        .contact-icon-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none !important;
            font-size: 1.2rem;
            margin: 0 5px;
        }
        
        .contact-icon-btn:hover {
            transform: translateY(-5px);
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .contact-icon-btn.call { background: linear-gradient(45deg, #0d6efd, #0a58ca); }
        .contact-icon-btn.whatsapp { background: linear-gradient(45deg, #25D366, #128C7E); }
        .contact-icon-btn.imo { background: linear-gradient(45deg, #00b0ff, #0091ea); }
        
        /* Mobile Responsiveness for Footer */
        @media (max-width: 767px) {
            .main-footer {
                text-align: center;
            }
            .main-footer h5, .main-footer h6 {
                margin-top: 15px;
            }
            .main-footer .contact-icons {
                justify-content: center;
            }
            .main-footer .col-lg-4, .main-footer .col-lg-2, .main-footer .col-lg-3 {
                margin-bottom: 2rem !important;
            }
            .footer-credit {
                margin-top: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .product-card .product-info {
                padding: 12px;
            }
            
            .product-card .product-name {
                font-size: 0.85rem;
                height: 2.8em;
            }
            
            .product-card .current-price {
                font-size: 1rem;
            }
            
            .product-card .order-btn {
                padding: 10px;
                font-size: 0.9rem;
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
                
                <div class="search-box">
                    <input type="text" id="productSearch" placeholder="পণ্য খুঁজুন..." onkeyup="filterProducts()">
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
    
    <!-- Mobile Search Row (visible only on mobile) -->
    <div class="mobile-search-row">
        <div class="search-box">
            <input type="text" id="mobileProductSearch" placeholder="পণ্য খুঁজুন..." onkeyup="filterProductsMobile()">
        </div>
    </div>
    
    <!-- Category Grid Section -->
    <?php if (!empty($allStoreCategories) || $hasUncategorized): ?>
    <section class="category-section">
        <div class="container">
            <div class="section-header">
                <h2><i class="bi bi-grid-3x3-gap" style="color: #10b981;"></i> ক্যাটেগরি</h2>
                <a href="<?php echo baseUrl('store/' . $storeCode . '/categories'); ?>" class="view-all">
                    সব দেখুন <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            
            <div class="category-grid">
                <!-- সকল প্রোডাক্টস Box -->
                <a href="<?php echo baseUrl('store/' . $storeCode); ?>" class="category-card special-card <?php echo $categoryFilter === 0 ? 'active' : ''; ?>">
                    <div class="category-image">
                        <i class="bi bi-box-seam-fill category-icon"></i>
                    </div>
                    <div class="category-name">সকল প্রোডাক্টস</div>
                    <div class="product-count"><?php echo $totalProductsCount; ?> টি পণ্য</div>
                </a>
                
                <!-- 6 Random Categories -->
                <?php foreach ($displayCategories as $cat): ?>
                <a href="<?php echo baseUrl('store/' . $storeCode . '?category=' . $cat['id']); ?>" class="category-card <?php echo $categoryFilter === $cat['id'] ? 'active' : ''; ?>">
                    <div class="category-image">
                        <?php if (!empty($cat['image'])): ?>
                        <img src="<?php echo imageUrl($cat['image']); ?>" alt="<?php echo sanitize($cat['name']); ?>">
                        <?php else: ?>
                        <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo sanitize($cat['name']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="category-name"><?php echo sanitize($cat['name']); ?></div>
                </a>
                <?php endforeach; ?>
                
                <!-- আনক্যাটাগরাইজড Box -->
                <?php if ($hasUncategorized): ?>
                <a href="<?php echo baseUrl('store/' . $storeCode . '?category=-1'); ?>" class="category-card uncategorized-card <?php echo $categoryFilter === -1 ? 'active' : ''; ?>">
                    <div class="category-image">
                        <i class="bi bi-folder-x category-icon"></i>
                    </div>
                    <div class="category-name">আনক্যাটাগরাইজড</div>
                    <div class="product-count"><?php echo $uncategorizedCount; ?> টি পণ্য</div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Products Section -->
    <section class="products-section">
        <div class="container">
            <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="bi bi-box-seam"></i>
                <h5 class="mt-3">কোনো পণ্য পাওয়া যায়নি</h5>
                <p class="text-muted">শীঘ্রই পণ্য যোগ করা হবে</p>
            </div>
            <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                <?php 
                    $price = $product['sale_price'] ?: $product['regular_price'];
                    $hasDiscount = $product['sale_price'] && $product['regular_price'] > $product['sale_price'];
                    $discountPercent = $hasDiscount ? round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100) : 0;
                    $productUrl = baseUrl('store-product/' . $storeCode . '/' . $product['id']);
                ?>
                <div class="product-card h-100 d-flex flex-column">
                    <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark flex-grow-1">
                        <div class="product-image">
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="<?php echo sanitize($product['name']); ?>">
                            <?php if ($hasDiscount): ?>
                            <span class="discount-badge">-<?php echo $discountPercent; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info border-bottom-0 pb-0">
                            <h6 class="product-name"><?php echo sanitize(html_entity_decode($product['name'] ?? '', ENT_QUOTES | ENT_HTML5)); ?></h6>
                            <div class="product-price">
                                <span class="current-price"><?php echo formatPrice($price); ?></span>
                                <?php if ($hasDiscount): ?>
                                <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <div class="p-3 pt-0 mt-auto">
                        <div class="d-grid gap-2">
                            <a href="<?php echo $productUrl; ?>" class="btn btn-sm order-btn w-100 text-decoration-none">
                                <i class="bi bi-bag-check me-1"></i>অর্ডার করুন
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-success w-100 fw-bold" onclick="addToCart(<?php echo $product['id']; ?>)">
                                <i class="bi bi-cart-plus me-1"></i>কার্টে যোগ করুন
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Footer - Same as main website -->
    <footer class="main-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-3"><?php echo getSettings('site_title') ?: 'কিলাগ্বো'; ?></h5>
                    <p class="mb-2"><?php echo getSettings('site_tagline') ?: 'আপনার বিশ্বস্ত অনলাইন শপ'; ?></p>
                    <p class="mb-3 small text-white-50">
                        আমাদের সাইটে আপনি পাচ্ছেন Retail, Affiliate, Reselling, Wholesale এবং Seller সুবিধা। আজই যুক্ত হোন আমাদের সাথে এবং উপভোগ করুন সেরা অনলাইন শপিং অভিজ্ঞতা।
                    </p>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">দ্রুত লিংক</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?php echo baseUrl('shop'); ?>" class="text-white text-decoration-none">Retail</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('affiliate'); ?>" class="text-white text-decoration-none">Affiliate</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('reselling'); ?>" class="text-white text-decoration-none">Reselling</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('wholesale'); ?>" class="text-white text-decoration-none">Wholesale</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('seller/welcome.php'); ?>" class="text-white text-decoration-none">Seller</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">অ্যাকাউন্ট</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?php echo baseUrl('login'); ?>" class="text-white text-decoration-none">লগইন</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('registration'); ?>" class="text-white text-decoration-none">রেজিস্ট্রেশন</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('account'); ?>" class="text-white text-decoration-none">আমার অ্যাকাউন্ট</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">যোগাযোগ</h6>
                    <p class="mb-3 small">আমাদের সাথে যোগাযোগ করুন</p>
                    <!-- Contact Icons -->
                    <div class="contact-icons d-flex mb-3">
                        <a href="tel:01629923301" class="contact-icon-btn call" title="Call Us">
                            <i class="bi bi-telephone-fill"></i>
                        </a>
                        <a href="https://wa.me/8801629923301" target="_blank" class="contact-icon-btn whatsapp" title="WhatsApp">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                        <a href="tel:01629923301" class="contact-icon-btn imo" title="IMO">
                            <i class="bi bi-chat-text-fill"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="footer-credit">
                <p class="mb-0">Developed by <a href="https://www.webnifybd.com" target="_blank">Webnify Bangladesh</a></p>
            </div>
        </div>
    </footer>
    
    <!-- Toast Container -->
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

        function addToCart(productId) {
            const qty = 1;
            const storeId = <?php echo $store['id']; ?>;
            const affiliatorId = <?php echo $store['affiliator_id']; ?>;
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', qty);
            formData.append('section', 'affiliate');
            formData.append('store_id', storeId);
            formData.append('affiliator_id', affiliatorId);
            
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
        
        function filterProducts() {
            const searchTerm = document.getElementById('productSearch').value.toLowerCase();
            const products = document.querySelectorAll('.product-card');
            
            products.forEach(product => {
                const name = product.querySelector('.product-name').textContent.toLowerCase();
                if (name.includes(searchTerm)) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }
        
        // Mobile search function
        function filterProductsMobile() {
            const searchTerm = document.getElementById('mobileProductSearch').value.toLowerCase();
            const products = document.querySelectorAll('.product-card');
            
            products.forEach(product => {
                const name = product.querySelector('.product-name').textContent.toLowerCase();
                if (name.includes(searchTerm)) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
            
            // Sync with desktop search
            document.getElementById('productSearch').value = document.getElementById('mobileProductSearch').value;
        }
    </script>
</body>
</html>
