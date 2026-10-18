<?php
/**
 * Kilagbo - View Order
 * Developed by Webnify Bangladesh
 */

$currentPage = 'orders';

require_once '../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();
require_once __DIR__ . '/../includes/steadfast.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect(adminUrl('orders'));
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'অর্ডার পাওয়া যায়নি');
    redirect(adminUrl('orders'));
}

$pageTitle = 'অর্ডার: ' . $order['order_number'];

// Fetch order items
$itemsStmt = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $status = sanitize($_POST['status']);
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $id]);
        setFlash('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে');
        redirect(adminUrl('orders/view/' . $id));
    }

    if ($action === 'add_note') {
        $notes = sanitize($_POST['notes']);
        $pdo->prepare("UPDATE orders SET notes = ? WHERE id = ?")->execute([$notes, $id]);
        setFlash('success', 'নোট সেভ হয়েছে');
        redirect(adminUrl('orders/view/' . $id));
    }

    if ($action === 'update_customer') {
        $customerName = sanitize($_POST['customer_name']);
        $customerPhone = sanitize($_POST['customer_phone']);
        $customerAddress = sanitize($_POST['customer_address']);
        $deliveryArea = sanitize($_POST['delivery_area']);

        $pdo->prepare("UPDATE orders SET customer_name = ?, customer_phone = ?, customer_address = ?, delivery_area = ? WHERE id = ?")
            ->execute([$customerName, $customerPhone, $customerAddress, $deliveryArea, $id]);
        setFlash('success', 'কাস্টমার তথ্য আপডেট হয়েছে');
        redirect(adminUrl('orders/view/' . $id));
    }

    if ($action === 'send_to_steadfast') {
        $result = steadfastSendOrders($pdo, [['type' => 'retail', 'id' => $id]], true);
        steadfastFlashSendResult($result);
        redirect(adminUrl('orders/view/' . $id));
    }
}

if (!empty($order['steadfast_consignment_id'])) {
    steadfastSyncOrder($pdo, 'retail', $id, false);
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
}

