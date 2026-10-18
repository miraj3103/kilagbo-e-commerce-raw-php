<?php
/**
 * Kilagbo - Retail Delivery Charges
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'খুচরা ডেলিভারি চার্জ';
$currentPage = 'retail-delivery';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deliveryType = sanitize($_POST['delivery_type'] ?? 'paid');
    $insideDhaka = sanitize($_POST['inside_dhaka_charge'] ?? '60');
    $outsideDhaka = sanitize($_POST['outside_dhaka_charge'] ?? '130');
    $insideDhakaLabel = sanitize($_POST['inside_dhaka_label'] ?? 'ঢাকার ভেতরে');
    $outsideDhakaLabel = sanitize($_POST['outside_dhaka_label'] ?? 'ঢাকার বাহিরে');
    
    updateSetting('delivery_type', $deliveryType);
    updateSetting('inside_dhaka_charge', $insideDhaka);
    updateSetting('outside_dhaka_charge', $outsideDhaka);
    updateSetting('inside_dhaka_label', $insideDhakaLabel);
    updateSetting('outside_dhaka_label', $outsideDhakaLabel);
    
    setFlash('success', 'ডেলিভারি চার্জ আপডেট করা হয়েছে');
    redirect(adminUrl('retail-delivery'));
}

$deliveryType = getSettings('delivery_type') ?: 'paid';
$insideDhakaCharge = getSettings('inside_dhaka_charge') ?: '60';
$outsideDhakaCharge = getSettings('outside_dhaka_charge') ?: '130';
$insideDhakaLabel = getSettings('inside_dhaka_label') ?: 'ঢাকার ভেতরে';
$outsideDhakaLabel = getSettings('outside_dhaka_label') ?: 'ঢাকার বাহিরে';

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="row">
    <div class="col-lg-6">
        <div class="admin-card">
            <h5 class="card-title">
                <i class="bi bi-truck me-2"></i>খুচরা ডেলিভারি চার্জ সেটিংস
            </h5>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">ডেলিভারি টাইপ</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="delivery_type" id="deliveryFree" value="free" <?php echo $deliveryType == 'free' ? 'checked' : ''; ?> onchange="toggleDeliveryFields()">
                        <label class="form-check-label" for="deliveryFree">
                            <i class="bi bi-gift me-1"></i>ফ্রী ডেলিভারী
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="delivery_type" id="deliveryPaid" value="paid" <?php echo $deliveryType == 'paid' ? 'checked' : ''; ?> onchange="toggleDeliveryFields()">
                        <label class="form-check-label" for="deliveryPaid">
                            <i class="bi bi-cash me-1"></i>পেইড ডেলিভারী (এলাকা অনুযায়ী)
                        </label>
                    </div>
                </div>
                
                <div id="paidDeliveryFields" style="<?php echo $deliveryType == 'free' ? 'display: none;' : ''; ?>">
                    <div class="mb-3">
                        <label class="form-label">ঢাকার ভেতরে লেবেল</label>
                        <input type="text" name="inside_dhaka_label" class="form-control" value="<?php echo $insideDhakaLabel; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ঢাকার ভেতরে চার্জ (৳)</label>
                        <input type="number" name="inside_dhaka_charge" class="form-control" value="<?php echo $insideDhakaCharge; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ঢাকার বাহিরে লেবেল</label>
                        <input type="text" name="outside_dhaka_label" class="form-control" value="<?php echo $outsideDhakaLabel; ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">ঢাকার বাহিরে চার্জ (৳)</label>
                        <input type="number" name="outside_dhaka_charge" class="form-control" value="<?php echo $outsideDhakaCharge; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="admin-card">
            <h5 class="card-title">
                <i class="bi bi-info-circle me-2"></i>তথ্য
            </h5>
            <p class="text-muted mb-2">
                এই সেটিংস শুধুমাত্র <strong>খুচরা বিক্রয়</strong> (Retail) সেকশনের জন্য প্রযোজ্য।
            </p>
            <ul class="text-muted" style="font-size: 0.9rem;">
                <li><strong>ফ্রী ডেলিভারী:</strong> কাস্টমার কোন ডেলিভারি চার্জ দিবে না</li>
                <li><strong>পেইড ডেলিভারী:</strong> এলাকা অনুযায়ী ডেলিভারি চার্জ যোগ হবে</li>
            </ul>
        </div>
    </div>
</div>

<script>
function toggleDeliveryFields() {
    const paidFields = document.getElementById('paidDeliveryFields');
    const isPaid = document.getElementById('deliveryPaid').checked;
    paidFields.style.display = isPaid ? 'block' : 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
