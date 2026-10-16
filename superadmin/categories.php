<?php
/**
 * Kilagbo - Admin Categories Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'ক্যাটেগরি';
$currentPage = 'categories';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = generateSlug($name) ?: 'category-' . time();
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $showOnTop = isset($_POST['show_on_top']) ? 1 : 0;
        $displayOnHome = isset($_POST['display_on_home']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Make slug unique
        $existingSlug = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
        $existingSlug->execute([$slug]);
        if ($existingSlug->fetchColumn() > 0) {
            $slug .= '-' . time();
        }
        
        // Handle image
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'categories');
            if ($upload['success']) {
                $image = uploadUrl($upload['filename']);
            }
        } elseif (!empty($_POST['image_url'])) {
            $image = sanitize($_POST['image_url']);
        }
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, image, parent_id, show_on_top, display_on_home, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $image, $parentId, $showOnTop, $displayOnHome, $sortOrder, $status]);
        
        setFlash('success', 'ক্যাটেগরি সফলভাবে যোগ করা হয়েছে');
        redirect(adminUrl('categories'));
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $showOnTop = isset($_POST['show_on_top']) ? 1 : 0;
        $displayOnHome = isset($_POST['display_on_home']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Handle image
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'categories');
            if ($upload['success']) {
                $pdo->prepare("UPDATE categories SET image = ? WHERE id = ?")->execute([uploadUrl($upload['filename']), $id]);
            }
        } elseif (!empty($_POST['image_url'])) {
            $pdo->prepare("UPDATE categories SET image = ? WHERE id = ?")->execute([sanitize($_POST['image_url']), $id]);
        }
        
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, parent_id = ?, show_on_top = ?, display_on_home = ?, sort_order = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $parentId, $showOnTop, $displayOnHome, $sortOrder, $status, $id]);
        
        setFlash('success', 'ক্যাটেগরি আপডেট করা হয়েছে');
        redirect(adminUrl('categories'));
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        // Set children's parent to null
        $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        setFlash('success', 'ক্যাটেগরি মুছে ফেলা হয়েছে');
        redirect(adminUrl('categories'));
    }
    
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM categories WHERE id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' টি ক্যাটেগরি মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('categories'));
    }
}

// Fetch categories with product count
$categories = $pdo->query("
    SELECT c.*, 
        p.name as parent_name,
        (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY c.parent_id IS NULL DESC, c.sort_order ASC
")->fetchAll();

// Get parent categories for dropdown
$parentCategories = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="admin-card">
            <h5 class="card-title">নতুন ক্যাটেগরি যোগ করুন</h5>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="mb-3">
                    <label class="form-label">ক্যাটেগরি নাম *</label>
                    <input type="text" name="name" class="form-control" placeholder="ক্যাটেগরি নাম" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">প্যারেন্ট ক্যাটেগরি</label>
                    <select name="parent_id" class="form-select">
                        <option value="">-- কোন প্যারেন্ট নেই --</option>
                        <?php foreach ($parentCategories as $parent): ?>
                        <option value="<?php echo $parent['id']; ?>"><?php echo sanitize($parent['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ইমেজ আপলোড</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">অথবা ইমেজ URL</label>
                    <input type="url" name="image_url" class="form-control" placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">সর্ট অর্ডার</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="show_on_top" class="form-check-input" id="showOnTop">
                        <label class="form-check-label" for="showOnTop">টপ ক্যাটেগরিতে দেখান</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="display_on_home" class="form-check-input" id="displayOnHome">
                        <label class="form-check-label" for="displayOnHome">হোমপেইজে পণ্য দেখান</label>
                        <small class="text-muted d-block">এই ক্যাটেগরির পণ্য হোমপেইজে দেখাবে</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="status" class="form-check-input" id="statusAdd" checked>
                        <label class="form-check-label" for="statusAdd">সক্রিয়়</label>
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
                <h5 class="card-title mb-0">ক্যাটেগরি তালিকা (<?php echo count($categories); ?>)</h5>
                <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()" id="bulkDeleteBtn" style="display: none;">
                    <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন
                </button>
            </div>
            
            <?php if (empty($categories)): ?>
            <p class="text-muted">কোন ক্যাটেগরি নেই</p>
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
                                <th width="60">ইমেজ</th>
                                <th>নাম</th>
                                <th>প্যারেন্ট</th>
                                <th>পণ্য</th>
                                <th>টপ</th>
                                <th>ডিসপ্লে</th>
                                <th>স্ট্যাটাস</th>
                                <th width="100">অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="ids[]" value="<?php echo $cat['id']; ?>" class="item-checkbox" onchange="updateBulkBtn()">
                                </td>
                                <td>
                                    <?php if ($cat['image']): ?>
                                    <img src="<?php echo $cat['image']; ?>" alt="" style="width: 45px; height: 45px; object-fit: cover; border-radius: 50%;">
                                    <?php else: ?>
                                    <div style="width: 45px; height: 45px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-image text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cat['parent_id']): ?>
                                    <span class="text-muted me-2">└</span>
                                    <?php endif; ?>
                                    <?php echo sanitize($cat['name']); ?>
                                </td>
                                <td><?php echo $cat['parent_name'] ? sanitize($cat['parent_name']) : '-'; ?></td>
                                <td><?php echo $cat['product_count']; ?></td>
                                <td>
                                    <?php if ($cat['show_on_top']): ?>
                                    <i class="bi bi-check-circle text-success"></i>
                                    <?php else: ?>
                                    <i class="bi bi-x-circle text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($cat['display_on_home'])): ?>
                                    <i class="bi bi-check-circle text-success"></i>
                                    <?php else: ?>
                                    <i class="bi bi-x-circle text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cat['status']): ?>
                                    <span class="badge bg-success">সক্রিয়</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">নিষ্ক্রিয়</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-dark" onclick='editCategory(<?php echo json_encode($cat); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>)">
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
                    <h5 class="modal-title">ক্যাটেগরি এডিট করুন</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <img id="editImagePreview" src="" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ক্যাটেগরি নাম *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">প্যারেন্ট ক্যাটেগরি</label>
                        <select name="parent_id" id="editParentId" class="form-select">
                            <option value="">-- কোন প্যারেন্ট নেই --</option>
                            <?php foreach ($parentCategories as $parent): ?>
                            <option value="<?php echo $parent['id']; ?>"><?php echo sanitize($parent['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        <label class="form-label">সর্ট অর্ডার</label>
                        <input type="number" name="sort_order" id="editSortOrder" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="show_on_top" class="form-check-input" id="editShowOnTop">
                            <label class="form-check-label" for="editShowOnTop">টপ ক্যাটেগরিতে দেখান</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="display_on_home" class="form-check-input" id="editDisplayOnHome">
                            <label class="form-check-label" for="editDisplayOnHome">হোমপেইজে পণ্য দেখান</label>
                        </div>
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
    function editCategory(cat) {
        document.getElementById('editId').value = cat.id;
        document.getElementById('editName').value = cat.name || '';
        document.getElementById('editParentId').value = cat.parent_id || '';
        document.getElementById('editSortOrder').value = cat.sort_order || 0;
        document.getElementById('editShowOnTop').checked = cat.show_on_top == 1;
        document.getElementById('editDisplayOnHome').checked = cat.display_on_home == 1;
        document.getElementById('editStatus').checked = cat.status == 1;
        document.getElementById('editImageUrl').value = cat.image || '';
        
        const preview = document.getElementById('editImagePreview');
        if (cat.image) {
            preview.src = cat.image;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    
    function deleteCategory(id) {
        if (confirm('আপনি কি নিশ্চিত এই ক্যাটেগরি মুছে ফেলতে চান?')) {
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
        if (confirm('আপনি কি নিশ্চিত নির্বাচিত ক্যাটেগরি গুলো মুছে ফেলতে চান?')) {
            document.getElementById('bulkForm').submit();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
