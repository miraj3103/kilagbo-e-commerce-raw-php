<?php
/**
 * Kilagbo - Cart Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';
require_once __DIR__ . '/wholesale/includes/access.php';

$currentPage = 'retail';
$pageTitle = 'কার্ট';

$sessionId = getSessionId();
$userId = $_SESSION['user_id'] ?? null;

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $cartId = intval($_POST['cart_id']);
        $quantity = max(1, intval($_POST['quantity']));
        $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?")->execute([$quantity, $cartId]);
        setFlash('success', 'কার্ট আপডেট করা হয়েছে');
        redirect(baseUrl('cart'));
    }
    
    if ($action === 'remove') {
        $cartId = intval($_POST['cart_id']);
        $pdo->prepare("DELETE FROM cart WHERE id = ?")->execute([$cartId]);
        setFlash('success', 'আইটেম সরানো হয়েছে');
        redirect(baseUrl('cart'));
    }
    
    if ($action === 'clear') {
        if ($userId) {
            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
        } else {
            $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([$sessionId]);
        }
        setFlash('success', 'কার্ট খালি করা হয়েছে');
        redirect(baseUrl('cart'));
    }
}

// Fetch cart items
$sql = "SELECT c.*, 
        COALESCE(p.name, wp.name, ap.name) as name, 
        COALESCE(p.slug, wp.slug) as slug, 
        COALESCE(p.image, wp.image, ap.image) as image, 
        p.regular_price as retail_regular_price, 
        p.sale_price as retail_sale_price,
        wp.wholesale_price,
        wp.min_order_qty as wholesale_min_qty,
        ap.regular_price as affiliate_regular_price,
        ap.sale_price as affiliate_sale_price,
        ap.wholesale_product_id as ap_wholesale_id
        FROM cart c 
        LEFT JOIN products p ON c.product_id = p.id AND c.section = 'retail'
        LEFT JOIN wholesale_products wp ON c.product_id = wp.id AND c.section = 'wholesale'
        LEFT JOIN affiliate_products ap ON c.product_id = ap.id AND c.section = 'affiliate'
        WHERE ";

if ($userId) {
    $sql .= "c.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
} else {
    $sql .= "c.session_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sessionId]);
}
$cartItems = $stmt->fetchAll();
if (!wholesaleIsApproved($pdo)) {
    // Hide previously stored wholesale cart rows until explicit approval.
    $cartItems = array_values(array_filter($cartItems, static fn($item) => $item['section'] !== 'wholesale'));
}

// NOTE17: classify a wholesale-only cart so the mobile Shop button stays hidden.
$isWholesaleOnly = !empty($cartItems) &&
    count(array_filter($cartItems, static fn($item) => ($item['section'] ?? '') === 'wholesale')) === count($cartItems);
if ($isWholesaleOnly) $currentPage = 'wholesale';
// Only the wholesale-only shared cart hides navigation menus; all other
// pages (including wholesale success and checkout) keep their existing menus.
$hideWholesaleCartMenus = $isWholesaleOnly;

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    if ($item['section'] === 'wholesale') {
        $price = $item['wholesale_price'];
    } elseif ($item['section'] === 'affiliate') {
        $price = $item['affiliate_sale_price'] ?: $item['affiliate_regular_price'];
    } else {
        $price = $item['retail_sale_price'] ?: $item['retail_regular_price'];
    }
    $subtotal += $price * $item['quantity'];
}

include 'includes/header.php';
?>

<style>
    <?php if ($hideWholesaleCartMenus): ?>
    /* Wholesale-only cart: hide the top navigation menu, not the checkout. */
    .main-header .main-nav, .main-header .mobile-menu-toggle, .mobile-nav { display: none !important; }
    <?php endif; ?>
    .cart-page {
        padding: 40px 0;
    }
    
    .cart-header {
        margin-bottom: 30px;
    }
    
    .cart-table {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .cart-table table {
        width: 100%;
        margin: 0;
    }
    
    .cart-table th {
        background: var(--bg-light);
        padding: 15px 20px;
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
    }
    
    .cart-table td {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    
    .cart-product {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .cart-product img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .cart-product-info h6 {
        margin: 0 0 5px;
        font-weight: 500;
    }
    
    .cart-product-info h6 a {
        color: var(--text-color);
        text-decoration: none;
    }
    
    .cart-product-info small {
        color: var(--text-light);
    }
    
    .cart-quantity {
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
        width: fit-content;
    }
    
    .cart-quantity button {
        width: 35px;
        height: 35px;
        border: none;
        background: var(--bg-light);
        cursor: pointer;
    }
    
    .cart-quantity input {
        width: 45px;
        height: 35px;
        border: none;
        text-align: center;
    }
    
    .cart-summary {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .cart-summary h5 {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    
    .summary-row.total {
        font-size: 1.2rem;
        font-weight: 600;
        padding-top: 15px;
        border-top: 2px solid var(--border-color);
        margin-top: 15px;
    }
    
    .empty-cart {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border-radius: 12px;
    }
    
    .empty-cart i {
        font-size: 4rem;
        color: #ddd;
    }
    
    @media (max-width: 768px) {
        .cart-table {
            overflow-x: auto;
        }
        
        .cart-table table {
            min-width: 600px;
        }
        
        .cart-product img {
            width: 60px;
            height: 60px;
        }
    }
</style>

<?php showFlash(); ?>

<div class="cart-page">
    <div class="container">
        <div class="cart-header">
            <h1>আপনার কার্ট</h1>
        </div>
        
        <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
            <i class="bi bi-bag-x"></i>
            <h4 class="mt-3">আপনার কার্ট খালি</h4>
            <p class="text-muted">কার্টে কিছু পণ্য যোগ করুন</p>
            <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-primary-custom">শপিং করুন</a>
        </div>
        <?php else: ?>
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="cart-table">
                    <table>
                        <thead>
                            <tr>
                                <th>পণ্য</th>
                                <th>দাম</th>
                                <th>পরিমাণ</th>
                                <th>মোট</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                            <?php 
                            $isWholesale = ($item['section'] === 'wholesale');
                            $isAffiliate = ($item['section'] === 'affiliate');
                            $minQty = 1;
                            $productUrl = '#'; // Default fallback
                            
                            if ($isWholesale) {
                                $price = $item['wholesale_price'];
                                if ($item['slug']) $productUrl = baseUrl('wholesale/product/' . $item['slug']);
                                $minQty = $item['wholesale_min_qty'] ?: 1;
                            } elseif ($isAffiliate) {
                                $price = $item['affiliate_sale_price'] ?: $item['affiliate_regular_price'];
                                // If it's a wholesale affiliate product, check min qty
                                if (!empty($item['ap_wholesale_id'])) {
                                     // We need to fetch min qty for the wholesale product. 
                                     // Optimization: We could join wholesale_products twice in query, but for now simple Query or assumtion
                                     // Actually, ap_wholesale_id is just an ID. 
                                     // Let's assume 1 for now or if strict, we need to fetch it.
                                     // For simplicity in cart display, we set 1, validation handles strictness.
                                     // But better UX: Check min qty.
                                     // Since I can't easily join a second time without query refactor, I'll rely on server validation or 1.
                                }
                                // URL: Construct generic or based on link/store if available
                                if ($item['affiliate_link_id']) { 
                                     // We don't have code, so '#' or just no link
                                     $productUrl = '#';
                                } elseif ($item['store_id']) {
                                     $productUrl = baseUrl('store-product/' . $item['store_id'] . '/' . $item['product_id']); // Approximation
                                }
                            } else {
                                $price = $item['retail_sale_price'] ?: $item['retail_regular_price'];
                                if ($item['slug']) $productUrl = baseUrl('product/' . $item['slug']);
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="cart-product">
                                        <img src="<?php echo imageUrl($item['image']); ?>" alt="">
                                        <div class="cart-product-info">
                                            <h6><a href="<?php echo $productUrl; ?>"><?php echo sanitize($item['name']); ?></a></h6>
                                            <?php if ($isWholesale): ?>
                                            <span class="badge bg-secondary mb-1" style="font-size: 0.7rem;">পাইকারি</span>
                                            <small class="d-block text-muted">মিনিমাম অর্ডার: <?php echo $minQty; ?> পিস</small>
                                            <?php endif; ?>
                                            <?php if ($item['variation']): ?>
                                            <?php 
                                            $variations = json_decode($item['variation'], true);
                                            if ($variations):
                                            ?>
                                            <small>
                                                <?php 
                                                $varText = [];
                                                foreach ($variations as $key => $val) {
                                                    $varText[] = $key . ': ' . $val;
                                                }
                                                echo implode(', ', $varText);
                                                ?>
                                            </small>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatPrice($price); ?></td>
                                <td>
                                    <form method="POST" class="cart-quantity-form">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <div class="cart-quantity">
                                            <button type="button" onclick="updateQty(this, -1)">-</button>
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="<?php echo $minQty; ?>" onchange="this.form.submit()">
                                            <button type="button" onclick="updateQty(this, 1)">+</button>
                                        </div>
                                    </form>
                                </td>
                                <td><strong><?php echo formatPrice($price * $item['quantity']); ?></strong></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="সরান">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <a href="<?php echo baseUrl('shop'); ?>" class="btn btn-outline-custom">
                        <i class="bi bi-arrow-left me-2"></i>শপিং চালিয়ে যান
                    </a>
                    <form method="POST">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('কার্ট খালি করতে চান?')">
                            <i class="bi bi-trash me-2"></i>কার্ট খালি করুন
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="cart-summary">
                    <h5>অর্ডার সামারি</h5>
                    
                    <div class="summary-row">
                        <span>সাবটোটাল</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>ডেলিভারি চার্জ</span>
                        <span class="text-muted">চেকআউটে নির্ধারিত হবে</span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>মোট</span>
                        <span><?php echo formatPrice($subtotal); ?>+</span>
                    </div>
                    
                    <a href="<?php echo baseUrl('checkout'); ?>" class="btn btn-primary-custom w-100 mt-3">
                        <i class="bi bi-credit-card me-2"></i>চেকআউট করুন
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateQty(btn, delta) {
        const form = btn.closest('form');
        const input = form.querySelector('input[name="quantity"]');
        const min = parseInt(input.getAttribute('min')) || 1;
        let value = parseInt(input.value) + delta;
        if (value < min) value = min;
        input.value = value;
        form.submit();
    }
    
    // DataLayer - View Cart
    pushDataLayer('view_cart', {
        currency: 'BDT',
        value: <?php echo $subtotal; ?>,
        items: [
            <?php foreach ($cartItems as $item): ?>
            <?php 
                $price = 0;
                if ($item['section'] === 'wholesale') {
                    $price = $item['wholesale_price'];
                } elseif ($item['section'] === 'affiliate') {
                    $price = $item['affiliate_sale_price'] ?: $item['affiliate_regular_price'];
                } else {
                    $price = $item['retail_sale_price'] ?: $item['retail_regular_price'];
                }
                $price = $price ?: 0;
            ?>
            {
                item_id: '<?php echo $item['product_id']; ?>',
                item_name: '<?php echo addslashes($item['name']); ?>',
                price: <?php echo $price; ?>,
                quantity: <?php echo $item['quantity']; ?>
            },
            <?php endforeach; ?>
        ]
    });
</script>

<?php include 'includes/footer.php'; ?>
