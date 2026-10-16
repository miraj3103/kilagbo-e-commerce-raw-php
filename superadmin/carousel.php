<?php
/**
 * Kilagbo - Admin Carousel Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'ক্যারোসেল';
$currentPage = 'carousel';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $title = sanitize($_POST['title'] ?? '');
        $link = sanitize($_POST['link'] ?? '#');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Handle image upload or URL
        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'carousel');
            if ($upload['success']) {
                $image = uploadUrl($upload['filename']);
            }
        } elseif (!empty($_POST['image_url'])) {
            $image = sanitize($_POST['image_url']);
        }
        
        if ($image) {
            $stmt = $pdo->prepare("INSERT INTO carousel (image, title, link, sort_order, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$image, $title, $link, $sortOrder, $status]);
            setFlash('success', 'ক্যারোসেল সফলভাবে যোগ করা হয়েছে');
        } else {
            setFlash('error', 'ইমেজ আপলোড করুন');
        }
        redirect(adminUrl('carousel'));
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $title = sanitize($_POST['title'] ?? '');
        $link = sanitize($_POST['link'] ?? '#');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Check for new image
        $imageUpdate = '';
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'carousel');
            if ($upload['success']) {
                $imageUpdate = ", image = '" . uploadUrl($upload['filename']) . "'";
            }
        } elseif (!empty($_POST['image_url'])) {
            $imageUpdate = ", image = '" . sanitize($_POST['image_url']) . "'";
        }
        
        $sql = "UPDATE carousel SET title = ?, link = ?, sort_order = ?, status = ? $imageUpdate WHERE id = ?";
        $stmt = $pdo->prepare("UPDATE carousel SET title = ?, link = ?, sort_order = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $link, $sortOrder, $status, $id]);
        
        if ($imageUpdate) {
            $pdo->exec("UPDATE carousel SET " . ltrim($imageUpdate, ', ') . " WHERE id = $id");
        }
        
        setFlash('success', 'ক্যারোসেল আপডেট করা হয়েছে');
        redirect(adminUrl('carousel'));
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM carousel WHERE id = ?")->execute([$id]);
        setFlash('success', 'ক্যারোসেল মুছে ফেলা হয়েছে');
        redirect(adminUrl('carousel'));
    }
    
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM carousel WHERE id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' টি ক্যারোসেল মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('carousel'));
    }
}

// Fetch carousel items
$carousels = $pdo->query("SELECT * FROM carousel ORDER BY sort_order ASC")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="admin-card">
            <h5 class="card-title">নতুন ক্যারোসেল যোগ করুন</h5>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="mb-3">
                    <label class="form-label">ইমেজ আপলোড করুন</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">অথবা ইমেজ URL দিন</label>
                    <input type="url" name="image_url" class="form-control" placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">টাইটেল (ঐচ্ছিক)</label>
                    <input type="text" name="title" class="form-control" placeholder="ব্যানার টাইটেল">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">লিংক</label>
                    <input type="text" name="link" class="form-control" value="#" placeholder="https://example.com">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সর্ট অর্ডার</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="status" class="form-check-input" id="statusAdd" checked>
                        <label class="form-check-label" for="statusAdd">সক্রিয়</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-admin w-100">
                    <i class="bi bi-plus-circle me-2"></i>যোগ করুন
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-8 mb-4">
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">ক্যারোসেল তালিকা</h5>
                <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()" id="bulkDeleteBtn" style="display: none;">
                    <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন
                </button>
            </div>
            
            <?php if (empty($carousels)): ?>
            <p class="text-muted">কোন ক্যারোসেল নেই</p>
            <?php else: ?>
            <form id="bulkForm" method="POST">
                <input type="hidden" name="action" value="bulk_delete">
                
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th width="100">ইমেজ</th>
                                <th>টাইটেল</th>
                                <th>অর্ডার</th>
                                <th>স্ট্যাটাস</th>
                                <th width="120">অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($carousels as $item): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="ids[]" value="<?php echo $item['id']; ?>" class="item-checkbox" onchange="updateBulkBtn()">
                                </td>
                                <td>
                                    <img src="<?php echo $item['image']; ?>" alt="" style="width: 80px; height: 40px; object-fit: cover; border-radius: 4px;">
                                </td>
                                <td><?php echo sanitize($item['title']) ?: '-'; ?></td>
                                <td><?php echo $item['sort_order']; ?></td>
                                <td>
                                    <?php if ($item['status']): ?>
                                    <span class="badge bg-success">সক্রিয়</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">নিষ্ক্রিয়</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="editCarousel(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCarousel(<?php echo $item['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="modal-header">
                    <h5 class="modal-title">ক্যারোসেল এডিট করুন</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <img id="editImagePreview" src="" alt="" style="width: 100%; max-height: 150px; object-fit: cover; border-radius: 8px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">নতুন ইমেজ (ঐচ্ছিক)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">অথবা ইমেজ URL</label>
                        <input type="url" name="image_url" id="editImageUrl" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">টাইটেল</label>
                        <input type="text" name="title" id="editTitle" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">লিংক</label>
                        <input type="text" name="link" id="editLink" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">সর্ট অর্ডার</label>
                        <input type="number" name="sort_order" id="editSortOrder" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="status" class="form-check-input" id="editStatus">
                            <label class="form-check-label" for="editStatus">সক্রিয়</label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-admin">আপডেট করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
    function editCarousel(item) {
        document.getElementById('editId').value = item.id;
        document.getElementById('editTitle').value = item.title || '';
        document.getElementById('editLink').value = item.link || '';
        document.getElementById('editSortOrder').value = item.sort_order || 0;
        document.getElementById('editStatus').checked = item.status == 1;
        document.getElementById('editImagePreview').src = item.image;
        document.getElementById('editImageUrl').value = item.image;
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    
    function deleteCarousel(id) {
        if (confirm('আপনি কি নিশ্চিত এই ক্যারোসেল মুছে ফেলতে চান?')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
    
    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = checked);
        updateBulkBtn();
    }
    
    function updateBulkBtn() {
        const checked = document.querySelectorAll('.item-checkbox:checked').length;
        document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? 'inline-block' : 'none';
    }
    
    function bulkDelete() {
        if (confirm('আপনি কি নিশ্চিত নির্বাচিত ক্যারোসেল গুলো মুছে ফেলতে চান?')) {
            document.getElementById('bulkForm').submit();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
