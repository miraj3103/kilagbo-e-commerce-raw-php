<?php
/**
 * Kilagbo Seller Portal - Welcome/Landing Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'সেলার পোর্টাল';

// Redirect if already logged in
if (isSellerLoggedIn()) {
    $seller = getSellerData();
    if ($seller && $seller['status'] === 'approved') {
        redirect(baseUrl('seller/index.php'));
    } elseif ($seller && $seller['status'] === 'pending') {
        redirect(baseUrl('seller/pending.php'));
    }
}

$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteFavicon = getSettings('site_favicon');
$siteLogo = getSettings('site_logo');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | <?php echo $siteTitle; ?></title>
    
    <?php if ($siteFavicon): ?>
    <link rel="icon" href="<?php echo uploadUrl($siteFavicon); ?>" type="image/x-icon">
    <?php endif; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        * {
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        :root {
            --primary-color: #16a34a;
            --primary-dark: #15803d;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }
        
        .navbar {
            background: rgba(255,255,255,0.1) !important;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            color: #fff !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: #fff !important;
        }
        
        .hero-section {
            padding: 100px 0 80px;
            color: #fff;
            text-align: center;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-hero {
            padding: 14px 35px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-hero-primary {
            background: #fff;
            color: var(--primary-color);
            border: none;
        }
        
        .btn-hero-primary:hover {
            background: #f8f9fa;
            color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .btn-hero-outline {
            background: transparent;
            color: #fff;
            border: 2px solid #fff;
        }
        
        .btn-hero-outline:hover {
            background: #fff;
            color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        .info-section {
            background: #fff;
            padding: 80px 0;
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .section-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 50px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 30px;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        
        .info-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }
        
        .info-card h4 {
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .info-card p {
            color: #666;
            margin: 0;
            line-height: 1.7;
        }
        
        .process-section {
            background: #f8f9fa;
            padding: 80px 0;
        }
        
        .process-step {
            text-align: center;
            padding: 30px 20px;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .process-step h5 {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .process-step p {
            color: #666;
            font-size: 0.95rem;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            padding: 80px 0;
            text-align: center;
            color: #fff;
        }
        
        .cta-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-text {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        .footer {
            background: #222;
            color: #fff;
            padding: 30px 0;
            text-align: center;
        }
        
        .footer a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo baseUrl(); ?>">
                <?php if ($siteLogo): ?>
                <img src="<?php echo uploadUrl($siteLogo); ?>" alt="<?php echo $siteTitle; ?>" height="40">
                <?php else: ?>
                <?php echo $siteTitle; ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-2">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo baseUrl(); ?>">হোম</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo baseUrl('seller/login.php'); ?>">লগইন</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-hero-primary btn-sm px-4" href="<?php echo baseUrl('seller/register.php'); ?>">রেজিস্টার</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">পাইকারি বিক্রেতাদের জন্য<br>বিশেষ প্ল্যাটফর্ম</h1>
            <p class="hero-subtitle">
                আপনার পাইকারি পণ্য বিক্রি করুন আমাদের প্ল্যাটফর্মে। সরাসরি অ্যাডমিনের কাছে বিক্রি করুন এবং আপনার ব্যবসা বাড়ান।
            </p>
            <div class="hero-buttons">
                <a href="<?php echo baseUrl('seller/register.php'); ?>" class="btn btn-hero btn-hero-primary">
                    <i class="bi bi-person-plus me-2"></i>সেলার হিসেবে যোগ দিন
                </a>
                <a href="<?php echo baseUrl('seller/login.php'); ?>" class="btn btn-hero btn-hero-outline">
                    <i class="bi bi-box-arrow-in-right me-2"></i>লগইন করুন
                </a>
            </div>
        </div>
    </section>
    
    <!-- Info Section -->
    <section class="info-section">
        <div class="container">
            <h2 class="section-title">কেন আমাদের সাথে যুক্ত হবেন?</h2>
            <p class="section-subtitle">পাইকারি বিক্রেতাদের জন্য সেরা সুযোগ</p>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="info-icon">
                            <i class="bi bi-shop"></i>
                        </div>
                        <h4>সহজে পণ্য আপলোড</h4>
                        <p>আপনার পাইকারি পণ্য সহজেই আপলোড করুন। পণ্যের ছবি, বিবরণ এবং দাম দিন। ব্যস, আপনার কাজ শেষ!</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="info-icon">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <h4>নির্ধারিত দামে বিক্রি</h4>
                        <p>আপনি আপনার পাইকারি দাম নির্ধারণ করুন। অ্যাডমিন সেই দামে আপনার কাছ থেকে পণ্য কিনবে।</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="info-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h4>আয় ট্র্যাক করুন</h4>
                        <p>আপনার সব আয় এবং বিক্রি ট্র্যাক করুন। সম্পূর্ণ স্বচ্ছ লেনদেন সিস্টেম।</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Process Section -->
    <section class="process-section">
        <div class="container">
            <h2 class="section-title">কিভাবে কাজ করে?</h2>
            <p class="section-subtitle">মাত্র ৪টি সহজ ধাপে শুরু করুন</p>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">১</div>
                        <h5>অ্যাকাউন্ট তৈরি করুন</h5>
                        <p>আপনার ব্যবসার তথ্য দিয়ে সেলার অ্যাকাউন্ট তৈরি করুন</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">২</div>
                        <h5>অনুমোদনের জন্য অপেক্ষা</h5>
                        <p>অ্যাডমিন আপনার আবেদন পর্যালোচনা করে অনুমোদন দিবে</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">৩</div>
                        <h5>পণ্য আপলোড করুন</h5>
                        <p>আপনার পাইকারি পণ্য আপলোড করুন দাম ও বিবরণ সহ</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">৪</div>
                        <h5>অর্ডার পান ও আয় করুন</h5>
                        <p>অ্যাডমিনের অর্ডার পূর্ণ করুন এবং আয় করুন</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">আজই শুরু করুন!</h2>
            <p class="cta-text">আপনার পাইকারি পণ্য বিক্রি করুন আমাদের প্ল্যাটফর্মে</p>
            <a href="<?php echo baseUrl('seller/register.php'); ?>" class="btn btn-hero btn-hero-primary">
                <i class="bi bi-arrow-right-circle me-2"></i>এখনই রেজিস্টার করুন
            </a>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo $siteTitle; ?>। সর্বস্বত্ব সংরক্ষিত।</p>
            <small>Developed by <a href="https://webnify.com.bd" target="_blank">Webnify Bangladesh</a></small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
