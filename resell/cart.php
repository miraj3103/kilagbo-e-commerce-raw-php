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

    // NOTE17: Validate every quantity and update the cart in one atomic save.
    if ($action === 'bulk_update') {
        $quantities = $_POST['quantities'] ?? null;
        try {
            if (!is_array($quantities) || !$quantities) {
                throw new InvalidArgumentException('পরিমাণগুলো সঠিকভাবে দিন');
            }
            $pdo->beginTransaction();
            $cartStmt = $pdo->prepare("SELECT rc.id, rc.quantity, rp.name, rp.stock_quantity, rp.status
                FROM resell_cart rc
                JOIN resell_products rp ON rp.id = rc.resell_product_id
                WHERE rc.reseller_id = ? FOR UPDATE");
            $cartStmt->execute([$resellerId]);
            $rows = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($quantities) !== count($rows)) {
                throw new InvalidArgumentException('কার্ট পরিবর্তিত হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন');
            }
            $validated = [];
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $raw = $quantities[$id] ?? null;
                if (!is_string($raw) && !is_int($raw)) {
                    throw new InvalidArgumentException('সব পণ্যের পরিমাণ দিন');
                }
                $quantity = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($quantity === false) {
                    throw new InvalidArgumentException('প্রতিটি পরিমাণ অন্তত ১ হতে হবে');
                }
                if ((int)$row['status'] !== 1) {
                    // An unavailable item can remain unchanged while saving other rows.
                    if ($quantity !== (int)$row['quantity']) {
                        throw new InvalidArgumentException($row['name'] . ' এখন উপলব্ধ নয়');
                    }
                } elseif ($row['stock_quantity'] !== null && $quantity > (int)$row['stock_quantity']
                          && $quantity !== (int)$row['quantity']) {
                    throw new InvalidArgumentException($row['name'] . ': স্টকে সর্বোচ্চ ' . (int)$row['stock_quantity'] . ' পিস আছে');
                }
                if ($quantity !== (int)$row['quantity']) $validated[$id] = $quantity;
            }
            $updateStmt = $pdo->prepare("UPDATE resell_cart SET quantity = ?, updated_at = NOW() WHERE id = ? AND reseller_id = ?");
            foreach ($validated as $id => $quantity) {
                $updateStmt->execute([$quantity, $id, $resellerId]);
            }
            $pdo->commit();
            setFlash('success', 'সব পণ্যের পরিমাণ একসঙ্গে সেভ হয়েছে');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('NOTE17 reseller bulk cart update: ' . $e->getMessage());
            setFlash('error', $e instanceof InvalidArgumentException
                ? $e->getMessage() : 'কার্ট সেভ করা যায়নি, আবার চেষ্টা করুন');
        }
        redirect(baseUrl('resell/cart.php'));
    }

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
.cart-save-hint { font-size:.83rem; }
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
/* NOTE17 mobile UI revision - visual/layout only; preserves atomic Save All. */
.cart-shell { padding-bottom: 28px; }
.cart-card, .summary-card { border: 1px solid #ebeff7; box-shadow: 0 10px 30px rgba(20,34,74,.06); border-radius: 18px; }
.cart-item { gap: 14px; }
.cart-price { font-size: 1rem; }
.cart-name { overflow-wrap: anywhere; }
.cart-page-heading .btn { border-radius: 10px; }
.cart-shell .summary-card h5 { font-weight: 700; }
.cart-shell .summary-card .btn { border-radius: 10px; }
.cart-shell .qty-form input { height: 42px; border-radius: 9px; border: 1px solid #d5deed; width: 80px; font-weight: 650; }
.cart-shell .qty-form input:focus { border-color: #667eea; box-shadow: 0 0 0 .2rem rgba(102,126,234,.12); }
@media (max-width: 767.98px) {
    .cart-shell { padding: 0 10px 105px; max-width: 100%; }
    .cart-page-heading { align-items: stretch !important; gap: 14px !important; }
    .cart-page-heading > div:first-child { width: 100%; }
    .cart-page-heading h3 { font-size: 1.45rem; font-weight: 750; }
    .cart-toolbar { display: grid !important; grid-template-columns: minmax(0, 1fr) auto; width: 100%; gap: 8px !important; align-items: stretch; }
    .cart-toolbar > .btn, .cart-toolbar > form > .btn { min-height: 46px; padding: 9px 10px; font-size: .85rem; line-height: 1.25; white-space: normal; }
    .cart-toolbar > form { margin: 0; }
    .cart-card { border-radius: 16px; overflow: hidden; }
    .cart-item { display: grid; grid-template-columns: 68px minmax(0,1fr) 100px; grid-template-areas: 'photo details remove' 'quantity quantity total'; align-items: start; gap: 12px 11px; padding: 16px 12px; border-bottom: 1px solid #edf0f6; }
    .cart-item:last-child { border-bottom: 0; }
    .cart-img { grid-area: photo; width: 68px; height: 68px; border-radius: 11px; }
    .cart-item > div:nth-child(2) { grid-area: details; min-width: 0; }
    .cart-name { font-size: .95rem; line-height: 1.4; }
    .cart-item .qty-wrap { grid-area: quantity; grid-column: 1 / 3; min-width: 0; }
    .cart-item .line-total { grid-area: total; grid-column: 3; justify-self: end; align-self: center; text-align: right; font-size: .94rem; white-space: normal; overflow-wrap: anywhere; }
    .cart-item .remove-wrap { grid-area: remove; grid-column: 3; justify-self: end; }
    .cart-item .remove-wrap .btn { width: 34px; height: 34px; display: inline-flex; justify-content: center; align-items: center; padding: 0; border-radius: 8px; }
    .cart-shell .qty-form { gap: 0; }
    .cart-shell .qty-form input { width: 76px; height: 38px; }
    .summary-card.position-sticky { position: static !important; top: auto !important; padding: 20px 16px; border-radius: 16px; }
    .cart-shell .row.g-4 { --bs-gutter-y: 16px; }
    .cart-shell .summary-card .btn { min-height: 47px; }
}
@media (max-width: 360px) {
    .cart-item { grid-template-columns: 56px minmax(0,1fr) 94px; gap: 10px 8px; }
    .cart-img { width: 56px; height: 56px; }
    .cart-item .line-total { font-size: .86rem; }
    .cart-toolbar > .btn, .cart-toolbar > form > .btn { font-size: .77rem; }
}
</style>

<div class="cart-shell">
    <div class="cart-page-heading d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="bi bi-cart3 me-2"></i>রিসেলার কার্ট</h3>
            <div class="text-muted">মোট <?php echo count($cartItems); ?> ধরনের পণ্য</div>
        </div>
        <?php if (!empty($cartItems)): ?>
        <div class="cart-toolbar d-flex flex-wrap gap-2">
            <button type="submit" form="resell-bulk-cart-form" class="btn btn-primary">
                <i class="bi bi-check2-all me-1"></i>সব পরিমাণ একসঙ্গে সেভ করুন
            </button>
        <form method="POST" onsubmit="return confirm('কার্ট খালি করতে চান?')">
            <input type="hidden" name="action" value="clear">
            <button class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i>কার্ট খালি করুন</button>
        </form>
        </div>
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
    <form id="resell-bulk-cart-form" method="POST">
        <input type="hidden" name="action" value="bulk_update">
    </form>
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
                        <div class="qty-form">
                            <input type="number" name="quantities[<?php echo (int)$item['id']; ?>]"
                                   form="resell-bulk-cart-form" min="1"
                                   data-unit-price="<?php echo htmlspecialchars((string)(float)$item['reseller_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                   data-original-quantity="<?php echo (int)$item['quantity']; ?>"
                                   <?php echo $item['stock_quantity'] !== null && (int)$item['stock_quantity'] >= (int)$item['quantity'] ? 'max="'.(int)$item['stock_quantity'].'"' : ''; ?>
                                   value="<?php echo (int)$item['quantity']; ?>" class="form-control form-control-sm resell-cart-qty" required>
                        </div>
                    </div>
                    <div class="line-total fw-bold" data-cart-line-total>
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
                <div class="d-flex justify-content-between mb-2"><span>পণ্য খরচ</span><strong id="resellCartBaseTotal"><?php echo formatPrice($cartBaseTotal); ?></strong></div>
                <div class="d-flex justify-content-between mb-3"><span>মোট আইটেম</span><strong id="resellCartItemsCount"><?php echo getResellerCartCount($resellerId); ?></strong></div>
                <hr>
                <a id="resellCartCheckoutLink" href="<?php echo baseUrl('resell/checkout.php'); ?>" class="btn btn-primary w-100 py-3 fw-bold">
                    <i class="bi bi-bag-check me-2"></i>চেকআউট করুন
                </a>
                <a href="<?php echo baseUrl('resell/products.php'); ?>" class="btn btn-outline-secondary w-100 mt-2">আরও পণ্য যোগ করুন</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const inputs = Array.from(document.querySelectorAll('.resell-cart-qty'));
    const checkout = document.getElementById('resellCartCheckoutLink');
    const hint = document.getElementById('resellCartUnsavedHint');
    if (inputs.length === 0) return;
    const formatter = new Intl.NumberFormat('bn-BD', {minimumFractionDigits: 0, maximumFractionDigits: 2});
    function syncTotals() {
        let total = 0;
        let count = 0;
        let dirty = false;
        inputs.forEach(input => {
            const quantity = Math.max(0, parseInt(input.value, 10) || 0);
            const unit = Number(input.dataset.unitPrice) || 0;
            const lineTotal = input.closest('.cart-item')?.querySelector('[data-cart-line-total]');
            if (lineTotal) lineTotal.textContent = '৳' + formatter.format(quantity * unit);
            total += quantity * unit;
            count += quantity;
            if (quantity !== Number(input.dataset.originalQuantity)) dirty = true;
        });
        document.getElementById('resellCartBaseTotal').textContent = '৳' + formatter.format(total);
        document.getElementById('resellCartItemsCount').textContent = count.toLocaleString('bn-BD');
        if (hint) hint.hidden = !dirty;
        if (checkout) {
            // Quantities shown on screen must match the database before checkout.
            if (dirty) {
                checkout.removeAttribute('href');
                checkout.setAttribute('aria-disabled', 'true');
                checkout.classList.add('disabled');
            } else {
                checkout.href = <?php echo json_encode(baseUrl('resell/checkout.php')); ?>;
                checkout.removeAttribute('aria-disabled');
                checkout.classList.remove('disabled');
            }
        }
    }
    inputs.forEach(input => input.addEventListener('input', syncTotals));
    syncTotals();
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
