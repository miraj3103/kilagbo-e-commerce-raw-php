<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/config.php';
}
requireAdmin();
require_once __DIR__ . '/rbac.php';
requireAdminPermissionForCurrentPage();
$adminRole = adminCurrentRole();

$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteFavicon = getSettings('site_favicon');
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminPicture = $_SESSION['admin_picture'] ?? null;
$unreadNotifications = getUnreadNotificationCount();

// Get reselling notification counts
$resellingNotifs = getAdminResellingNotifications();

// Get pending order counts for notification badges
// Use session to track if admin has viewed the page - badge disappears after viewing
$pendingRetailOrders = 0;
$pendingServiceOrders = 0;
$pendingDigitalOrders = 0;

// If current page is orders, mark as viewed in session
if (($currentPage ?? '') === 'orders') {
    $_SESSION['admin_viewed_retail_orders'] = time();
}
if (($currentPage ?? '') === 'service-orders') {
    $_SESSION['admin_viewed_service_orders'] = time();
}
if (($currentPage ?? '') === 'digital-orders') {
    $_SESSION['admin_viewed_digital_orders'] = time();
}

// Count pending orders only if admin hasn't viewed the page in this session
// OR count orders created after the last view time
$lastViewedRetail = $_SESSION['admin_viewed_retail_orders'] ?? 0;
$lastViewedService = $_SESSION['admin_viewed_service_orders'] ?? 0;
$lastViewedDigital = $_SESSION['admin_viewed_digital_orders'] ?? 0;

if ($lastViewedRetail == 0) {
    $pendingRetailOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND created_at > FROM_UNIXTIME(?)");
    $stmt->execute([$lastViewedRetail]);
    $pendingRetailOrders = $stmt->fetchColumn();
}

try {
    if ($lastViewedService == 0) {
        $pendingServiceOrders = $pdo->query("SELECT COUNT(*) FROM service_orders WHERE status = 'pending'")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_orders WHERE status = 'pending' AND created_at > FROM_UNIXTIME(?)");
        $stmt->execute([$lastViewedService]);
        $pendingServiceOrders = $stmt->fetchColumn();
    }
} catch (PDOException $e) {}

try {
    if ($lastViewedDigital == 0) {
        $pendingDigitalOrders = $pdo->query("SELECT COUNT(*) FROM digital_orders WHERE status = 'pending'")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM digital_orders WHERE status = 'pending' AND created_at > FROM_UNIXTIME(?)");
        $stmt->execute([$lastViewedDigital]);
        $pendingDigitalOrders = $stmt->fetchColumn();
    }
} catch (PDOException $e) {}

