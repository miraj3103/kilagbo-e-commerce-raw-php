<?php
/**
 * Kilagbo Admin - Edit Resell Product
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-products';
require_once 'includes/header.php';

$pageTitle = 'রিসেল পণ্য সম্পাদনা';

$productId = intval($_GET['id'] ?? 0);
if (!$productId) {
    setFlash('error', 'পণ্য আইডি পাওয়া যায়নি');
    redirect(adminUrl('resell-products.php'));
}

// Get product data
$stmt = $pdo->prepare("SELECT * FROM resell_products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'পণ্য পাওয়া যায়নি');
    redirect(adminUrl('resell-products.php'));
}

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
    $status = isset($_POST['status']) ? 1 : 0;
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;
    $videoUrl = sanitize($_POST['video_url'] ?? '');

    // Generate slug if empty
    if (empty($slug)) {
        $slug = 'resell-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) . '-' . $productId;
    }

    // Handle image upload
    $imageUpdate = '';
    $imageParams = [];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $uploadResult = uploadImage($_FILES['image'], 'products');
        if ($uploadResult['success']) {
            $imageUpdate = ", image = ?";
            $imageParams[] = $uploadResult['filename'];

            // Delete old image
            if ($product['image'] && file_exists('../uploads/' . $product['image'])) {
                unlink('../uploads/' . $product['image']);
            }
        } else {
            setFlash('error', 'ছবি আপডেট ব্যর্থ: ' . $uploadResult['message']);
        }
    }

    // Handle gallery images removal
    $currentGallery = !empty($product['gallery']) ? json_encode(json_decode($product['gallery'], true)) : '[]';
    $currentGalleryArr = json_decode($currentGallery, true) ?: [];
    
    if (isset($_POST['remove_gallery_images'])) {
        $removeImages = $_POST['remove_gallery_images'];
        foreach ($removeImages as $imgToRemove) {
            // Find and remove from array
            if (($key = array_search($imgToRemove, $currentGalleryArr)) !== false) {
                unset($currentGalleryArr[$key]);
                
                // Optional: Delete physical file if it's an uploaded file (not a URL from retail)
                // Since gallery images are stored as URLs (imageUrl function), we might need to parse the filename
                $filename = basename($imgToRemove);
                if (file_exists('../uploads/products/' . $filename)) {
                    unlink('../uploads/products/' . $filename);
                }
            }
        }
    }

    // Handle new gallery images upload
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
                    $currentGalleryArr[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = json_encode(array_values($currentGalleryArr));

    // Validation
    if (empty($name)) {
        setFlash('error', 'পণ্যের নাম দিন');
    } elseif ($resellerPrice <= 0) {
        setFlash('error', 'রিসেলার মূল্য দিন');
    } else {
        $params = [
            $name, $slug, $description, $shortDescription,
            $categoryId ?: null, $originalPrice, $resellerPrice,
            $suggestedPrice, $stockQuantity, $isHotDeal, $hasVariations, $videoUrl ?: null, $galleryJson, $status
        ];

        // Add image param if uploaded (before productId)
        if (!empty($imageParams)) {
            $params = array_merge($params, $imageParams);
        }
        
        // Add productId at the end for WHERE clause
        $params[] = $productId;

        $stmt = $pdo->prepare("
            UPDATE resell_products SET
                name = ?, slug = ?, description = ?, short_description = ?,
                category_id = ?, original_price = ?, reseller_price = ?,
                suggested_selling_price = ?, stock_quantity = ?, is_hot_deal = ?, has_variations = ?, video_url = ?, gallery = ?, status = ?
                $imageUpdate
            WHERE id = ?
        ");

        $stmt->execute($params);

        // Update variations
        $pdo->prepare("DELETE FROM resell_product_variations WHERE resell_product_id = ?")->execute([$productId]);
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

        setFlash('success', 'পণ্য সফলভাবে আপডেট হয়েছে');
        redirect(adminUrl('resell-edit.php?id=' . $productId));
    }
}

// Handle status toggle
if (isset($_POST['toggle_status'])) {
    $newStatus = $product['status'] ? 0 : 1;
    $pdo->prepare("UPDATE resell_products SET status = ? WHERE id = ?")->execute([$newStatus, $productId]);
    setFlash('success', 'স্ট্যাটাস পরিবর্তন হয়েছে');
    redirect(adminUrl('resell-edit.php?id=' . $productId));
}

// Get categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Fetch variations
$stmt = $pdo->prepare("SELECT attribute_name, GROUP_CONCAT(attribute_value) as attribute_values FROM resell_product_variations WHERE resell_product_id = ? GROUP BY attribute_name");
$stmt->execute([$productId]);
$existingVariations = $stmt->fetchAll();
?>

<style>
    .variation-row {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .gallery-preview-item {
        position: relative;
        display: inline-block;
        margin-right: 10px;
        margin-bottom: 10px;
    }
    .gallery-preview-item img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #ddd;
    }
    .gallery-preview-item .remove-btn {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4d4d;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        line-height: 20px;
        text-align: center;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        border: none;
        padding: 0;
    }
    .gallery-preview-item .remove-btn:hover {
        background: #ff0000;
    }
</style>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-pencil-square me-2"></i>রিসেল পণ্য সম্পাদনা</h4>
        <a href="<?php echo adminUrl('resell-products.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ফিরে যান
        </a>
    </div>

    <?php showFlash(); ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">পণ্যের নাম *</label>
                                    <input type="text" class="form-control" name="name" value="<?php echo sanitize($product['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">স্লাগ (URL)</label>
                                    <input type="text" class="form-control" name="slug" value="<?php echo sanitize($product['slug']); ?>" placeholder="স্বয়ংক্রিয় তৈরি হবে">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">ক্যাটেগরি</label>
                                    <select class="form-select" name="category_id">
                                        <option value="">সিলেক্ট করুন</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo $cat['name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">মূল মূল্য</label>
                                    <input type="number" class="form-control" name="original_price" step="0.01" value="<?php echo $product['original_price']; ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">রিসেলার মূল্য *</label>
                                    <input type="number" class="form-control" name="reseller_price" step="0.01" value="<?php echo $product['reseller_price']; ?>" required>
                                    <small class="text-muted">রিসেলাররা এই মূল্যে পণ্য কিনবে</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">সাজেস্টেড বিক্রয় মূল্য</label>
                                    <input type="number" class="form-control" name="suggested_selling_price" step="0.01" value="<?php echo $product['suggested_selling_price']; ?>">
                                    <small class="text-muted">রিসেলারদের জন্য সাজেস্টেড মূল্য</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">পণ্যের ছবি</label>
                                    <input type="file" class="form-control" name="image" accept="image/*">
                                    <?php if ($product['image']): ?>
                                    <div class="mt-2">
                                        <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 8px;">
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">গ্যালারি ছবি (একাধিক)</label>
                                    <input type="file" class="form-control mb-2" name="gallery[]" accept="image/*" multiple>
                                    <div id="galleryPreview" class="d-flex flex-wrap">
                                        <?php 
                                        $gallery = !empty($product['gallery']) ? json_decode($product['gallery'], true) : [];
                                        if (is_array($gallery)):
                                            foreach ($gallery as $img):
                                        ?>
                                            <div class="gallery-preview-item">
                                                <img src="<?php echo imageUrl($img); ?>" alt="">
                                                <button type="button" class="remove-btn" onclick="removeExistingImage(this, '<?php echo $img; ?>')">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                                <input type="hidden" name="existing_gallery[]" value="<?php echo $img; ?>">
                                            </div>
                                        <?php 
                                            endforeach;
                                        endif; 
                                        ?>
                                    </div>
                                    <small class="text-muted">একাধিক ছবি সিলেক্ট করতে পারবেন</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">স্টক পরিমাণ</label>
                                    <input type="number" class="form-control" name="stock_quantity" value="<?php echo $product['stock_quantity'] !== null ? $product['stock_quantity'] : ''; ?>" placeholder="খালি রাখলে আনলিমিটেড">
                                    <small class="text-muted">খালি রাখলে আনলিমিটেড স্টক, ০ দিলে স্টক আউট</small>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_hot_deal" id="isHotDeal" <?php echo $product['is_hot_deal'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="isHotDeal">
                                            <i class="bi bi-fire text-warning"></i> হট ডিল / ট্রেন্ডিং
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="status" id="status" <?php echo $product['status'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="status">
                                            <i class="bi bi-check-circle text-success"></i> সক্রিয়
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">সংক্ষিপ্ত বিবরণ</label>
                                    <textarea class="form-control" name="short_description" rows="2"><?php echo sanitize($product['short_description']); ?></textarea>
                                </div>

                                <div class="mb-3">
                            <label class="form-label">বিস্তারিত বিবরণ</label>
                            <textarea class="form-control" name="description" rows="5"><?php echo $product['description']; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">পণ্যের ভিডিও (YouTube লিংক)</label>
                            <input type="url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo $product['video_url']; ?>">
                        </div>

                        <!-- Variations -->
                        <div class="mb-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="has_variations" class="form-check-input" id="hasVariations" onchange="toggleVariations()" <?php echo $product['has_variations'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="hasVariations">এই পণ্যে ভেরিয়েশন আছে (সাইজ, রঙ ইত্যাদি)</label>
                            </div>
                            
                            <div id="variationsContainer" style="display: <?php echo $product['has_variations'] ? 'block' : 'none'; ?>;">
                                <div id="variationsList">
                                    <?php if ($existingVariations): ?>
                                        <?php foreach ($existingVariations as $var): ?>
                                            <div class="variation-row">
                                                <div class="row">
                                                    <div class="col-md-4 mb-2">
                                                        <input type="text" name="variations[attribute_name][]" class="form-control" value="<?php echo $var['attribute_name']; ?>" placeholder="অ্যাট্রিবিউট নাম (যেমন: সাইজ)">
                                                    </div>
                                                    <div class="col-md-7 mb-2">
                                                        <input type="text" name="variations[attribute_value][]" class="form-control" value="<?php echo $var['attribute_values']; ?>" placeholder="মান গুলো কমা দিয়ে (যেমন: S, M, L, XL)">
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
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVariation()">
                                    <i class="bi bi-plus me-1"></i>আরও ভেরিয়েশন যোগ করুন
                                </button>
                            </div>
                        </div>
                    </div>
                            </div>
                        </div>

                        <hr>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> আপডেট করুন
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">পণ্যের তথ্য</h5>
                </div>
                <div class="card-body">
                    <p><strong>পণ্য আইডি:</strong> #<?php echo $product['id']; ?></p>
                    <p><strong>ধরন:</strong> <?php echo $product['is_standalone'] ? 'স্বতন্ত্র' : 'রিটেইল থেকে'; ?></p>
                    <?php if (!$product['is_standalone']): ?>
                    <p><strong>রিটেইল পণ্য আইডি:</strong> #<?php echo $product['retail_product_id']; ?></p>
                    <?php endif; ?>
                    <p><strong>তৈরি:</strong> <?php echo date('d M Y, H:i', strtotime($product['created_at'])); ?></p>
                    <p><strong>আপডেট:</strong> <?php echo date('d M Y, H:i', strtotime($product['updated_at'])); ?></p>
                </div>
            </div>
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

    function removeExistingImage(btn, imgUrl) {
        if (confirm('আপনি কি এই ছবিটি মুছে ফেলতে চান?')) {
            const item = btn.closest('.gallery-preview-item');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_gallery_images[]';
            input.value = imgUrl;
            document.querySelector('form').appendChild(input);
            item.remove();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
