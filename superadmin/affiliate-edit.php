<?php
/**
 * Kilagbo Admin - Edit Affiliate Product
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-products';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট পণ্য এডিট';

$productId = intval($_GET['id'] ?? 0);

if (!$productId) {
    redirect(adminUrl('affiliate-products.php'));
}

// Get product
$stmt = $pdo->prepare("SELECT * FROM affiliate_products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'পণ্য পাওয়া যায়নি');
    redirect(adminUrl('affiliate-products.php'));
}

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
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Handle image upload
    $image = $product['image'];
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
            UPDATE affiliate_products SET 
                name = ?, description = ?, image = ?, category_id = ?, 
                regular_price = ?, sale_price = ?, commission_type = ?, 
                commission_value = ?, stock_quantity = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $description, $image, $categoryId ?: null,
            $regularPrice, $salePrice ?: null, $commissionType, 
            $commissionValue, $stockQuantity, $status, $productId
        ]);
        
        setFlash('success', 'পণ্য আপডেট হয়েছে');
        redirect(adminUrl('affiliate-products.php'));
    }
}

// Get categories
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-pencil me-2"></i>অ্যাফিলিয়েট পণ্য এডিট</h4>
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
                            <input type="text" class="form-control" name="name" required value="<?php echo sanitize($product['name']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">বিবরণ</label>
                            <textarea class="form-control" name="description" rows="5"><?php echo sanitize($product['description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">রেগুলার মূল্য (৳) *</label>
                                    <input type="number" class="form-control" name="regular_price" step="0.01" min="0" required value="<?php echo $product['regular_price']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">সেল মূল্য (৳)</label>
                                    <input type="number" class="form-control" name="sale_price" step="0.01" min="0" value="<?php echo $product['sale_price']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">স্টক পরিমাণ <small class="text-muted">(ফাঁকা = আনলিমিটেড)</small></label>
                                    <input type="number" class="form-control" name="stock_quantity" min="0" value="<?php echo $product['stock_quantity'] !== null ? $product['stock_quantity'] : ''; ?>" placeholder="ফাঁকা রাখলে আনলিমিটেড">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ক্যাটেগরি</label>
                                    <select class="form-select" name="category_id">
                                        <option value="">সিলেক্ট করুন</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
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
                                <option value="percentage" <?php echo $product['commission_type'] === 'percentage' ? 'selected' : ''; ?>>শতাংশ (%)</option>
                                <option value="fixed" <?php echo $product['commission_type'] === 'fixed' ? 'selected' : ''; ?>>নির্দিষ্ট টাকা (৳)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">কমিশন পরিমাণ</label>
                            <input type="number" class="form-control" name="commission_value" step="0.01" min="0" value="<?php echo $product['commission_value']; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">পণ্যের ছবি</div>
                    <div class="card-body">
                        <?php if ($product['image']): ?>
                        <div class="mb-3 text-center">
                            <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="max-width: 150px; border-radius: 8px;">
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <small class="text-muted">নতুন ছবি আপলোড করলে পুরাতনটি প্রতিস্থাপিত হবে</small>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="status" id="status" <?php echo $product['status'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">সক্রিয়</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-circle me-1"></i>আপডেট করুন
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
