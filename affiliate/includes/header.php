<?php
$affiliator = requireAffiliatorLogin();
$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteLogo = getSettings('site_logo');
$siteFavicon = getSettings('site_favicon');

// fetch live balance for header display
$headerBalance = 0;
try {
    $stmt = $pdo->prepare("SELECT balance FROM affiliators WHERE id = ?");
    $stmt->execute([$affiliator['id']]);
    $headerBalance = (float)($stmt->fetchColumn() ?: 0);
} catch (Exception $e) {
    $headerBalance = (float)($affiliator['balance'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - অ্যাফিলিয়েট' : 'অ্যাফিলিয়েট ড্যাশবোর্ড'; ?> | <?php echo $siteTitle; ?></title>
    
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
            --sidebar-width: 260px;
        }

        .balance-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #10b981;
            padding: 8px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
        }

        .balance-pill i {
            color: #10b981;
        }
        
        body {
            background: #f3f4f6;
        }
        
        /* Sidebar */
        .affiliate-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
            color: #fff;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar-logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        
        .sidebar-logo img {
            max-height: 40px;
            filter: brightness(0) invert(1);
        }
        
        .sidebar-logo h2 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 10px 0 0;
        }
        
        .sidebar-logo small {
            opacity: 0.8;
            font-size: 0.75rem;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border-left-color: #fff;
        }
        
        .sidebar-nav .nav-link i {
            width: 24px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .affiliate-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        /* Header */
        .affiliate-header {
            background: #fff;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.3rem;
            color: #000;
        }
        
        .user-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #333;
        }
        
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Content */
        .affiliate-content {
            padding: 25px;
        }
        
        /* Cards */
        .aff-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        /* Footer */
        .affiliate-footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .affiliate-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .affiliate-sidebar {
                transform: translateX(-100%);
            }
            
            .affiliate-sidebar.active {
                transform: translateX(0);
            }
            
            .affiliate-main {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <aside class="affiliate-sidebar" id="affiliateSidebar">
        <div class="sidebar-logo">
            <h2 style="margin-bottom: 4px;">Kilagbo</h2>
            <small>অ্যাফিলিয়েট প্যানেল</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="<?php echo baseUrl('affiliate/'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i> ড্যাশবোর্ড
                    </a>
                </li>
                
                <!-- Products Dropdown -->
                <li class="nav-item">
                    <a href="#productsSubmenu" class="nav-link <?php echo in_array(($currentPage ?? ''), ['products', 'products-retail', 'products-wholesale']) ? 'active' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array(($currentPage ?? ''), ['products', 'products-retail', 'products-wholesale']) ? 'true' : 'false'; ?>">
                        <i class="bi bi-box-seam"></i> পণ্য সমূহ
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <ul class="collapse <?php echo in_array(($currentPage ?? ''), ['products', 'products-retail', 'products-wholesale']) ? 'show' : ''; ?>" id="productsSubmenu" style="list-style: none; padding-left: 35px;">
                        <li class="nav-item">
                            <a href="<?php echo baseUrl('affiliate/products-retail.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'products-retail' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-shop me-2"></i> রিটেইল পণ্য
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo baseUrl('affiliate/products-wholesale.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'products-wholesale' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-boxes me-2"></i> পাইকারি পণ্য
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Create Store -->
                <li class="nav-item">
                    <a href="<?php echo baseUrl('affiliate/create-store.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'create-store' ? 'active' : ''; ?>">
                        <i class="bi bi-shop-window"></i> স্টোর তৈরি
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl('affiliate/orders.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'orders' ? 'active' : ''; ?>">
                        <i class="bi bi-receipt"></i> অর্ডার সমূহ
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('affiliate/withdraw.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'withdraw' ? 'active' : ''; ?>">
                        <i class="bi bi-wallet2"></i> উত্তোলন
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('affiliate/profile.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'profile' ? 'active' : ''; ?>">
                        <i class="bi bi-person-circle"></i> প্রোফাইল
                    </a>
                </li>
                <li class="nav-item mt-3" style="border-top: 1px solid rgba(255,255,255,0.15); padding-top: 15px;">
                    <a href="<?php echo baseUrl(); ?>" class="nav-link" target="_blank">
                        <i class="bi bi-globe"></i> মেইন সাইট
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('affiliate/logout.php'); ?>" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i> লগআউট
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <div class="affiliate-main">
        <!-- Header -->
        <header class="affiliate-header">
            <div class="d-flex align-items-center gap-3">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="mb-0 d-none d-md-block"><?php echo $pageTitle ?? 'ড্যাশবোর্ড'; ?></h5>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <span class="balance-pill">
                    <i class="bi bi-wallet2"></i>
                    <?php echo formatPrice($headerBalance); ?>
                </span>
                
                <div class="dropdown user-dropdown">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($affiliator['name'], 0, 1)); ?>
                    </div>
                    <span class="d-none d-sm-inline"><?php echo sanitize($affiliator['name']); ?></span>
                    <i class="bi bi-chevron-down"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo baseUrl('affiliate/profile.php'); ?>"><i class="bi bi-person me-2"></i>প্রোফাইল</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo baseUrl('affiliate/logout.php'); ?>"><i class="bi bi-box-arrow-right me-2"></i>লগআউট</a></li>
                </ul>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="affiliate-content">
