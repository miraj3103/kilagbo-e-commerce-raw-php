<?php
/**
 * Kilagbo - Digital Services Shop Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'digital-services';
$pageTitle = 'ডিজিটাল সার্ভিস';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Search
$search = sanitize($_GET['search'] ?? '');

$whereClause = "status = 1";
$params = [];

if ($search) {
    $whereClause .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE $whereClause");
$countStmt->execute($params);
$totalServices = $countStmt->fetchColumn();
$totalPages = ceil($totalServices / $perPage);

// Fetch services
$stmt = $pdo->prepare("
    SELECT * FROM services 
    WHERE $whereClause 
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$services = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
    .shop-page {
        padding: 40px 0;
    }
    
    .shop-header {
        margin-bottom: 30px;
    }
    
    .shop-title {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .shop-filters {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        border: 1px solid var(--border-color);
    }
    
    .service-card {
        background: var(--secondary-color);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .service-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transform: translateY(-5px);
    }
    
    .service-card .service-image {
        position: relative;
        overflow: hidden;
        aspect-ratio: 16/10;
        background: #f8f9fa;
    }
    
    .service-card .service-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .service-card:hover .service-image img {
        transform: scale(1.05);
    }
    
    .service-card .service-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: #6f42c1;
        color: #fff;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .service-card .service-info {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    
    .service-card .service-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-color);
        text-decoration: none;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    @media (max-width: 576px) {
        .service-card .service-title {
            font-size: 0.9rem;
        }
        .service-card .service-info {
            padding: 12px;
        }
        .service-card .service-desc {
            display: none;
        }
        .service-card .service-meta {
            font-size: 0.75rem;
            gap: 8px;
        }
        .service-card .current-price {
            font-size: 1rem;
        }
        .service-card .btn-service {
            padding: 10px;
            font-size: 0.85rem;
        }
    }
    
    .service-card .service-title:hover {
        color: #6f42c1;
    }
    
    .service-card .service-desc {
        font-size: 0.9rem;
        color: var(--text-light);
        margin-bottom: 15px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .service-card .service-meta {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        font-size: 0.85rem;
        color: var(--text-light);
    }
    
    .service-card .service-price {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .service-card .regular-price {
        font-size: 0.9rem;
        color: var(--text-light);
        text-decoration: line-through;
    }
    
    .service-card .current-price {
        font-size: 1.2rem;
        font-weight: 700;
        color: #6f42c1;
    }
    
    .service-card .btn-service {
        width: 100%;
        background: #6f42c1;
        color: #fff;
        border: none;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        margin-top: auto;
        text-decoration: none;
        text-align: center;
        display: block;
    }
    
    .service-card .btn-service:hover {
        background: #5a32a3;
        color: #fff;
    }
    
    .no-services {
        text-align: center;
        padding: 60px 20px;
    }
    
    .no-services i {
        font-size: 4rem;
        color: var(--text-light);
        opacity: 0.5;
    }
    
    .pagination .page-link {
        color: #6f42c1;
    }
    
    .pagination .page-item.active .page-link {
        background: #6f42c1;
        border-color: #6f42c1;
    }
</style>

<div class="shop-page">
    <div class="container">
        <div class="shop-header">
            <h1 class="shop-title">
                <i class="bi bi-briefcase me-2"></i>ডিজিটাল সার্ভিস
            </h1>
            <p class="text-muted">আমাদের প্রফেশনাল ডিজিটাল সার্ভিসসমূহ</p>
        </div>
        
        <!-- Search Filter -->
        <div class="shop-filters">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="সার্ভিস খুঁজুন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn w-100" style="background: #6f42c1; color: #fff;">
                        <i class="bi bi-search me-1"></i>খুঁজুন
                    </button>
                </div>
                <?php if ($search): ?>
                <div class="col-md-2">
                    <a href="<?php echo baseUrl('digital-services'); ?>" class="btn btn-secondary w-100">রিসেট</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($services)): ?>
        <div class="no-services">
            <i class="bi bi-briefcase"></i>
            <h3 class="mt-3">কোন সার্ভিস পাওয়া যায়নি</h3>
            <p class="text-muted">শীঘ্রই নতুন সার্ভিস যোগ করা হবে।</p>
        </div>
        <?php else: ?>
        
        <div class="row g-4">
            <?php foreach ($services as $service): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="service-card">
                    <a href="<?php echo baseUrl('service/' . $service['slug']); ?>" class="service-image">
                        <?php if ($service['image']): ?>
                        <img src="<?php echo $service['image']; ?>" alt="<?php echo sanitize($service['name']); ?>">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-briefcase" style="font-size: 3rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                        <span class="service-badge">সার্ভিস</span>
                    </a>
                    <div class="service-info">
                        <a href="<?php echo baseUrl('service/' . $service['slug']); ?>" class="service-title">
                            <?php echo sanitize($service['name']); ?>
                        </a>
                        <?php if ($service['short_description']): ?>
                        <p class="service-desc"><?php echo sanitize($service['short_description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="service-meta">
                            <?php if ($service['duration']): ?>
                            <span><i class="bi bi-clock me-1"></i><?php echo sanitize($service['duration']); ?></span>
                            <?php endif; ?>
                            <?php if ($service['delivery_time']): ?>
                            <span><i class="bi bi-truck me-1"></i><?php echo sanitize($service['delivery_time']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="service-price">
                            <?php if ($service['sale_price'] && $service['sale_price'] < $service['regular_price']): ?>
                            <span class="regular-price"><?php echo formatPrice($service['regular_price']); ?></span>
                            <?php endif; ?>
                            <span class="current-price"><?php echo formatPrice($service['sale_price'] ?: $service['regular_price']); ?></span>
                        </div>
                        
                        <a href="<?php echo baseUrl('service/' . $service['slug']); ?>" class="btn-service">
                            <i class="bi bi-eye me-2"></i>বিস্তারিত দেখুন
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
