<?php
/**
 * Kilagbo - Digital Products Shop Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

$currentPage = 'digital-products';
$pageTitle = 'ডিজিটাল প্রোডাক্ট';

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
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM digital_products WHERE $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Fetch products
$stmt = $pdo->prepare("
    SELECT * FROM digital_products 
    WHERE $whereClause 
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

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
    
    .digital-card {
        background: var(--secondary-color);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .digital-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transform: translateY(-5px);
    }
    
    .digital-card .product-image {
        position: relative;
        overflow: hidden;
        aspect-ratio: 1;
        background: #f8f9fa;
    }
    
    .digital-card .product-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 15px;
        transition: transform 0.3s ease;
    }
    
    .digital-card:hover .product-image img {
        transform: scale(1.05);
    }
    
    .digital-card .product-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: #0d6efd;
        color: #fff;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .digital-card .file-type-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: rgba(0,0,0,0.7);
        color: #fff;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .digital-card .product-info {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    
    .digital-card .product-title {
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
        .digital-card .product-title {
            font-size: 0.9rem;
        }
        .digital-card .product-info {
            padding: 12px;
        }
        .digital-card .product-meta {
            font-size: 0.7rem;
            gap: 8px;
        }
        .digital-card .current-price {
            font-size: 1rem;
        }
        .digital-card .btn-buy {
            padding: 10px;
            font-size: 0.85rem;
        }
    }
    
    .digital-card .product-title:hover {
        color: #0d6efd;
    }
    
    .digital-card .product-meta {
        display: flex;
        gap: 15px;
        margin-bottom: 12px;
        font-size: 0.8rem;
        color: var(--text-light);
    }
    
    .digital-card .product-price {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .digital-card .sale-badge {
        position: absolute;
        bottom: 12px;
        left: 12px;
        background: #ff6600;
        color: #fff;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .digital-card .regular-price {
        font-size: 0.9rem;
        color: var(--text-light);
        text-decoration: line-through;
    }
    
    .digital-card .current-price {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0d6efd;
    }
    
    .digital-card .btn-buy {
        width: 100%;
        background: #0d6efd;
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
    
    .digital-card .btn-buy:hover {
        background: #0b5ed7;
        color: #fff;
    }
    
    .no-products {
        text-align: center;
        padding: 60px 20px;
    }
    
    .no-products i {
        font-size: 4rem;
        color: var(--text-light);
        opacity: 0.5;
    }
    
    .pagination .page-link {
        color: #0d6efd;
    }
    
    .pagination .page-item.active .page-link {
        background: #0d6efd;
        border-color: #0d6efd;
    }
</style>

<div class="shop-page">
    <div class="container">
        <div class="shop-header">
            <h1 class="shop-title">
                <i class="bi bi-file-earmark-code me-2"></i>ডিজিটাল প্রোডাক্ট
            </h1>
            <p class="text-muted">সফটওয়্যার, ইবুক, টেমপ্লেট এবং আরও অনেক কিছু</p>
        </div>
        
        <!-- Search Filter -->
        <div class="shop-filters">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="প্রোডাক্ট খুঁজুন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn w-100" style="background: #0d6efd; color: #fff;">
                        <i class="bi bi-search me-1"></i>খুঁজুন
                    </button>
                </div>
                <?php if ($search): ?>
                <div class="col-md-2">
                    <a href="<?php echo baseUrl('digital-products'); ?>" class="btn btn-secondary w-100">রিসেট</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($products)): ?>
        <div class="no-products">
            <i class="bi bi-file-earmark-code"></i>
            <h3 class="mt-3">কোন প্রোডাক্ট পাওয়া যায়নি</h3>
            <p class="text-muted">শীঘ্রই নতুন প্রোডাক্ট যোগ করা হবে।</p>
        </div>
        <?php else: ?>
        
        <div class="row g-4">
            <?php foreach ($products as $product): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="digital-card">
                    <a href="<?php echo baseUrl('digital-product/' . $product['slug']); ?>" class="product-image">
                        <?php if ($product['image']): ?>
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-file-earmark-code" style="font-size: 3rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        <?php endif; ?>
                        <span class="product-badge">ডিজিটাল</span>
                        <?php if ($product['file_type']): ?>
                        <span class="file-type-badge"><?php echo sanitize($product['file_type']); ?></span>
                        <?php endif; ?>
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                        <?php $discount = round((($product['regular_price'] - $product['sale_price']) / $product['regular_price']) * 100); ?>
                        <span class="sale-badge"><?php echo $discount; ?>% ছাড়</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <a href="<?php echo baseUrl('digital-product/' . $product['slug']); ?>" class="product-title">
                            <?php echo sanitize($product['name']); ?>
                        </a>
                        
                        <div class="product-meta">
                            <?php if ($product['file_size']): ?>
                            <span><i class="bi bi-hdd me-1"></i><?php echo sanitize($product['file_size']); ?></span>
                            <?php endif; ?>
                            <?php if ($product['access_type']): ?>
                            <span><i class="bi bi-download me-1"></i>
                                <?php 
                                $accessLabels = ['download' => 'ডাউনলোড', 'streaming' => 'স্ট্রিমিং', 'both' => 'উভয়'];
                                echo $accessLabels[$product['access_type']] ?? $product['access_type'];
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-price">
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
                            <span class="regular-price"><?php echo formatPrice($product['regular_price']); ?></span>
                            <?php endif; ?>
                            <span class="current-price"><?php echo formatPrice($product['sale_price'] ?: $product['regular_price']); ?></span>
                        </div>
                        
                        <a href="<?php echo baseUrl('digital-product/' . $product['slug']); ?>" class="btn-buy">
                            <i class="bi bi-bag me-2"></i>এখনই কিনুন
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
