<?php
/**
 * Kilagbo - Edit Service
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'সার্ভিস সম্পাদনা';
$currentPage = 'services';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

$serviceId = intval($_GET['id'] ?? 0);
if (!$serviceId) {
    redirect(adminUrl('services'));
}

// Fetch service
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    setFlash('error', 'সার্ভিস পাওয়া যায়নি');
    redirect(adminUrl('services'));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = generateSlug($name) ?: 'service-' . time();
    $description = $_POST['description'] ?? '';
    $shortDescription = sanitize($_POST['short_description'] ?? '');
    $regularPrice = floatval($_POST['regular_price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $videoUrl = sanitize($_POST['video_url'] ?? '');
    $duration = sanitize($_POST['duration'] ?? '');
    $deliveryTime = sanitize($_POST['delivery_time'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Make slug unique (exclude current)
    $existingSlug = $pdo->prepare("SELECT COUNT(*) FROM services WHERE slug = ? AND id != ?");
    $existingSlug->execute([$slug, $serviceId]);
    if ($existingSlug->fetchColumn() > 0) {
        $slug .= '-' . time();
    }
    
    // Handle main image
    $image = $service['image'];
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'services');
        if ($upload['success']) {
            $image = uploadUrl($upload['filename']);
        }
    } elseif (!empty($_POST['image_url'])) {
        $image = sanitize($_POST['image_url']);
    }
    
    // Handle gallery images
    $gallery = $service['gallery'] ? json_decode($service['gallery'], true) : [];
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
                $upload = uploadImage($file, 'services');
                if ($upload['success']) {
                    $gallery[] = uploadUrl($upload['filename']);
                }
            }
        }
    }
    
    $galleryJson = !empty($gallery) ? json_encode($gallery) : null;
    
    // Update service
    $stmt = $pdo->prepare("
        UPDATE services SET 
            name = ?, slug = ?, description = ?, short_description = ?, 
            regular_price = ?, sale_price = ?, image = ?, gallery = ?, 
            video_url = ?, category_id = ?, duration = ?, delivery_time = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $slug, $description, $shortDescription, $regularPrice, $salePrice, $image, $galleryJson, $videoUrl, $categoryId, $duration, $deliveryTime, $status, $serviceId]);
    
    setFlash('success', 'সার্ভিস সফলভাবে আপডেট করা হয়েছে');
    redirect(adminUrl('services'));
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name")->fetchAll();
$gallery = $service['gallery'] ? json_decode($service['gallery'], true) : [];

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('products'); ?>">পণ্য</a></li>
            <li class="breadcrumb-item"><a href="<?php echo adminUrl('services'); ?>">সার্ভিস</a></li>
            <li class="breadcrumb-item active">সম্পাদনা</li>
        </ol>
    </nav>
</div>

<?php showFlash(); ?>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-lg-8">
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>সার্ভিস তথ্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">সার্ভিসের নাম <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?php echo sanitize($service['name']); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সংক্ষিপ্ত বিবরণ</label>
                    <textarea name="short_description" class="form-control" rows="2"><?php echo sanitize($service['short_description']); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">বিস্তারিত বিবরণ</label>
                    <textarea name="description" class="form-control" rows="6"><?php echo $service['description']; ?></textarea>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-clock me-2"></i>সার্ভিস সময়সীমা</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">সার্ভিস সময়কাল</label>
                        <input type="text" name="duration" class="form-control" value="<?php echo sanitize($service['duration']); ?>" placeholder="যেমন: ১ ঘন্টা, ২ দিন">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ডেলিভারি সময়</label>
                        <input type="text" name="delivery_time" class="form-control" value="<?php echo sanitize($service['delivery_time']); ?>" placeholder="যেমন: ২৪ ঘন্টা, ৩ দিন">
                    </div>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-images me-2"></i>ছবি</h5>
                
                <div class="mb-3">
                    <label class="form-label">মূল ছবি</label>
                    <?php if ($service['image']): ?>
                    <div class="mb-2">
                        <img src="<?php echo $service['image']; ?>" alt="" style="max-height: 150px; border-radius: 8px;">
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
                    <small class="text-muted">নতুন ছবি যোগ হবে, পুরাতন থাকবে</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ভিডিও URL (ঐচ্ছিক)</label>
                    <input type="text" name="video_url" class="form-control" value="<?php echo sanitize($service['video_url']); ?>">
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-currency-dollar me-2"></i>মূল্য</h5>
                
                <div class="mb-3">
                    <label class="form-label">নিয়মিত মূল্য (৳) <span class="text-danger">*</span></label>
                    <input type="number" name="regular_price" class="form-control" step="0.01" required value="<?php echo $service['regular_price']; ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">অফার মূল্য (৳)</label>
                    <input type="number" name="sale_price" class="form-control" step="0.01" value="<?php echo $service['sale_price']; ?>">
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-folder me-2"></i>ক্যাটেগরি</h5>
                
                <div class="mb-3">
                    <select name="category_id" class="form-select">
                        <option value="">-- ক্যাটেগরি নির্বাচন করুন --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $service['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="admin-card mb-4">
                <h5 class="card-title"><i class="bi bi-gear me-2"></i>স্ট্যাটাস</h5>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="status" id="status" <?php echo $service['status'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="status">সক্রিয় (পাবলিশ করুন)</label>
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-admin btn-lg">
                    <i class="bi bi-check-circle me-2"></i>আপডেট করুন
                </button>
                <a href="<?php echo adminUrl('services'); ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>বাতিল করুন
                </a>
            </div>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>
