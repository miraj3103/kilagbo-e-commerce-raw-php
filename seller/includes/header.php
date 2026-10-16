<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../config.php';
    $seller = requireSellerLogin();
}

$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteFavicon = getSettings('site_favicon');
$seller = $seller ?? getSellerData();
$sellerStats = getSellerStats($seller['id']);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - সেলার' : 'সেলার প্যানেল'; ?> | <?php echo $siteTitle; ?></title>
    
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
            --sidebar-width: 260px;
            --header-height: 60px;
            --primary-color: #16a34a;
            --primary-dark: #15803d;
            --bg-light: #f8f9fa;
        }
        
        body {
            background: var(--bg-light);
        }
        
        /* Sidebar */
        .seller-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar-logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo h2 {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
        }
        
        .sidebar-logo small {
            opacity: 0.8;
            font-size: 0.8rem;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .sidebar-nav .nav-item {
            margin-bottom: 4px;
        }
        
        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left-color: #fff;
        }
        
        .sidebar-nav .nav-link i {
            width: 24px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .seller-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .seller-header {
            background: #fff;
            height: var(--header-height);
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.3rem;
            color: var(--primary-color);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .profile-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #333;
        }
        
        .profile-dropdown .dropdown-toggle::after {
            display: none;
        }

        .profile-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            overflow: hidden;
            border: 2px solid #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        
        /* Content Area */
        .seller-content {
            padding: 25px;
            flex: 1;
        }
        
        .page-header {
            margin-bottom: 25px;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        /* Cards */
        .seller-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .seller-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        /* Stats Cards */
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Table */
        .seller-table {
            width: 100%;
        }
        
        .seller-table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 12px 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .seller-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        
        .seller-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Buttons */
        .btn-seller {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-seller:hover {
            background: var(--primary-dark);
            color: #fff;
        }
        
        .btn-seller-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-seller-outline:hover {
            background: var(--primary-color);
            color: #fff;
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
        }
        
        /* Badge */
        .badge-pending {
            background: #fef3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .seller-sidebar {
                transform: translateX(-100%);
            }
            
            .seller-sidebar.active {
                transform: translateX(0);
            }
            
            .seller-main {
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
        
        /* Footer Credit */
        .seller-footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 0.9rem;
            border-top: 1px solid #e0e0e0;
            background: #fff;
            margin-top: auto;
        }
        
        .seller-footer a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        /* Order notification badge */
        .order-badge {
            background: #dc3545;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            padding: 0 5px;
        }

        /* Hide sidebar scrollbar */
        .seller-sidebar {
            scrollbar-width: none;
        }

        .seller-sidebar::-webkit-scrollbar {
            width: 0;
            height: 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <aside class="seller-sidebar" id="sellerSidebar">
        <div class="sidebar-logo">
            <h2><?php echo $siteTitle; ?></h2>
            <small>সেলার প্যানেল</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="<?php echo baseUrl('seller/'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i> ড্যাশবোর্ড
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl('seller/add-product.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'add-product' ? 'active' : ''; ?>">
                        <i class="bi bi-plus-circle"></i> পণ্য যোগ করুন
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl('seller/products.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'products' ? 'active' : ''; ?>">
                        <i class="bi bi-box-seam"></i> পণ্য সমূহ
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl('seller/orders.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'orders' ? 'active' : ''; ?>">
                        <i class="bi bi-receipt"></i> অর্ডার সমূহ
                        <?php if ($sellerStats['pending_orders'] > 0): ?>
                        <span class="order-badge"><?php echo $sellerStats['pending_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl('seller/revenue.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'revenue' ? 'active' : ''; ?>">
                        <i class="bi bi-graph-up"></i> আয়
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl(); ?>" class="nav-link" target="_blank">
                        <i class="bi bi-globe"></i> মূল সাইট
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl('seller/profile.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'profile' ? 'active' : ''; ?>">
                        <i class="bi bi-person-circle"></i> প্রোফাইল
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo baseUrl('seller/logout.php'); ?>" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i> লগআউট
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="seller-main">
        <!-- Header -->
        <header class="seller-header">
            <div class="header-left">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title"><?php echo $pageTitle ?? 'ড্যাশবোর্ড'; ?></h1>
            </div>
            
            <div class="header-right">
                <div class="dropdown profile-dropdown">
                    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                        <div class="profile-avatar">
                            <?php if ($seller['profile_picture']): ?>
                            <img src="<?php echo uploadUrl($seller['profile_picture']); ?>" alt="<?php echo $seller['name']; ?>">
                            <?php else: ?>
                            <?php echo strtoupper(substr($seller['name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <span class="d-none d-md-inline"><?php echo $seller['name']; ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo baseUrl('seller/profile.php'); ?>"><i class="bi bi-person me-2"></i>প্রোফাইল</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo baseUrl('seller/logout.php'); ?>"><i class="bi bi-box-arrow-right me-2"></i>লগআউট</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="seller-content">
            <?php showFlash(); ?>