$statusLabels = [
    'pending' => ['label' => 'পেন্ডিং', 'class' => 'bg-warning'],
    'processing' => ['label' => 'প্রসেসিং', 'class' => 'bg-info'],
    'shipped' => ['label' => 'শিপড', 'class' => 'bg-primary'],
    'delivered' => ['label' => 'ডেলিভার্ড', 'class' => 'bg-success'],
    'cancelled' => ['label' => 'বাতিল', 'class' => 'bg-danger']
];

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="mb-4">
    <a href="<?php echo adminUrl('orders'); ?>" class="btn btn-admin-outline">
        <i class="bi bi-arrow-left me-2"></i>অর্ডার তালিকায় ফিরুন
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Order Items -->
        <div class="admin-card">
            <h5 class="card-title">অর্ডারকৃত পণ্য</h5>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="60"></th>
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
                                <img src="<?php echo $item['image'] ?: 'https://via.placeholder.com/50'; ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td>
                                <?php echo sanitize($item['product_name']); ?>
                                <?php
                                $variation = json_decode($item['variation'], true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($variation)):
                                    foreach ($variation as $key => $value):
                                        if ($key === 'section') continue;
                                        // Specific handling for color to display Bengali text if encoded, though json_decode usually handles utf8.
                                        // The user's JSON had "\u0995\u09be\u09b2\u09be\u09b0".
                                        echo '<br><small class="text-muted">' . ucfirst($key) . ': ' . sanitize($value) . '</small>';
                                    endforeach;
                                elseif ($item['variation']):
                                ?>
                                <br><small class="text-muted"><?php echo sanitize($item['variation']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatPrice($item['price']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><strong><?php echo formatPrice($item['total']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end">সাবটোটাল:</td>
                            <td><strong><?php echo formatPrice($order['subtotal']); ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end">
                                ডেলিভারি চার্জ
                                (<?php echo $order['delivery_area'] == 'inside_dhaka' ? $insideDhakaLabel : $outsideDhakaLabel; ?>):
                            </td>
                            <td><strong><?php echo formatPrice($order['delivery_charge']); ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end"><strong>সর্বমোট:</strong></td>
                            <td><strong class="text-primary" style="font-size: 1.2rem;"><?php echo formatPrice($order['total']); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Order Notes -->
        <div class="admin-card">
            <h5 class="card-title">নোট</h5>
            <form method="POST">
                <input type="hidden" name="action" value="add_note">
                <textarea name="notes" class="form-control mb-3" rows="3" placeholder="অর্ডার সম্পর্কে নোট লিখুন..."><?php echo sanitize($order['notes']); ?></textarea>
                <button type="submit" class="btn btn-admin btn-sm">
                    <i class="bi bi-save me-1"></i>সেভ করুন
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Order Info -->
        <div class="admin-card">
            <h5 class="card-title">অর্ডার তথ্য</h5>

            <div class="mb-3">
                <strong>অর্ডার নং:</strong><br>
                <span class="text-primary"><?php echo $order['order_number']; ?></span>
            </div>

            <div class="mb-3">
                <strong>তারিখ:</strong><br>
                <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
            </div>

            <div class="mb-3">
                <strong>স্ট্যাটাস:</strong><br>
                <span class="badge <?php echo $statusLabels[$order['status']]['class']; ?>">
                    <?php echo $statusLabels[$order['status']]['label']; ?>
                </span>
            </div>

            <div class="mb-3">
                <strong>পেমেন্ট মেথড:</strong><br>
                <?php
                if ($order['payment_method'] == 'online') {
                    echo '<span class="text-success">অনলাইন পেমেন্ট</span>';
                    if (!empty($order['payment_type'])) {
                        echo ' (' . ucfirst($order['payment_type']) . ')';
                    }
                    if (!empty($order['payment_number'])) {
                        echo '<br><small>নম্বর: ' . sanitize($order['payment_number']) . '</small>';
                    }
                    if (!empty($order['transaction_id'])) {
                        echo '<br><small>TrxID: ' . sanitize($order['transaction_id']) . '</small>';
                    }
                } else {
                    echo 'ক্যাশ অন ডেলিভারি';
                }
                ?>
            </div>

            <?php if ($order['steadfast_tracking_code']): ?>
            <div class="mb-3">
                <strong>ট্র্যাকিং কোড:</strong><br>
                <code><?php echo $order['steadfast_tracking_code']; ?></code>
            </div>
            <?php endif; ?>

            <hr>

            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <div class="mb-3">
                    <label class="form-label">স্ট্যাটাস পরিবর্তন করুন</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statusLabels as $key => $val): ?>
                        <option value="<?php echo $key; ?>" <?php echo $order['status'] == $key ? 'selected' : ''; ?>>
                            <?php echo $val['label']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-admin w-100">
                    <i class="bi bi-check-circle me-1"></i>আপডেট করুন
                </button>
            </form>
        </div>

        <!-- Customer Info (Editable) -->
        <div class="admin-card">
            <h5 class="card-title">
                কাস্টমার তথ্য
                <button type="button" class="btn btn-sm btn-outline-primary float-end" onclick="toggleCustomerEdit()">
                    <i class="bi bi-pencil"></i> এডিট
                </button>
            </h5>

            <!-- View Mode -->
            <div id="customerView">
                <div class="mb-3">
                    <strong><i class="bi bi-person me-2"></i>নাম:</strong><br>
                    <?php echo sanitize($order['customer_name']); ?>
                </div>

                <div class="mb-3">
                    <strong><i class="bi bi-telephone me-2"></i>ফোন:</strong><br>
                    <a href="tel:<?php echo $order['customer_phone']; ?>"><?php echo sanitize($order['customer_phone']); ?></a>
                </div>

                <div class="mb-3">
                    <strong><i class="bi bi-geo-alt me-2"></i>ঠিকানা:</strong><br>
                    <?php echo nl2br(sanitize($order['customer_address'])); ?>
                </div>

                <div class="mb-0">
                    <strong><i class="bi bi-truck me-2"></i>ডেলিভারি এরিয়া:</strong><br>
                    <?php echo $order['delivery_area'] == 'inside_dhaka' ? 'ঢাকার ভেতরে' : 'ঢাকার বাহিরে'; ?>
                </div>
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
                    <label class="form-label"><i class="bi bi-geo-alt me-2"></i>ঠিকানা</label>
                    <textarea name="customer_address" class="form-control" rows="3" required><?php echo sanitize($order['customer_address']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-truck me-2"></i>ডেলিভারি এরিয়া</label>
                    <select name="delivery_area" class="form-select">
                        <option value="inside_dhaka" <?php echo $order['delivery_area'] == 'inside_dhaka' ? 'selected' : ''; ?>>ঢাকার ভেতরে</option>
                        <option value="outside_dhaka" <?php echo $order['delivery_area'] == 'outside_dhaka' ? 'selected' : ''; ?>>ঢাকার বাহিরে</option>
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

        <?php
        $steadfastActive = getSettings('steadfast_active') == 1 && getSettings('steadfast_api_key');
        if ($steadfastActive && $order['status'] != 'cancelled'):
        ?>
        <!-- Steadfast Send -->
        <div class="admin-card">
            <h5 class="card-title">
                <img src="https://steadfast.com.bd/images/logo.png" alt="Steadfast" style="height: 20px; margin-right: 8px;">
                Steadfast কুরিয়ার
            </h5>
            <?php if ($order['steadfast_consignment_id']): ?>
            <div class="alert alert-success mb-3">
                <i class="bi bi-check-circle me-2"></i>ইতিমধ্যে পাঠানো হয়েছে
                <?php if (!empty($order['courier_status'])): ?>
                <br><small>Courier status: <strong><?php echo steadfastCourierStatusLabel($order['courier_status']); ?></strong></small>
                <?php endif; ?>
                <?php if ($order['steadfast_tracking_code']): ?>
                <br><small>ট্র্যাকিং: <code><?php echo $order['steadfast_tracking_code']; ?></code></small>
                <?php endif; ?>
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

        <!-- Print -->
        <div class="d-grid">
            <button onclick="window.print()" class="btn btn-admin-outline">
                <i class="bi bi-printer me-2"></i>প্রিন্ট করুন
            </button>
        </div>
    </div>
</div>

<style>
    @media print {
        .admin-sidebar, .admin-header, .btn, form, .admin-footer {
            display: none !important;
        }
        .admin-main {
            margin-left: 0 !important;
        }
    }
</style>

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
