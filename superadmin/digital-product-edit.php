<?php
/**
 * Kilagbo - Edit Digital Product
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'ডিজিটাল প্রোডাক্ট সম্পাদনা';
$currentPage = 'digital-products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

$productId = intval($_GET['id'] ?? 0);
if (!$productId) {
    redirect(adminUrl('digital-products'));
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM digital_products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'প্রোডাক্ট পাওয়া যায়নি');
    redirect(adminUrl('digital-products'));
}

// Fetch variations
$varStmt = $pdo->prepare("SELECT * FROM digital_product_variations WHERE product_id = ?");
$varStmt->execute([$productId]);
$variations = $varStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = generateSlug($name) ?: 'digital-' . time();
    $description = $_POST['description'] ?? '';
    $shortDescription = sanitize($_POST['short_description'] ?? '');
    $regularPrice = floatval($_POST['regular_price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $fileUrl = sanitize($_POST['file_url'] ?? '');
    $fileType = sanitize($_POST['file_type'] ?? '');
    $fileSize = sanitize($_POST['file_size'] ?? '');
    $accessType = sanitize($_POST['access_type'] ?? 'download');
    $status = isset($_POST['status']) ? 1 : 0;
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;
    
    // Make slug unique (exclude current)
    $existingSlug = $pdo->prepare("SELECT COUNT(*) FROM digital_products WHERE slug = ? AND id != ?");
    $existingSlug->execute([$slug, $productId]);
    if ($existingSlug->fetchColumn() > 0) {
        $slug .= '-' . time();
    }
    
    // Handle main image
    $image = $product['image'];
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'digital');
        if ($upload['success']) {
            $image = uploadUrl($upload['filename']);
        }
    } elseif (!empty($_POST['image_url'])) {
        $image = sanitize($_POST['image_url']);
    }
    
    // Handle gallery images
    $gallery = $product['gallery'] ? json_decode($product['gallery'], true) : [];
    if (!empty($_POST['gallery_urls'])) {
        $galleryUrls = array_filter(array_map('trim', explode("\n", $_POST['gallery_urls'])));
        foreach ($galleryUrls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) && !in_array($url, $gallery)) {
                $gallery[] = $url;
            }
        }
    }
    if (!empty($_FILES['gallery']['name'][0])) {
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp) {
            if ($_FILES['gallery']['error'][$key] === 0) {
                $file = [
                    'name' => $_FILES['gallery']['name'][$key],
                    'type' => $_FILES['gallery']['type'][$key],
                    'tmp_name' => $tmp,
                    'error' => $_FILES['gallery']['error'][$key],
                    'size' => $_FILES['gallery']['size'][$key]
                ];
                $upload = uploadImage($file, 'digital');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = !empty($gallery) ? json_encode($gallery) : null;
    
    // Update product
    $stmt = $pdo->prepare("
        UPDATE digital_products SET 
            name = ?, slug = ?, description = ?, short_description = ?, 
            regular_price = ?, sale_price = ?, image = ?, gallery = ?, 
            video_url = ?, category_id = ?, file_url = ?, file_type = ?, 
            file_size = ?, access_type = ?, has_variations = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $slug, $description, $shortDescription, $regularPrice, $salePrice, $image, $galleryJson, $videoUrl, $categoryId, $fileUrl, $fileType, $fileSize, $accessType, $hasVariations, $status, $productId]);
    
    // Handle variations
    $pdo->prepare("DELETE FROM digital_product_variations WHERE product_id = ?")->execute([$productId]);
    if ($hasVariations && !empty($_POST['variations'])) {
        $vars = $_POST['variations'];
        foreach ($vars['attribute_name'] as $key => $attrName) {
            if (!empty($attrName) && !empty($vars['attribute_value'][$key])) {
                // New logic: Single value per row to allow specific pricing
                $value = trim($vars['attribute_value'][$key]);
                $priceAdj = floatval($vars['price_adjustment'][$key] ?? 0);
                
                $stmt = $pdo->prepare("INSERT INTO digital_product_variations (product_id, attribute_name, attribute_value, price_adjustment) VALUES (?, ?, ?, ?)");
                $stmt->execute([$productId, sanitize($attrName), sanitize($value), $priceAdj]);
            }
        }
    }
    
    setFlash('success', 'ডিজিটাল প্রোডাক্ট সফলভাবে আপডেট করা হয়েছে');
    redirect(adminUrl('digital-products'));
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name")->fetchAll();
$gallery = $product['gallery'] ? json_decode($product['gallery'], true) : [];

// Don't group variations anymore - we want individual lines
$individualVariations = $variations;

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('products'); ?>">পণ্য</a></li>
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('digital-products'); ?>">ডিজিটাল প্রোডাক্ট</a></li>
            <li class="breadcrumb-item active">সম্পাদনা</li>
        </ol>
    </nav>
</div>

<?php showFlash(); ?>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-lg-8">
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>প্রোডাক্ট তথ্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">প্রোডাক্টের নাম <span class="text-danger">*</span></label>
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
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-file-earmark-arrow-down me-2"></i>ফাইল তথ্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">ডাউনলোড লিংক / ফাইল URL</label>
                    <input type="text" name="file_url" class="form-control" value="<?php echo sanitize($product['file_url']); ?>">
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">ফাইল টাইপ</label>
                        <select name="file_type" class="form-select">
                            <option value="">নির্বাচন করুন</option>
                            <?php 
                            $fileTypes = ['PDF', 'ZIP', 'RAR', 'MP3', 'MP4', 'PSD', 'AI', 'EPS', 'DOC', 'XLS', 'PPT', 'APK', 'Software', 'Course', 'Other'];
                            foreach ($fileTypes as $ft): ?>
                            <option value="<?php echo $ft; ?>" <?php echo $product['file_type'] == $ft ? 'selected' : ''; ?>><?php echo $ft; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">ফাইল সাইজ</label>
                        <input type="text" name="file_size" class="form-control" value="<?php echo sanitize($product['file_size']); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">অ্যাক্সেস টাইপ</label>
                        <select name="access_type" class="form-select">
                            <option value="download" <?php echo $product['access_type'] == 'download' ? 'selected' : ''; ?>>ডাউনলোড</option>
                            <option value="streaming" <?php echo $product['access_type'] == 'streaming' ? 'selected' : ''; ?>>স্ট্রিমিং</option>
                            <option value="both" <?php echo $product['access_type'] == 'both' ? 'selected' : ''; ?>>উভয়</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-images me-2"></i>ছবি</h5>
                
                <div class="mb-3">
                    <label class="form-label">মূল ছবি</label>
                    <?php if ($product['image']): ?>
                    <div class="mb-2">
                        <img src="<?php echo $product['image']; ?>" alt="" style="max-height: 150px; border-radius: 8px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="mt-2">
                        <small class="text-muted">অথবা ছবির URL দিন:</small>
                        <input type="text" name="image_url" class="form-control mt-1" placeholder="https://example.com/image.jpg">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">গ্যালারি ছবি</label>
                    <?php if (!empty($gallery)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach ($gallery as $img): ?>
                        <img src="<?php echo $img; ?>" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ভিডিও URL</label>
                    <input type="text" name="video_url" class="form-control" value="<?php echo sanitize($product['video_url']); ?>">
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-list-check me-2"></i>ভ্যারিয়েশন</h5>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="has_variations" id="hasVariations" <?php echo $product['has_variations'] ? 'checked' : ''; ?> onchange="toggleVariations()">
                    <label class="form-check-label" for="hasVariations">ভ্যারিয়েশন আছে</label>
                </div>
                
                <div id="variationsSection" style="display: <?php echo $product['has_variations'] ? 'block' : 'none'; ?>;">
                    <div id="variationsList">
                        <?php if (!empty($individualVariations)): ?>
                        <?php foreach ($individualVariations as $var): ?>
                        <div class="variation-item border rounded p-3 mb-2">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">অ্যাট্রিবিউট নাম</label>
                                    <input type="text" name="variations[attribute_name][]" class="form-control" value="<?php echo sanitize($var['attribute_name']); ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">ভ্যালু</label>
                                    <input type="text" name="variations[attribute_value][]" class="form-control" value="<?php echo sanitize($var['attribute_value']); ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label small">অতিরিক্ত মূল্য</label>
                                    <input type="number" name="variations[price_adjustment][]" class="form-control" value="<?php echo $var['price_adjustment']; ?>">
                                </div>
                                <div class="col-md-1 mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.variation-item').remove()">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="variation-item border rounded p-3 mb-2">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <input type="text" name="variations[attribute_name][]" class="form-control" placeholder="অ্যাট্রিবিউট">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">ভ্যালু</label>
                                    <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="যেমন: পার্সোনাল">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label small">অতিরিক্ত মূল্য</label>
                                    <input type="number" name="variations[price_adjustment][]" class="form-control" placeholder="0">
                                </div>
                                <div class="col-md-1 mb-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.variation-item').remove()">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVariation()">
                        <i class="bi bi-plus me-1"></i>আরো যোগ করুন
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-currency-dollar me-2"></i>মূল্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">নিয়মিত মূল্য (৳) <span class="text-danger">*</span></label>
                    <input type="number" name="regular_price" class="form-control" step="0.01" required value="<?php echo $product['regular_price']; ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">অফার মূল্য (৳)</label>
                    <input type="number" name="sale_price" class="form-control" step="0.01" value="<?php echo $product['sale_price']; ?>">
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-folder me-2"></i>ক্যাটেগরি</h5>
                
                <div class="mb-3">
                    <select name="category_id" class="form-select">
                        <option value="">-- ক্যাটেগরি নির্বাচন করুন --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-gear me-2"></i>স্ট্যাটাস</h5>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="status" id="status" <?php echo $product['status'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="status">সক্রিয়</label>
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-admin btn-lg">
                    <i class="bi bi-check-circle me-2"></i>আপডেট করুন
                </button>
                <a href="<?php echo adminUrl('digital-products'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>বাতিল করুন
                </a>
            </div>
        </div>
    </div>
</form>

<script>
function toggleVariations() {
    const section = document.getElementById('variationsSection');
    const checkbox = document.getElementById('hasVariations');
    section.style.display = checkbox.checked ? 'block' : 'none';
}

function addVariation() {
    const list = document.getElementById('variationsList');
    const item = document.createElement('div');
    item.className = 'variation-item border rounded p-3 mb-2';
    item.innerHTML = `
        <div class="row">
            <div class="col-md-4 mb-2">
                <input type="text" name="variations[attribute_name][]" class="form-control" placeholder="অ্যাট্রিবিউট">
            </div>
            <div class="col-md-4 mb-2">
                <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="ভ্যালু">
            </div>
            <div class="col-md-3 mb-2">
                <input type="number" name="variations[price_adjustment][]" class="form-control" placeholder="অতিরিক্ত মূল্য">
            </div>
            <div class="col-md-1 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.variation-item').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    list.appendChild(item);
}
</script>

<?php require_once 'includes/footer.php'; ?>
