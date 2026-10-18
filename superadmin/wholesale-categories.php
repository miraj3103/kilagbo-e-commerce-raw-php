<?php
/**
 * Kilagbo - Admin Wholesale Categories
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পাইকারি ক্যাটেগরি';
$currentPage = 'wholesale-categories';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_wholesale') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE categories SET wholesale_enabled = NOT wholesale_enabled WHERE id = ?")->execute([$id]);
        setFlash('success', 'ক্যাটেগরি আপডেট হয়েছে');
        redirect(adminUrl('wholesale-categories'));
    }
    
    if ($action === 'toggle_top') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE categories SET show_on_top = NOT show_on_top WHERE id = ?")->execute([$id]);
        setFlash('success', 'টপ ক্যাটেগরি আপডেট হয়েছে');
        redirect(adminUrl('wholesale-categories'));
    }
}

// Get all categories
$categories = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM wholesale_products wp WHERE wp.category_id = c.id) as wholesale_product_count
    FROM categories c 
    WHERE c.status = 1 
    ORDER BY c.name
")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="admin-card mb-4">
    <p class="mb-0">
        <i class="bi bi-info-circle me-2"></i>
        এখানে আপনি ক্যাটেগরিগুলো পাইকারি বিভাগে সক্রিয়/নিষ্ক্রিয় করতে পারবেন। সক্রিয় ক্যাটেগরিগুলো পাইকারি হোমপেজে দেখাবে।
    </p>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th width="60">ছবি</th>
                    <th>ক্যাটেগরি নাম</th>
                    <th>পাইকারি পণ্য</th>
                    <th>পাইকারিতে সক্রিয়</th>
                    <th>টপ ক্যাটেগরি</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="5" class="text-center py-4">কোনো ক্যাটেগরি পাওয়া যায়নি</td>
                </tr>
                <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <?php if ($cat['image']): ?>
                        <img src="<?php echo imageUrl($cat['image']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                        <?php else: ?>
                        <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-folder text-muted"></i>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo $cat['name']; ?></strong></td>
                    <td>
                        <span class="badge bg-info"><?php echo $cat['wholesale_product_count']; ?> টি পণ্য</span>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle_wholesale">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <button type="submit" class="btn btn-sm <?php echo $cat['wholesale_enabled'] ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                <?php if ($cat['wholesale_enabled']): ?>
                                <i class="bi bi-check-lg me-1"></i>সক্রিয়
                                <?php else: ?>
                                <i class="bi bi-x-lg me-1"></i>নিষ্ক্রিয়
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle_top">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <button type="submit" class="btn btn-sm <?php echo $cat['show_on_top'] ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                <?php if ($cat['show_on_top']): ?>
                                <i class="bi bi-star-fill me-1"></i>টপ
                                <?php else: ?>
                                <i class="bi bi-star me-1"></i>টপ নয়
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