// Fetch recent notifications
$notifStmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
$notifications = $notifStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - এডমিন' : 'এডমিন প্যানেল'; ?> | <?php echo $siteTitle; ?></title>
    
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
            --primary-color: #000;
            --bg-light: #f8f9fa;
        }
        
        body {
            background: var(--bg-light);
        }
        
        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #000;
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
            opacity: 0.7;
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
            color: rgba(255,255,255,0.7);
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
        
        /* Main Content */
        .admin-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .admin-header {
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
            color: #000;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-light);
            color: #000;
            text-decoration: none;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .header-icon:hover {
            background: #000;
            color: #fff;
        }
        
        .header-icon .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: #fff;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        .profile-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #000;
        }
        
        .profile-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #000;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Content Area */
        .admin-content {
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
        .admin-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .admin-card .card-title {
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
        .admin-table {
            width: 100%;
        }
        
        .admin-table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 12px 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .admin-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        
        .admin-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Buttons */
        .btn-admin {
            background: #000;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-admin:hover {
            background: #333;
            color: #fff;
        }
        
        .btn-admin-outline {
            background: transparent;
            color: #000;
            border: 1px solid #000;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-admin-outline:hover {
            background: #000;
            color: #fff;
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #000;
            box-shadow: none;
        }
        
        /* Notifications dropdown */
        .notifications-dropdown {
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #f0f7ff;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-main {
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
        .admin-footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 0.9rem;
            border-top: 1px solid #e0e0e0;
            background: #fff;
            margin-top: auto;
        }
        
        .admin-footer a {
            color: #000;
            text-decoration: none;
        }
        
        /* Menu notification badges */
        .menu-badge {
            background: #dc3545;
            color: #fff;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
            min-width: 18px;
            text-align: center;
        }
        
        .nav-link {
            position: relative;
        }

        /* Hide sidebar scrollbar but keep scroll functionality */
        .admin-sidebar {
            scrollbar-width: none; /* Firefox */
        }

        .admin-sidebar::-webkit-scrollbar {
            width: 0;
            height: 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-logo">
            <h2><?php echo $siteTitle; ?></h2>
            <small>এডমিন প্যানেল</small>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <?php if (adminCan('dashboard.view')): ?>
                <li class="nav-item">
                    <a href="<?php echo adminUrl('dashboard'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i> ড্যাশবোর্ড
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (adminCan('retail.manage')): ?>
<!-- Retail Section (includes Services & Digital Products) -->
                <li class="nav-item">
                    <a href="#retailSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['products', 'orders', 'categories', 'carousel', 'retail-delivery', 'services', 'service-orders', 'digital-products', 'digital-orders']) ? 'active' : ''; ?>" data-bs-toggle="collapse">
                        <i class="bi bi-cart3"></i> খুচরা বিক্রয়
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
                    </a>
                    <ul class="collapse <?php echo in_array($currentPage ?? '', ['products', 'orders', 'categories', 'carousel', 'retail-delivery', 'services', 'service-orders', 'digital-products', 'digital-orders']) ? 'show' : ''; ?>" id="retailSubmenu" style="list-style: none; padding-left: 30px;">
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('products'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'products' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-box-seam"></i> পণ্য
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('orders'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'orders' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-receipt"></i> অর্ডার
                                <?php if ($pendingRetailOrders > 0): ?>
                                <span class="order-badge"><?php echo $pendingRetailOrders; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('categories'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'categories' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-grid"></i> ক্যাটেগরি
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('carousel'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'carousel' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-images"></i> ক্যারোসেল
                            </a>
                        </li>
                        
                        <!-- Services Nested Submenu -->
                        <li class="nav-item">
                            <a href="#servicesNestedSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['services', 'service-orders']) ? 'active' : ''; ?>" data-bs-toggle="collapse" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-briefcase"></i> সার্ভিস
                                <i class="bi bi-chevron-down ms-auto" style="font-size: 0.6rem;"></i>
                            </a>
                            <ul class="collapse <?php echo in_array($currentPage ?? '', ['services', 'service-orders']) ? 'show' : ''; ?>" id="servicesNestedSubmenu" style="list-style: none; padding-left: 20px;">
                                <li class="nav-item">
                                    <a href="<?php echo adminUrl('services'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'services' ? 'active' : ''; ?>" style="padding: 6px 12px; font-size: 0.85rem;">
                                        <i class="bi bi-list-ul"></i> সার্ভিস সমূহ
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo adminUrl('service-orders'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'service-orders' ? 'active' : ''; ?>" style="padding: 6px 12px; font-size: 0.85rem;">
                                        <i class="bi bi-receipt"></i> অর্ডার
                                        <?php if ($pendingServiceOrders > 0): ?>
                                        <span class="order-badge"><?php echo $pendingServiceOrders; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- Digital Products Nested Submenu -->
                        <li class="nav-item">
                            <a href="#digitalNestedSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['digital-products', 'digital-orders']) ? 'active' : ''; ?>" data-bs-toggle="collapse" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-file-earmark-code"></i> ডিজিটাল প্রোডাক্ট
                                <i class="bi bi-chevron-down ms-auto" style="font-size: 0.6rem;"></i>
                            </a>
                            <ul class="collapse <?php echo in_array($currentPage ?? '', ['digital-products', 'digital-orders']) ? 'show' : ''; ?>" id="digitalNestedSubmenu" style="list-style: none; padding-left: 20px;">
                                <li class="nav-item">
                                    <a href="<?php echo adminUrl('digital-products'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'digital-products' ? 'active' : ''; ?>" style="padding: 6px 12px; font-size: 0.85rem;">
                                        <i class="bi bi-list-ul"></i> প্রোডাক্ট সমূহ
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo adminUrl('digital-orders'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'digital-orders' ? 'active' : ''; ?>" style="padding: 6px 12px; font-size: 0.85rem;">
                                        <i class="bi bi-receipt"></i> অর্ডার
                                        <?php if ($pendingDigitalOrders > 0): ?>
                                        <span class="order-badge"><?php echo $pendingDigitalOrders; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('retail-delivery'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'retail-delivery' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-truck"></i> ডেলিভারি চার্জ
                            </a>
                        </li>
                    </ul>
                </li>
                
                <?php endif; ?>
                <?php if (adminCan('wholesale.manage')): ?>
<!-- Wholesale Section -->
                <li class="nav-item">
                    <a href="#wholesaleSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['wholesale-products', 'wholesale-requests', 'wholesale-orders', 'wholesale-categories', 'wholesale-delivery']) ? 'active' : ''; ?>" data-bs-toggle="collapse">
                        <i class="bi bi-shop-window"></i> পাইকারি
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
                    </a>
                    <ul class="collapse <?php echo in_array($currentPage ?? '', ['wholesale-products', 'wholesale-requests', 'wholesale-orders', 'wholesale-categories', 'wholesale-delivery']) ? 'show' : ''; ?>" id="wholesaleSubmenu" style="list-style: none; padding-left: 30px;">
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('wholesale-requests.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') === 'wholesale-requests' ? 'active' : ''; ?>" style="padding:8px 15px;font-size:.9rem;">
                                <i class="bi bi-person-check"></i> পাইকারি অনুমোদন
                                <?php
                                try {
                                    $pendingWholesale = (int)$pdo->query("SELECT COUNT(*) FROM wholesale_access_requests WHERE status='pending'")->fetchColumn();
                                } catch (Throwable $e) { $pendingWholesale = 0; }
                                if ($pendingWholesale > 0): ?><span class="menu-badge"><?php echo $pendingWholesale; ?></span><?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('wholesale-products'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'wholesale-products' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-box-seam"></i> পণ্য
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('wholesale-add'); ?>" class="nav-link" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-plus-circle"></i> পণ্য যোগ করুন
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('wholesale-orders'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'wholesale-orders' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-receipt"></i> অর্ডার চেক করুন
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('wholesale-delivery'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'wholesale-delivery' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-truck"></i> ডেলিভারি চার্জ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('wholesale-categories'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'wholesale-categories' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-grid"></i> ক্যাটাগরী
                            </a>
                        </li>
                    </ul>
                </li>
                
                <?php endif; ?>
                <?php if (adminCan('resell.manage')): ?>
<!-- Reselling Section -->
                <li class="nav-item">
                    <a href="#resellingSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['resell-products', 'resell-orders', 'resellers', 'resell-payments', 'resell-delivery']) ? 'active' : ''; ?>" data-bs-toggle="collapse">
                        <i class="bi bi-person-badge"></i> রিসেলিং
                        <?php if ($resellingNotifs['total'] > 0): ?>
                        <span class="menu-badge"><?php echo $resellingNotifs['total']; ?></span>
                        <?php endif; ?>
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
                    </a>
                    <ul class="collapse <?php echo in_array($currentPage ?? '', ['resell-products', 'resell-orders', 'resellers', 'resell-payments', 'resell-delivery']) ? 'show' : ''; ?>" id="resellingSubmenu" style="list-style: none; padding-left: 30px;">
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('resell-products'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'resell-products' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-box-seam"></i> পণ্য সমূহ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('resell-add'); ?>" class="nav-link" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-plus-circle"></i> পণ্য যোগ করুন
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('resell-orders'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'resell-orders' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-receipt"></i> অর্ডার সমূহ
                                <?php if ($resellingNotifs['orders'] > 0): ?>
                                <span class="menu-badge"><?php echo $resellingNotifs['orders']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('resellers'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'resellers' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-people"></i> রিসেলার
                                <?php if ($resellingNotifs['resellers'] > 0): ?>
                                <span class="menu-badge"><?php echo $resellingNotifs['resellers']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('resell-payments'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'resell-payments' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-wallet2"></i> পেমেন্ট
                                <?php if ($resellingNotifs['payments'] > 0): ?>
                                <span class="menu-badge"><?php echo $resellingNotifs['payments']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('resell-balance-requests.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'resell-payments' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-plus-circle"></i> ব্যালেন্স রিকুয়েস্ট
                                <?php if ($resellingNotifs['balance_requests'] > 0): ?>
                                <span class="menu-badge"><?php echo $resellingNotifs['balance_requests']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('resell-delivery'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'resell-delivery' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-truck"></i> ডেলিভারি চার্জ
                            </a>
                        </li>
                    </ul>
                </li>
                
                <?php endif; ?>
                <?php if (adminCan('affiliate.manage')): ?>
<!-- Affiliate Section -->
                <?php 
                $affiliateNotifs = [];
                try {
                    $affiliateNotifs['affiliators'] = $pdo->query("SELECT COUNT(*) FROM affiliators WHERE status = 'pending'")->fetchColumn();
                    $affiliateNotifs['orders'] = $pdo->query("SELECT COUNT(*) FROM affiliate_orders WHERE status = 'pending'")->fetchColumn();
                    $affiliateNotifs['payments'] = $pdo->query("SELECT COUNT(*) FROM affiliator_withdrawals WHERE status = 'pending'")->fetchColumn();
                    $affiliateNotifs['total'] = $affiliateNotifs['affiliators'] + $affiliateNotifs['orders'] + $affiliateNotifs['payments'];
                } catch (Exception $e) { $affiliateNotifs = ['total' => 0, 'affiliators' => 0, 'orders' => 0, 'payments' => 0]; }
                ?>
                <li class="nav-item">
                    <a href="#affiliateSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['affiliate-products', 'affiliate-orders', 'affiliators', 'affiliate-payments', 'affiliate-delivery']) ? 'active' : ''; ?>" data-bs-toggle="collapse">
                        <i class="bi bi-share"></i> অ্যাফিলিয়েট
                        <?php if ($affiliateNotifs['total'] > 0): ?>
                        <span class="menu-badge"><?php echo $affiliateNotifs['total']; ?></span>
                        <?php endif; ?>
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
                    </a>
                    <ul class="collapse <?php echo in_array($currentPage ?? '', ['affiliate-products', 'affiliate-orders', 'affiliators', 'affiliate-payments', 'affiliate-delivery']) ? 'show' : ''; ?>" id="affiliateSubmenu" style="list-style: none; padding-left: 30px;">
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('affiliate-products'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'affiliate-products' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-box-seam"></i> পণ্য সমূহ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('affiliate-add'); ?>" class="nav-link" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-plus-circle"></i> পণ্য যোগ করুন
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('affiliate-orders'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'affiliate-orders' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-receipt"></i> অর্ডার সমূহ
                                <?php if ($affiliateNotifs['orders'] > 0): ?>
                                <span class="menu-badge"><?php echo $affiliateNotifs['orders']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('affiliators'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'affiliators' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-people"></i> অ্যাফিলিয়েটর
                                <?php if ($affiliateNotifs['affiliators'] > 0): ?>
                                <span class="menu-badge"><?php echo $affiliateNotifs['affiliators']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('affiliate-payments'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'affiliate-payments' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-wallet2"></i> পেমেন্ট
                                <?php if ($affiliateNotifs['payments'] > 0): ?>
                                <span class="menu-badge"><?php echo $affiliateNotifs['payments']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('affiliate-delivery'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'affiliate-delivery' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-truck"></i> ডেলিভারি চার্জ
                            </a>
                        </li>
                    </ul>
                </li>
                
                <?php endif; ?>
                <?php if (adminCan('seller.manage')): ?>
<!-- Seller Section -->
                <?php 
                $sellerNotifs = [];
                try {
                    $sellerNotifs['sellers'] = $pdo->query("SELECT COUNT(*) FROM sellers WHERE status = 'pending'")->fetchColumn();
                    $sellerNotifs['products'] = $pdo->query("SELECT COUNT(*) FROM seller_products WHERE status = 'pending'")->fetchColumn();
                    $sellerNotifs['orders'] = $pdo->query("SELECT COUNT(*) FROM seller_orders WHERE status = 'pending'")->fetchColumn();
                    $sellerNotifs['total'] = $sellerNotifs['sellers'] + $sellerNotifs['products'] + $sellerNotifs['orders'];
                } catch (Exception $e) { $sellerNotifs = ['total' => 0, 'sellers' => 0, 'products' => 0, 'orders' => 0]; }
                ?>
                <li class="nav-item">
                    <a href="#sellerSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['seller-products', 'sellers', 'seller-activity', 'seller-orders']) ? 'active' : ''; ?>" data-bs-toggle="collapse">
                        <i class="bi bi-shop"></i> সেলার
                        <?php if ($sellerNotifs['total'] > 0): ?>
                        <span class="menu-badge"><?php echo $sellerNotifs['total']; ?></span>
                        <?php endif; ?>
                        <i class="bi bi-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
                    </a>
                    <ul class="collapse <?php echo in_array($currentPage ?? '', ['seller-products', 'sellers', 'seller-activity', 'seller-orders']) ? 'show' : ''; ?>" id="sellerSubmenu" style="list-style: none; padding-left: 30px;">
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('seller-products'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'seller-products' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-box-seam"></i> পণ্য সমূহ
                                <?php if ($sellerNotifs['products'] > 0): ?>
                                <span class="menu-badge"><?php echo $sellerNotifs['products']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('seller-orders.php'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'seller-orders' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-receipt"></i> অর্ডার
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('sellers'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'sellers' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-people"></i> সেলার সমূহ
                                <?php if ($sellerNotifs['sellers'] > 0): ?>
                                <span class="menu-badge"><?php echo $sellerNotifs['sellers']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo adminUrl('seller-activity'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'seller-activity' ? 'active' : ''; ?>" style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="bi bi-activity"></i> কার্যকলাপ
                            </a>
                        </li>
                    </ul>
                </li>
                
<?php endif; ?>
                                <?php if (adminCan('admin_users.manage') || adminCan('roles.manage')): ?>
                <li class="nav-item">
                    <a href="#adminAccessSubmenu" class="nav-link <?php echo in_array($currentPage ?? '', ['admin-users','roles']) ? 'active' : ''; ?>" data-bs-toggle="collapse">
                        <i class="bi bi-shield-lock"></i> Admin Access
                        <i class="bi bi-chevron-down ms-auto" style="font-size:0.7rem"></i>
                    </a>
                    <ul class="collapse <?php echo in_array($currentPage ?? '', ['admin-users','roles']) ? 'show' : ''; ?>" id="adminAccessSubmenu" style="list-style:none;padding-left:30px">
                        <?php if (adminCan('admin_users.manage')): ?><li class="nav-item"><a href="<?php echo adminUrl('admin-users.php'); ?>" class="nav-link <?php echo ($currentPage ?? '')==='admin-users'?'active':''; ?>" style="padding:8px 15px;font-size:.9rem"><i class="bi bi-person-gear"></i> Admin Users</a></li><?php endif; ?>
                        <?php if (adminCan('roles.manage')): ?><li class="nav-item"><a href="<?php echo adminUrl('roles.php'); ?>" class="nav-link <?php echo ($currentPage ?? '')==='roles'?'active':''; ?>" style="padding:8px 15px;font-size:.9rem"><i class="bi bi-key"></i> Roles & Permissions</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (adminCan('courier.manage')): ?>
                <li class="nav-item"><a href="<?php echo adminUrl('courier'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'courier' ? 'active' : ''; ?>"><i class="bi bi-truck"></i> কুরিয়ার সেটআপ</a></li>
                <?php endif; ?>

                <?php if (adminCan('site.manage')): ?>
                <li class="nav-item"><a href="<?php echo adminUrl('pixel-gtm'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'pixel-gtm' ? 'active' : ''; ?>"><i class="bi bi-bar-chart"></i> পিক্সেল ও GTM</a></li>
                <li class="nav-item"><a href="<?php echo adminUrl('social-login'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'social-login' ? 'active' : ''; ?>"><i class="bi bi-people"></i> সোশ্যাল লগইন</a></li>
                <li class="nav-item"><a href="<?php echo adminUrl('contacts'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'contacts' ? 'active' : ''; ?>"><i class="bi bi-chat-dots"></i> যোগাযোগ</a></li>
                <?php endif; ?>

                <?php if (adminCan('payments.manage')): ?>
                <li class="nav-item"><a href="<?php echo adminUrl('payment-accounts'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'payment-accounts' ? 'active' : ''; ?>"><i class="bi bi-wallet2"></i> পেমেন্ট একাউন্ট</a></li>
                <?php endif; ?>

                <?php if (adminCan('settings.manage')): ?>
                <li class="nav-item"><a href="<?php echo adminUrl('settings'); ?>" class="nav-link <?php echo ($currentPage ?? '') == 'settings' ? 'active' : ''; ?>"><i class="bi bi-gear"></i> সেটিংস</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <div class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-left">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title d-none d-md-block"><?php echo $pageTitle ?? 'ড্যাশবোর্ড'; ?></h1>
            </div>
            
            <div class="header-right">
                <!-- Visit Frontend -->
                <a href="<?php echo baseUrl(); ?>" target="_blank" class="header-icon" title="ওয়েবসাইট দেখুন">
                    <i class="bi bi-globe"></i>
                </a>
                
                <!-- Notifications -->
                <div class="dropdown">
                    <a href="#" class="header-icon" data-bs-toggle="dropdown" id="notificationBell" onclick="markNotificationsRead()">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadNotifications > 0): ?>
                        <span class="badge" id="notifBadge"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown" style="min-width: 320px;">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">নোটিফিকেশন</h6>
                            <?php if (!empty($notifications)): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllNotifications()" style="font-size: 0.75rem; padding: 2px 8px;">
                                <i class="bi bi-trash me-1"></i>সব মুছুন
                            </button>
                            <?php endif; ?>
                        </div>
                        <div id="notificationList">
                            <?php if (empty($notifications)): ?>
                            <div class="p-3 text-center text-muted">
                                কোন নোটিফিকেশন নেই
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <a href="<?php echo $notif['link'] ?: '#'; ?>" class="notification-item d-block text-decoration-none text-dark <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <strong><?php echo sanitize($notif['title']); ?></strong>
                                <p class="mb-0 small text-muted"><?php echo sanitize($notif['message']); ?></p>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Profile -->
                <div class="dropdown profile-dropdown">
                    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                        <div class="profile-avatar">
                            <?php if ($adminPicture): ?>
                            <img src="<?php echo uploadUrl($adminPicture); ?>" alt="Profile">
                            <?php else: ?>
                            <?php echo strtoupper(substr($adminName, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <span class="d-none d-md-inline"><?php echo sanitize($adminName); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($adminRole): ?><li><span class="dropdown-item-text small text-muted"><i class="bi bi-shield-check me-2"></i><?php echo sanitize($adminRole['name']); ?></span></li><?php endif; ?>
                        <?php if (adminCan('settings.manage')): ?><li><a class="dropdown-item" href="<?php echo adminUrl('settings'); ?>"><i class="bi bi-person me-2"></i>প্রোফাইল / সেটিংস</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo adminUrl('logout'); ?>"><i class="bi bi-box-arrow-right me-2"></i>লগআউট</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="admin-content">
