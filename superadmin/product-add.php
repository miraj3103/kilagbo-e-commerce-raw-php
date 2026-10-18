<?php
/**
 * Kilagbo - Add Product
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'নতুন পণ্য যোগ করুন';
$currentPage = 'products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store raw name; escape only on output to avoid HTML entities in DB
    $name = trim($_POST['name'] ?? '');
    $slug = generateSlug($name) ?: 'product-' . time();
    $description = $_POST['description'] ?? '';
    $regularPrice = floatval($_POST['regular_price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $stockQuantity = ($_POST['stock_quantity'] !== '' && $_POST['stock_quantity'] !== null) ? intval($_POST['stock_quantity']) : null;
    $status = isset($_POST['status']) ? 1 : 0;
    $isHotDeal = isset($_POST['is_hot_deal']) ? 1 : 0;
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;
    
    // Make slug unique
    $existingSlug = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
    $existingSlug->execute([$slug]);
    if ($existingSlug->fetchColumn() > 0) {
        $slug .= '-' . time();
    }
    
    // Handle main image
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'products');
        if ($upload['success']) {
            $image = uploadUrl($upload['filename']);
        }
    } elseif (!empty($_POST['image_url'])) {
        $image = sanitize($_POST['image_url']);
    }
    
    // Handle gallery images
    $gallery = [];
    if (!empty($_POST['gallery_urls'])) {
        $galleryUrls = array_filter(array_map('trim', explode("\n", $_POST['gallery_urls'])));
        foreach ($galleryUrls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
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
                $upload = uploadImage($file, 'products');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = !empty($gallery) ? json_encode($gallery) : null;
    
    // Insert product
    $stmt = $pdo->prepare("
        INSERT INTO products (name, slug, description, regular_price, sale_price, image, gallery, video_url, category_id, is_hot_deal, has_variations, stock_quantity, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $slug, $description, $regularPrice, $salePrice, $image, $galleryJson, $videoUrl, $categoryId, $isHotDeal, $hasVariations, $stockQuantity, $status]);
    $productId = $pdo->lastInsertId();
    
    // Handle variations
    if ($hasVariations && !empty($_POST['variations'])) {
        $variations = $_POST['variations'];
        foreach ($variations['attribute_name'] as $key => $attrName) {
            if (!empty($attrName) && !empty($variations['attribute_value'][$key])) {
                $attrValues = array_map('trim', explode(',', $variations['attribute_value'][$key]));
                foreach ($attrValues as $value) {
                    $stmt = $pdo->prepare("INSERT INTO product_variations (product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                    $stmt->execute([$productId, sanitize($attrName), sanitize($value)]);
                }
            }
        }
    }
    
    setFlash('success', 'পণ্য সফলভাবে যোগ করা হয়েছে');
    redirect(adminUrl('products'));
}

// Get categories
$categories = $pdo->query("
    SELECT c.*, p.name as parent_name 
    FROM categories c 
    LEFT JOIN categories p ON c.parent_id = p.id 
    ORDER BY c.parent_id IS NULL DESC, c.name
")->fetchAll();

require_once 'includes/header.php';
?>

<style>
    .variation-row {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }
</style>

<div class="admin-card">
    <h5 class="card-title">পণ্যের তথ্য</h5>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label">পণ্যের নাম *</label>
                    <input type="text" name="name" class="form-control" placeholder="পণ্যের নাম লিখুন" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6" placeholder="পণ্যের বিবরণ লিখুন"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">রেগুলার দাম (৳) *</label>
                        <input type="number" name="regular_price" class="form-control" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">সেল দাম (৳)</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">পণ্যের ছবি *</label>
                    <input type="file" name="image" id="mainImageInput" class="form-control mb-2" accept="image/*">
                    <div id="mainImagePreview" class="image-preview-container"></div>
                    <input type="url" name="image_url" class="form-control" placeholder="অথবা ইমেজ URL দিন">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">গ্যালারি ছবি</label>
                    <input type="file" name="gallery[]" id="galleryInput" class="form-control mb-2" accept="image/*" multiple>
                    <div id="galleryPreview" class="image-preview-container"></div>
                    <textarea name="gallery_urls" class="form-control" rows="3" placeholder="অথবা প্রতি লাইনে একটি করে ইমেজ URL দিন"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">পণ্যের ভিডিও (YouTube লিংক)</label>
                    <input type="url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                </div>
                
                <!-- Variations -->
                <div class="mb-4">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="has_variations" class="form-check-input" id="hasVariations" onchange="toggleVariations()">
                        <label class="form-check-label" for="hasVariations">এই পণ্যে ভেরিয়েশন আছে (সাইজ, রঙ ইত্যাদি)</label>
                    </div>
                    
                    <div id="variationsContainer" style="display: none;">
                        <div id="variationsList">
                            <div class="variation-row">
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <input type="text" name="variations[attribute_name][]" class="form-control" placeholder="অ্যাট্রিবিউট নাম (যেমন: সাইজ)">
                                    </div>
                                    <div class="col-md-7 mb-2">
                                        <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="মান গুলো কমা দিয়ে (যেমন: S, M, L, XL)">
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-admin-outline btn-sm" onclick="addVariation()">
                            <i class="bi bi-plus me-1"></i>আরও ভেরিয়েশন যোগ করুন
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="admin-card" style="background: #f8f9fa;">
                    <h6 class="mb-3">প্রকাশ</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">ক্যাটেগরি</label>
                        <select name="category_id" class="form-select">
                            <option value="">ক্যাটেগরি নির্বাচন করুন</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo $cat['parent_name'] ? '— ' : ''; ?><?php echo sanitize($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">স্টক পরিমাণ</label>
                        <input type="number" name="stock_quantity" class="form-control" placeholder="খালি রাখলে আনলিমিটেড">
                        <small class="text-muted">খালি রাখলে আনলিমিটেড স্টক, ০ দিলে স্টক আউট</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_hot_deal" class="form-check-input" id="isHotDeal">
                            <label class="form-check-label" for="isHotDeal">হট ডিলে যোগ করুন</label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="status" class="form-check-input" id="status" checked>
                            <label class="form-check-label" for="status">প্রকাশ করুন</label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-admin">
                            <i class="bi bi-check-circle me-2"></i>পণ্য যোগ করুন
                        </button>
                        <a href="<?php echo adminUrl('products'); ?>" class="btn btn-secondary">বাতিল</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function toggleVariations() {
        const container = document.getElementById('variationsContainer');
        const checkbox = document.getElementById('hasVariations');
        container.style.display = checkbox.checked ? 'block' : 'none';
    }
    
    function addVariation() {
        const list = document.getElementById('variationsList');
        const html = `
            <div class="variation-row">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" name="variations[attribute_name][]" class="form-control" placeholder="অ্যাট্রিবিউট নাম (যেমন: রঙ)">
                    </div>
                    <div class="col-md-7 mb-2">
                        <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="মান গুলো কমা দিয়ে (যেমন: লাল, নীল, সবুজ)">
                    </div>
                    <div class="col-md-1 mb-2">
                        <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        list.insertAdjacentHTML('beforeend', html);
    }
    
    function removeVariation(btn) {
        btn.closest('.variation-row').remove();
    }
</script>

<?php require_once 'includes/footer.php'; ?>
