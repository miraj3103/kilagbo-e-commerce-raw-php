<?php
/**
 * Kilagbo Reseller Dashboard - Header
 */

$reseller = requireResellerLogin();

// Get balance from resellers table
$stmt = $pdo->prepare("SELECT balance FROM resellers WHERE id = ?");
$stmt->execute([$reseller['id']]);
$resellerRow = $stmt->fetch();
$correctBalance = $resellerRow['balance'] ?? 0.00;

// Minimum balance requirement
$minBalance = (float)(getSettings('resell_min_balance') ?: 200);
$hasLowBalance = $correctBalance < $minBalance;

// Get reseller notifications
$resellerNotifs = getResellerNotifications($reseller['id']);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'ড্যাশবোর্ড'; ?> - রিসেলার প্যানেল</title>
    
    <?php if (getSettings('site_favicon')): ?>
    <link rel="icon" href="<?php echo uploadUrl(getSettings('site_favicon')); ?>">
    <?php endif; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --sidebar-bg: #1a1c23;
            --sidebar-hover: #2d3748;
            --text-light: #a0aec0;
        }
        
        * {
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        body {
            background: #f7fafc;
            min-height: 100vh;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand img {
            height: 40px;
        }
        
        .sidebar-brand h5 {
            color: #fff;
            font-weight: 600;
            margin: 10px 0 0;
            font-size: 1rem;
        }
        
        .sidebar-menu {
            padding: 15px 0;
            list-style: none;
            margin: 0;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background: var(--sidebar-hover);
            color: #fff;
            border-left-color: var(--primary);
        }
        
        .sidebar-menu li a i {
            font-size: 1.1rem;
            width: 24px;
        }
        
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .top-navbar {
            background: #fff;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .page-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: #333;
            margin: 0;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .balance-badge {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
        }
        
        .content-area {
            padding: 25px;
        }
        
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
        }
        
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
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
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h4 style="color: #fff; font-weight: 700; margin: 0; font-size: 1.5rem; letter-spacing: 1px;">Kilagbo</h4>
        <h5>রিসেলার প্যানেল</h5>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo baseUrl('resell/'); ?>" class="<?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i> <span>ড্যাশবোর্ড</span>
            </a>
        </li>
        <li>
            <a href="<?php echo baseUrl('resell/products.php'); ?>" class="<?php echo ($currentPage ?? '') === 'products' ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> <span>পণ্য সমূহ</span>
                <?php if ($resellerNotifs['products'] > 0): ?>
                <span class="menu-badge"><?php echo $resellerNotifs['products']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo baseUrl('resell/orders.php'); ?>" class="<?php echo ($currentPage ?? '') === 'orders' ? 'active' : ''; ?>">
                <i class="bi bi-bag-check"></i> <span>অর্ডার সমূহ</span>
                <?php if ($resellerNotifs['orders'] > 0): ?>
                <span class="menu-badge"><?php echo $resellerNotifs['orders']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo baseUrl('resell/withdraw.php'); ?>" class="<?php echo ($currentPage ?? '') === 'withdraw' ? 'active' : ''; ?>">
                <i class="bi bi-wallet2"></i> <span>উইথড্র</span>
                <?php if ($resellerNotifs['withdrawals'] > 0): ?>
                <span class="menu-badge"><?php echo $resellerNotifs['withdrawals']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo baseUrl('resell/add-balance.php'); ?>" class="<?php echo ($currentPage ?? '') === 'add-balance' ? 'active' : ''; ?>">
                <i class="bi bi-plus-square"></i> <span>এড ব্যালেন্স</span>
            </a>
        </li>
        <li>
            <a href="<?php echo baseUrl('resell/profile.php'); ?>" class="<?php echo ($currentPage ?? '') === 'profile' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle"></i> <span>প্রোফাইল</span>
            </a>
        </li>
        <li>
            <a href="<?php echo baseUrl(); ?>">
                <i class="bi bi-house"></i> <span>মূল সাইট</span>
            </a>
        </li>
        <li>
            <a href="<?php echo baseUrl('resell/logout.php'); ?>">
                <i class="bi bi-box-arrow-left"></i> <span>লগআউট</span>
            </a>
        </li>
    </ul>
</aside>

<main class="main-content">
    <nav class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-toggle" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title"><?php echo $pageTitle ?? 'ড্যাশবোর্ড'; ?></h1>
        </div>
        
        <div class="user-menu">
            <div class="balance-badge <?php echo $hasLowBalance ? 'bg-danger text-white border-0' : ''; ?>">
                <i class="bi bi-wallet me-1"></i> <?php echo formatPrice($correctBalance); ?>
                <?php if ($hasLowBalance): ?>
                    <span class="ms-1 small" title="অর্ডার করার জন্য নূন্যতম <?php echo number_format($minBalance); ?> টাকা ব্যালেন্স প্রয়োজন">(Low)</span>
                <?php endif; ?>
            </div>
            
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($reseller['name'], 0, 1)); ?>
                    </div>
                    <span class="d-none d-md-inline"><?php echo sanitize($reseller['name']); ?></span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo baseUrl('resell/profile.php'); ?>"><i class="bi bi-person me-2"></i>প্রোফাইল</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo baseUrl('resell/logout.php'); ?>"><i class="bi bi-box-arrow-left me-2"></i>লগআউট</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="content-area">
        <?php if ($hasLowBalance && ($currentPage === 'products' || $currentPage === 'dashboard') && !strpos($_SERVER['PHP_SELF'], 'add-balance.php')): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4 border-0 shadow-sm" style="background: #fee2e2; color: #991b1b;">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div>
                <strong>অর্ডার করতে পারবেন না!</strong><br>
                অর্ডার করতে হলে আপনার মেইন ব্যালেন্স নূন্যতম <strong>৳<?php echo number_format($minBalance); ?></strong> হতে হবে। বর্তমানে আপনার ব্যালেন্স ৳<?php echo number_format($correctBalance, 2); ?>। অর্ডার চালিয়ে যেতে দয়া করে ব্যালেন্স এড করুন।
                <a href="<?php echo baseUrl('resell/add-balance.php'); ?>" class="btn btn-sm btn-danger ms-3">এড ব্যালেন্স</a>
            </div>
        </div>
        <?php endif; ?>
