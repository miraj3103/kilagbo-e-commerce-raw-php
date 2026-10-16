<?php
/**
 * Kilagbo Admin - Add Standalone Resell Product
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-products';
require_once 'includes/header.php';

$pageTitle = 'আলাদা ভাবে রিসেল পণ্য যোগ করুন';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $description = $_POST['description'] ?? '';
    $shortDescription = sanitize($_POST['short_description'] ?? '');
    $categoryId = intval($_POST['category_id'] ?? 0);
    $originalPrice = floatval($_POST['original_price'] ?? 0);
    $resellerPrice = floatval($_POST['reseller_price'] ?? 0);
    $suggestedPrice = floatval($_POST['suggested_selling_price'] ?? 0);
    $stockQuantity = ($_POST['stock_quantity'] !== '' && $_POST['stock_quantity'] !== null) ? intval($_POST['stock_quantity']) : null;
    $isHotDeal = isset($_POST['is_hot_deal']) ? 1 : 0;
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    
    // Generate slug if empty
    if (empty($slug)) {
        $slug = 'resell-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) . '-' . time();
    }
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $uploadResult = uploadImage($_FILES['image'], 'products');
        if ($uploadResult['success']) {
            $image = $uploadResult['filename'];
        } else {
            setFlash('error', 'ছবি আপলোড ব্যর্থ: ' . $uploadResult['message']);
            // Continue with form processing even if image upload fails
        }
    }
    
    // Handle gallery images
    $gallery = [];
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
    
    // Validation
    if (empty($name)) {
        setFlash('error', 'পণ্যের নাম দিন');
    } elseif ($resellerPrice <= 0) {
        setFlash('error', 'রিসেলার মূল্য দিন');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO resell_products 
            (name, slug, description, short_description, image, gallery, video_url, category_id, original_price, reseller_price, suggested_selling_price, stock_quantity, is_hot_deal, has_variations, is_standalone, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
        ");
        $stmt->execute([
            $name,
            $slug,
            $description,
            $shortDescription,
            $image,
            $galleryJson,
            $videoUrl ?: null,
            $categoryId ?: null,
            $originalPrice ?: $resellerPrice,
            $resellerPrice,
            $suggestedPrice ?: $resellerPrice + 100,
            $stockQuantity,
            $isHotDeal,
            $hasVariations
        ]);
        $productId = $pdo->lastInsertId();

        // Handle variations
        if ($hasVariations && !empty($_POST['variations'])) {
            $variations = $_POST['variations'];
            foreach ($variations['attribute_name'] as $key => $attrName) {
                if (!empty($attrName) && !empty($variations['attribute_value'][$key])) {
                    $attrValues = array_map('trim', explode(',', $variations['attribute_value'][$key]));
                    foreach ($attrValues as $value) {
                        $stmt = $pdo->prepare("INSERT INTO resell_product_variations (resell_product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                        $stmt->execute([$productId, sanitize($attrName), sanitize($value)]);
                    }
                }
            }
        }
        
        setFlash('success', 'পণ্য সফলভাবে যোগ হয়েছে');
        redirect(adminUrl('resell-products.php'));
    }
}

// Get categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-plus-circle me-2"></i>আলাদা ভাবে রিসেল পণ্য যোগ করুন</h4>
        <a href="<?php echo adminUrl('resell-products.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ফিরে যান
        </a>
    </div>
    
    <?php showFlash(); ?>
    
    <style>
        .variation-row {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
    </style>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">পণ্যের নাম *</label>
                            <input type="text" class="form-control" name="name" required value="<?php echo $_POST['name'] ?? ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">স্লাগ (URL)</label>
                            <input type="text" class="form-control" name="slug" placeholder="স্বয়ংক্রিয় তৈরি হবে" value="<?php echo $_POST['slug'] ?? ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">সংক্ষিপ্ত বিবরণ</label>
                            <textarea class="form-control" name="short_description" rows="2"><?php echo $_POST['short_description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">বিস্তারিত বিবরণ</label>
                            <textarea class="form-control" name="description" rows="5"><?php echo $_POST['description'] ?? ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">পণ্যের ভিডিও (YouTube লিংক)</label>
                            <input type="url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo $_POST['video_url'] ?? ''; ?>">
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
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVariation()">
                                    <i class="bi bi-plus me-1"></i>আরও ভেরিয়েশন যোগ করুন
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">পণ্যের ছবি</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">গ্যালারি ছবি (একাধিক)</label>
                            <input type="file" class="form-control" name="gallery[]" accept="image/*" multiple>
                            <small class="text-muted">একাধিক ছবি সিলেক্ট করতে পারবেন</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ক্যাটেগরি</label>
                            <select class="form-select" name="category_id">
                                <option value="">সিলেক্ট করুন</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">মূল মূল্য *</label>
                            <input type="number" class="form-control" name="original_price" step="0.01" required value="<?php echo $_POST['original_price'] ?? ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">রিসেলার মূল্য *</label>
                            <input type="number" class="form-control" name="reseller_price" step="0.01" required value="<?php echo $_POST['reseller_price'] ?? ''; ?>">
                            <small class="text-muted">রিসেলাররা এই মূল্যে পণ্য কিনবে</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">সাজেস্টেড বিক্রয় মূল্য</label>
                            <input type="number" class="form-control" name="suggested_selling_price" step="0.01" value="<?php echo $_POST['suggested_selling_price'] ?? ''; ?>">
                            <small class="text-muted">রিসেলারদের জন্য সাজেস্টেড মূল্য</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">স্টক পরিমাণ</label>
                            <input type="number" class="form-control" name="stock_quantity" value="<?php echo isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? $_POST['stock_quantity'] : ''; ?>" placeholder="খালি রাখলে আনলিমিটেড">
                            <small class="text-muted">খালি রাখলে আনলিমিটেড স্টক, ০ দিলে স্টক আউট</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_hot_deal" id="isHotDeal">
                                <label class="form-check-label" for="isHotDeal">
                                    <i class="bi bi-fire text-warning"></i> হট ডিল / ট্রেন্ডিং
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> পণ্য যোগ করুন
                </button>
            </form>
        </div>
    </div>
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
