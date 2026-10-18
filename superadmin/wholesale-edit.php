<?php
/**
 * Kilagbo - Edit Wholesale Product
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পাইকারি পণ্য সম্পাদনা';
$currentPage = 'wholesale-products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(adminUrl('wholesale-products'));
}

// Get product
$stmt = $pdo->prepare("SELECT * FROM wholesale_products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'পণ্য পাওয়া যায়নি');
    redirect(adminUrl('wholesale-products'));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
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
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Validate
    if (empty($name) || $wholesalePrice <= 0) {
        setFlash('error', 'পণ্যের নাম এবং পাইকারি দাম আবশ্যক');
        redirect(adminUrl('wholesale-edit/' . $id));
    }
    
    // Handle image upload
    $image = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = UPLOADS_PATH . '/products/';
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $image = 'products/' . $fileName;
        }
    }
    
    // Handle gallery images
    $gallery = json_decode($product['gallery'] ?? '[]', true) ?: [];
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
    
    $stmt = $pdo->prepare("
        UPDATE wholesale_products SET
        name = ?, description = ?, short_description = ?, category_id = ?, image = ?, gallery = ?, video_url = ?,
        regular_price = ?, sale_price = ?, wholesale_price = ?, min_order_qty = ?, stock = ?, sku = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name, $description, $shortDescription, $categoryId ?: null, 
        $image, json_encode($gallery), $videoUrl,
        $regularPrice, $salePrice ?: null, $wholesalePrice, $minOrderQty, $stock, $sku, $status, $id
    ]);
    
    setFlash('success', 'পণ্য সফলভাবে আপডেট হয়েছে');
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
                    <input type="text" name="name" class="form-control" required value="<?php echo sanitize($product['name']); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সংক্ষিপ্ত বিবরণ</label>
                    <textarea name="short_description" class="form-control" rows="2"><?php echo sanitize($product['short_description']); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">বিস্তারিত বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6"><?php echo $product['description']; ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">রেগুলার দাম</label>
                        <input type="number" name="regular_price" class="form-control" step="0.01" value="<?php echo $product['regular_price']; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">সেল দাম</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" value="<?php echo $product['sale_price']; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">পাইকারি দাম *</label>
                        <input type="number" name="wholesale_price" class="form-control" step="0.01" required value="<?php echo $product['wholesale_price']; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">ন্যূনতম অর্ডার পরিমাণ *</label>
                        <input type="number" name="min_order_qty" class="form-control" min="1" required value="<?php echo $product['min_order_qty']; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">স্টক</label>
                        <input type="number" name="stock" class="form-control" value="<?php echo $product['stock']; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" value="<?php echo sanitize($product['sku']); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ভিডিও URL</label>
                    <input type="url" name="video_url" class="form-control" value="<?php echo sanitize($product['video_url']); ?>">
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label">ক্যাটেগরি</label>
                    <select name="category_id" class="form-select">
                        <option value="">ক্যাটেগরি নির্বাচন করুন</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">প্রধান ছবি</label>
                    <?php if ($product['image']): ?>
                    <div class="mb-2">
                        <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="width: 100%; max-width: 200px; border-radius: 8px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">গ্যালারি ছবি</label>
                    <?php 
                    $gallery = json_decode($product['gallery'] ?? '[]', true) ?: [];
                    if (!empty($gallery)): 
                    ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach ($gallery as $img): ?>
                        <img src="<?php echo imageUrl($img); ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                </div>
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?php echo $product['status'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="status">সক্রিয়</label>
                    </div>
                </div>
                
                <?php if ($product['is_wholesale_only']): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    এটি শুধুমাত্র পাইকারি পণ্য।
                </div>
                <?php else: ?>
                <div class="alert alert-secondary">
                    <i class="bi bi-link-45deg me-2"></i>
                    রিটেইল পণ্য থেকে লিংকড।
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <hr>
        
        <div class="d-flex gap-2">
            <a href="<?php echo adminUrl('wholesale-products'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>বাতিল
            </a>
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-check-lg me-2"></i>আপডেট করুন
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
