<?php
require_once __DIR__ . '/../config/config.php';
$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteTagline = getSettings('site_tagline') ?: 'আপনার বিশ্বস্ত অনলাইন শপ';
$siteLogo = getSettings('site_logo');
$siteFavicon = getSettings('site_favicon');
$cartCount = getCartCount();
$wishlistCount = getWishlistCount();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . $siteTitle : $siteTitle . ' - ' . $siteTagline; ?></title>
    
    <?php if ($siteFavicon): ?>
    <link rel="icon" href="<?php echo uploadUrl($siteFavicon); ?>" type="image/x-icon">
    <?php endif; ?>
    
    <!-- Google Fonts - Hind Siliguri -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
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
        
        * {
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        body {
            color: var(--text-color);
            background-color: var(--secondary-color);
        }
        
        /* Header Styles */
        .main-header {
            background: var(--secondary-color);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-top {
            padding: 12px 0;
        }
        
        .logo img {
            max-height: 50px;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .main-nav {
            display: flex;
            gap: 8px;
        }
        
        .main-nav .nav-link {
            color: var(--text-color);
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .main-nav .nav-link:hover,
        .main-nav .nav-link.active {
            background: var(--primary-color);
            color: var(--secondary-color);
        }
        
        .main-nav .nav-link.coming-soon {
            opacity: 0.6;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            border: 1px solid var(--border-color);
            border-radius: 25px;
            padding: 8px 40px 8px 16px;
            width: 200px;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            width: 260px;
            border-color: var(--primary-color);
            outline: none;
            box-shadow: none;
        }
        
        .search-box button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
        }
        
        .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            background: var(--bg-light);
        }
        
        .header-icon:hover {
            background: var(--primary-color);
            color: var(--secondary-color);
        }
        
        .header-icon .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary-color);
            color: var(--secondary-color);
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        .mobile-search-form {
            position: relative;
            width: 100%;
        }
        
        .mobile-search-form input {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 6px 35px 6px 15px;
            font-size: 0.85rem;
            background: var(--bg-light);
            transition: all 0.3s ease;
        }

        .mobile-search-form input:focus {
            border-color: var(--primary-color);
            outline: none;
            background: #fff;
        }
        
        .mobile-search-form button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            padding: 0;
            display: flex;
            align-items: center;
        }
        
        /* Mobile Menu */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .mobile-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--secondary-color);
            z-index: 9999;
            padding: 20px;
        }
        
        .mobile-nav.active {
            display: block;
        }
        
        .mobile-nav-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
        }
        
        .mobile-nav-links {
            padding-top: 60px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .mobile-nav-links a {
            display: block;
            padding: 12px 20px;
            color: var(--text-color);
            text-decoration: none;
            font-size: 1.1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .mobile-nav-links a:hover,
        .mobile-nav-links a.active {
            background: var(--primary-color);
            color: var(--secondary-color);
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-nav {
                display: none;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .search-box input {
                width: 150px;
            }
            
            .search-box input:focus {
                width: 180px;
            }
        }
        
        @media (max-width: 576px) {
            .search-box {
                display: none;
            }
            
            .header-icon {
                display: none !important;
            }
            
            .header-icon {
                width: 36px;
                height: 36px;
            }
            
            .logo-text {
                font-size: 1.2rem;
                white-space: nowrap;
            }

            .header-top {
                gap: 10px;
            }
        }
        
        /* Button Styles */
        .btn-primary-custom {
            background: var(--primary-color);
            color: var(--secondary-color);
            border: none;
            padding: 10px 24px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            background: var(--accent-color);
            color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-outline-custom {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 10px 24px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary-color);
            color: var(--secondary-color);
        }
        
        /* Product Card - New Design */
        .product-card {
            background: var(--secondary-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .product-card .product-image {
            position: relative;
            overflow: hidden;
            aspect-ratio: 1;
            background: #f8f9fa;
        }
        
        .product-card .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
            padding: 10px;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-card .sale-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #ff6600;
            color: #fff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .product-card .stock-out-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #6c757d;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .product-card.stock-out {
            opacity: 0.7;
        }
        
        .product-card.stock-out .product-image {
            position: relative;
        }
        
        .product-card .wholesale-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #dc3545;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .product-card .product-info {
            padding: 12px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        
        .product-card .product-title {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-color);
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.6em;
        }
        
        .product-card .product-title:hover {
            color: #ff6600;
        }
        
        .product-card .product-price {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .product-card .regular-price {
            font-size: 0.85rem;
            color: var(--text-light);
            text-decoration: line-through;
        }
        
        .product-card .current-price {
            font-size: 1rem;
            font-weight: 600;
            color: #ff6600;
        }
        
        .product-card .btn-order {
            width: 100%;
            background: #ff6600;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: auto;
            text-decoration: none;
            text-align: center;
            display: block;
        }
        
        .product-card .btn-order:hover {
            background: #e55a00;
            color: #fff;
        }
        
        /* Section Styles */
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
        }
        
        /* Footer */
        .main-footer {
            background: var(--primary-color);
            color: var(--secondary-color);
            padding: 40px 0 20px;
            margin-top: 60px;
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
    </style>
    
    <?php 
    // Facebook Pixel
    $pixelId = getSettings('facebook_pixel_id');
    $pixelActive = getSettings('facebook_pixel_active') == '1';
    $conversionApi = getSettings('facebook_conversion_api');
    if ($pixelId && $pixelActive): 
    ?>
    <!-- Facebook Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo $pixelId; ?>');
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?php echo $pixelId; ?>&ev=PageView&noscript=1"/></noscript>
    <?php endif; ?>
    
    <?php 
    // Google Tag Manager
    $gtmId = getSettings('gtm_id');
    $gtmActive = getSettings('gtm_active') == '1';
    if ($gtmId && $gtmActive): 
    ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo $gtmId; ?>');</script>
    <?php endif; ?>
    
    <!-- DataLayer -->
    <script>
        window.dataLayer = window.dataLayer || [];
        function pushDataLayer(event, data) {
            window.dataLayer.push({
                event: event,
                ...data
            });
        }
    </script>
</head>
<body>
    <?php if ($gtmId && $gtmActive): ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo $gtmId; ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <?php endif; ?>

    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-top d-flex justify-content-between align-items-center">
                <!-- Logo -->
                <a href="<?php echo baseUrl(); ?>" class="logo">
                    <?php if ($siteLogo): ?>
                        <img src="<?php echo uploadUrl($siteLogo); ?>" alt="<?php echo $siteTitle; ?>">
                    <?php else: ?>
                        <span class="logo-text"><?php echo $siteTitle; ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Mobile Search Box -->
                <div class="mobile-search-container d-flex d-lg-none flex-grow-1 mx-2">
                    <form action="<?php echo baseUrl('shop'); ?>" method="GET" class="mobile-search-form">
                        <input type="text" name="search" placeholder="কাঙ্ক্ষিত পণ্যটি খুঁজুন..." value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>">
                        <button type="submit"><i class="bi bi-search"></i></button>
                    </form>
                </div>
                
                <!-- Main Navigation -->
                <nav class="main-nav">
                    <a href="<?php echo baseUrl(); ?>" class="nav-link <?php echo (!isset($currentPage) || $currentPage == 'retail') ? 'active' : ''; ?>">রিটেইল</a>
                    <a href="<?php echo baseUrl('wholesale'); ?>" class="nav-link <?php echo (isset($currentPage) && $currentPage == 'wholesale') ? 'active' : ''; ?>">পাইকারি</a>
                    <a href="<?php echo baseUrl('reselling'); ?>" class="nav-link <?php echo (isset($currentPage) && $currentPage == 'reselling') ? 'active' : ''; ?>">রিসেলিং</a>
                    <a href="<?php echo baseUrl('affiliate'); ?>" class="nav-link <?php echo (isset($currentPage) && $currentPage == 'affiliate') ? 'active' : ''; ?>">এফিলিয়েট</a>
                    <a href="<?php echo baseUrl('seller/welcome.php'); ?>" class="nav-link <?php echo (isset($currentPage) && $currentPage == 'seller') ? 'active' : ''; ?>">সেলার</a>
                </nav>
                
                <!-- Header Actions -->
                <div class="header-actions">
                    <form action="<?php echo baseUrl('shop'); ?>" method="GET" class="search-box">
                        <input type="text" name="search" placeholder="খুঁজুন..." value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>">
                        <button type="submit"><i class="bi bi-search"></i></button>
                    </form>
                    
                    <a href="<?php echo baseUrl(isUserLoggedIn() ? 'account' : 'login'); ?>" class="header-icon" title="অ্যাকাউন্ট">
                        <i class="bi bi-person"></i>
                    </a>
                    
                    <a href="<?php echo baseUrl('wishlist'); ?>" class="header-icon" id="wishlist-icon" title="উইশলিস্ট">
                        <i class="bi bi-heart"></i>
                        <?php if ($wishlistCount > 0): ?>
                        <span class="badge"><?php echo $wishlistCount; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="<?php echo baseUrl('cart'); ?>" class="header-icon" id="cart-icon" title="কার্ট">
                        <i class="bi bi-bag"></i>
                        <?php if ($cartCount > 0): ?>
                        <span class="badge"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Mobile Navigation -->
    <div class="mobile-nav" id="mobileNav">
        <button class="mobile-nav-close" onclick="toggleMobileMenu()">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="mobile-nav-links">
            <a href="<?php echo baseUrl(); ?>" class="<?php echo (!isset($currentPage) || $currentPage == 'retail') ? 'active' : ''; ?>">রিটেইল</a>
            <a href="<?php echo baseUrl('wholesale'); ?>" class="<?php echo (isset($currentPage) && $currentPage == 'wholesale') ? 'active' : ''; ?>">পাইকারি</a>
            <a href="<?php echo baseUrl('reselling'); ?>">রিসেলিং</a>
            <a href="<?php echo baseUrl('affiliate'); ?>">এফিলিয়েট</a>
            <a href="<?php echo baseUrl('seller/welcome.php'); ?>">সেলার</a>
            <hr>
            <a href="<?php echo baseUrl('shop'); ?>"><i class="bi bi-shop me-2"></i> শপিং করুন</a>
            <a href="<?php echo baseUrl(isUserLoggedIn() ? 'account' : 'login'); ?>"><i class="bi bi-person me-2"></i> অ্যাকাউন্ট</a>
            <a href="<?php echo baseUrl('wishlist'); ?>"><i class="bi bi-heart me-2"></i> উইশলিস্ট</a>
            <a href="<?php echo baseUrl('cart'); ?>"><i class="bi bi-bag me-2"></i> কার্ট</a>
        </div>
    </div>
    
    <main>
