<?php
/**
 * Kilagbo - Affiliate Store Categories Page
 * View all categories for an affiliate store
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

// Get products based on store type
if ($store['store_type'] === 'retail') {
    $catWhere = "WHERE ap.status = 1 AND ap.retail_product_id IS NOT NULL AND (ap.wholesale_product_id IS NULL OR ap.wholesale_product_id = 0)";
} else {
    $catWhere = "WHERE ap.status = 1 AND ap.wholesale_product_id IS NOT NULL AND ap.wholesale_product_id > 0";
}

// Get all categories for this store's products with product count
$catStmt = $pdo->prepare("
    SELECT c.*, COUNT(ap.id) as product_count
    FROM categories c
    JOIN affiliate_products ap ON c.id = ap.category_id
    $catWhere
    GROUP BY c.id
    ORDER BY c.name ASC
");
$catStmt->execute();
$allStoreCategories = $catStmt->fetchAll();

// Count total products for this store
$totalProductsStmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_products ap $catWhere");
$totalProductsStmt->execute();
$totalProductsCount = $totalProductsStmt->fetchColumn();

// Check if there are any uncategorized products in this store
$uncatStmt = $pdo->prepare("
    SELECT COUNT(*) FROM affiliate_products ap
    $catWhere AND (ap.category_id = 0 OR ap.category_id IS NULL)
");
$uncatStmt->execute();
$uncategorizedCount = $uncatStmt->fetchColumn();
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
    <title>সকল ক্যাটেগরি - <?php echo sanitize($storeName); ?></title>
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
        
        .store-header .btn-back {
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
        
        .store-header .btn-back:hover {
            background: #059669;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }
        
        @media (max-width: 768px) {
            .store-header {
                position: relative;
            }
            
            body {
                padding-top: 0;
            }
            
            .store-header .btn-back span {
                display: none;
            }
            
            .store-header .btn-back {
                padding: 10px 14px;
            }
        }
        
        /* Page Title */
        .page-title {
            background: #fff;
            padding: 25px 0;
            border-bottom: 1px solid #eee;
        }
        
        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-title .store-name {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 5px;
        }
        
        /* Category Grid Section */
        .categories-section {
            padding: 30px 0 50px;
            flex: 1;
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
            
            .page-title h1 {
                font-size: 1.2rem;
            }
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
        
        /* Main Footer */
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
                
                <a href="<?php echo baseUrl('store/' . $storeCode); ?>" class="btn-back">
                    <i class="bi bi-arrow-left"></i>
                    <span>স্টোরে ফিরে যান</span>
                </a>
            </div>
        </div>
    </header>
    
    <!-- Page Title -->
    <div class="page-title">
        <div class="container">
            <h1><i class="bi bi-grid-3x3-gap" style="color: #10b981;"></i> সকল ক্যাটেগরি</h1>
            <p class="store-name"><?php echo sanitize($storeName); ?></p>
        </div>
    </div>
    
    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <?php if (empty($allStoreCategories) && !$hasUncategorized): ?>
            <div class="empty-state">
                <i class="bi bi-grid"></i>
                <h5 class="mt-3">কোনো ক্যাটেগরি পাওয়া যায়নি</h5>
                <p class="text-muted">শীঘ্রই ক্যাটেগরি যোগ করা হবে</p>
            </div>
            <?php else: ?>
            <div class="category-grid">
                <!-- সকল প্রোডাক্টস Box -->
                <a href="<?php echo baseUrl('store/' . $storeCode); ?>" class="category-card special-card">
                    <div class="category-image">
                        <i class="bi bi-box-seam-fill category-icon"></i>
                    </div>
                    <div class="category-name">সকল প্রোডাক্টস</div>
                    <div class="product-count"><?php echo $totalProductsCount; ?> টি পণ্য</div>
                </a>
                
                <!-- All Categories -->
                <?php foreach ($allStoreCategories as $cat): ?>
                <a href="<?php echo baseUrl('store/' . $storeCode . '?category=' . $cat['id']); ?>" class="category-card">
                    <div class="category-image">
                        <?php if (!empty($cat['image'])): ?>
                        <img src="<?php echo imageUrl($cat['image']); ?>" alt="<?php echo sanitize($cat['name']); ?>">
                        <?php else: ?>
                        <img src="<?php echo baseUrl('assets/images/placeholder.png'); ?>" alt="<?php echo sanitize($cat['name']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="category-name"><?php echo sanitize($cat['name']); ?></div>
                    <div class="product-count"><?php echo $cat['product_count']; ?> টি পণ্য</div>
                </a>
                <?php endforeach; ?>
                
                <!-- আনক্যাটাগরাইজড Box -->
                <?php if ($hasUncategorized): ?>
                <a href="<?php echo baseUrl('store/' . $storeCode . '?category=-1'); ?>" class="category-card uncategorized-card">
                    <div class="category-image">
                        <i class="bi bi-folder-x category-icon"></i>
                    </div>
                    <div class="category-name">আনক্যাটাগরাইজড</div>
                    <div class="product-count"><?php echo $uncategorizedCount; ?> টি পণ্য</div>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Footer -->
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
