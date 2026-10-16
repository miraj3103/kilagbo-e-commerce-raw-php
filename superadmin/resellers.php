<?php
/**
 * Kilagbo Admin - Resellers Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resellers';
require_once 'includes/header.php';

$pageTitle = 'রিসেলার ব্যবস্থাপনা';

// Handle approve
if (isset($_POST['approve'])) {
    $resellerId = intval($_POST['reseller_id']);
    $pdo->prepare("UPDATE resellers SET status = 'approved', approved_at = NOW() WHERE id = ?")->execute([$resellerId]);
    setFlash('success', 'রিসেলার অনুমোদিত হয়েছে');
    redirect(adminUrl('resellers.php'));
}

// Handle reject
if (isset($_POST['reject'])) {
    $resellerId = intval($_POST['reseller_id']);
    $reason = sanitize($_POST['reason'] ?? '');
    
    $stmt = $pdo->prepare("SELECT email, phone FROM resellers WHERE id = ?");
    $stmt->execute([$resellerId]);
    $resellerData = $stmt->fetch();
    
    if ($resellerData) {
        $pdo->prepare("INSERT INTO reseller_rejected_credentials (email, phone, reason) VALUES (?, ?, ?)")->execute([$resellerData['email'], $resellerData['phone'], $reason]);
        $pdo->prepare("UPDATE resellers SET status = 'rejected' WHERE id = ?")->execute([$resellerId]);
        setFlash('success', 'রিসেলার প্রত্যাখ্যাত হয়েছে');
    }
    redirect(adminUrl('resellers.php'));
}

// Handle Approve Deletion
if (isset($_POST['approve_deletion'])) {
    $resellerId = intval($_POST['reseller_id']);
    
    // Get reseller data for cleanup of rejected credentials
    $stmt = $pdo->prepare("SELECT email, phone FROM resellers WHERE id = ?");
    $stmt->execute([$resellerId]);
    $resellerData = $stmt->fetch();
    
    if ($resellerData) {
        // Delete related data
        $pdo->prepare("DELETE FROM reseller_balance_history WHERE reseller_id = ?")->execute([$resellerId]);
        $pdo->prepare("DELETE FROM reseller_withdrawals WHERE reseller_id = ?")->execute([$resellerId]);
        $pdo->prepare("DELETE FROM resell_order_items WHERE order_id IN (SELECT id FROM resell_orders WHERE reseller_id = ?)")->execute([$resellerId]);
        $pdo->prepare("DELETE FROM resell_orders WHERE reseller_id = ?")->execute([$resellerId]);
        
        // Delete from rejected credentials if any
        $pdo->prepare("DELETE FROM reseller_rejected_credentials WHERE email = ? OR phone = ?")->execute([$resellerData['email'], $resellerData['phone']]);
        
        // Delete the reseller
        $pdo->prepare("DELETE FROM resellers WHERE id = ?")->execute([$resellerId]);
        
        setFlash('success', 'রিসেলার অ্যাকাউন্ট স্থায়ীভাবে ডিলিট করা হয়েছে');
    } else {
        setFlash('error', 'রিসেলার পাওয়া যায়নি');
    }
    redirect(adminUrl('resellers.php'));
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_resellers'])) {
    $selectedIds = array_map('intval', $_POST['selected_resellers']);
    
    if (!empty($selectedIds)) {
        $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
        
        // Delete related data first
        $pdo->prepare("DELETE FROM reseller_balance_history WHERE reseller_id IN ($placeholders)")->execute($selectedIds);
        $pdo->prepare("DELETE FROM reseller_withdrawals WHERE reseller_id IN ($placeholders)")->execute($selectedIds);
        $pdo->prepare("DELETE FROM resell_order_items WHERE order_id IN (SELECT id FROM resell_orders WHERE reseller_id IN ($placeholders))")->execute($selectedIds);
        $pdo->prepare("DELETE FROM resell_orders WHERE reseller_id IN ($placeholders)")->execute($selectedIds);
        $pdo->prepare("DELETE FROM reseller_rejected_credentials WHERE email IN (SELECT email FROM resellers WHERE id IN ($placeholders)) OR phone IN (SELECT phone FROM resellers WHERE id IN ($placeholders))")->execute(array_merge($selectedIds, $selectedIds));
        
        // Delete resellers
        $pdo->prepare("DELETE FROM resellers WHERE id IN ($placeholders)")->execute($selectedIds);
        
        setFlash('success', count($selectedIds) . ' টি রিসেলার এবং তাদের সকল ডেটা মুছে ফেলা হয়েছে');
        redirect(adminUrl('resellers.php'));
    }
}

// Get filters
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($status) {
    $where .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("SELECT * FROM resellers $where ORDER BY created_at DESC");
$stmt->execute($params);
$resellers = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM resellers")->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-people me-2"></i>রিসেলার ব্যবস্থাপনা</h4>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p class="mb-0">মোট রিসেলার</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p class="mb-0">পেন্ডিং</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p class="mb-0">অনুমোদিত</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p class="mb-0">প্রত্যাখ্যাত</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="নাম, ইমেইল বা ফোন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">সব স্ট্যাটাস</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>পেন্ডিং</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>অনুমোদিত</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>প্রত্যাখ্যাত</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">খুঁজুন</button>
                </div>
            </form>
        </div>
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>রিসেলার তালিকা</span>
            <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" style="display: none;" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                <i class="bi bi-trash"></i> বাল্ক ডিলিট (0)
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($resellers)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people" style="font-size: 4rem; color: #ddd;"></i>
                <p class="mt-3 text-muted">কোনো রিসেলার নেই</p>
            </div>
            <?php else: ?>
            <form method="POST" id="bulkForm">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>নাম</th>
                                <th>ইমেইল</th>
                                <th>ফোন</th>
                                <th>ব্যালেন্স</th>
                                <th>অর্ডার</th>
                                <th>স্ট্যাটাস</th>
                                <th>ডিলিট রিকোয়েস্ট</th>
                                <th>তারিখ</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resellers as $reseller): ?>
                            <tr class="<?php echo ($reseller['balance'] < 200) ? 'table-danger' : ''; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input reseller-checkbox" name="selected_resellers[]" value="<?php echo $reseller['id']; ?>">
                                </td>
                                <td>
                                    <?php 
                                    $iconColor = $reseller['deletion_request'] ? 'text-danger' : 'text-primary';
                                    if ($reseller['balance'] < 200) $iconColor = 'text-danger';
                                    ?>
                                    <i class="bi bi-person-circle <?php echo $iconColor; ?> me-1"></i>
                                    <strong><?php echo sanitize($reseller['name']); ?></strong>
                                    <?php if ($reseller['balance'] < 200): ?>
                                        <br><small class="badge bg-danger">Low Balance</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $reseller['email']; ?></td>
                                <td><?php echo $reseller['phone']; ?></td>
                                <td>
                                    <span class="<?php echo ($reseller['balance'] < 200) ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo formatPrice($reseller['balance']); ?>
                                    </span>
                                </td>
                                <td><?php echo $reseller['total_orders']; ?></td>
                                <td>
                                    <?php if ($reseller['status'] === 'pending'): ?>
                                    <span class="badge bg-warning">পেন্ডিং</span>
                                    <?php elseif ($reseller['status'] === 'approved'): ?>
                                    <span class="badge bg-success">অনুমোদিত</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">প্রত্যাখ্যাত</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($reseller['deletion_request']): ?>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteRequestModal<?php echo $reseller['id']; ?>">
                                            <i class="bi bi-trash"></i> বিস্তারিত
                                        </button>

                                        <!-- Deletion Modal -->
                                        <div class="modal fade" id="deleteRequestModal<?php echo $reseller['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <input type="hidden" name="reseller_id" value="<?php echo $reseller['id']; ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title text-danger">ডিলিট রিকোয়েস্ট বিস্তারিত</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>রিসেলার নাম:</strong> <?php echo sanitize($reseller['name']); ?></p>
                                                            <p><strong>ইমেইল:</strong> <?php echo $reseller['email']; ?></p>
                                                            <p><strong>ডিলিট করার কারণ:</strong></p>
                                                            <div class="bg-light p-3 border rounded mb-3">
                                                                <?php echo nl2br(sanitize($reseller['deletion_request_reason'] ?: 'কোনো কারণ উল্লেখ করেননি')); ?>
                                                            </div>
                                                            <div class="alert alert-danger">
                                                                <strong>সতর্কতা:</strong> ডিলিট কনফার্ম করলে এই রিসেলারের সকল ডাটা স্থায়ীভাবে মুছে যাবে।
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                            <button type="submit" name="approve_deletion" class="btn btn-danger">কনফার্ম ডিলিট</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">কোন রিকোয়েস্ট নেই</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($reseller['created_at'])); ?></td>
                                <td>
                                    <?php if ($reseller['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="reseller_id" value="<?php echo $reseller['id']; ?>">
                                        <button type="submit" name="approve" class="btn btn-sm btn-success" onclick="return confirm('অনুমোদন করতে চান?')">
                                            <i class="bi bi-check-lg"></i> অনুমোদন
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $reseller['id']; ?>">
                                        <i class="bi bi-x-lg"></i> প্রত্যাখ্যান
                                    </button>
                                    
                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?php echo $reseller['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">প্রত্যাখ্যান করুন</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="reseller_id" value="<?php echo $reseller['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">প্রত্যাখ্যানের কারণ</label>
                                                            <textarea class="form-control" name="reason" rows="3" placeholder="কারণ লিখুন (ঐচ্ছিক)"></textarea>
                                                        </div>
                                                        <div class="alert alert-warning">
                                                            <small><i class="bi bi-exclamation-triangle me-1"></i>প্রত্যাখ্যাত হলে এই ইমেইল/ফোন দিয়ে আর অ্যাকাউন্ট তৈরি করা যাবে না।</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                        <button type="submit" name="reject" class="btn btn-danger">প্রত্যাখ্যান করুন</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <a href="<?php echo adminUrl('reseller-view.php?id=' . $reseller['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> বিবরণ
                                    </a>
                                    <?php endif; ?>
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

    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="bulkDeleteForm">
                    <input type="hidden" name="bulk_delete" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title">বাল্ক ডিলিট নিশ্চিত করুন</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>আপনি কি নিশ্চিত যে এই রিসেলারদের এবং তাদের সকল ডেটা (অর্ডার, ব্যালেন্স, উত্তোলন ইতিহাস) মুছে ফেলতে চান?</p>
                        <div class="alert alert-danger">
                            <strong>সতর্কতা:</strong> এই অ্যাকশন পূর্বাবস্থায় ফেরানো যাবে না।
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                        <button type="submit" class="btn btn-danger">ডিলিট করুন</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Bulk delete functionality
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const resellerCheckboxes = document.querySelectorAll('.reseller-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
    
    // Select all functionality
    selectAllCheckbox.addEventListener('change', function() {
        resellerCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkDeleteButton();
    });
    
    // Individual checkbox change
    resellerCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkDeleteButton();
            
            // Update select all checkbox
            const checkedBoxes = document.querySelectorAll('.reseller-checkbox:checked');
            selectAllCheckbox.checked = checkedBoxes.length === resellerCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < resellerCheckboxes.length;
        });
    });
    
    function updateBulkDeleteButton() {
        const selectedCount = document.querySelectorAll('.reseller-checkbox:checked').length;
        if (selectedCount > 0) {
            bulkDeleteBtn.style.display = 'inline-block';
            bulkDeleteBtn.innerHTML = `<i class="bi bi-trash"></i> বাল্ক ডিলিট (${selectedCount})`;
        } else {
            bulkDeleteBtn.style.display = 'none';
        }
    }
    
    // Handle bulk delete confirmation
    bulkDeleteBtn.addEventListener('click', function() {
        const selectedCheckboxes = document.querySelectorAll('.reseller-checkbox:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        // Add selected IDs to the form
        const form = document.getElementById('bulkDeleteForm');
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_resellers[]';
            input.value = id;
            form.appendChild(input);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
