<?php
/**
 * Kilagbo Seller Portal - Edit Product
 * Seller edits are submitted for admin re-approval before published copies change.
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'পণ্য এডিট করুন';
$currentPage = 'products';

$seller = requireSellerLogin();
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    setFlash('error', 'পণ্য পাওয়া যায়নি');
    redirect(baseUrl('seller/products.php'));
}

// Seller can edit only their own product.
$stmt = $pdo->prepare("SELECT * FROM seller_products WHERE id = ? AND seller_id = ?");
$stmt->execute([$productId, $seller['id']]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'পণ্য পাওয়া যায়নি অথবা এই পণ্যটি আপনার নয়');
    redirect(baseUrl('seller/products.php'));
}

function sellerEditVariationGroups($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT attribute_name, attribute_value FROM seller_product_variations WHERE seller_product_id = ? ORDER BY id ASC");
    $stmt->execute([$productId]);

    $groups = [];
    foreach ($stmt->fetchAll() as $row) {
        $groups[$row['attribute_name']][] = $row['attribute_value'];
    }
    return $groups;
}

$variationGroups = sellerEditVariationGroups($pdo, $productId);

// Snapshot the currently published wholesale listing. Seller edits below change only
// seller_products until Super Admin approves/re-saves the pending product.
$publishedWholesale = null;
if (!empty($product['wholesale_added'])) {
    $publishedWholesaleStmt = $pdo->prepare("SELECT id, wholesale_price, min_order_qty FROM wholesale_products WHERE seller_product_id = ? AND seller_id = ? ORDER BY id DESC LIMIT 1");
    $publishedWholesaleStmt->execute([$productId, $seller['id']]);
    $publishedWholesale = $publishedWholesaleStmt->fetch() ?: null;
}

$currentGallery = json_decode($product['gallery'] ?? '[]', true);
if (!is_array($currentGallery)) {
    $currentGallery = [];
}

$error = '';

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = $_POST['description'] ?? '';
    $sellerPrice = floatval($_POST['seller_price'] ?? 0);
    $minimumQuantity = isset($_POST['minimum_quantity']) && $_POST['minimum_quantity'] !== ''
        ? max(0, intval($_POST['minimum_quantity']))
        : 0;

    // Wholesale storefront price/minimum are a seller request until Admin approves.
    $wholesalePriceRaw = trim((string)($_POST['wholesale_price'] ?? ''));
    $wholesalePrice = $wholesalePriceRaw !== '' ? floatval($wholesalePriceRaw) : null;
    if (!empty($product['wholesale_added']) && ($wholesalePrice === null || $wholesalePrice <= 0)) {
        $error = 'পাইকারি সেকশনে প্রকাশিত পণ্যের জন্য সঠিক পাইকারি বিক্রয় মূল্য দিন';
    } elseif ($wholesalePrice !== null && $wholesalePrice <= 0) {
        $wholesalePrice = null;
    }

    $wholesaleMinQty = $wholesalePrice !== null
        ? max(1, intval($_POST['wholesale_min_qty'] ?? ($product['wholesale_min_qty'] ?: 1)))
        : null;

    $stock = (isset($_POST['stock']) && $_POST['stock'] !== '') ? intval($_POST['stock']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;

    if ($name === '') {
        $error = 'পণ্যের নাম লিখুন';
    }

    // Keep the original slug stable so existing published listings remain linked safely.
    $image = $product['image'];

    if (isset($_POST['remove_main_image']) && $_POST['remove_main_image'] === '1') {
        $image = '';
    }

    // Uploaded image has first priority; URL is second priority.
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'seller-products');
        if ($upload['success']) {
            $image = uploadUrl($upload['filename']);
        } else {
            $error = $upload['message'] ?? 'মূল ছবি আপলোড করা যায়নি';
        }
    } elseif (!empty($_POST['image_url'])) {
        $imageUrl = trim($_POST['image_url']);
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $image = $imageUrl;
        } else {
            $error = 'সঠিক ইমেজ URL দিন';
        }
    }

    // Preserve current gallery unless the seller explicitly removes images.
    $gallery = $currentGallery;
    $removeGallery = $_POST['remove_gallery'] ?? [];
    if (is_array($removeGallery) && !empty($removeGallery)) {
        $gallery = array_values(array_filter($gallery, function ($item) use ($removeGallery) {
            return !in_array($item, $removeGallery, true);
        }));
    }

    // Append gallery image URLs.
    if (!empty($_POST['gallery_urls'])) {
        $galleryUrls = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['gallery_urls'])));
        foreach ($galleryUrls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $gallery[] = $url;
            }
        }
    }

    // Append uploaded gallery images.
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
                $upload = uploadImage($file, 'seller-products');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                } else {
                    $error = $upload['message'] ?? 'গ্যালারি ছবি আপলোড করা যায়নি';
                    break;
                }
            }
        }
    }

    $gallery = array_values(array_unique(array_filter($gallery)));
    $galleryJson = !empty($gallery) ? json_encode($gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            // Only the seller source product is changed here. Published copies stay unchanged
            // until Super Admin approves/re-saves the pending product.
            $stmt = $pdo->prepare("\n                UPDATE seller_products\n                SET name = ?, description = ?, seller_price = ?, minimum_quantity = ?,\n                    wholesale_price = ?, wholesale_min_qty = ?, stock = ?,\n                    image = ?, gallery = ?, video_url = ?, category_id = ?, has_variations = ?,\n                    status = 'pending', admin_notes = NULL\n                WHERE id = ? AND seller_id = ?\n            ");
            $stmt->execute([
                $name,
                $description,
                $sellerPrice,
                $minimumQuantity,
                $wholesalePrice,
                $wholesaleMinQty,
                $stock,
                $image,
                $galleryJson,
                $videoUrl,
                $categoryId,
                $hasVariations,
                $productId,
                $seller['id']
            ]);

            // Replace seller-side variations with the edited set.
            $pdo->prepare("DELETE FROM seller_product_variations WHERE seller_product_id = ?")
                ->execute([$productId]);

            if ($hasVariations && !empty($_POST['variations']['attribute_name'])) {
                $variationNames = $_POST['variations']['attribute_name'];
                $variationValues = $_POST['variations']['attribute_value'] ?? [];

                foreach ($variationNames as $key => $attrName) {
                    $attrName = trim((string)$attrName);
                    $valueString = trim((string)($variationValues[$key] ?? ''));
                    if ($attrName === '' || $valueString === '') {
                        continue;
                    }

                    $attrValues = array_filter(array_map('trim', explode(',', $valueString)), static function ($value) {
                        return $value !== '';
                    });

                    foreach ($attrValues as $value) {
                        $vStmt = $pdo->prepare("INSERT INTO seller_product_variations (seller_product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                        $vStmt->execute([$productId, sanitize($attrName), sanitize($value)]);
                    }
                }
            }

            $pdo->commit();

            // Admin notification should not block a successful product edit if notification schema differs.
            try {
                $notify = $pdo->prepare("\n                    INSERT INTO notifications (user_type, user_id, type, title, message, link, is_read)\n                    VALUES ('admin', NULL, 'seller_product', '', ?, 'seller-products', 0)\n                ");
                $notify->execute(["সেলার পণ্য এডিট করা হয়েছে - {$name} ({$seller['name']})"]);
            } catch (Exception $notificationError) {
                // Keep the product edit successful; pending status itself is visible to Admin.
            }

            setFlash('success', 'পণ্য আপডেট হয়েছে এবং পুনরায় অ্যাডমিন অনুমোদনের জন্য পাঠানো হয়েছে। অনুমোদনের আগে প্রকাশিত পণ্যের তথ্য পরিবর্তন হবে না।');
            redirect(baseUrl('seller/products.php'));
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'পণ্য আপডেট করা যায়নি। আবার চেষ্টা করুন।';
        }
    }

    // Refill the form with submitted values after validation/upload errors.
    $product['name'] = $name;
    $product['description'] = $description;
    $product['seller_price'] = $sellerPrice;
    $product['minimum_quantity'] = $minimumQuantity;
    $product['wholesale_price'] = $wholesalePrice;
    $product['wholesale_min_qty'] = $wholesaleMinQty;
    $product['stock'] = $stock;
    $product['image'] = $image;
    $product['gallery'] = $galleryJson;
    $product['video_url'] = $videoUrl;
    $product['category_id'] = $categoryId;
    $product['has_variations'] = $hasVariations;
    $currentGallery = $gallery;

    if ($hasVariations) {
        $variationGroups = [];
        $variationNames = $_POST['variations']['attribute_name'] ?? [];
        $variationValues = $_POST['variations']['attribute_value'] ?? [];
        foreach ($variationNames as $key => $attrName) {
            $attrName = trim((string)$attrName);
            if ($attrName !== '') {
                $variationGroups[$attrName] = array_filter(array_map('trim', explode(',', (string)($variationValues[$key] ?? ''))));
            }
        }
    } else {
        $variationGroups = [];
    }
}

// Get categories.
$categories = $pdo->query("\n    SELECT c.*, p.name as parent_name\n    FROM categories c\n    LEFT JOIN categories p ON c.parent_id = p.id\n    ORDER BY c.parent_id IS NULL DESC, c.name\n")->fetchAll();

require_once 'includes/header.php';
?>

<style>
    .variation-row {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .image-preview-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }

    .image-preview-container img {
        border-radius: 8px;
        border: 2px solid #e0e0e0;
    }

    .current-image-card {
        display: inline-block;
        position: relative;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 8px;
        background: #fff;
    }

    .current-image-card img {
        width: 110px;
        height: 110px;
        object-fit: cover;
        border-radius: 8px;
    }

    .current-gallery-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 12px;
    }

    .current-gallery-item {
        width: 110px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 6px;
        background: #fff;
    }

    .current-gallery-item img {
        width: 96px;
        height: 96px;
        object-fit: cover;
        border-radius: 7px;
    }

    .info-box {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        color: #9a3412;
    }

    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 10px;
        padding: 25px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #fafafa;
    }

    .drop-zone:hover,
    .drop-zone.drag-over {
        border-color: #4CAF50;
        background: #f0fff0;
    }

    .drop-zone-icon {
        font-size: 42px;
        color: #aaa;
        margin-bottom: 8px;
    }

    .drop-zone input[type="file"] {
        display: none;
    }

    .preview-item {
        position: relative;
        display: inline-block;
    }

    .preview-item .remove-btn {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="info-box">
    <i class="bi bi-info-circle me-2"></i>
    <strong>রিভিউ প্রয়োজন:</strong> এই পণ্যটি সেভ করলে স্ট্যাটাস আবার পেন্ডিং হবে। অ্যাডমিন অনুমোদন না করা পর্যন্ত বর্তমানে প্রকাশিত খুচরা/পাইকারি/রিসেলার কপিগুলো আগের তথ্যেই থাকবে।
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<div class="seller-card">
    <h5 class="card-title">পণ্যের তথ্য এডিট করুন</h5>

    <form method="POST" enctype="multipart/form-data" id="editProductForm">
        <div class="row">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label">পণ্যের নাম *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6"><?php echo htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">পণ্যের দাম (৳) * <small class="text-muted">(সেলার দাম)</small></label>
                        <input type="number" name="seller_price" class="form-control" step="0.01" value="<?php echo htmlspecialchars((string)$product['seller_price'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">সর্বনিম্ন অর্ডার পরিমাণ</label>
                        <input type="number" name="minimum_quantity" class="form-control" min="0" value="<?php echo intval($product['minimum_quantity'] ?? 0); ?>">
                        <small class="text-muted">০ দিলে কোনো সীমা থাকবে না</small>
                    </div>
                </div>

                <div class="seller-card mb-3" style="background:#fffaf0; border:1px solid #fde68a;">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>পাইকারি সেকশন</h6>
                        <?php if (!empty($product['wholesale_added'])): ?>
                            <span class="badge bg-success">বর্তমানে প্রকাশিত</span>
                        <?php elseif (!empty($product['wholesale_price'])): ?>
                            <span class="badge bg-warning text-dark">অনুমোদনের অপেক্ষায়</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">ঐচ্ছিক</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($publishedWholesale): ?>
                    <div class="alert alert-light border py-2 px-3 mb-3">
                        <small class="text-muted">বর্তমানে পাবলিক পাইকারি সেকশনে:</small>
                        <div class="fw-semibold">
                            মূল্য: <?php echo formatPrice($publishedWholesale['wholesale_price']); ?>
                            <span class="mx-2">•</span>
                            ন্যূনতম: <?php echo intval($publishedWholesale['min_order_qty']); ?> পিস
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">প্রস্তাবিত পাইকারি বিক্রয় মূল্য (৳)</label>
                            <input type="number" name="wholesale_price" class="form-control" step="0.01" min="0" value="<?php echo $product['wholesale_price'] !== null ? htmlspecialchars((string)$product['wholesale_price'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="পাইকারি মূল্য" <?php echo !empty($product['wholesale_added']) ? 'required' : ''; ?>>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">পাইকারি ন্যূনতম অর্ডার</label>
                            <input type="number" name="wholesale_min_qty" class="form-control" min="1" value="<?php echo intval($product['wholesale_min_qty'] ?: 1); ?>">
                        </div>
                    </div>
                    <small class="text-muted">এখানে পরিবর্তন সেভ করলে পণ্যটি পেন্ডিং হবে। সুপার অ্যাডমিন সেভ/অনুমোদন না করা পর্যন্ত উপরের বর্তমান পাবলিক মূল্য পরিবর্তন হবে না।</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">স্টক (ঐচ্ছিক)</label>
                    <input type="number" name="stock" class="form-control" value="<?php echo $product['stock'] === null ? '' : intval($product['stock']); ?>" placeholder="ফাঁকা রাখুন আনলিমিটেড এর জন্য">
                    <small class="text-muted">ফাঁকা = আনলিমিটেড, ০ = স্টক আউট</small>
                </div>

                <div class="mb-4">
                    <label class="form-label d-block">বর্তমান মূল ছবি</label>
                    <?php if (!empty($product['image'])): ?>
                    <div class="current-image-card mb-2">
                        <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_main_image" value="1" id="removeMainImage">
                            <label class="form-check-label small text-danger" for="removeMainImage">ছবিটি সরান</label>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small">কোনো মূল ছবি নেই</p>
                    <?php endif; ?>

                    <label class="form-label mt-2">নতুন মূল ছবি (ঐচ্ছিক)</label>
                    <div class="drop-zone" id="mainImageDropZone">
                        <input type="file" name="image" id="mainImageInput" accept="image/*">
                        <div class="drop-zone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <div><strong>ক্লিক করুন</strong> অথবা ছবি এখানে টেনে আনুন</div>
                    </div>
                    <div id="mainImagePreview" class="image-preview-container"></div>
                    <input type="url" name="image_url" class="form-control mt-2" placeholder="অথবা নতুন ইমেজ URL দিন">
                </div>

                <div class="mb-4">
                    <label class="form-label">বর্তমান গ্যালারি</label>
                    <?php if (!empty($currentGallery)): ?>
                    <div class="current-gallery-grid">
                        <?php foreach ($currentGallery as $galleryImage): ?>
                        <label class="current-gallery-item">
                            <img src="<?php echo htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                            <span class="d-block mt-1 small text-danger">
                                <input type="checkbox" name="remove_gallery[]" value="<?php echo htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8'); ?>"> সরান
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small">কোনো গ্যালারি ছবি নেই</p>
                    <?php endif; ?>

                    <label class="form-label mt-2">নতুন গ্যালারি ছবি যোগ করুন</label>
                    <div class="drop-zone" id="galleryDropZone">
                        <input type="file" name="gallery[]" id="galleryInput" accept="image/*" multiple>
                        <div class="drop-zone-icon"><i class="bi bi-images"></i></div>
                        <div><strong>ক্লিক করুন</strong> অথবা একাধিক ছবি এখানে টেনে আনুন</div>
                    </div>
                    <div id="galleryPreview" class="image-preview-container"></div>
                    <textarea name="gallery_urls" class="form-control mt-2" rows="2" placeholder="অথবা প্রতি লাইনে একটি করে নতুন ইমেজ URL দিন"></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label">পণ্যের ভিডিও (YouTube লিংক)</label>
                    <input type="url" name="video_url" class="form-control" value="<?php echo htmlspecialchars($product['video_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.youtube.com/watch?v=...">
                </div>

                <div class="mb-4">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="has_variations" class="form-check-input" id="hasVariations" onchange="toggleVariations()" <?php echo !empty($product['has_variations']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="hasVariations">এই পণ্যে ভেরিয়েশন আছে (সাইজ, রঙ ইত্যাদি)</label>
                    </div>

                    <div id="variationsContainer" style="display: <?php echo !empty($product['has_variations']) ? 'block' : 'none'; ?>;">
                        <div id="variationsList">
                            <?php if (!empty($variationGroups)): ?>
                                <?php foreach ($variationGroups as $attributeName => $values): ?>
                                <div class="variation-row">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <input type="text" name="variations[attribute_name][]" class="form-control" value="<?php echo htmlspecialchars($attributeName, ENT_QUOTES, 'UTF-8'); ?>" placeholder="অ্যাট্রিবিউট নাম">
                                        </div>
                                        <div class="col-md-7 mb-2">
                                            <input type="text" name="variations[attribute_value][]" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $values), ENT_QUOTES, 'UTF-8'); ?>" placeholder="মান গুলো কমা দিয়ে">
                                        </div>
                                        <div class="col-md-1 mb-2">
                                            <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)"><i class="bi bi-trash"></i></button>
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
                                            <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="মান গুলো কমা দিয়ে (যেমন: S, M, L)">
                                        </div>
                                        <div class="col-md-1 mb-2">
                                            <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-seller-outline btn-sm" onclick="addVariation()">
                            <i class="bi bi-plus me-1"></i>আরও ভেরিয়েশন যোগ করুন
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="seller-card" style="background: #f8f9fa;">
                    <h6 class="mb-3">রিভিউতে পাঠান</h6>

                    <div class="mb-3">
                        <label class="form-label">ক্যাটেগরি</label>
                        <select name="category_id" class="form-select">
                            <option value="">ক্যাটেগরি নির্বাচন করুন</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo (string)$product['category_id'] === (string)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['parent_name'] ? '— ' : ''; ?><?php echo sanitize($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-clock-history me-2"></i>
                        <small>সেভ করার পরে পণ্যটি পেন্ডিং হবে এবং অ্যাডমিন পুনরায় অনুমোদন করবে।</small>
                    </div>

                    <?php if (!empty($product['admin_notes'])): ?>
                    <div class="alert alert-danger mb-3">
                        <strong>অ্যাডমিন নোট:</strong><br>
                        <small><?php echo nl2br(sanitize($product['admin_notes'])); ?></small>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-seller">
                            <i class="bi bi-check-circle me-2"></i>পরিবর্তন সেভ করুন
                        </button>
                        <a href="<?php echo baseUrl('seller/products.php'); ?>" class="btn btn-secondary">বাতিল</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let formChanged = false;
let formSubmitting = false;

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editProductForm');
    if (!form) return;

    form.addEventListener('input', function() { formChanged = true; });
    form.addEventListener('change', function() { formChanged = true; });
    form.addEventListener('submit', function() { formSubmitting = true; });

    initDropZone('mainImageDropZone', 'mainImageInput', 'mainImagePreview', false);
    initDropZone('galleryDropZone', 'galleryInput', 'galleryPreview', true);
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged && !formSubmitting) {
        e.preventDefault();
        e.returnValue = '';
    }
});

function initDropZone(dropZoneId, inputId, previewId, multiple) {
    const dropZone = document.getElementById(dropZoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!dropZone || !input || !preview) return;

    dropZone.addEventListener('click', function() { input.click(); });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    ['dragenter', 'dragover'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function() { dropZone.classList.add('drag-over'); });
    });

    ['dragleave', 'drop'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function() { dropZone.classList.remove('drag-over'); });
    });

    dropZone.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (!files.length) return;

        const dt = new DataTransfer();
        if (multiple && input.files) {
            Array.from(input.files).forEach(function(file) { dt.items.add(file); });
        }

        Array.from(files).forEach(function(file, index) {
            if (file.type.startsWith('image/') && (multiple || index === 0)) {
                dt.items.add(file);
            }
        });

        input.files = dt.files;
        updatePreview(input, preview);
        formChanged = true;
    });

    input.addEventListener('change', function() {
        updatePreview(input, preview);
        formChanged = true;
    });
}

function updatePreview(input, preview) {
    preview.innerHTML = '';
    Array.from(input.files || []).forEach(function(file, index) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = '<img src="' + e.target.result + '" style="width:100px;height:100px;object-fit:cover;">' +
                '<button type="button" class="remove-btn" onclick="removeFile(\'' + input.id + '\',' + index + ',\'' + preview.id + '\')"><i class="bi bi-x"></i></button>';
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function removeFile(inputId, index, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const dt = new DataTransfer();

    Array.from(input.files || []).forEach(function(file, i) {
        if (i !== index) dt.items.add(file);
    });

    input.files = dt.files;
    updatePreview(input, preview);
    formChanged = true;
}

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
                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>`;
    list.insertAdjacentHTML('beforeend', html);
    formChanged = true;
}

function removeVariation(button) {
    const row = button.closest('.variation-row');
    if (row) row.remove();
    formChanged = true;
}
</script>

<?php require_once 'includes/footer.php'; ?>
