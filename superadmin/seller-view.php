<?php
/**
 * Kilagbo Admin - Seller Details View
 * View complete seller information
 */

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

$sellerId = intval($_GET['id'] ?? 0);

if (!$sellerId) {
    setFlash('error', 'সেলার পাওয়া যায়নি।');
    redirect(adminUrl('sellers'));
}

// Get seller
$stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->execute([$sellerId]);
$seller = $stmt->fetch();

if (!$seller) {
    setFlash('error', 'সেলার পাওয়া যায়নি।');
    redirect(adminUrl('sellers'));
}

$pageTitle = 'সেলার বিস্তারিত - ' . $seller['name'];
$currentPage = 'sellers';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $pdo->prepare("UPDATE sellers SET status = 'approved' WHERE id = ?")->execute([$sellerId]);
        $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, description) VALUES ('seller_approved', ?, ?)")
            ->execute([$sellerId, "সেলার অনুমোদন করা হয়েছে"]);
        setFlash('success', 'সেলার সফলভাবে অনুমোদন করা হয়েছে।');
        redirect(adminUrl('seller-view/' . $sellerId));
    } elseif ($action === 'reject') {
        $reason = sanitize($_POST['reason'] ?? '');
        $pdo->prepare("INSERT INTO seller_rejected_credentials (email, phone, seller_id, reason) VALUES (?, ?, ?, ?)")
            ->execute([$seller['email'], $seller['phone'], $sellerId, $reason]);
        $pdo->prepare("UPDATE sellers SET status = 'rejected' WHERE id = ?")->execute([$sellerId]);
        $pdo->prepare("INSERT INTO seller_activity (activity_type, seller_id, description) VALUES ('seller_rejected', ?, ?)")
            ->execute([$sellerId, "সেলার প্রত্যাখ্যান করা হয়েছে: $reason"]);
        setFlash('success', 'সেলার প্রত্যাখ্যান করা হয়েছে।');
        redirect(adminUrl('sellers'));
    }
}

// Get seller stats
$productCount = $pdo->prepare("SELECT COUNT(*) FROM seller_products WHERE seller_id = ?");
$productCount->execute([$sellerId]);
$totalProducts = $productCount->fetchColumn();

$orderCount = $pdo->prepare("SELECT COUNT(*) FROM seller_orders WHERE seller_id = ?");
$orderCount->execute([$sellerId]);
$totalOrders = $orderCount->fetchColumn();

// Get recent products
$recentProducts = $pdo->prepare("SELECT * FROM seller_products WHERE seller_id = ? ORDER BY created_at DESC LIMIT 5");
$recentProducts->execute([$sellerId]);
$recentProducts = $recentProducts->fetchAll();

// Get recent orders
$recentOrders = $pdo->prepare("SELECT * FROM seller_orders WHERE seller_id = ? ORDER BY created_at DESC LIMIT 5");
$recentOrders->execute([$sellerId]);
$recentOrders = $recentOrders->fetchAll();

require_once 'includes/header.php';
?>

