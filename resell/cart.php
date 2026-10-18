<?php
/**
 * Kilagbo Reseller - Cart
 * NOTE 05
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'কার্ট';
$currentPage = 'cart';
$reseller = requireResellerLogin();
$resellerId = (int)$reseller['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $stmt = $pdo->prepare("SELECT rc.*, rp.stock_quantity FROM resell_cart rc JOIN resell_products rp ON rp.id = rc.resell_product_id WHERE rc.id = ? AND rc.reseller_id = ?");
        $stmt->execute([$cartId, $resellerId]);
        $row = $stmt->fetch();
        if ($row) {
            if ($row['stock_quantity'] !== null && $quantity > (int)$row['stock_quantity']) {
                setFlash('error', 'স্টকে সর্বোচ্চ ' . (int)$row['stock_quantity'] . ' পিস আছে');
            } else {
                $pdo->prepare("UPDATE resell_cart SET quantity = ?, updated_at = NOW() WHERE id = ? AND reseller_id = ?")
                    ->execute([$quantity, $cartId, $resellerId]);
                setFlash('success', 'কার্ট আপডেট হয়েছে');
            }
        }
        redirect(baseUrl('resell/cart.php'));
    }

    if ($action === 'remove') {
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $pdo->prepare("DELETE FROM resell_cart WHERE id = ? AND reseller_id = ?")->execute([$cartId, $resellerId]);
        setFlash('success', 'পণ্য কার্ট থেকে সরানো হয়েছে');
        redirect(baseUrl('resell/cart.php'));
    }

    if ($action === 'clear') {
        $pdo->prepare("DELETE FROM resell_cart WHERE reseller_id = ?")->execute([$resellerId]);
        setFlash('success', 'কার্ট খালি করা হয়েছে');
        redirect(baseUrl('resell/cart.php'));
    }
}

$stmt = $pdo->prepare("\n    SELECT rc.*, rp.name, rp.image, rp.reseller_price, rp.suggested_selling_price, rp.stock_quantity, rp.status\n    FROM resell_cart rc\n    JOIN resell_products rp ON rp.id = rc.resell_product_id\n    WHERE rc.reseller_id = ?\n    ORDER BY rc.created_at DESC\n");
$stmt->execute([$resellerId]);
$cartItems = $stmt->fetchAll();

$cartBaseTotal = 0;
foreach ($cartItems as $item) {
    $cartBaseTotal += ((float)$item['reseller_price'] * (int)$item['quantity']);
}

include __DIR__ . '/includes/header.php';
?>

<style>
.cart-shell { max-width: 1150px; margin: 0 auto; }
.cart-card { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.05); overflow:hidden; }
.cart-item { display:grid; grid-template-columns:88px minmax(0,1fr) 135px 140px 52px; gap:16px; align-items:center; padding:18px; border-bottom:1px solid #edf0f3; }
.cart-item:last-child { border-bottom:0; }
.cart-img { width:88px; height:88px; object-fit:contain; border-radius:10px; background:#f8f9fa; }
.cart-name { font-weight:700; margin-bottom:4px; }
.cart-variation { font-size:.82rem; color:#6c757d; }
.cart-price { font-weight:700; color:var(--primary); }
.qty-form { display:flex; gap:6px; align-items:center; }
.qty-form input { width:72px; text-align:center; }
.summary-card { background:#fff; border-radius:14px; padding:22px; box-shadow:0 2px 12px rgba(0,0,0,.05); }
.empty-cart { background:#fff; border-radius:14px; padding:70px 20px; text-align:center; box-shadow:0 2px 12px rgba(0,0,0,.05); }
.empty-cart i { font-size:4rem; color:#d4d9df; }
@media (max-width: 767.98px) {
    .cart-item { grid-template-columns:70px 1fr; gap:12px; }
    .cart-img { width:70px; height:70px; }
    .cart-item .qty-wrap, .cart-item .line-total, .cart-item .remove-wrap { grid-column:2; }
    .qty-form { justify-content:flex-start; }
}
</style>

<div class="cart-shell">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-cart3 me-2"></i>রিসেলার কার্ট</h3>
            <div class="text-muted">মোট <?php echo count($cartItems); ?> ধরনের পণ্য</div>
        </div>
        <?php if (!empty($cartItems)): ?>
        <form method="POST" onsubmit="return confirm('কার্ট খালি করতে চান?')">
            <input type="hidden" name="action" value="clear">
            <button class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i>কার্ট খালি করুন</button>
        </form>
        <?php endif; ?>
    </div>

    <?php showFlash(); ?>

    <?php if (empty($cartItems)): ?>
    <div class="empty-cart">
        <i class="bi bi-cart-x"></i>
        <h4 class="mt-3">কার্ট খালি</h4>
        <p class="text-muted">পণ্যসমূহ থেকে প্রয়োজনীয় পণ্য কার্টে যোগ করুন</p>
        <a href="<?php echo baseUrl('resell/products.php'); ?>" class="btn btn-primary px-4">পণ্য দেখুন</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="cart-card">
                <?php foreach ($cartItems as $item): ?>
                <?php
                    $variation = json_decode($item['variation'] ?? '', true) ?: [];
                    $available = (int)$item['status'] === 1 && ($item['stock_quantity'] === null || (int)$item['stock_quantity'] > 0);
                ?>
                <div class="cart-item <?php echo $available ? '' : 'opacity-50'; ?>">
                    <img src="<?php echo imageUrl($item['image']); ?>" class="cart-img" alt="">
                    <div>
                        <div class="cart-name"><?php echo sanitize($item['name']); ?></div>
                        <div class="cart-price"><?php echo formatPrice($item['reseller_price']); ?> / পিস</div>
                        <?php if ($variation): ?>
                        <div class="cart-variation mt-1">
                            <?php foreach ($variation as $key => $value): ?>
                                <span class="badge bg-light text-dark border me-1"><?php echo sanitize($key); ?>: <?php echo sanitize($value); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!$available): ?><small class="text-danger">এই পণ্য বর্তমানে অর্ডারের জন্য উপলব্ধ নয়</small><?php endif; ?>
                    </div>
                    <div class="qty-wrap">
                        <form method="POST" class="qty-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                            <input type="number" name="quantity" min="1" <?php echo $item['stock_quantity'] !== null ? 'max="'.(int)$item['stock_quantity'].'"' : ''; ?> value="<?php echo (int)$item['quantity']; ?>" class="form-control form-control-sm" required>
                            <button class="btn btn-sm btn-outline-primary" title="আপডেট"><i class="bi bi-check-lg"></i></button>
                        </form>
                    </div>
                    <div class="line-total fw-bold">
                        <?php echo formatPrice((float)$item['reseller_price'] * (int)$item['quantity']); ?>
                    </div>
                    <div class="remove-wrap">
                        <form method="POST" onsubmit="return confirm('পণ্যটি সরাবেন?')">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="summary-card position-sticky" style="top:95px;">
                <h5 class="mb-3">কার্ট সারাংশ</h5>
                <div class="d-flex justify-content-between mb-2"><span>পণ্য খরচ</span><strong><?php echo formatPrice($cartBaseTotal); ?></strong></div>
                <div class="d-flex justify-content-between mb-3"><span>মোট আইটেম</span><strong><?php echo getResellerCartCount($resellerId); ?></strong></div>
                <hr>
                <a href="<?php echo baseUrl('resell/checkout.php'); ?>" class="btn btn-primary w-100 py-3 fw-bold">
                    <i class="bi bi-bag-check me-2"></i>চেকআউট করুন
                </a>
                <a href="<?php echo baseUrl('resell/products.php'); ?>" class="btn btn-outline-secondary w-100 mt-2">আরও পণ্য যোগ করুন</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
