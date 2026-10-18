<?php
/**
 * Kilagbo - Edit Product
 * Developed by Webnify Bangladesh
 */

$currentPage = 'products';

require_once '../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect(adminUrl('products'));
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'পণ্য পাওয়া যায়নি');
    redirect(adminUrl('products'));
}

$pageTitle = 'পণ্য এডিট: ' . $product['name'];

// Fetch variations
$variationsStmt = $pdo->prepare("SELECT DISTINCT attribute_name, GROUP_CONCAT(attribute_value) as values_list FROM product_variations WHERE product_id = ? GROUP BY attribute_name");
$variationsStmt->execute([$id]);
$variations = $variationsStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Keep raw name; escape only on output
    $name = trim($_POST['name'] ?? '');
    $description = $_POST['description'] ?? '';
    $regularPrice = floatval($_POST['regular_price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $stockQuantity = ($_POST['stock_quantity'] !== '' && $_POST['stock_quantity'] !== null) ? intval($_POST['stock_quantity']) : null;
    $status = isset($_POST['status']) ? 1 : 0;
    $isHotDeal = isset($_POST['is_hot_deal']) ? 1 : 0;
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;
    
    // Handle main image
    $image = $product['image'];
    
    // Check if main image should be deleted
    if (isset($_POST['delete_main_image']) && $_POST['delete_main_image'] == '1') {
        $image = '';
    }
    
    // Handle new main image upload
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'products');
        if ($upload['success']) {
            $image = uploadUrl($upload['filename']);
        }
    } elseif (!empty($_POST['image_url'])) {
        $image = sanitize($_POST['image_url']);
    }
    
    // Handle gallery images - start with existing gallery from hidden field
    $gallery = [];
    if (!empty($_POST['existing_gallery'])) {
        $existingGallery = json_decode($_POST['existing_gallery'], true);
        if (is_array($existingGallery)) {
            $gallery = $existingGallery;
        }
    }
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
                $upload = uploadImage($file, 'products');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = !empty($gallery) ? json_encode($gallery) : null;
    
    // Update product
    $stmt = $pdo->prepare("
        UPDATE products SET 
            name = ?, description = ?, regular_price = ?, sale_price = ?, 
            image = ?, gallery = ?, video_url = ?, category_id = ?, 
            is_hot_deal = ?, has_variations = ?, stock_quantity = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $description, $regularPrice, $salePrice, $image, $galleryJson, $videoUrl, $categoryId, $isHotDeal, $hasVariations, $stockQuantity, $status, $id]);
    
    // Handle variations
    $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$id]);
    
    if ($hasVariations && !empty($_POST['variations'])) {
        $variationsData = $_POST['variations'];
        foreach ($variationsData['attribute_name'] as $key => $attrName) {
            if (!empty($attrName) && !empty($variationsData['attribute_value'][$key])) {
                $attrValues = array_map('trim', explode(',', $variationsData['attribute_value'][$key]));
                foreach ($attrValues as $value) {
                    $stmt = $pdo->prepare("INSERT INTO product_variations (product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                    $stmt->execute([$id, sanitize($attrName), sanitize($value)]);
                }
            }
        }
    }
    
    setFlash('success', 'পণ্য আপডেট করা হয়েছে');
    redirect(adminUrl('products/edit/' . $id));
}

// Get categories
$categories = $pdo->query("
    SELECT c.*, p.name as parent_name 
    FROM categories c 
    LEFT JOIN categories p ON c.parent_id = p.id 
    ORDER BY c.parent_id IS NULL DESC, c.name
")->fetchAll();

$galleryImages = json_decode($product['gallery'], true) ?: [];

require_once 'includes/header.php';
?>

<style>
    .variation-row {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .gallery-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
    }
    .gallery-preview img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
    }
</style>

<?php showFlash(); ?>

