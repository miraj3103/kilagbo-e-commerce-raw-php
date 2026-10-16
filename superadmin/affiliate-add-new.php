<?php
/**
 * Kilagbo Admin - Add New Standalone Affiliate Product
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-products';
require_once 'includes/header.php';

$pageTitle = 'নতুন অ্যাফিলিয়েট পণ্য যোগ করুন';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $regularPrice = floatval($_POST['regular_price']);
    $salePrice = floatval($_POST['sale_price'] ?? 0);
    $stockQuantity = (isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '') ? intval($_POST['stock_quantity']) : null;
    $commissionType = sanitize($_POST['commission_type']);
    $commissionValue = floatval($_POST['commission_value']);
    
    // Generate slug
    $slug = 'aff-' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . time();
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $fileName = 'aff-' . time() . '-' . rand(1000, 9999) . '.' . $ext;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
            $image = 'products/' . $fileName;
        }
    }
    
    if (empty($name) || $regularPrice <= 0) {
        setFlash('error', 'নাম এবং মূল্য আবশ্যক');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_products (name, slug, description, image, category_id, regular_price, sale_price, commission_type, commission_value, stock_quantity, is_standalone, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
        ");
        $stmt->execute([
            $name, $slug, $description, $image, $categoryId ?: null,
            $regularPrice, $salePrice ?: null, $commissionType, $commissionValue, $stockQuantity
        ]);
        
        setFlash('success', 'পণ্য সফলভাবে যোগ হয়েছে');
        redirect(adminUrl('affiliate-products.php'));
    }
}

// Get categories
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name")->fetchAll();

$defaultCommissionType = getSettings('affiliate_default_commission_type') ?: 'percentage';
$defaultCommissionValue = getSettings('affiliate_default_commission_value') ?: 5;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-plus-circle me-2"></i>নতুন অ্যাফিলিয়েট পণ্য যোগ করুন</h4>
        <a href="<?php echo adminUrl('affiliate-products.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>পণ্য তালিকা
        </a>
    </div>
    
    <?php showFlash(); ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">পণ্যের তথ্য</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">পণ্যের নাম *</label>
                            <input type="text" class="form-control" name="name" required value="<?php echo $_POST['name'] ?? ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">বিবরণ</label>
                            <textarea class="form-control" name="description" rows="5"><?php echo $_POST['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">রেগুলার মূল্য (৳) *</label>
                                    <input type="number" class="form-control" name="regular_price" step="0.01" min="0" required value="<?php echo $_POST['regular_price'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">সেল মূল্য (৳)</label>
                                    <input type="number" class="form-control" name="sale_price" step="0.01" min="0" value="<?php echo $_POST['sale_price'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">স্টক পরিমাণ <small class="text-muted">(ফাঁকা = আনলিমিটেড)</small></label>
                                    <input type="number" class="form-control" name="stock_quantity" min="0" value="<?php echo $_POST['stock_quantity'] ?? ''; ?>" placeholder="ফাঁকা রাখলে আনলিমিটেড">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ক্যাটেগরি</label>
                                    <select class="form-select" name="category_id">
                                        <option value="">সিলেক্ট করুন</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">কমিশন সেটিং</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">কমিশন টাইপ</label>
                            <select class="form-select" name="commission_type">
                                <option value="percentage" <?php echo $defaultCommissionType === 'percentage' ? 'selected' : ''; ?>>শতাংশ (%)</option>
                                <option value="fixed" <?php echo $defaultCommissionType === 'fixed' ? 'selected' : ''; ?>>নির্দিষ্ট টাকা (৳)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">কমিশন পরিমাণ</label>
                            <input type="number" class="form-control" name="commission_value" step="0.01" min="0" value="<?php echo $defaultCommissionValue; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">পণ্যের ছবি</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <small class="text-muted">সর্বোচ্চ 2MB, JPG/PNG</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-circle me-1"></i>পণ্য যোগ করুন
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
