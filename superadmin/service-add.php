<?php
/**
 * Kilagbo - Add Service
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'সার্ভিস যোগ করুন';
$currentPage = 'services';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = generateSlug($name) ?: 'service-' . time();
    $description = $_POST['description'] ?? '';
    $shortDescription = sanitize($_POST['short_description'] ?? '');
    $regularPrice = floatval($_POST['regular_price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    // Services automatically belong to "ডিজিটাল সার্ভিস" category - no category selection needed
    $categoryId = null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $duration = sanitize($_POST['duration'] ?? '');
    $deliveryTime = sanitize($_POST['delivery_time'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Make slug unique
    $existingSlug = $pdo->prepare("SELECT COUNT(*) FROM services WHERE slug = ?");
    $existingSlug->execute([$slug]);
    if ($existingSlug->fetchColumn() > 0) {
        $slug .= '-' . time();
    }
    
    // Handle main image
    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'services');
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
                $upload = uploadImage($file, 'services');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = !empty($gallery) ? json_encode($gallery) : null;
    
    // Insert service
    $stmt = $pdo->prepare("
        INSERT INTO services (name, slug, description, short_description, regular_price, sale_price, image, gallery, video_url, category_id, duration, delivery_time, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $slug, $description, $shortDescription, $regularPrice, $salePrice, $image, $galleryJson, $videoUrl, $categoryId, $duration, $deliveryTime, $status]);
    
    setFlash('success', 'সার্ভিস সফলভাবে যোগ করা হয়েছে');
    redirect(adminUrl('services'));
}

// No category selection needed - services auto-assign to "ডিজিটাল সার্ভিস"

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('products'); ?>">পণ্য</a></li>
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('services'); ?>">সার্ভিস</a></li>
            <li class="breadcrumb-item active">নতুন সার্ভিস</li>
        </ol>
    </nav>
</div>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-lg-8">
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>সার্ভিস তথ্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">সার্ভিসের নাম <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="সার্ভিসের নাম লিখুন">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সংক্ষিপ্ত বিবরণ</label>
                    <textarea name="short_description" class="form-control" rows="2" placeholder="সংক্ষেপে সার্ভিস সম্পর্কে লিখুন"></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">বিস্তারিত বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6" placeholder="সার্ভিসের বিস্তারিত বিবরণ লিখুন"></textarea>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-clock me-2"></i>সার্ভিস সময়সীমা</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">সার্ভিস সময়কাল</label>
                        <input type="text" name="duration" class="form-control" placeholder="যেমন: ১ ঘন্টা, ২ দিন">
                        <small class="text-muted">সার্ভিস সম্পন্ন করতে কত সময় লাগবে</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ডেলিভারি সময়</label>
                        <input type="text" name="delivery_time" class="form-control" placeholder="যেমন: ২৪ ঘন্টা, ৩ দিন">
                        <small class="text-muted">কত সময়ের মধ্যে ডেলিভারি হবে</small>
                    </div>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-images me-2"></i>ছবি</h5>
                
                <div class="mb-3">
                    <label class="form-label">মূল ছবি</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="mt-2">
                        <small class="text-muted">অথবা ছবির URL দিন:</small>
                        <input type="text" name="image_url" class="form-control mt-1" placeholder="https://example.com/image.jpg">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">গ্যালারি ছবি</label>
                    <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                    <small class="text-muted">একাধিক ছবি সিলেক্ট করতে পারবেন</small>
                    <div class="mt-2">
                        <small class="text-muted">অথবা URL দিন (প্রতি লাইনে একটি):</small>
                        <textarea name="gallery_urls" class="form-control mt-1" rows="3" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"></textarea>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ভিডিও URL (ঐচ্ছিক)</label>
                    <input type="text" name="video_url" class="form-control" placeholder="YouTube বা অন্য ভিডিও লিংক">
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
                    সার্ভিসগুলো স্বয়ংক্রিয়ভাবে <strong>"ডিজিটাল সার্ভিস"</strong> ক্যাটেগরিতে যুক্ত হবে।
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
                    <i class="bi bi-check-circle me-2"></i>সার্ভিস যোগ করুন
                </button>
                <a href="<?php echo adminUrl('services'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>বাতিল করুন
                </a>
            </div>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>
