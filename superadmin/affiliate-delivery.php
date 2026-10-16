<?php
/**
 * Kilagbo Admin - Affiliate Delivery Charges Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'affiliate-delivery';
require_once 'includes/header.php';

$pageTitle = 'অ্যাফিলিয়েট ডেলিভারি চার্জ';

// Handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_charge'])) {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize($_POST['name']);
        $charge = floatval($_POST['charge']);
        $areaType = sanitize($_POST['area_type']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if ($id) {
            $pdo->prepare("UPDATE affiliate_delivery_charges SET name = ?, charge = ?, area_type = ?, status = ? WHERE id = ?")
                ->execute([$name, $charge, $areaType, $status, $id]);
            setFlash('success', 'ডেলিভারি চার্জ আপডেট হয়েছে');
        } else {
            $pdo->prepare("INSERT INTO affiliate_delivery_charges (name, charge, area_type, status) VALUES (?, ?, ?, ?)")
                ->execute([$name, $charge, $areaType, $status]);
            setFlash('success', 'নতুন ডেলিভারি চার্জ যোগ হয়েছে');
        }
        redirect(adminUrl('affiliate-delivery.php'));
    }
    
    if (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM affiliate_delivery_charges WHERE id = ?")->execute([$id]);
        setFlash('success', 'ডেলিভারি চার্জ মুছে ফেলা হয়েছে');
        redirect(adminUrl('affiliate-delivery.php'));
    }
}

// Get all charges
$charges = $pdo->query("SELECT * FROM affiliate_delivery_charges ORDER BY charge ASC")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-truck me-2"></i>অ্যাফিলিয়েট ডেলিভারি চার্জ</h4>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">ডেলিভারি চার্জ তালিকা</div>
                <div class="card-body">
                    <?php if (empty($charges)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-truck" style="font-size: 4rem; color: #ddd;"></i>
                        <p class="mt-3 text-muted">কোনো ডেলিভারি চার্জ নেই</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>নাম</th>
                                    <th>এরিয়া টাইপ</th>
                                    <th>চার্জ</th>
                                    <th>স্ট্যাটাস</th>
                                    <th>অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($charges as $c): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($c['name']); ?></strong></td>
                                    <td>
                                        <?php
                                        $areaLabels = ['inside_dhaka' => 'ঢাকার ভিতরে', 'outside_dhaka' => 'ঢাকার বাইরে', 'custom' => 'কাস্টম'];
                                        echo $areaLabels[$c['area_type']] ?? $c['area_type'];
                                        ?>
                                    </td>
                                    <td><strong><?php echo formatPrice($c['charge']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $c['status'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $c['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $c['id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('মুছে ফেলতে চান?')">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?php echo $c['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">ডেলিভারি চার্জ এডিট</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">নাম</label>
                                                                <input type="text" class="form-control" name="name" value="<?php echo sanitize($c['name']); ?>" required>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">এরিয়া টাইপ</label>
                                                                <select class="form-select" name="area_type">
                                                                    <option value="inside_dhaka" <?php echo $c['area_type'] === 'inside_dhaka' ? 'selected' : ''; ?>>ঢাকার ভিতরে</option>
                                                                    <option value="outside_dhaka" <?php echo $c['area_type'] === 'outside_dhaka' ? 'selected' : ''; ?>>ঢাকার বাইরে</option>
                                                                    <option value="custom" <?php echo $c['area_type'] === 'custom' ? 'selected' : ''; ?>>কাস্টম</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">চার্জ (৳)</label>
                                                                <input type="number" class="form-control" name="charge" value="<?php echo $c['charge']; ?>" step="0.01" min="0" required>
                                                            </div>
                                                            
                                                            <div class="form-check">
                                                                <input type="checkbox" class="form-check-input" name="status" id="status<?php echo $c['id']; ?>" <?php echo $c['status'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="status<?php echo $c['id']; ?>">সক্রিয়</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                                            <button type="submit" name="save_charge" class="btn btn-primary">সেভ করুন</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">নতুন ডেলিভারি চার্জ যোগ করুন</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">নাম</label>
                            <input type="text" class="form-control" name="name" required placeholder="যেমন: ঢাকার ভিতরে">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">এরিয়া টাইপ</label>
                            <select class="form-select" name="area_type">
                                <option value="inside_dhaka">ঢাকার ভিতরে</option>
                                <option value="outside_dhaka">ঢাকার বাইরে</option>
                                <option value="custom">কাস্টম</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">চার্জ (৳)</label>
                            <input type="number" class="form-control" name="charge" step="0.01" min="0" required placeholder="60">
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="status" id="newStatus" checked>
                            <label class="form-check-label" for="newStatus">সক্রিয়</label>
                        </div>
                        
                        <button type="submit" name="save_charge" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i>যোগ করুন
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
