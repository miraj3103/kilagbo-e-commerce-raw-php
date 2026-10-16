<?php
/**
 * Kilagbo - User Account
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

if (!isUserLoggedIn()) {
    redirect(baseUrl('login'));
}

$currentPage = 'retail';
$pageTitle = 'আমার অ্যাকাউন্ট';

$userId = $_SESSION['user_id'];

// Fetch user data
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// Fetch user orders
$ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$ordersStmt->execute([$userId]);
$orders = $ordersStmt->fetchAll();

// Fetch user addresses (check if table exists)
$addresses = [];
try {
    $addressStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $addressStmt->execute([$userId]);
    $addresses = $addressStmt->fetchAll();
} catch (PDOException $e) {
    // Table doesn't exist yet
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    
    // Update profile
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        
        $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")
            ->execute([$name, $phone, $userId]);
        
        $_SESSION['user_name'] = $name;
        
        setFlash('success', 'প্রোফাইল আপডেট করা হয়েছে');
        redirect(baseUrl('account'));
    }
    
    // Add new address
    if ($action === 'add_address') {
        $addressType = sanitize($_POST['address_type'] ?? 'custom');
        $label = sanitize($_POST['label'] ?? '');
        $recipientName = sanitize($_POST['recipient_name'] ?? '');
        $phone = sanitize($_POST['address_phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $area = sanitize($_POST['area'] ?? 'inside_dhaka');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        if (empty($recipientName) || empty($phone) || empty($address)) {
            setFlash('error', 'সকল প্রয়োজনীয় ফিল্ড পূরণ করুন');
        } else {
            // If setting as default, unset other defaults
            if ($isDefault) {
                $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }
            
            $pdo->prepare("INSERT INTO user_addresses (user_id, address_type, label, recipient_name, phone, address, area, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$userId, $addressType, $label ?: null, $recipientName, $phone, $address, $area, $isDefault]);
            
            setFlash('success', 'ঠিকানা যোগ করা হয়েছে');
        }
        redirect(baseUrl('account'));
    }
    
    // Delete address
    if ($action === 'delete_address') {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?")->execute([$addressId, $userId]);
        setFlash('success', 'ঠিকানা মুছে ফেলা হয়েছে');
        redirect(baseUrl('account'));
    }
    
    // Set default address
    if ($action === 'set_default') {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$addressId, $userId]);
        setFlash('success', 'ডিফল্ট ঠিকানা সেট করা হয়েছে');
        redirect(baseUrl('account'));
    }
}

$statusLabels = [
    'pending' => ['label' => 'পেন্ডিং', 'class' => 'bg-warning'],
    'processing' => ['label' => 'প্রসেসিং', 'class' => 'bg-info'],
    'shipped' => ['label' => 'শিপড', 'class' => 'bg-primary'],
    'delivered' => ['label' => 'ডেলিভার্ড', 'class' => 'bg-success'],
    'cancelled' => ['label' => 'বাতিল', 'class' => 'bg-danger']
];

include 'includes/header.php';
?>

<style>
    .account-page {
        padding: 40px 0;
    }
    
    .account-card {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }
    
    .account-card h5 {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .account-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
    }
    
    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--primary-color);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 600;
    }
    
    .orders-table {
        width: 100%;
    }
    
    .orders-table th {
        background: var(--bg-light);
        padding: 12px 15px;
        font-weight: 600;
    }
    
    .orders-table td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    @media (max-width: 768px) {
        .orders-table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<?php showFlash(); ?>

<div class="account-page">
    <div class="container">
        <div class="account-header">
            <div class="d-flex align-items-center gap-3">
                <div class="user-avatar">
                    <?php echo strtoupper(mb_substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <h4 class="mb-0"><?php echo sanitize($user['name']); ?></h4>
                    <small class="text-muted"><?php echo sanitize($user['email']); ?></small>
                </div>
            </div>
            <a href="<?php echo baseUrl('logout'); ?>" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i>লগআউট
            </a>
        </div>
        
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="account-card">
                    <h5><i class="bi bi-person me-2"></i>প্রোফাইল তথ্য</h5>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="mb-3">
                            <label class="form-label">নাম</label>
                            <input type="text" name="name" class="form-control" value="<?php echo sanitize($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ইমেইল</label>
                            <input type="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" disabled>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">ফোন</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo sanitize($user['phone']); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary-custom w-100">
                            <i class="bi bi-save me-2"></i>আপডেট করুন
                        </button>
                    </form>
                </div>
                
                <!-- Saved Addresses -->
                <div class="account-card">
                    <h5><i class="bi bi-geo-alt me-2"></i>সংরক্ষিত ঠিকানা</h5>
                    
                    <?php if (empty($addresses)): ?>
                    <p class="text-muted small mb-3">কোনো সংরক্ষিত ঠিকানা নেই</p>
                    <?php else: ?>
                    <div class="address-list mb-3">
                        <?php foreach ($addresses as $addr): ?>
                        <div class="address-item p-3 mb-2 border rounded <?php echo $addr['is_default'] ? 'border-dark' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge <?php echo $addr['address_type'] === 'home' ? 'bg-primary' : 'bg-secondary'; ?>">
                                    <?php echo $addr['address_type'] === 'home' ? 'বাড়ি' : ($addr['label'] ?: 'কাস্টম'); ?>
                                </span>
                                <?php if ($addr['is_default']): ?>
                                <span class="badge bg-success">ডিফল্ট</span>
                                <?php endif; ?>
                            </div>
                            <p class="mb-1"><strong><?php echo $addr['recipient_name']; ?></strong></p>
                            <p class="mb-1 small"><?php echo $addr['phone']; ?></p>
                            <p class="mb-2 small text-muted"><?php echo $addr['address']; ?></p>
                            <div class="d-flex gap-2">
                                <?php if (!$addr['is_default']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="set_default">
                                    <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-dark">ডিফল্ট করুন</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('মুছে ফেলতে চান?')">
                                    <input type="hidden" name="action" value="delete_address">
                                    <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-dark btn-sm w-100" type="button" data-bs-toggle="collapse" data-bs-target="#addAddressForm">
                        <i class="bi bi-plus-lg me-1"></i>নতুন ঠিকানা যোগ করুন
                    </button>
                    
                    <div class="collapse mt-3" id="addAddressForm">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_address">
                            
                            <div class="mb-2">
                                <label class="form-label small">ঠিকানার ধরন</label>
                                <select name="address_type" class="form-select form-select-sm" onchange="toggleLabel(this)">
                                    <option value="home">বাড়ি</option>
                                    <option value="custom">কাস্টম</option>
                                </select>
                            </div>
                            
                            <div class="mb-2" id="labelField" style="display: none;">
                                <label class="form-label small">লেবেল</label>
                                <input type="text" name="label" class="form-control form-control-sm" placeholder="যেমন: অফিস, দোকান">
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small">প্রাপকের নাম *</label>
                                <input type="text" name="recipient_name" class="form-control form-control-sm" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small">ফোন *</label>
                                <input type="tel" name="address_phone" class="form-control form-control-sm" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small">সম্পূর্ণ ঠিকানা *</label>
                                <textarea name="address" class="form-control form-control-sm" rows="2" required></textarea>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small">এলাকা</label>
                                <select name="area" class="form-select form-select-sm">
                                    <option value="inside_dhaka">ঢাকার ভিতরে</option>
                                    <option value="outside_dhaka">ঢাকার বাইরে</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
                                    <label class="form-check-label small" for="isDefault">ডিফল্ট ঠিকানা হিসেবে সেট করুন</label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary-custom btn-sm w-100">
                                <i class="bi bi-check-lg me-1"></i>সেভ করুন
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            function toggleLabel(select) {
                document.getElementById('labelField').style.display = select.value === 'custom' ? 'block' : 'none';
            }
            </script>
            
            <div class="col-lg-8">
                <div class="account-card">
                    <h5><i class="bi bi-receipt me-2"></i>আমার অর্ডার</h5>
                    
                    <?php if (empty($orders)): ?>
                    <p class="text-muted text-center py-4">আপনার কোন অর্ডার নেই</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>অর্ডার নং</th>
                                    <th>তারিখ</th>
                                    <th>মোট</th>
                                    <th>স্ট্যাটাস</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo $order['order_number']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                                    <td><?php echo formatPrice($order['total']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusLabels[$order['status']]['class']; ?>">
                                            <?php echo $statusLabels[$order['status']]['label']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <a href="<?php echo baseUrl('wishlist'); ?>" class="account-card d-block text-decoration-none text-dark">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-heart text-danger" style="font-size: 2rem; margin-right: 15px;"></i>
                                <div>
                                    <h6 class="mb-0">উইশলিস্ট</h6>
                                    <small class="text-muted">পছন্দের পণ্য দেখুন</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="<?php echo baseUrl('cart'); ?>" class="account-card d-block text-decoration-none text-dark">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-bag text-primary" style="font-size: 2rem; margin-right: 15px;"></i>
                                <div>
                                    <h6 class="mb-0">কার্ট</h6>
                                    <small class="text-muted">কার্টে থাকা পণ্য দেখুন</small>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