<div class="admin-card">
    <h5 class="card-title">পণ্য এডিট করুন</h5>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label">পণ্যের নাম *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo sanitize($product['name']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6"><?php echo sanitize($product['description']); ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">রেগুলার দাম (৳) *</label>
                        <input type="number" name="regular_price" class="form-control" step="0.01" value="<?php echo $product['regular_price']; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">সেল দাম (৳)</label>
                        <input type="number" name="sale_price" class="form-control" step="0.01" value="<?php echo $product['sale_price']; ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">পণ্যের ছবি</label>
                    <input type="file" name="image" id="mainImageInput" class="form-control mb-2" accept="image/*">
                    <div id="mainImagePreview" class="image-preview-container"></div>
                    <input type="url" name="image_url" class="form-control" placeholder="অথবা নতুন ইমেজ URL দিন">
                    <input type="hidden" name="current_image" value="<?php echo $product['image']; ?>">
                    <input type="hidden" name="delete_main_image" id="deleteMainImage" value="0">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">গ্যালারি ছবি</label>
                    <div id="galleryPreview" class="image-preview-container"></div>
                    <input type="file" name="gallery[]" class="form-control mb-2" accept="image/*" multiple>
                    <textarea name="gallery_urls" class="form-control" rows="3" placeholder="নতুন ইমেজ যোগ করতে প্রতি লাইনে একটি করে URL দিন"></textarea>
                    <input type="hidden" name="existing_gallery" id="existingGallery" value='<?php echo json_encode($galleryImages); ?>'>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">পণ্যের ভিডিও (YouTube লিংক)</label>
                    <input type="url" name="video_url" class="form-control" value="<?php echo sanitize($product['video_url']); ?>" placeholder="https://www.youtube.com/watch?v=...">
                </div>
                
                <!-- Variations -->
                <div class="mb-4">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="has_variations" class="form-check-input" id="hasVariations" <?php echo $product['has_variations'] ? 'checked' : ''; ?> onchange="toggleVariations()">
                        <label class="form-check-label" for="hasVariations">এই পণ্যে ভেরিয়েশন আছে</label>
                    </div>
                    
                    <div id="variationsContainer" style="display: <?php echo $product['has_variations'] ? 'block' : 'none'; ?>;">
                        <div id="variationsList">
                            <?php if (!empty($variations)): ?>
                                <?php foreach ($variations as $var): ?>
                                <div class="variation-row">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <input type="text" name="variations[attribute_name][]" class="form-control" value="<?php echo sanitize($var['attribute_name']); ?>" placeholder="অ্যাট্রিবিউট নাম">
                                        </div>
                                        <div class="col-md-7 mb-2">
                                            <input type="text" name="variations[attribute_value][]" class="form-control" value="<?php echo sanitize($var['values_list']); ?>" placeholder="মান গুলো কমা দিয়ে">
                                        </div>
                                        <div class="col-md-1 mb-2">
                                            <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="variation-row">
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <input type="text" name="variations[attribute_name][]" class="form-control" placeholder="অ্যাট্রিবিউট নাম">
                                    </div>
                                    <div class="col-md-7 mb-2">
                                        <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="মান গুলো কমা দিয়ে">
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
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
                            <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['parent_name'] ? '— ' : ''; ?><?php echo sanitize($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">স্টক পরিমাণ</label>
                        <input type="number" name="stock_quantity" class="form-control" value="<?php echo $product['stock_quantity'] !== null ? $product['stock_quantity'] : ''; ?>" placeholder="খালি রাখলে আনলিমিটেড">
                        <small class="text-muted">খালি রাখলে আনলিমিটেড স্টক, ০ দিলে স্টক আউট</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_hot_deal" class="form-check-input" id="isHotDeal" <?php echo $product['is_hot_deal'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isHotDeal">হট ডিলে যোগ করুন</label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="status" class="form-check-input" id="status" <?php echo $product['status'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">প্রকাশিত</label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-admin">
                            <i class="bi bi-check-circle me-2"></i>আপডেট করুন
                        </button>
                        <a href="<?php echo baseUrl('product/' . $product['slug']); ?>" target="_blank" class="btn btn-admin-outline">
                            <i class="bi bi-eye me-2"></i>পণ্য দেখুন
                        </a>
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
                        <input type="text" name="variations[attribute_name][]" class="form-control" placeholder="অ্যাট্রিবিউট নাম">
                    </div>
                    <div class="col-md-7 mb-2">
                        <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="মান গুলো কমা দিয়ে">
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
    
    // Initialize existing images after page load
    document.addEventListener('DOMContentLoaded', function() {
        // Main image preview
        <?php if ($product['image']): ?>
        const mainContainer = document.getElementById('mainImagePreview');
        const mainItem = document.createElement('div');
        mainItem.className = 'image-preview-item main-image';
        mainItem.dataset.existingUrl = '<?php echo $product['image']; ?>';
        mainItem.innerHTML = `
            <img src="<?php echo $product['image']; ?>" alt="Main image">
            <button type="button" class="delete-btn" title="মুছুন">
                <i class="bi bi-x"></i>
            </button>
        `;
        mainItem.querySelector('.delete-btn').addEventListener('click', function(e) {
            e.preventDefault();
            mainItem.remove();
            document.getElementById('deleteMainImage').value = '1';
        });
        mainContainer.appendChild(mainItem);
        <?php endif; ?>
        
        // Gallery images preview
        <?php if (!empty($galleryImages)): ?>
        const galleryContainer = document.getElementById('galleryPreview');
        const galleryImages = <?php echo json_encode($galleryImages); ?>;
        let remainingGallery = [...galleryImages];
        
        galleryImages.forEach(function(url) {
            const item = document.createElement('div');
            item.className = 'image-preview-item';
            item.dataset.existingUrl = url;
            item.innerHTML = `
                <img src="${url}" alt="Gallery image">
                <button type="button" class="delete-btn" title="মুছুন">
                    <i class="bi bi-x"></i>
                </button>
            `;
            item.querySelector('.delete-btn').addEventListener('click', function(e) {
                e.preventDefault();
                item.remove();
                remainingGallery = remainingGallery.filter(u => u !== url);
                document.getElementById('existingGallery').value = JSON.stringify(remainingGallery);
            });
            galleryContainer.appendChild(item);
        });
        <?php endif; ?>
    });
</script>

<?php require_once 'includes/footer.php'; ?>