<style>
    .info-section {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .info-section h6 {
        color: #16a34a;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-row {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        width: 40%;
        color: #666;
        font-weight: 500;
    }
    
    .info-value {
        width: 60%;
        color: #333;
    }
    
    .seller-avatar-large {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 2.5rem;
        margin: 0 auto 20px;
    }
    
    .status-badge-large {
        padding: 8px 20px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .status-pending { background: #fef3cd; color: #856404; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="<?php echo adminUrl('sellers'); ?>" class="btn btn-admin-outline">
        <i class="bi bi-arrow-left me-2"></i>ফিরে যান
    </a>
    
    <div class="d-flex gap-2">
        <?php if ($seller['status'] === 'pending'): ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('সেলারকে অনুমোদন করতে চান?')">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-circle me-2"></i>অনুমোদন করুন
            </button>
        </form>
        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#rejectModal">
            <i class="bi bi-x-circle me-2"></i>প্রত্যাখ্যান
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Left Column - Seller Profile -->
    <div class="col-lg-4">
        <div class="admin-card text-center">
            <div class="seller-avatar-large">
                <?php if ($seller['profile_picture']): ?>
                <img src="<?php echo uploadUrl($seller['profile_picture']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                <?php echo strtoupper(substr($seller['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            
            <h4><?php echo sanitize($seller['name']); ?></h4>
            <p class="text-muted"><?php echo sanitize($seller['business_name']); ?></p>
            
            <span class="status-badge-large status-<?php echo $seller['status']; ?>">
                <?php 
                echo $seller['status'] === 'pending' ? 'অনুমোদনের অপেক্ষায়' : 
                     ($seller['status'] === 'approved' ? 'অনুমোদিত' : 'প্রত্যাখ্যাত'); 
                ?>
            </span>
            
            <hr class="my-4">
            
            <div class="row text-center">
                <div class="col-4">
                    <h5 class="mb-0"><?php echo $totalProducts; ?></h5>
                    <small class="text-muted">পণ্য</small>
                </div>
                <div class="col-4">
                    <h5 class="mb-0"><?php echo $totalOrders; ?></h5>
                    <small class="text-muted">অর্ডার</small>
                </div>
                <div class="col-4">
                    <h5 class="mb-0">৳<?php echo number_format($seller['total_earnings']); ?></h5>
                    <small class="text-muted">আয়</small>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="admin-card">
            <h6 class="mb-3"><i class="bi bi-lightning me-2"></i>দ্রুত অ্যাকশন</h6>
            <div class="d-grid gap-2">
                <a href="<?php echo adminUrl('seller-products.php?seller_id=' . $seller['id']); ?>" class="btn btn-admin-outline">
                    <i class="bi bi-box-seam me-2"></i>পণ্য দেখুন
                </a>
                <a href="mailto:<?php echo $seller['email']; ?>" class="btn btn-admin-outline">
                    <i class="bi bi-envelope me-2"></i>ইমেইল পাঠান
                </a>
                <a href="tel:<?php echo $seller['phone']; ?>" class="btn btn-admin-outline">
                    <i class="bi bi-telephone me-2"></i>কল করুন
                </a>
            </div>
        </div>
    </div>
    
    <!-- Right Column - Details -->
    <div class="col-lg-8">
        <div class="admin-card">
            <!-- Personal Info -->
            <div class="info-section">
                <h6><i class="bi bi-person"></i> ব্যক্তিগত তথ্য</h6>
                <div class="info-row">
                    <div class="info-label">পুরো নাম</div>
                    <div class="info-value"><?php echo sanitize($seller['name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">ইমেইল</div>
                    <div class="info-value"><a href="mailto:<?php echo $seller['email']; ?>"><?php echo sanitize($seller['email']); ?></a></div>
                </div>
                <div class="info-row">
                    <div class="info-label">মোবাইল</div>
                    <div class="info-value"><a href="tel:<?php echo $seller['phone']; ?>"><?php echo sanitize($seller['phone']); ?></a></div>
                </div>
                <div class="info-row">
                    <div class="info-label">জাতীয় পরিচয়পত্র</div>
                    <div class="info-value"><?php echo sanitize($seller['nid_number'] ?: 'N/A'); ?></div>
                </div>
            </div>
            
            <!-- Business Info -->
            <div class="info-section">
                <h6><i class="bi bi-shop"></i> ব্যবসার তথ্য</h6>
                <div class="info-row">
                    <div class="info-label">ব্যবসার নাম</div>
                    <div class="info-value"><?php echo sanitize($seller['business_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">ব্যবসার ধরন</div>
                    <div class="info-value">
                        <?php
                        $types = [
                            'manufacturer' => 'ম্যানুফ্যাকচারার',
                            'wholesaler' => 'পাইকারি বিক্রেতা',
                            'importer' => 'আমদানিকারক',
                            'distributor' => 'ডিস্ট্রিবিউটর',
                            'other' => 'অন্যান্য'
                        ];
                        echo $types[$seller['business_type']] ?? 'N/A';
                        ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">ট্রেড লাইসেন্স</div>
                    <div class="info-value"><?php echo sanitize($seller['trade_license'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">জেলা</div>
                    <div class="info-value"><?php echo sanitize($seller['district'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">ঠিকানা</div>
                    <div class="info-value"><?php echo sanitize($seller['address'] ?: 'N/A'); ?></div>
                </div>
            </div>
            
            <!-- Payment Info -->
            <div class="info-section">
                <h6><i class="bi bi-wallet2"></i> পেমেন্ট তথ্য</h6>
                <div class="info-row">
                    <div class="info-label">বিকাশ</div>
                    <div class="info-value"><?php echo sanitize($seller['bkash_number'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">নগদ</div>
                    <div class="info-value"><?php echo sanitize($seller['nagad_number'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">ব্যাংক</div>
                    <div class="info-value"><?php echo sanitize($seller['bank_name'] ?: 'N/A'); ?></div>
                </div>
                <?php if ($seller['bank_name']): ?>
                <div class="info-row">
                    <div class="info-label">অ্যাকাউন্ট নাম</div>
                    <div class="info-value"><?php echo sanitize($seller['bank_account_name'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">অ্যাকাউন্ট নম্বর</div>
                    <div class="info-value"><?php echo sanitize($seller['bank_account_number'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">ব্রাঞ্চ</div>
                    <div class="info-value"><?php echo sanitize($seller['bank_branch'] ?: 'N/A'); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Account Info -->
            <div class="info-section">
                <h6><i class="bi bi-calendar"></i> অ্যাকাউন্ট তথ্য</h6>
                <div class="info-row">
                    <div class="info-label">রেজিস্ট্রেশন</div>
                    <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($seller['created_at'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">সর্বশেষ লগইন</div>
                    <div class="info-value"><?php echo $seller['last_login'] ? date('d M Y, h:i A', strtotime($seller['last_login'])) : 'এখনো লগইন করেননি'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Recent Products -->
        <?php if (!empty($recentProducts)): ?>
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0" style="border: none; padding: 0;">সাম্প্রতিক পণ্য</h5>
                <a href="<?php echo adminUrl('seller-products.php?seller_id=' . $seller['id']); ?>" class="btn btn-sm btn-admin-outline">সব দেখুন</a>
            </div>
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>পণ্য</th>
                            <th>দাম</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentProducts as $product): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($product['image']): ?>
                                    <img src="<?php echo $product['image']; ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                    <?php endif; ?>
                                    <span><?php echo mb_strimwidth(sanitize($product['name']), 0, 30, '...'); ?></span>
                                </div>
                            </td>
                            <td><?php echo formatPrice($product['seller_price']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $product['status']; ?>">
                                    <?php 
                                    echo $product['status'] === 'pending' ? 'পেন্ডিং' : 
                                         ($product['status'] === 'approved' ? 'অনুমোদিত' : 'প্রত্যাখ্যাত'); 
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h5 class="modal-title">সেলার প্রত্যাখ্যান</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">প্রত্যাখ্যানের কারণ</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="কেন প্রত্যাখ্যান করছেন..." required></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>সতর্কতা:</strong> প্রত্যাখ্যানের পর এই সেলার একই ইমেইল ও ফোন নম্বর দিয়ে আবার রেজিস্ট্রেশন করতে পারবে না।
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-warning">প্রত্যাখ্যান করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
