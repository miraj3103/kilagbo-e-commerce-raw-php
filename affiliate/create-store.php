<?php
/**
 * Kilagbo Affiliate Portal - Create Store Page
 * Allows affiliators to generate shop URLs for retail and wholesale products
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'স্টোর তৈরি';
$currentPage = 'create-store';
$affiliatorId = getAffiliatorId();
$affiliator = getAffiliatorData();

// Check if store codes exist, if not generate them
$retailStoreCode = null;
$wholesaleStoreCode = null;

// Check for existing store codes in affiliate_stores table
try {
    $stmt = $pdo->prepare("SELECT * FROM affiliate_stores WHERE affiliator_id = ?");
    $stmt->execute([$affiliatorId]);
    $stores = $stmt->fetchAll();
    
    foreach ($stores as $store) {
        if ($store['store_type'] === 'retail') {
            $retailStoreCode = $store['store_code'];
        } elseif ($store['store_type'] === 'wholesale') {
            $wholesaleStoreCode = $store['store_code'];
        }
    }
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS affiliate_stores (
            id INT(11) NOT NULL AUTO_INCREMENT,
            affiliator_id INT(11) NOT NULL,
            store_type ENUM('retail', 'wholesale') NOT NULL,
            store_code VARCHAR(20) NOT NULL UNIQUE,
            store_name VARCHAR(255) DEFAULT NULL,
            clicks INT(11) DEFAULT 0,
            orders INT(11) DEFAULT 0,
            total_earned DECIMAL(10,2) DEFAULT 0.00,
            status TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliator_id (affiliator_id),
            KEY store_code (store_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Handle store generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeType = sanitize($_POST['store_type'] ?? '');
    $storeName = sanitize($_POST['store_name'] ?? '');
    
    if (in_array($storeType, ['retail', 'wholesale'])) {
        // Check if already exists
        $stmt = $pdo->prepare("SELECT id FROM affiliate_stores WHERE affiliator_id = ? AND store_type = ?");
        $stmt->execute([$affiliatorId, $storeType]);
        
        if ($stmt->fetch()) {
            setFlash('info', 'এই ধরনের স্টোর আগেই তৈরি হয়েছে');
        } else {
            // Generate unique store code
            $storeCode = strtoupper(substr(md5($affiliatorId . $storeType . time()), 0, 8));
            
            $stmt = $pdo->prepare("INSERT INTO affiliate_stores (affiliator_id, store_type, store_code, store_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$affiliatorId, $storeType, $storeCode, $storeName ?: $affiliator['name'] . "'s Store"]);
            
            setFlash('success', 'স্টোর সফলভাবে তৈরি হয়েছে!');
        }
        
        redirect(baseUrl('affiliate/create-store.php'));
    }
}

// Refresh store codes after potential creation
try {
    $stmt = $pdo->prepare("SELECT * FROM affiliate_stores WHERE affiliator_id = ?");
    $stmt->execute([$affiliatorId]);
    $stores = $stmt->fetchAll();
    
    foreach ($stores as $store) {
        if ($store['store_type'] === 'retail') {
            $retailStore = $store;
            $retailStoreCode = $store['store_code'];
        } elseif ($store['store_type'] === 'wholesale') {
            $wholesaleStore = $store;
            $wholesaleStoreCode = $store['store_code'];
        }
    }
} catch (PDOException $e) {
    // Ignore
}

// Count products
$retailProductCount = 0;
$wholesaleProductCount = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM affiliate_products WHERE status = 1 AND retail_product_id IS NOT NULL AND (wholesale_product_id IS NULL OR wholesale_product_id = 0)");
    $retailProductCount = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM affiliate_products WHERE status = 1 AND wholesale_product_id IS NOT NULL AND wholesale_product_id > 0");
    $wholesaleProductCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Ignore
}

include __DIR__ . '/includes/header.php';
?>

<style>
    .store-card {
        background: #fff;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        height: 100%;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }
    
    .store-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    .store-card.retail {
        border-color: #3b82f6;
    }
    
    .store-card.wholesale {
        border-color: #f59e0b;
    }
    
    .store-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin: 0 auto 20px;
    }
    
    .store-card.retail .store-icon {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: #fff;
    }
    
    .store-card.wholesale .store-icon {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #fff;
    }
    
    .store-title {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 10px;
        text-align: center;
    }
    
    .store-desc {
        color: #666;
        text-align: center;
        margin-bottom: 20px;
        font-size: 0.9rem;
    }
    
    .store-stats {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
    }
    
    .store-stat {
        text-align: center;
    }
    
    .store-stat .value {
        font-size: 1.3rem;
        font-weight: 700;
        color: #10b981;
    }
    
    .store-stat .label {
        font-size: 0.75rem;
        color: #666;
    }
    
    .store-link-box {
        background: #ecfdf5;
        border: 2px dashed #10b981;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .store-link-box .link-label {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 5px;
    }
    
    .store-link-box .link-url {
        font-size: 0.85rem;
        color: #10b981;
        word-break: break-all;
        font-weight: 600;
    }
    
    .btn-copy-link {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border: none;
        padding: 12px 25px;
        border-radius: 10px;
        font-weight: 600;
        width: 100%;
        transition: all 0.3s ease;
    }
    
    .btn-copy-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16,185,129,0.3);
        color: #fff;
    }
    
    .btn-create-store {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #fff;
        border: none;
        padding: 15px 30px;
        border-radius: 10px;
        font-weight: 600;
        width: 100%;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    .btn-create-store:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(99,102,241,0.3);
        color: #fff;
    }
    
    .product-count {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f3f4f6;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        margin-bottom: 20px;
    }
    
    .info-banner {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
    }
    
    .info-banner h4 {
        margin-bottom: 10px;
    }
    
    .info-banner p {
        margin: 0;
        opacity: 0.9;
    }
</style>

<?php showFlash(); ?>

<div class="info-banner">
    <h4><i class="bi bi-shop-window me-2"></i>আপনার অ্যাফিলিয়েট স্টোর</h4>
    <p>এখান থেকে আপনি রিটেইল এবং পাইকারি পণ্যের জন্য আলাদা স্টোর লিংক তৈরি করতে পারবেন। এই লিংক শেয়ার করলে কাস্টমাররা সব পণ্য একসাথে দেখতে পারবে এবং অর্ডার করলে আপনি কমিশন পাবেন।</p>
</div>

<div class="row g-4">
    <!-- Retail Store -->
    <div class="col-md-6">
        <div class="store-card retail">
            <div class="store-icon">
                <i class="bi bi-shop"></i>
            </div>
            <h5 class="store-title">রিটেইল স্টোর</h5>
            <p class="store-desc">রিটেইল সেকশনের সব পণ্য এক লিংকে শেয়ার করুন</p>
            
            <div class="text-center">
                <span class="product-count">
                    <i class="bi bi-box-seam"></i>
                    <?php echo $retailProductCount; ?> টি পণ্য
                </span>
            </div>
            
            <?php if ($retailStoreCode): ?>
            <div class="store-stats">
                <div class="store-stat">
                    <div class="value"><?php echo $retailStore['clicks'] ?? 0; ?></div>
                    <div class="label">ক্লিক</div>
                </div>
                <div class="store-stat">
                    <div class="value"><?php echo $retailStore['orders'] ?? 0; ?></div>
                    <div class="label">অর্ডার</div>
                </div>
                <div class="store-stat">
                    <div class="value"><?php echo formatPrice($retailStore['total_earned'] ?? 0); ?></div>
                    <div class="label">আয়</div>
                </div>
            </div>
            
            <div class="store-link-box">
                <div class="link-label">আপনার রিটেইল স্টোর লিংক:</div>
                <div class="link-url" id="retailLink"><?php echo baseUrl('store/' . $retailStoreCode); ?></div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-copy-link flex-grow-1" onclick="copyToClipboard('<?php echo baseUrl('store/' . $retailStoreCode); ?>', this)">
                    <i class="bi bi-clipboard me-1"></i>লিংক কপি
                </button>
                <a href="<?php echo baseUrl('store/' . $retailStoreCode); ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="store_type" value="retail">
                <input type="text" name="store_name" class="form-control mb-3" placeholder="স্টোরের নাম (ঐচ্ছিক)">
                <button type="submit" class="btn btn-create-store">
                    <i class="bi bi-plus-circle me-2"></i>রিটেইল স্টোর তৈরি করুন
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Wholesale Store -->
    <div class="col-md-6">
        <div class="store-card wholesale">
            <div class="store-icon">
                <i class="bi bi-boxes"></i>
            </div>
            <h5 class="store-title">পাইকারি স্টোর</h5>
            <p class="store-desc">পাইকারি সেকশনের সব পণ্য এক লিংকে শেয়ার করুন</p>
            
            <div class="text-align: center;">
                <span class="product-count">
                    <i class="bi bi-boxes"></i>
                    <?php echo $wholesaleProductCount; ?> টি পণ্য
                </span>
            </div>
            
            <?php if ($wholesaleStoreCode): ?>
            <div class="store-stats">
                <div class="store-stat">
                    <div class="value"><?php echo $wholesaleStore['clicks'] ?? 0; ?></div>
                    <div class="label">ক্লিক</div>
                </div>
                <div class="store-stat">
                    <div class="value"><?php echo $wholesaleStore['orders'] ?? 0; ?></div>
                    <div class="label">অর্ডার</div>
                </div>
                <div class="store-stat">
                    <div class="value"><?php echo formatPrice($wholesaleStore['total_earned'] ?? 0); ?></div>
                    <div class="label">আয়</div>
                </div>
            </div>
            
            <div class="store-link-box">
                <div class="link-label">আপনার পাইকারি স্টোর লিংক:</div>
                <div class="link-url" id="wholesaleLink"><?php echo baseUrl('store/' . $wholesaleStoreCode); ?></div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-copy-link flex-grow-1" onclick="copyToClipboard('<?php echo baseUrl('store/' . $wholesaleStoreCode); ?>', this)">
                    <i class="bi bi-clipboard me-1"></i>লিংক কপি
                </button>
                <a href="<?php echo baseUrl('store/' . $wholesaleStoreCode); ?>" target="_blank" class="btn btn-outline-warning">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="store_type" value="wholesale">
                <input type="text" name="store_name" class="form-control mb-3" placeholder="স্টোরের নাম (ঐচ্ছিক)">
                <button type="submit" class="btn btn-create-store">
                    <i class="bi bi-plus-circle me-2"></i>পাইকারি স্টোর তৈরি করুন
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mt-4 p-4 bg-light rounded-3">
    <h6><i class="bi bi-lightbulb me-2"></i>কিভাবে কাজ করে?</h6>
    <ol class="mb-0" style="padding-left: 20px;">
        <li>উপরে থেকে আপনার পছন্দের স্টোর তৈরি করুন</li>
        <li>তৈরি হওয়া লিংক কপি করে সোশ্যাল মিডিয়া বা অন্যত্র শেয়ার করুন</li>
        <li>কাস্টমার এই লিংকে ক্লিক করলে আপনার স্টোরের সব পণ্য দেখতে পাবে</li>
        <li>কাস্টমার অর্ডার করলে আপনার অ্যাকাউন্টে কমিশন যোগ হবে</li>
    </ol>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
