<?php
/**
 * Kilagbo - Admin Services Management
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'সার্ভিস সমূহ';
$currentPage = 'services';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
        setFlash('success', 'সার্ভিস মুছে ফেলা হয়েছে');
        redirect(adminUrl('services'));
    }
    
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM services WHERE id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' টি সার্ভিস মুছে ফেলা হয়েছে');
        }
        redirect(adminUrl('services'));
    }
    
    if ($action === 'toggle_status') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE services SET status = NOT status WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search and filter
$search = sanitize($_GET['search'] ?? '');
$categoryFilter = intval($_GET['category'] ?? 0);

$whereClause = "1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (s.name LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter) {
    $whereClause .= " AND s.category_id = ?";
    $params[] = $categoryFilter;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM services s WHERE $whereClause");
$countStmt->execute($params);
$totalServices = $countStmt->fetchColumn();
$totalPages = ceil($totalServices / $perPage);

// Fetch services
$stmt = $pdo->prepare("
    SELECT s.*, c.name as category_name 
    FROM services s 
    LEFT JOIN categories c ON s.category_id = c.id 
    WHERE $whereClause 
    ORDER BY s.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$services = $stmt->fetchAll();

// Fetch categories for filter
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-primary fs-6"><?php echo $totalServices; ?> টি সার্ভিস</span>
    </div>
    <a href="<?php echo adminUrl('service-add'); ?>" class="btn btn-admin">
        <i class="bi bi-plus-circle me-2"></i>নতুন সার্ভিস যোগ করুন
    </a>
</div>

<div class="admin-card">
    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="সার্ভিস খুঁজুন..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">সকল ক্যাটেগরি</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo sanitize($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-admin-outline w-100">
                <i class="bi bi-search me-1"></i>খুঁজুন
            </button>
        </div>
        <?php if ($search || $categoryFilter): ?>
        <div class="col-md-2">
            <a href="<?php echo adminUrl('services'); ?>" class="btn btn-secondary w-100">রিসেট</a>
        </div>
        <?php endif; ?>
    </form>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0">সার্ভিস তালিকা</h5>
        <div id="bulkActions" style="display: none;">
            <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                <i class="bi bi-trash me-1"></i>নির্বাচিত মুছুন
            </button>
        </div>
    </div>
    
    <?php if (empty($services)): ?>
    <div class="text-center py-5">
        <i class="bi bi-briefcase display-1 text-muted"></i>
        <p class="text-muted mt-3">কোন সার্ভিস পাওয়া যায়নি</p>
        <a href="<?php echo adminUrl('service-add'); ?>" class="btn btn-admin">
            <i class="bi bi-plus-circle me-2"></i>প্রথম সার্ভিস যোগ করুন
        </a>
    </div>
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
                        <th width="80">ছবি</th>
                        <th>সার্ভিস</th>
                        <th>ক্যাটেগরি</th>
                        <th>মূল্য</th>
                        <th>সময়কাল</th>
                        <th>স্ট্যাটাস</th>
                        <th width="120">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?php echo $service['id']; ?>" class="service-checkbox" onchange="updateBulkActions()">
                        </td>
                        <td>
                            <?php if ($service['image']): ?>
                            <img src="<?php echo $service['image']; ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                            <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-briefcase text-muted"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo sanitize($service['name']); ?></strong>
                            <?php if ($service['short_description']): ?>
                            <br><small class="text-muted"><?php echo mb_substr(sanitize($service['short_description']), 0, 50); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($service['category_name']): ?>
                            <span class="badge bg-secondary"><?php echo sanitize($service['category_name']); ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($service['sale_price']): ?>
                            <span class="text-decoration-line-through text-muted">৳<?php echo number_format($service['regular_price']); ?></span><br>
                            <strong class="text-success">৳<?php echo number_format($service['sale_price']); ?></strong>
                            <?php else: ?>
                            <strong>৳<?php echo number_format($service['regular_price']); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($service['duration']): ?>
                            <small><i class="bi bi-clock me-1"></i><?php echo sanitize($service['duration']); ?></small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" <?php echo $service['status'] ? 'checked' : ''; ?> onchange="toggleStatus(<?php echo $service['id']; ?>)">
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo adminUrl('service-edit/' . $service['id']); ?>" class="btn btn-sm btn-outline-primary" title="এডিট">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteService(<?php echo $service['id']; ?>)" title="মুছুন">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.service-checkbox');
    const selectAll = document.getElementById('selectAll');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.service-checkbox:checked');
    document.getElementById('bulkActions').style.display = checked.length > 0 ? 'block' : 'none';
}

function bulkDelete() {
    if (confirm('নির্বাচিত সার্ভিসগুলো মুছে ফেলতে চান?')) {
        document.getElementById('bulkForm').submit();
    }
}

function deleteService(id) {
    if (confirm('এই সার্ভিসটি মুছে ফেলতে চান?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleStatus(id) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_status&id=${id}`
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
