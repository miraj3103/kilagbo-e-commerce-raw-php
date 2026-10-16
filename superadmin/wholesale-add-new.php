<?php
/**
 * Kilagbo - Add New Wholesale-Only Product
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'নতুন পাইকারি পণ্য যোগ করুন';
$currentPage = 'wholesale-products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = generateSlug($name);
    $description = $_POST['description'] ?? '';
    $shortDescription = sanitize($_POST['short_description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $regularPrice = (float)($_POST['regular_price'] ?? 0);
    $salePrice = (float)($_POST['sale_price'] ?? 0);
    $wholesalePrice = (float)($_POST['wholesale_price'] ?? 0);
    $minOrderQty = (int)($_POST['min_order_qty'] ?? 1);
    $stock = (int)($_POST['stock'] ?? 0);
    $sku = sanitize($_POST['sku'] ?? '');
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    
    // Validate
    if (empty($name) || $wholesalePrice <= 0) {
        setFlash('error', 'পণ্যের নাম এবং পাইকারি দাম আবশ্যক');
        redirect(adminUrl('wholesale-add-new'));
    }
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = UPLOADS_PATH . '/products/';
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $image = 'products/' . $fileName;
        }
    }
    
    // Handle gallery images
    $gallery = [];
    if (isset($_FILES['gallery'])) {
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = time() . '_' . $key . '_' . basename($_FILES['gallery']['name'][$key]);
                $targetPath = UPLOADS_PATH . '/products/' . $fileName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $gallery[] = 'products/' . $fileName;
                }
            }
        }
    }
    
    // Make slug unique
    $checkSlug = $pdo->prepare("SELECT id FROM wholesale_products WHERE slug = ?");
    $checkSlug->execute([$slug]);
    if ($checkSlug->fetch()) {
        $slug .= '-' . time();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO wholesale_products 
        (name, slug, description, short_description, category_id, image, gallery, video_url, 
         regular_price, sale_price, wholesale_price, min_order_qty, stock, sku, is_wholesale_only, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
    ");
    $stmt->execute([
        $name, $slug, $description, $shortDescription, $categoryId ?: null, 
        $image, json_encode($gallery), $videoUrl,
        $regularPrice, $salePrice ?: null, $wholesalePrice, $minOrderQty, $stock, $sku
    ]);
    
    setFlash('success', 'পণ্য সফলভাবে যোগ করা হয়েছে');
    redirect(adminUrl('wholesale-products'));
}

// Get categories
$categories = $pdo->query("SELECT id, name, parent_id FROM categories WHERE status = 1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="admin-card">
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label">পণ্যের নাম *</label>
                    <input type="text" name="name" class="form-control" required placeholder="পণ্যের নাম লিখুন">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সংক্ষিপ্ত বিবরণ</label>
                    <textarea name="short_description" class="form-control" rows="2" placeholder="সংক্ষিপ্ত বিবরণ"></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">বিস্তারিত বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6" placeholder="বিস্তারিত বিবরণ"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">রেগুলার দাম</label>
                        <input type="number" name="regular_price" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">সেল দাম</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">পাইকারি দাম *</label>
                        <input type="number" name="wholesale_price" class="form-control" step="0.01" required placeholder="0.00">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">ন্যূনতম অর্ডার পরিমাণ *</label>
                        <input type="number" name="min_order_qty" class="form-control" min="1" value="10" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">স্টক</label>
                        <input type="number" name="stock" class="form-control" value="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" placeholder="SKU কোড">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ভিডিও URL</label>
                    <input type="url" name="video_url" class="form-control" placeholder="YouTube বা অন্য ভিডিও লিংক">
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label">ক্যাটেগরি</label>
                    <select name="category_id" class="form-select">
                        <option value="">ক্যাটেগরি নির্বাচন করুন</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">প্রধান ছবি</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small class="text-muted">সর্বোচ্চ 2MB, JPG/PNG</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">গ্যালারি ছবি</label>
                    <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                    <small class="text-muted">একাধিক ছবি সিলেক্ট করতে পারেন</small>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    এই পণ্য শুধুমাত্র পাইকারি বিভাগে দেখাবে, রিটেইলে দেখাবে না।
                </div>
            </div>
        </div>
        
        <hr>
        
        <div class="d-flex gap-2">
            <a href="<?php echo adminUrl('wholesale-products'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>বাতিল
            </a>
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-check-lg me-2"></i>পণ্য যোগ করুন
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
