<?php
/**
 * Kilagbo - Admin Wholesale Order View
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'পাইকারি অর্ডার বিস্তারিত';
$currentPage = 'wholesale-orders';

require_once __DIR__ . '/../config/config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(adminUrl('wholesale-orders'));
}

// Get order
$stmt = $pdo->prepare("SELECT * FROM wholesale_orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'অর্ডার পাওয়া যায়নি');
    redirect(adminUrl('wholesale-orders'));
}

// Get order items
$itemsStmt = $pdo->prepare("SELECT * FROM wholesale_order_items WHERE order_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $status = sanitize($_POST['status']);
        $pdo->prepare("UPDATE wholesale_orders SET order_status = ? WHERE id = ?")->execute([$status, $id]);
        setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
        redirect(adminUrl('wholesale-order-view/' . $id));
    }
    
    if ($action === 'add_note') {
        $note = sanitize($_POST['admin_notes']);
        $pdo->prepare("UPDATE wholesale_orders SET admin_notes = ? WHERE id = ?")->execute([$note, $id]);
        setFlash('success', 'নোট যোগ করা হয়েছে');
        redirect(adminUrl('wholesale-order-view/' . $id));
    }
    
    if ($action === 'update_tracking') {
        $courierName = sanitize($_POST['courier_name']);
        $trackingId = sanitize($_POST['tracking_id']);
        $pdo->prepare("UPDATE wholesale_orders SET courier_name = ?, tracking_id = ? WHERE id = ?")
            ->execute([$courierName, $trackingId, $id]);
        setFlash('success', 'ট্র্যাকিং তথ্য আপডেট হয়েছে');
        redirect(adminUrl('wholesale-order-view/' . $id));
    }
    
    if ($action === 'update_customer') {
        $customerName = sanitize($_POST['customer_name']);
        $customerPhone = sanitize($_POST['customer_phone']);
        $customerEmail = sanitize($_POST['customer_email']);
        $shippingAddress = sanitize($_POST['shipping_address']);
        $shippingArea = sanitize($_POST['shipping_area']);
        
        $pdo->prepare("UPDATE wholesale_orders SET customer_name = ?, customer_phone = ?, customer_email = ?, shipping_address = ?, shipping_area = ? WHERE id = ?")
            ->execute([$customerName, $customerPhone, $customerEmail, $shippingAddress, $shippingArea, $id]);
        setFlash('success', 'কাস্টমার তথ্য আপডেট হয়েছে');
        redirect(adminUrl('wholesale-order-view/' . $id));
    }
    
    if ($action === 'send_to_steadfast') {
        $apiKey = getSettings('steadfast_api_key');
        $secretKey = getSettings('steadfast_secret_key');
        $isActive = getSettings('steadfast_active');
        
        if ($isActive && $apiKey && $secretKey) {
            $data = [
                'invoice' => $order['order_number'],
                'recipient_name' => $order['customer_name'],
                'recipient_phone' => $order['customer_phone'],
                'recipient_address' => $order['shipping_address'],
                'cod_amount' => $order['total'],
                'note' => $order['notes'] ?? ''
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, STEADFAST_BASE_URL . '/create_order');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Api-Key: ' . $apiKey,
                'Secret-Key: ' . $secretKey
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode == 200 && isset($result['consignment']['consignment_id'])) {
                $consignmentId = $result['consignment']['consignment_id'];
                $trackingCode = $result['consignment']['tracking_code'] ?? '';
                
                $pdo->prepare("UPDATE wholesale_orders SET courier_name = 'Steadfast', tracking_id = ?, order_status = 'processing' WHERE id = ?")
                    ->execute([$trackingCode, $id]);
                
                setFlash('success', 'অর্ডার সফলভাবে Steadfast এ পাঠানো হয়েছে। ট্র্যাকিং কোড: ' . $trackingCode);
            } else {
                setFlash('error', 'Steadfast এ পাঠাতে সমস্যা হয়েছে: ' . ($result['message'] ?? 'Unknown error'));
            }
        } else {
            setFlash('error', 'Steadfast কুরিয়ার সেটআপ করা হয়নি বা সক্রিয় নেই');
        }
        redirect(adminUrl('wholesale-order-view/' . $id));
    }
}

// Status labels
$statusLabels = [
    'pending' => ['label' => 'পেন্ডিং', 'class' => 'warning'],
    'confirmed' => ['label' => 'কনফার্মড', 'class' => 'info'],
    'processing' => ['label' => 'প্রসেসিং', 'class' => 'primary'],
    'shipped' => ['label' => 'শিপড', 'class' => 'secondary'],
    'delivered' => ['label' => 'ডেলিভারড', 'class' => 'success'],
    'cancelled' => ['label' => 'বাতিল', 'class' => 'danger'],
    'returned' => ['label' => 'রিটার্নড', 'class' => 'dark']
];

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">অর্ডার #<?php echo $order['order_number']; ?></h4>
        <span class="badge bg-<?php echo $statusLabels[$order['order_status']]['class']; ?>">
            <?php echo $statusLabels[$order['order_status']]['label']; ?>
        </span>
        <small class="text-muted ms-2"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></small>
    </div>
    <a href="<?php echo adminUrl('wholesale-orders'); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>ফিরে যান
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Order Items -->
        <div class="admin-card mb-4">
            <h5 class="card-title">অর্ডার আইটেম</h5>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>পণ্য</th>
                            <th>দাম</th>
                            <th>পরিমাণ</th>
                            <th>মোট</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($item['product_image']): ?>
                                    <img src="<?php echo imageUrl($item['product_image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo $item['product_name']; ?></strong>
                                        <?php 
                                        if ($item['variation']) {
                                            $vars = json_decode($item['variation'], true);
                                            if ($vars) {
                                                echo '<br><small class="text-muted">';
                                                foreach ($vars as $k => $v) {
                                                    echo sanitize($k) . ': ' . sanitize($v) . ' ';
                                                }
                                                echo '</small>';
                                            } else {
                                                echo '<br><small class="text-muted">' . sanitize($item['variation']) . '</small>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo formatPrice($item['unit_price']); ?></td>
                            <td><?php echo $item['quantity']; ?> পিস</td>
                            <td><strong><?php echo formatPrice($item['total_price']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end">সাবটোটাল</td>
                            <td><strong><?php echo formatPrice($order['subtotal']); ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end">ডেলিভারি চার্জ</td>
                            <td><?php echo formatPrice($order['delivery_charge']); ?></td>
                        </tr>
                        <?php if ($order['discount'] > 0): ?>
                        <tr>
                            <td colspan="3" class="text-end">ডিসকাউন্ট</td>
                            <td>-<?php echo formatPrice($order['discount']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" class="text-end"><strong>মোট</strong></td>
                            <td><strong class="text-primary" style="font-size: 1.2rem;"><?php echo formatPrice($order['total']); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Customer Notes -->
        <?php if ($order['notes']): ?>
        <div class="admin-card mb-4">
            <h5 class="card-title">কাস্টমার নোট</h5>
            <p class="mb-0"><?php echo nl2br($order['notes']); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Admin Notes -->
        <div class="admin-card mb-4">
            <h5 class="card-title">এডমিন নোট</h5>
            <form method="POST">
                <input type="hidden" name="action" value="add_note">
                <textarea name="admin_notes" class="form-control mb-3" rows="3"><?php echo $order['admin_notes']; ?></textarea>
                <button type="submit" class="btn btn-admin btn-sm">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Customer Info (Editable) -->
        <div class="admin-card mb-4">
            <h5 class="card-title">
                কাস্টমার ও ডেলিভারি তথ্য
                <button type="button" class="btn btn-sm btn-outline-primary float-end" onclick="toggleCustomerEdit()">
                    <i class="bi bi-pencil"></i> এডিট
                </button>
            </h5>
            
            <!-- View Mode -->
            <div id="customerView">
                <p class="mb-2"><strong><i class="bi bi-person me-2"></i><?php echo $order['customer_name']; ?></strong></p>
                <p class="mb-2"><i class="bi bi-telephone me-2"></i><?php echo $order['customer_phone']; ?></p>
                <?php if ($order['customer_email']): ?>
                <p class="mb-2"><i class="bi bi-envelope me-2"></i><?php echo $order['customer_email']; ?></p>
                <?php endif; ?>
                <hr>
                <p class="mb-2"><i class="bi bi-geo-alt me-2"></i><?php echo nl2br($order['shipping_address']); ?></p>
                <span class="badge bg-info"><?php echo $order['shipping_area'] === 'inside_dhaka' ? 'ঢাকার ভিতরে' : 'ঢাকার বাইরে'; ?></span>
            </div>
            
            <!-- Edit Mode -->
            <form method="POST" id="customerEditForm" style="display: none;">
                <input type="hidden" name="action" value="update_customer">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-person me-2"></i>নাম</label>
                    <input type="text" name="customer_name" class="form-control" value="<?php echo sanitize($order['customer_name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-telephone me-2"></i>ফোন</label>
                    <input type="text" name="customer_phone" class="form-control" value="<?php echo sanitize($order['customer_phone']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-envelope me-2"></i>ইমেইল</label>
                    <input type="email" name="customer_email" class="form-control" value="<?php echo sanitize($order['customer_email']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-geo-alt me-2"></i>ঠিকানা</label>
                    <textarea name="shipping_address" class="form-control" rows="3" required><?php echo sanitize($order['shipping_address']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-truck me-2"></i>ডেলিভারি এরিয়া</label>
                    <select name="shipping_area" class="form-select">
                        <option value="inside_dhaka" <?php echo $order['shipping_area'] == 'inside_dhaka' ? 'selected' : ''; ?>>ঢাকার ভিতরে</option>
                        <option value="outside_dhaka" <?php echo $order['shipping_area'] == 'outside_dhaka' ? 'selected' : ''; ?>>ঢাকার বাইরে</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-admin btn-sm">
                        <i class="bi bi-save me-1"></i>সেভ করুন
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleCustomerEdit()">
                        বাতিল
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Payment Information -->
        <div class="admin-card mb-4">
            <h5 class="card-title">পেমেন্ট তথ্য</h5>
            <div class="mb-3">
                <strong>পেমেন্ট মেথড:</strong><br>
                <?php if ($order['payment_method'] === 'online'): ?>
                    <span class="text-success">অনলাইন পেমেন্ট</span>
                    <?php if (!empty($order['payment_type'])): ?>
                        (<?php echo ucfirst(sanitize($order['payment_type'])); ?>)
                    <?php endif; ?>
                    
                    <?php if (!empty($order['payment_number'])): ?>
                        <br><small class="text-muted">নম্বর: <?php echo sanitize($order['payment_number']); ?></small>
                    <?php endif; ?>
                    
                    <?php if (!empty($order['transaction_id'])): ?>
                        <br><small class="text-muted">TrxID: <?php echo sanitize($order['transaction_id']); ?></small>
                    <?php endif; ?>
                    
                    <div class="mt-2">
                        <strong>স্ট্যাটাস:</strong> 
                        <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'pending_verification' ? 'warning' : 'secondary'); ?>">
                            <?php 
                            if ($order['payment_status'] === 'paid') echo 'পেইড';
                            elseif ($order['payment_status'] === 'pending_verification') echo 'ভেরিফিকেশন পেন্ডিং';
                            else echo 'পেন্ডিং';
                            ?>
                        </span>
                    </div>
                <?php else: ?>
                    <span class="text-muted">ক্যাশ অন ডেলিভারি</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Update Status -->
        <div class="admin-card mb-4">
            <h5 class="card-title">স্ট্যাটাস আপডেট</h5>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <select name="status" class="form-select mb-3">
                    <?php foreach ($statusLabels as $key => $val): ?>
                    <option value="<?php echo $key; ?>" <?php echo $order['order_status'] == $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-admin w-100">
                    <i class="bi bi-check-lg me-2"></i>আপডেট করুন
                </button>
            </form>
        </div>
        
        <?php 
        $steadfastActive = getSettings('steadfast_active') == 1 && getSettings('steadfast_api_key');
        if ($steadfastActive && $order['order_status'] != 'cancelled'): 
        ?>
        <!-- Steadfast Send -->
        <div class="admin-card mb-4">
            <h5 class="card-title">
                <img src="https://steadfast.com.bd/images/logo.png" alt="Steadfast" style="height: 20px; margin-right: 8px;">
                Steadfast কুরিয়ার
            </h5>
            <?php if ($order['courier_name'] === 'Steadfast' && $order['tracking_id']): ?>
            <div class="alert alert-success mb-3">
                <i class="bi bi-check-circle me-2"></i>ইতিমধ্যে পাঠানো হয়েছে
                <br><small>ট্র্যাকিং: <code><?php echo $order['tracking_id']; ?></code></small>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_to_steadfast">
                <button type="submit" class="btn btn-warning w-100" onclick="return confirm('এই অর্ডার ইতিমধ্যে Steadfast এ পাঠানো হয়েছে। আবার পাঠাতে চান?')">
                    <i class="bi bi-arrow-repeat me-2"></i>আবার পাঠান
                </button>
            </form>
            <?php else: ?>
            <p class="text-muted small mb-3">কাস্টমার তথ্য এডিট করে সঠিক করুন, তারপর Steadfast এ পাঠান।</p>
            <form method="POST">
                <input type="hidden" name="action" value="send_to_steadfast">
                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Steadfast এ পাঠাতে চান?')">
                    <i class="bi bi-send me-2"></i>Steadfast এ পাঠান
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Tracking Info -->
        <div class="admin-card">
            <h5 class="card-title">ট্র্যাকিং তথ্য</h5>
            <form method="POST">
                <input type="hidden" name="action" value="update_tracking">
                <div class="mb-3">
                    <label class="form-label">কুরিয়ার নাম</label>
                    <input type="text" name="courier_name" class="form-control" value="<?php echo $order['courier_name']; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">ট্র্যাকিং আইডি</label>
                    <input type="text" name="tracking_id" class="form-control" value="<?php echo $order['tracking_id']; ?>">
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-save me-2"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCustomerEdit() {
    const viewDiv = document.getElementById('customerView');
    const editForm = document.getElementById('customerEditForm');
    
    if (viewDiv.style.display === 'none') {
        viewDiv.style.display = 'block';
        editForm.style.display = 'none';
    } else {
        viewDiv.style.display = 'none';
        editForm.style.display = 'block';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
