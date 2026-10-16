<?php
/**
 * Kilagbo Admin - Resell Delivery Charges Management
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-delivery';
require_once 'includes/header.php';

$pageTitle = 'ডেলিভারি চার্জ';

// Handle add/edit delivery charge
if (isset($_POST['save_charge'])) {
    $chargeId = intval($_POST['charge_id'] ?? 0);
    $name = sanitize($_POST['name']);
    $charge = floatval($_POST['charge']);
    
    if (empty($name)) {
        setFlash('error', 'ডেলিভারি নাম দিন');
    } else {
        if ($chargeId > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE resell_delivery_charges SET name = ?, charge = ? WHERE id = ?");
            $stmt->execute([$name, $charge, $chargeId]);
            setFlash('success', 'ডেলিভারি চার্জ আপডেট হয়েছে');
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO resell_delivery_charges (name, charge) VALUES (?, ?)");
            $stmt->execute([$name, $charge]);
            setFlash('success', 'ডেলিভারি চার্জ যোগ হয়েছে');
        }
    }
    redirect(adminUrl('resell-delivery.php'));
}

// Handle delete delivery charge
if (isset($_GET['delete'])) {
    $chargeId = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM resell_delivery_charges WHERE id = ?")->execute([$chargeId]);
    setFlash('success', 'ডেলিভারি চার্জ মুছে ফেলা হয়েছে');
    redirect(adminUrl('resell-delivery.php'));
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $chargeId = intval($_GET['toggle']);
    $pdo->prepare("UPDATE resell_delivery_charges SET status = NOT status WHERE id = ?")->execute([$chargeId]);
    setFlash('success', 'স্ট্যাটাস পরিবর্তন হয়েছে');
    redirect(adminUrl('resell-delivery.php'));
}

// Handle add custom product delivery
if (isset($_POST['add_custom'])) {
    $productId = intval($_POST['product_id']);
    $customCharge = floatval($_POST['custom_charge']);
    
    if ($productId > 0) {
        // Check if already exists
        $stmt = $pdo->prepare("SELECT id FROM resell_product_delivery WHERE product_id = ?");
        $stmt->execute([$productId]);
        
        if ($stmt->fetch()) {
            // Update
            $stmt = $pdo->prepare("UPDATE resell_product_delivery SET custom_charge = ? WHERE product_id = ?");
            $stmt->execute([$customCharge, $productId]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO resell_product_delivery (product_id, custom_charge) VALUES (?, ?)");
            $stmt->execute([$productId, $customCharge]);
        }
        setFlash('success', 'কাস্টম ডেলিভারি চার্জ যোগ হয়েছে');
    }
    redirect(adminUrl('resell-delivery.php'));
}

// Handle remove custom product delivery
if (isset($_GET['remove_custom'])) {
    $productId = intval($_GET['remove_custom']);
    $pdo->prepare("DELETE FROM resell_product_delivery WHERE product_id = ?")->execute([$productId]);
    setFlash('success', 'কাস্টম ডেলিভারি চার্জ সরানো হয়েছে');
    redirect(adminUrl('resell-delivery.php'));
}

// Get all delivery charges
$deliveryCharges = $pdo->query("SELECT * FROM resell_delivery_charges ORDER BY created_at DESC")->fetchAll();

// Get products with custom delivery charges
$customProducts = $pdo->query("
    SELECT rpd.*, rp.name as product_name, rp.image as product_image 
    FROM resell_product_delivery rpd 
    LEFT JOIN resell_products rp ON rpd.product_id = rp.id 
    ORDER BY rpd.created_at DESC
")->fetchAll();

// Get all resell products for dropdown
$allProducts = $pdo->query("SELECT id, name FROM resell_products WHERE status = 1 ORDER BY name")->fetchAll();

// Edit mode
$editCharge = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM resell_delivery_charges WHERE id = ?");
    $stmt->execute([$editId]);
    $editCharge = $stmt->fetch();
}
?>

<style>
    .charge-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    
    .charge-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 10px;
        background: #f8f9fa;
    }
    
    .charge-item:hover {
        background: #fff;
        border-color: var(--primary);
    }
    
    .charge-info h6 {
        margin: 0 0 5px;
        font-weight: 600;
    }
    
    .charge-info .price {
        color: var(--primary);
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .product-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 10px;
        background: #f8f9fa;
    }
    
    .product-item img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .product-item .info {
        flex: 1;
    }
    
    .product-item .info h6 {
        margin: 0 0 5px;
        font-weight: 600;
    }
    
    .product-item .custom-price {
        color: #28a745;
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
    }
    
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-truck me-2"></i>রিসেল ডেলিভারি চার্জ</h4>
    </div>
    
    <?php showFlash(); ?>
    
    <div class="row">
        <!-- Add/Edit Delivery Charge Form -->
        <div class="col-lg-4">
            <div class="charge-card">
                <h5 class="mb-3">
                    <i class="bi bi-plus-circle me-2"></i>
                    <?php echo $editCharge ? 'ডেলিভারি চার্জ এডিট' : 'নতুন ডেলিভারি চার্জ'; ?>
                </h5>
                
                <form method="POST">
                    <input type="hidden" name="charge_id" value="<?php echo $editCharge['id'] ?? 0; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">ডেলিভারি নাম *</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo sanitize($editCharge['name'] ?? ''); ?>" 
                               placeholder="যেমন: ঢাকার ভিতরে" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ডেলিভারি খরচ (৳) *</label>
                        <input type="number" class="form-control" name="charge" step="0.01" min="0"
                               value="<?php echo $editCharge['charge'] ?? ''; ?>" 
                               placeholder="0.00" required>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="save_charge" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>
                            <?php echo $editCharge ? 'আপডেট করুন' : 'যোগ করুন'; ?>
                        </button>
                        <?php if ($editCharge): ?>
                        <a href="<?php echo adminUrl('resell-delivery.php'); ?>" class="btn btn-secondary">বাতিল</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Custom Product Delivery -->
            <div class="charge-card">
                <h5 class="mb-3"><i class="bi bi-box-seam me-2"></i>প্রোডাক্ট কাস্টম চার্জ</h5>
                <p class="text-muted small mb-3">নির্দিষ্ট প্রোডাক্টে আলাদা ডেলিভারি চার্জ সেট করুন</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">প্রোডাক্ট সিলেক্ট করুন *</label>
                        <select class="form-select" name="product_id" id="productSelect" required>
                            <option value="">-- প্রোডাক্ট খুঁজুন --</option>
                            <?php foreach ($allProducts as $product): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo sanitize($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">কাস্টম ডেলিভারি চার্জ (৳) *</label>
                        <input type="number" class="form-control" name="custom_charge" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    
                    <button type="submit" name="add_custom" class="btn btn-success w-100">
                        <i class="bi bi-plus-lg me-1"></i>কাস্টম চার্জ যোগ করুন
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Delivery Charges List -->
        <div class="col-lg-8">
            <div class="charge-card">
                <h5 class="mb-3"><i class="bi bi-list-ul me-2"></i>ডিফল্ট ডেলিভারি চার্জ সমূহ</h5>
                
                <?php if (empty($deliveryCharges)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                    <p class="mt-2">কোনো ডেলিভারি চার্জ নেই</p>
                </div>
                <?php else: ?>
                <?php foreach ($deliveryCharges as $dc): ?>
                <div class="charge-item">
                    <div class="charge-info">
                        <h6><?php echo sanitize($dc['name']); ?></h6>
                        <span class="price"><?php echo formatPrice($dc['charge']); ?></span>
                        <span class="ms-2 <?php echo $dc['status'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $dc['status'] ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                        </span>
                    </div>
                    <div class="actions">
                        <a href="<?php echo adminUrl('resell-delivery.php?toggle=' . $dc['id']); ?>" 
                           class="btn btn-sm btn-outline-<?php echo $dc['status'] ? 'warning' : 'success'; ?>" 
                           title="<?php echo $dc['status'] ? 'নিষ্ক্রিয় করুন' : 'সক্রিয় করুন'; ?>">
                            <i class="bi bi-<?php echo $dc['status'] ? 'pause' : 'play'; ?>"></i>
                        </a>
                        <a href="<?php echo adminUrl('resell-delivery.php?edit=' . $dc['id']); ?>" 
                           class="btn btn-sm btn-outline-primary" title="এডিট">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="<?php echo adminUrl('resell-delivery.php?delete=' . $dc['id']); ?>" 
                           class="btn btn-sm btn-outline-danger" 
                           onclick="return confirm('মুছে ফেলতে চান?')" title="মুছুন">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Products with Custom Delivery -->
            <div class="charge-card">
                <h5 class="mb-3"><i class="bi bi-box-seam me-2"></i>কাস্টম ডেলিভারি চার্জ যুক্ত প্রোডাক্ট</h5>
                
                <?php if (empty($customProducts)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                    <p class="mt-2">কোনো কাস্টম চার্জ নেই</p>
                </div>
                <?php else: ?>
                <?php foreach ($customProducts as $cp): ?>
                <div class="product-item">
                    <img src="<?php echo imageUrl($cp['product_image']); ?>" alt="">
                    <div class="info">
                        <h6><?php echo sanitize($cp['product_name']); ?></h6>
                        <span class="custom-price">
                            <i class="bi bi-truck me-1"></i><?php echo formatPrice($cp['custom_charge']); ?>
                        </span>
                    </div>
                    <a href="<?php echo adminUrl('resell-delivery.php?remove_custom=' . $cp['product_id']); ?>" 
                       class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('কাস্টম চার্জ সরাতে চান?')" title="সরান">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Select2 for product search if available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('#productSelect').select2({
            placeholder: '-- প্রোডাক্ট খুঁজুন --',
            allowClear: true
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
