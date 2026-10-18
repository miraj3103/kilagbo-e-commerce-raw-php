<?php
/**
 * Kilagbo - Add Digital Product
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'ডিজিটাল প্রোডাক্ট যোগ করুন';
$currentPage = 'digital-products';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = generateSlug($name) ?: 'digital-' . time();
    $description = $_POST['description'] ?? '';
    $shortDescription = sanitize($_POST['short_description'] ?? '');
    $regularPrice = floatval($_POST['regular_price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    // Digital products automatically belong to "ডিজিটাল প্রোডাক্ট" category - no category selection needed
    $categoryId = null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $fileUrl = sanitize($_POST['file_url'] ?? '');
    $fileType = sanitize($_POST['file_type'] ?? '');
    $fileSize = sanitize($_POST['file_size'] ?? '');
    $accessType = sanitize($_POST['access_type'] ?? 'download');
    
    // Handle Digital File Upload
    if (!empty($_FILES['digital_file']['name'])) {
        $uploadDir = '../uploads/digital_files/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['digital_file']['name']);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['digital_file']['tmp_name'], $targetFile)) {
            $fileUrl = uploadUrl('digital_files/' . $fileName);
            // Auto detect size and type if not provided
            if (empty($fileSize)) {
                $bytes = $_FILES['digital_file']['size'];
                if ($bytes >= 1073741824) { $fileSize = number_format($bytes / 1073741824, 2) . ' GB'; }
                elseif ($bytes >= 1048576) { $fileSize = number_format($bytes / 1048576, 2) . ' MB'; }
                elseif ($bytes >= 1024) { $fileSize = number_format($bytes / 1024, 2) . ' KB'; }
                else { $fileSize = $bytes . ' bytes'; }
            }
            if (empty($fileType)) {
                $fileType = strtoupper(pathinfo($fileName, PATHINFO_EXTENSION));
            }
        }
    }
    $status = isset($_POST['status']) ? 1 : 0;
    $hasVariations = isset($_POST['has_variations']) ? 1 : 0;
    
    // Make slug unique
    $existingSlug = $pdo->prepare("SELECT COUNT(*) FROM digital_products WHERE slug = ?");
    $existingSlug->execute([$slug]);
    if ($existingSlug->fetchColumn() > 0) {
        $slug .= '-' . time();
    }
    
    // Handle main image
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'digital');
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
                $upload = uploadImage($file, 'digital');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = !empty($gallery) ? json_encode($gallery) : null;
    
    // Insert digital product
    $stmt = $pdo->prepare("
        INSERT INTO digital_products (name, slug, description, short_description, regular_price, sale_price, image, gallery, video_url, category_id, file_url, file_type, file_size, access_type, has_variations, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $slug, $description, $shortDescription, $regularPrice, $salePrice, $image, $galleryJson, $videoUrl, $categoryId, $fileUrl, $fileType, $fileSize, $accessType, $hasVariations, $status]);
    $productId = $pdo->lastInsertId();
    
    // Handle variations
    if ($hasVariations && !empty($_POST['variations'])) {
        $variations = $_POST['variations'];
        foreach ($variations['attribute_name'] as $key => $attrName) {
            if (!empty($attrName) && !empty($variations['attribute_value'][$key])) {
                // New logic: Single value per row to allow specific pricing
                $value = trim($variations['attribute_value'][$key]);
                $priceAdj = floatval($variations['price_adjustment'][$key] ?? 0);
                
                $stmt = $pdo->prepare("INSERT INTO digital_product_variations (product_id, attribute_name, attribute_value, price_adjustment) VALUES (?, ?, ?, ?)");
                $stmt->execute([$productId, sanitize($attrName), sanitize($value), $priceAdj]);
            }
        }
    }
    
    setFlash('success', 'ডিজিটাল প্রোডাক্ট সফলভাবে যোগ করা হয়েছে');
    redirect(adminUrl('digital-products'));
}

// No category selection needed - digital products auto-assign to "ডিজিটাল প্রোডাক্ট"

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('products'); ?>">পণ্য</a></li>
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('digital-products'); ?>">ডিজিটাল প্রোডাক্ট</a></li>
            <li class="breadcrumb-item active">নতুন প্রোডাক্ট</li>
        </ol>
    </nav>
</div>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-lg-8">
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>প্রোডাক্ট তথ্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">প্রোডাক্টের নাম <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="প্রোডাক্টের নাম লিখুন">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সংক্ষিপ্ত বিবরণ</label>
                    <textarea name="short_description" class="form-control" rows="2" placeholder="সংক্ষেপে প্রোডাক্ট সম্পর্কে লিখুন"></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">বিস্তারিত বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6" placeholder="প্রোডাক্টের বিস্তারিত বিবরণ লিখুন"></textarea>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-file-earmark-arrow-down me-2"></i>ফাইল তথ্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">ফাইল আপলোড</label>
                    <div class="drop-zone border-dashed p-4 text-center bg-light rounded position-relative" style="border: 2px dashed #ccc; cursor: pointer;" onclick="document.getElementById('digital_file').click()">
                        <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                        <p class="mt-2 text-muted mb-0">ফাইল ড্রপ করুন অথবা ক্লিক করে আপলোড করুন</p>
                        <input type="file" name="digital_file" id="digital_file" class="d-none" onchange="showFileName(this)">
                        <p id="file_name_display" class="mt-2 fw-bold text-success mb-0"></p>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">অথবা, ডাউনলোড লিংক / ফাইল URL</label>
                    <input type="text" name="file_url" class="form-control" placeholder="https://example.com/file.zip বা Google Drive লিংক">
                    <small class="text-muted">ক্রেতা পেমেন্ট করার পর এই লিংক পাবে</small>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">ফাইল টাইপ</label>
                        <select name="file_type" class="form-select">
                            <option value="">নির্বাচন করুন</option>
                            <option value="PDF">PDF</option>
                            <option value="ZIP">ZIP</option>
                            <option value="RAR">RAR</option>
                            <option value="MP3">MP3 (অডিও)</option>
                            <option value="MP4">MP4 (ভিডিও)</option>
                            <option value="PSD">PSD</option>
                            <option value="AI">AI</option>
                            <option value="EPS">EPS</option>
                            <option value="DOC">DOC/DOCX</option>
                            <option value="XLS">XLS/XLSX</option>
                            <option value="PPT">PPT/PPTX</option>
                            <option value="APK">APK</option>
                            <option value="Software">Software</option>
                            <option value="Course">কোর্স</option>
                            <option value="Other">অন্যান্য</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">ফাইল সাইজ</label>
                        <input type="text" name="file_size" class="form-control" placeholder="যেমন: 50 MB, 2 GB">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">অ্যাক্সেস টাইপ</label>
                        <select name="access_type" class="form-select">
                            <option value="download">ডাউনলোড</option>
                            <option value="streaming">স্ট্রিমিং</option>
                            <option value="both">উভয়</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-images me-2"></i>ছবি</h5>
                
                <div class="mb-3">
                    <label class="form-label">মূল ছবি (থাম্বনেইল)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="mt-2">
                        <small class="text-muted">অথবা ছবির URL দিন:</small>
                        <input type="text" name="image_url" class="form-control mt-1" placeholder="https://example.com/image.jpg">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">গ্যালারি ছবি / স্ক্রিনশট</label>
                    <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                    <small class="text-muted">প্রোডাক্টের স্ক্রিনশট বা প্রিভিউ ছবি</small>
                    <div class="mt-2">
                        <small class="text-muted">অথবা URL দিন (প্রতি লাইনে একটি):</small>
                        <textarea name="gallery_urls" class="form-control mt-1" rows="3" placeholder="https://example.com/screenshot1.jpg&#10;https://example.com/screenshot2.jpg"></textarea>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ভিডিও প্রিভিউ URL (ঐচ্ছিক)</label>
                    <input type="text" name="video_url" class="form-control" placeholder="YouTube বা অন্য ভিডিও লিংক">
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-list-check me-2"></i>ভ্যারিয়েশন (ঐচ্ছিক)</h5>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="has_variations" id="hasVariations" onchange="toggleVariations()">
                    <label class="form-check-label" for="hasVariations">ভ্যারিয়েশন আছে (যেমন: লাইসেন্স টাইপ, প্যাকেজ)</label>
                </div>
                
                <div id="variationsSection" style="display: none;">
                    <p class="text-muted small mb-3"><i class="bi bi-info-circle"></i> প্রতিটি ভিন্ন ভ্যারিয়েশন এবং তার জন্য নির্ধারিত অতিরিক্ত মূল্য আলাদা লাইন হিসেবে যোগ করুন।</p>
                    <div id="variationsList">
                        <div class="variation-item border rounded p-3 mb-2">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">অ্যাট্রিবিউট নাম</label>
                                    <input type="text" name="variations[attribute_name][]" class="form-control" placeholder="যেমন: লাইসেন্স">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">ভ্যালু</label>
                                    <input type="text" name="variations[attribute_value][]" class="form-control" placeholder="যেমন: পার্সোনাল">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label small">অতিরিক্ত মূল্য</label>
                                    <input type="number" name="variations[price_adjustment][]" class="form-control" placeholder="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVariation()">
                        <i class="bi bi-plus me-1"></i>আরো ভ্যারিয়েশন যোগ করুন
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-currency-dollar me-2"></i>মূল্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">নিয়মিত মূল্য (৳) <span class="text-danger">*</span></label>
                    <input type="number" name="regular_price" class="form-control" step="0.01" required placeholder="0.00">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">অফার মূল্য (৳)</label>
                    <input type="number" name="sale_price" class="form-control" step="0.01" placeholder="ছাড় থাকলে দিন">
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-folder me-2"></i>ক্যাটেগরি</h5>
                
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    ডিজিটাল প্রোডাক্টগুলো স্বয়ংক্রিয়ভাবে <strong>"ডিজিটাল প্রোডাক্ট"</strong> ক্যাটেগরিতে যুক্ত হবে।
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-gear me-2"></i>স্ট্যাটাস</h5>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="status" id="status" checked>
                    <label class="form-check-label" for="status">সক্রিয় (পাবলিশ করুন)</label>
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-admin btn-lg">
                    <i class="bi bi-check-circle me-2"></i>প্রোডাক্ট যোগ করুন
                </button>
                <a href="<?php echo adminUrl('digital-products'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>বাতিল করুন
                </a>
            </div>
        </div>
    </div>
</form>

<script>
function showFileName(input) {
    const display = document.getElementById('file_name_display');
    if (input.files && input.files[0]) {
        display.textContent = 'নির্বাচিত ফাইল: ' + input.files[0].name;
    } else {
        display.textContent = '';
    }
}

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
