<?php
/**
 * Kilagbo Seller Portal - Add Product
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'পণ্য যোগ করুন';
$currentPage = 'add-product';

$seller = requireSellerLogin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = generateSlug($name) ?: 'product-' . time();
    $description = $_POST['description'] ?? '';
    $sellerPrice = floatval($_POST['seller_price'] ?? 0);
    $minimumQuantity = isset($_POST['minimum_quantity']) && $_POST['minimum_quantity'] !== '' ? intval($_POST['minimum_quantity']) : 0;

    // Optional wholesale storefront proposal. This remains pending until Super Admin approval.
    $wholesalePriceRaw = trim((string)($_POST['wholesale_price'] ?? ''));
    $wholesalePrice = $wholesalePriceRaw !== '' ? floatval($wholesalePriceRaw) : null;
    if ($wholesalePrice !== null && $wholesalePrice <= 0) {
        $wholesalePrice = null;
    }
    $wholesaleMinQty = $wholesalePrice !== null
        ? max(1, intval($_POST['wholesale_min_qty'] ?? 1))
        : null;

    $stock = (isset($_POST['stock']) && $_POST['stock'] !== '') ? intval($_POST['stock']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;
    
    // Make slug unique
    $existingSlug = $pdo->prepare("SELECT COUNT(*) FROM seller_products WHERE slug = ?");
    $existingSlug->execute([$slug]);
    if ($existingSlug->fetchColumn() > 0) {
        $slug .= '-' . time();
    }
    
    // Handle main image
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'seller-products');
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
                $upload = uploadImage($file, 'seller-products');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = !empty($gallery) ? json_encode($gallery) : null;
    
    // Insert product
    $stmt = $pdo->prepare("
        INSERT INTO seller_products (
            seller_id, name, slug, description, seller_price, minimum_quantity,
            wholesale_price, wholesale_min_qty, stock, image, gallery, video_url,
            category_id, has_variations, status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $seller['id'], $name, $slug, $description, $sellerPrice, $minimumQuantity,
        $wholesalePrice, $wholesaleMinQty, $stock, $image, $galleryJson, $videoUrl,
        $categoryId, $hasVariations
    ]);
    $productId = $pdo->lastInsertId();
    
    // Handle variations
    if ($hasVariations && !empty($_POST['variations'])) {
        $variations = $_POST['variations'];
        foreach ($variations['attribute_name'] as $key => $attrName) {
            if (!empty($attrName) && !empty($variations['attribute_value'][$key])) {
                $attrValues = array_map('trim', explode(',', $variations['attribute_value'][$key]));
                foreach ($attrValues as $value) {
                    $stmt = $pdo->prepare("INSERT INTO seller_product_variations (seller_product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                    $stmt->execute([$productId, sanitize($attrName), sanitize($value)]);
                }
            }
        }
    }
    
    // Add notification for admin
    $pdo->prepare("INSERT INTO notifications (type, message, link) VALUES ('seller_product', ?, ?)")
        ->execute(["নতুন সেলার পণ্য - $name ({$seller['name']})", "seller-products"]);
    
    setFlash('success', 'পণ্য সফলভাবে যোগ করা হয়েছে এবং অ্যাডমিনের অনুমোদনের জন্য পাঠানো হয়েছে');
    redirect(baseUrl('seller/products.php'));
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
    
    .info-box {
        background: #e8f5e9;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        color: #2e7d32;
    }
    
    /* Drag and Drop Upload Styles */
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #fafafa;
    }
    
    .drop-zone:hover {
        border-color: #4CAF50;
        background: #f0fff0;
    }
    
    .drop-zone.drag-over {
        border-color: #4CAF50;
        background: #e8f5e9;
        transform: scale(1.02);
    }
    
    .drop-zone-icon {
        font-size: 48px;
        color: #aaa;
        margin-bottom: 10px;
    }
    
    .drop-zone.drag-over .drop-zone-icon {
        color: #4CAF50;
    }
    
    .drop-zone-text {
        color: #666;
        margin-bottom: 10px;
    }
    
    .drop-zone-text strong {
        color: #4CAF50;
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
    
    .preview-item .remove-btn:hover {
        background: #c82333;
    }
</style>

<div class="info-box">
    <i class="bi bi-info-circle me-2"></i>
    <strong>পাইকারি দাম:</strong> এখানে আপনার পাইকারি দাম দিন। এই দামে অ্যাডমিন আপনার কাছ থেকে পণ্য কিনবে। পণ্য যোগ করার পর অ্যাডমিনের অনুমোদন লাগবে।
</div>

<div class="seller-card">
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
                    <textarea name="description" class="form-control" rows="5" placeholder="পণ্যের বিবরণ লিখুন"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">পণ্যের দাম (৳) * <small class="text-muted">(পাইকারি দাম)</small></label>
                        <input type="number" name="seller_price" class="form-control" step="0.01" placeholder="0.00" required>
                        <small class="text-muted">এটা অবশ্যই পাইকারি দাম হতে হবে</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">সর্বনিম্ন অর্ডার পরিমাণ</label>
                        <input type="number" name="minimum_quantity" class="form-control" min="0" value="0" placeholder="সর্বনিম্ন পরিমাণ লিখুন">
                        <small class="text-muted">ঐচ্ছিক - ফাঁকা রাখলে বা ০ দিলে কোনো সীমা থাকবে না</small>
                    </div>
                </div>

                <div class="seller-card mb-3" style="background:#fffaf0; border:1px solid #fde68a;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>পাইকারি সেকশন (ঐচ্ছিক)</h6>
                        <span class="badge bg-warning text-dark">অ্যাডমিন অনুমোদন লাগবে</span>
                    </div>
                    <p class="small text-muted mb-3">পণ্যটি পাইকারি সেকশনে দিতে চাইলে প্রস্তাবিত বিক্রয় মূল্য ও ন্যূনতম পরিমাণ দিন। ফাঁকা রাখলে পণ্যটি সাধারণ সেলার পণ্য হিসেবেই পেন্ডিং থাকবে।</p>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">পাইকারি বিক্রয় মূল্য (৳)</label>
                            <input type="number" name="wholesale_price" class="form-control" step="0.01" min="0" placeholder="যেমন: 450">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">পাইকারি ন্যূনতম অর্ডার</label>
                            <input type="number" name="wholesale_min_qty" class="form-control" min="1" value="1">
                        </div>
                    </div>
                    <small class="text-muted">সুপার অ্যাডমিন অনুমোদন/সেভ করার আগে এই মূল্য পাবলিক পাইকারি সেকশনে কার্যকর হবে না।</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">স্টক (ঐচ্ছিক)</label>
                    <input type="number" name="stock" class="form-control" placeholder="ফাঁকা রাখুন আনলিমিটেড এর জন্য, ০ দিলে স্টক আউট">
                    <small class="text-muted">কিছু না লিখলে আনলিমিটেড স্টক হবে (১০,০০০+)। ০ দিলে আউট অফ স্টক দেখাবে।</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">পণ্যের ছবি *</label>
                    <div class="drop-zone" id="mainImageDropZone">
                        <input type="file" name="image" id="mainImageInput" accept="image/*">
                        <div class="drop-zone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <div class="drop-zone-text"><strong>ক্লিক করুন</strong> অথবা ছবি এখানে টেনে আনুন</div>
                        <small class="text-muted">সর্বোচ্চ 5MB, JPG/PNG/GIF</small>
                    </div>
                    <div id="mainImagePreview" class="image-preview-container"></div>
                    <input type="url" name="image_url" class="form-control mt-2" placeholder="অথবা ইমেজ URL দিন">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">গ্যালারি ছবি</label>
                    <div class="drop-zone" id="galleryDropZone">
                        <input type="file" name="gallery[]" id="galleryInput" accept="image/*" multiple>
                        <div class="drop-zone-icon"><i class="bi bi-images"></i></div>
                        <div class="drop-zone-text"><strong>ক্লিক করুন</strong> অথবা একাধিক ছবি এখানে টেনে আনুন</div>
                        <small class="text-muted">একাধিক ছবি নির্বাচন করতে পারবেন</small>
                    </div>
                    <div id="galleryPreview" class="image-preview-container"></div>
                    <textarea name="gallery_urls" class="form-control mt-2" rows="2" placeholder="অথবা প্রতি লাইনে একটি করে ইমেজ URL দিন"></textarea>
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
                        <button type="button" class="btn btn-seller-outline btn-sm" onclick="addVariation()">
                            <i class="bi bi-plus me-1"></i>আরও ভেরিয়েশন যোগ করুন
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="seller-card" style="background: #f8f9fa;">
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
                    
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <small>পণ্য যোগ করার পর অ্যাডমিনের অনুমোদন লাগবে।</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-seller">
                            <i class="bi bi-check-circle me-2"></i>পণ্য যোগ করুন
                        </button>
                        <a href="<?php echo baseUrl('seller/products.php'); ?>" class="btn btn-secondary">বাতিল</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Track form changes for unsaved warning
    let formChanged = false;
    let formSubmitting = false;
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        
        // Track all input changes
        form.addEventListener('input', function() {
            formChanged = true;
        });
        
        form.addEventListener('change', function() {
            formChanged = true;
        });
        
        // Handle form submission
        form.addEventListener('submit', function() {
            formSubmitting = true;
        });
        
        // Initialize drag and drop
        initDropZone('mainImageDropZone', 'mainImageInput', 'mainImagePreview', false);
        initDropZone('galleryDropZone', 'galleryInput', 'galleryPreview', true);
    });
    
    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (formChanged && !formSubmitting) {
            e.preventDefault();
            e.returnValue = 'আপনার অসংরক্ষিত পরিবর্তন আছে। আপনি কি সত্যিই এই পৃষ্ঠা ছেড়ে যেতে চান?';
            return e.returnValue;
        }
    });
    
    // Drag and Drop functionality
    function initDropZone(dropZoneId, inputId, previewId, multiple) {
        const dropZone = document.getElementById(dropZoneId);
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        
        if (!dropZone || !input) return;
        
        // Click to upload
        dropZone.addEventListener('click', function() {
            input.click();
        });
        
        // Drag events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.remove('drag-over');
            }, false);
        });
        
        // Handle drop
        dropZone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                if (multiple) {
                    // For gallery - append files
                    const dt = new DataTransfer();
                    
                    // Add existing files
                    if (input.files) {
                        for (let i = 0; i < input.files.length; i++) {
                            dt.items.add(input.files[i]);
                        }
                    }
                    
                    // Add new files
                    for (let i = 0; i < files.length; i++) {
                        if (files[i].type.startsWith('image/')) {
                            dt.items.add(files[i]);
                        }
                    }
                    
                    input.files = dt.files;
                } else {
                    // For single image
                    const dt = new DataTransfer();
                    if (files[0].type.startsWith('image/')) {
                        dt.items.add(files[0]);
                        input.files = dt.files;
                    }
                }
                updatePreview(input, preview, multiple);
                formChanged = true;
            }
        }, false);
        
        // Handle file input change
        input.addEventListener('change', function() {
            updatePreview(input, preview, multiple);
            formChanged = true;
        });
    }
    
    function updatePreview(input, preview, multiple) {
        preview.innerHTML = '';
        
        if (input.files && input.files.length > 0) {
            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" style="max-width: 100px; max-height: 100px; object-fit: cover;">
                        <button type="button" class="remove-btn" onclick="removeFile('${input.id}', ${index}, '${preview.id}', ${multiple})">
                            <i class="bi bi-x"></i>
                        </button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }
    }
    
    function removeFile(inputId, index, previewId, multiple) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const dt = new DataTransfer();
        
        for (let i = 0; i < input.files.length; i++) {
            if (i !== index) {
                dt.items.add(input.files[i]);
            }
        }
        
        input.files = dt.files;
        updatePreview(input, preview, multiple);
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
                        <button type="button" class="btn btn-outline-danger w-100" onclick="removeVariation(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        list.insertAdjacentHTML('beforeend', html);
        formChanged = true;
    }
    
    function removeVariation(btn) {
        btn.closest('.variation-row').remove();
        formChanged = true;
    }
</script>

<?php require_once 'includes/footer.php'; ?>
