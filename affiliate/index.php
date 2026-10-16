<?php
/**
 * Kilagbo Affiliate Portal - Landing Page / Dashboard
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

// If logged in, show dashboard
if (isAffiliatorLoggedIn()) {
    $affiliator = getAffiliatorData();
    if ($affiliator && $affiliator['status'] === 'approved') {
        // Show dashboard
        $pageTitle = 'ড্যাশবোর্ড';
        $currentPage = 'dashboard';
        
        $affiliatorId = getAffiliatorId();
        $stats = getAffiliatorStats($affiliatorId);
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT balance, total_earned, total_withdrawn FROM affiliators WHERE id = ?");
        $stmt->execute([$affiliatorId]);
        $balanceData = $stmt->fetch();
        
        // Recent orders
        $stmt = $pdo->prepare("SELECT * FROM affiliate_orders WHERE affiliator_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$affiliatorId]);
        $recentOrders = $stmt->fetchAll();
        
        // Top performing links
        $stmt = $pdo->prepare("
            SELECT al.*, ap.name as product_name, ap.image as product_image
            FROM affiliate_links al
            JOIN affiliate_products ap ON al.product_id = ap.id
            WHERE al.affiliator_id = ?
            ORDER BY al.conversions DESC
            LIMIT 4
        ");
        $stmt->execute([$affiliatorId]);
        $topLinks = $stmt->fetchAll();
        
        include __DIR__ . '/includes/header.php';
        ?>
        
        <style>
            .stat-card {
                background: #fff;
                border-radius: 12px;
                padding: 25px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                height: 100%;
            }
            
            .stat-card .icon {
                width: 60px;
                height: 60px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                color: #fff;
            }
            
            .stat-card .icon.primary { background: linear-gradient(135deg, #10b981, #059669); }
            .stat-card .icon.success { background: linear-gradient(135deg, #22c55e, #16a34a); }
            .stat-card .icon.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
            .stat-card .icon.info { background: linear-gradient(135deg, #3b82f6, #2563eb); }
            .stat-card .icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
            
            .stat-card h3 {
                font-size: 1.8rem;
                font-weight: 700;
                margin: 15px 0 5px;
            }
            
            .stat-card p {
                color: #666;
                margin: 0;
                font-size: 0.9rem;
            }
            
            .section-card {
                background: #fff;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                margin-top: 25px;
            }
            
            .section-card h5 {
                font-weight: 600;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            
            .welcome-banner {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                border-radius: 15px;
                padding: 30px;
                color: #fff;
                margin-bottom: 25px;
            }
            
            .welcome-banner h2 {
                font-weight: 700;
                margin-bottom: 10px;
            }
            
            .welcome-banner p {
                opacity: 0.9;
                margin: 0;
            }
            
            .order-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .order-item:last-child {
                border-bottom: none;
            }
            
            .status-badge {
                padding: 4px 10px;
                border-radius: 15px;
                font-size: 0.75rem;
                font-weight: 500;
            }
            
            .status-pending { background: #fff3cd; color: #856404; }
            .status-processing { background: #cce5ff; color: #004085; }
            .status-shipped { background: #d4edda; color: #155724; }
            .status-delivered { background: #d1e7dd; color: #0f5132; }
            .status-cancelled { background: #f8d7da; color: #721c24; }
            
            .link-item {
                display: flex;
                gap: 12px;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 10px;
                margin-bottom: 10px;
            }
            
            .link-item img {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 8px;
            }
            
            .link-item-info h6 {
                font-weight: 600;
                margin-bottom: 4px;
                font-size: 0.9rem;
            }
            
            .link-item-info small {
                color: #666;
            }
        </style>
        
        <?php showFlash(); ?>
        
        <div class="welcome-banner">
            <h2><i class="bi bi-stars me-2"></i>স্বাগতম, <?php echo sanitize($affiliator['name']); ?>!</h2>
            <p>আপনার অ্যাফিলিয়েট ড্যাশবোর্ডে স্বাগতম। পণ্যের লিংক শেয়ার করুন এবং প্রতিটি বিক্রয়ে কমিশন আয় করুন।</p>
        </div>
        
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon primary">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <h3><?php echo formatPrice($balanceData['balance']); ?></h3>
                    <p>বর্তমান ব্যালেন্স</p>
                </div>
            </div>
            
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h3><?php echo formatPrice($stats['monthly_earnings']); ?></h3>
                    <p>এই মাসের আয়</p>
                </div>
            </div>
            
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon info">
                        <i class="bi bi-cursor"></i>
                    </div>
                    <h3><?php echo $stats['total_clicks']; ?></h3>
                    <p>মোট ক্লিক</p>
                </div>
            </div>
            
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon purple">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <h3><?php echo $stats['total_orders']; ?></h3>
                    <p>মোট অর্ডার</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-7">
                <div class="section-card">
                    <h5><i class="bi bi-clock me-2"></i>সাম্প্রতিক অর্ডার</h5>
                    
                    <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">এখনো কোনো অর্ডার নেই</p>
                        <a href="<?php echo baseUrl('affiliate/products.php'); ?>" class="btn btn-sm btn-success">পণ্য দেখুন ও লিংক তৈরি করুন</a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <div class="order-item">
                        <div>
                            <span class="fw-bold text-success">#<?php echo $order['order_number']; ?></span>
                            <small class="text-muted d-block"><?php echo date('d M Y', strtotime($order['created_at'])); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php 
                                $statusLabels = [
                                    'pending' => 'পেন্ডিং',
                                    'processing' => 'প্রসেসিং',
                                    'shipped' => 'শিপড',
                                    'delivered' => 'ডেলিভার্ড',
                                    'cancelled' => 'বাতিল'
                                ];
                                echo $statusLabels[$order['status']] ?? $order['status'];
                                ?>
                            </span>
                            <small class="text-muted d-block mt-1">কমিশন: <?php echo formatPrice($order['commission_amount']); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo baseUrl('affiliate/orders.php'); ?>" class="btn btn-sm btn-outline-success">সব অর্ডার দেখুন</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="section-card">
                    <h5><i class="bi bi-link-45deg me-2"></i>টপ পারফর্মিং লিংক</h5>
                    
                    <?php if (empty($topLinks)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-link" style="font-size: 3rem;"></i>
                        <p class="mt-2">কোনো অ্যাফিলিয়েট লিংক নেই</p>
                        <a href="<?php echo baseUrl('affiliate/products.php'); ?>" class="btn btn-sm btn-success">লিংক তৈরি করুন</a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($topLinks as $link): ?>
                    <div class="link-item">
                        <img src="<?php echo imageUrl($link['product_image']); ?>" alt="">
                        <div class="link-item-info flex-grow-1">
                            <h6><?php echo sanitize($link['product_name']); ?></h6>
                            <small><i class="bi bi-cursor"></i> <?php echo $link['clicks']; ?> ক্লিক | <i class="bi bi-bag-check"></i> <?php echo $link['conversions']; ?> বিক্রয়</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo baseUrl('affiliate/products.php'); ?>" class="btn btn-sm btn-outline-success">সব লিংক দেখুন</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php
        include __DIR__ . '/includes/footer.php';
        exit;
    } elseif ($affiliator && $affiliator['status'] === 'pending') {
        redirect(baseUrl('affiliate/pending.php'));
    }
}

// Landing page for non-logged in users
$pageTitle = 'অ্যাফিলিয়েট মার্কেটিং প্রোগ্রাম';
$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteLogo = getSettings('site_logo');
$siteFavicon = getSettings('site_favicon');
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
        * { font-family: 'Hind Siliguri', sans-serif; }
        
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
        }
        
        body {
            background: #f8f9fa;
        }
        
        /* Header */
        .aff-header {
            background: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .aff-header .logo img {
            max-height: 45px;
        }
        
        .aff-header .btn-login {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .aff-header .btn-login:hover {
            background: var(--primary-dark);
            color: #fff;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 80px 0;
            color: #fff;
            text-align: center;
        }
        
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .hero-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto 30px;
        }
        
        .hero-section .btn-cta {
            background: #fff;
            color: var(--primary);
            border: none;
            padding: 15px 40px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .hero-section .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        /* How it works */
        .how-section {
            padding: 80px 0;
            background: #fff;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .step-card {
            text-align: center;
            padding: 30px 20px;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 20px;
        }
        
        .step-card h5 {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .step-card p {
            color: #666;
            font-size: 0.95rem;
        }
        
        /* Benefits */
        .benefits-section {
            padding: 80px 0;
            background: #f8f9fa;
        }
        
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .benefit-item i {
            font-size: 1.5rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .benefit-item h6 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .benefit-item p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            text-align: center;
        }
        
        .cta-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-section .btn-white {
            background: #fff;
            color: var(--primary);
            padding: 15px 40px;
            border-radius: 30px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        
        .cta-section .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        /* Footer */
        .aff-footer {
            background: #1f2937;
            color: #fff;
            padding: 30px 0;
            text-align: center;
        }
        
        .aff-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 1.8rem;
            }
            
            .hero-section {
                padding: 50px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="aff-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?php echo baseUrl(); ?>" class="logo">
                    <?php if ($siteLogo): ?>
                    <img src="<?php echo uploadUrl($siteLogo); ?>" alt="<?php echo $siteTitle; ?>">
                    <?php else: ?>
                    <h4 class="mb-0"><?php echo $siteTitle; ?></h4>
                    <?php endif; ?>
                </a>
                <div>
                    <a href="<?php echo baseUrl('affiliate/login.php'); ?>" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>লগইন / রেজিস্টার
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1><i class="bi bi-stars me-2"></i>কিলাগবো.কম – অ্যাফিলিয়েট মার্কেটিং প্রোগ্রাম</h1>
            <p>
                কিলাগবো.কম, বাংলাদেশের একটি দ্রুত বর্ধনশীল ই-কমার্স প্ল্যাটফর্ম, এখন আপনাকে প্রতিটি রেফার করা বিক্রয়ের জন্য কমিশন আয়ের সুযোগ দিচ্ছে! চাই আপনি ব্লগার, সোশ্যাল মিডিয়া ইনফ্লুয়েন্সার, ছাত্র-ছাত্রী, ফ্রিল্যান্সার কিংবা কন্টেন্ট ক্রিয়েটর—যে কেউ কিলাগবোর আমদানি করা লাইফস্টাইল, টেক এবং দৈনন্দিন প্রয়োজনীয় পণ্যগুলো প্রচার করে আয় করতে পারবেন।
            </p>
            <a href="<?php echo baseUrl('affiliate/register.php'); ?>" class="btn btn-cta">
                <i class="bi bi-person-plus me-2"></i>বিনামূল্যে রেজিস্টার করুন
            </a>
        </div>
    </section>
    
    <!-- How it Works -->
    <section class="how-section">
        <div class="container">
            <div class="section-title">
                <h2>কীভাবে কাজ করে?</h2>
                <p>মাত্র ৪টি সহজ ধাপে আয় শুরু করুন</p>
            </div>
            
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="step-card">
                        <div class="step-number">১</div>
                        <h5>বিনামূল্যে রেজিস্টার করুন</h5>
                        <p>অ্যাফিলিয়েট হিসেবে সাইন আপ করুন - কোনো খরচ নেই!</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="step-card">
                        <div class="step-number">২</div>
                        <h5>ট্র্যাকিং লিংক তৈরি করুন</h5>
                        <p>যেকোনো পণ্যের জন্য আপনার নিজস্ব অ্যাফিলিয়েট লিংক জেনারেট করুন</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="step-card">
                        <div class="step-number">৩</div>
                        <h5>লিংক শেয়ার করুন</h5>
                        <p>সোশ্যাল মিডিয়া, ব্লগ, হোয়াটসঅ্যাপ, ইমেইল এ শেয়ার করুন</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="step-card">
                        <div class="step-number">৪</div>
                        <h5>কমিশন পান</h5>
                        <p>আপনার লিংক থেকে প্রতিটি ক্রয়ে কমিশন আয় করুন!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Benefits -->
    <section class="benefits-section">
        <div class="container">
            <div class="section-title">
                <h2>সুবিধাসমূহ</h2>
                <p>কেন কিলাগবো অ্যাফিলিয়েট প্রোগ্রামে যোগ দেবেন?</p>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <h6>প্রতিটি সফল বিক্রয়ে আকর্ষক কমিশন</h6>
                            <p>প্রতিটি সফল অর্ডারে নির্ধারিত শতাংশ বা নির্দিষ্ট পরিমাণ কমিশন পাবেন</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <h6>শতাধিক ট্রেন্ডিং পণ্য</h6>
                            <p>গ্যাজেট থেকে শুরু করে ঘরোয়া প্রয়োজনীয় - বিভিন্ন ক্যাটেগরির পণ্য প্রচার করুন</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <h6>বিশ্বস্ত ব্র্যান্ডের সাথে অংশীদারিত্ব</h6>
                            <p>বাংলাদেশের অন্যতম বিশ্বস্ত ই-কমার্স ব্র্যান্ডের সাথে কাজ করুন</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <h6>রিয়েল-টাইম অ্যানালিটিক্স ড্যাশবোর্ড</h6>
                            <p>ক্লিক, কনভার্শন, আয় সব কিছু রিয়েল-টাইমে ট্র্যাক করুন</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <h6>সহজ উত্তোলন</h6>
                            <p>ন্যূনতম ১০০ টাকা জমা হলে বিকাশ বা নগদে উত্তোলন করুন</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <h6>নিবেদিত সাপোর্ট টিম</h6>
                            <p>যেকোনো সমস্যায় আমাদের সাপোর্ট টিম সবসময় আপনার পাশে</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>আজই যোগ দিন!</h2>
            <p class="mb-4">বাংলাদেশের অন্যতম সেরা ই-কমার্স প্ল্যাটফর্মের মাধ্যমে আয় শুরু করুন!</p>
            <a href="<?php echo baseUrl('affiliate/register.php'); ?>" class="btn btn-white me-2">
                <i class="bi bi-person-plus me-2"></i>রেজিস্টার করুন
            </a>
            <a href="<?php echo baseUrl('affiliate/login.php'); ?>" class="btn btn-outline-light">
                <i class="bi bi-box-arrow-in-right me-2"></i>লগইন করুন
            </a>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="aff-footer">
        <div class="container">
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo $siteTitle; ?> অ্যাফিলিয়েট প্রোগ্রাম | 
                ডেভেলপড বাই <a href="https://webnify.com.bd" target="_blank">Webnify Bangladesh</a>
            </p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
