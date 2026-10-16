<?php
/**
 * Kilagbo Admin - Add Retail Products to Resell
 * Developed by Webnify Bangladesh
 */

$currentPage = 'resell-products';
require_once 'includes/header.php';

$pageTitle = 'রিটেইল থেকে রিসেলে যোগ করুন';

// Handle bulk add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_products'])) {
    $selectedProducts = $_POST['products'] ?? [];
    $resellerPrices = $_POST['reseller_prices'] ?? [];
    
    if (empty($selectedProducts)) {
        setFlash('error', 'কমপক্ষে একটি পণ্য সিলেক্ট করুন');
    } else {
        $added = 0;
        $skipped = 0;
        
        foreach ($selectedProducts as $productId) {
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM resell_products WHERE retail_product_id = ?");
            $stmt->execute([$productId]);
            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }
            
            // Get retail product
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product) {
                $retailPrice = $product['sale_price'] ?: $product['regular_price'];
                $resellerPrice = !empty($resellerPrices[$productId]) ? (float)$resellerPrices[$productId] : $retailPrice;
                
                $stmt = $pdo->prepare("
                    INSERT INTO resell_products 
                    (retail_product_id, name, slug, description, short_description, image, gallery, video_url, category_id, original_price, reseller_price, suggested_selling_price, stock_quantity, is_standalone, status, has_variations) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)
                ");
                $stmt->execute([
                    $product['id'],
                    $product['name'],
                    'resell-' . $product['slug'],
                    $product['description'],
                    $product['short_description'],
                    $product['image'],
                    $product['gallery'],
                    $product['video_url'] ?? null,
                    $product['category_id'],
                    $retailPrice,
                    $resellerPrice,
                    $resellerPrice + 100, // Suggested price = reseller_price + 100
                    $product['stock_quantity'],
                    $product['has_variations']
                ]);
                
                $resellProductId = $pdo->lastInsertId();
                
                // Copy variations if exist
                if ($product['has_variations']) {
                    $vStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ?");
                    $vStmt->execute([$product['id']]);
                    $variations = $vStmt->fetchAll();

                    foreach ($variations as $var) {
                        $ivStmt = $pdo->prepare("INSERT INTO resell_product_variations (resell_product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
                        $ivStmt->execute([$resellProductId, $var['attribute_name'], $var['attribute_value']]);
                    }
                }
                
                $added++;
            }
        }
        
        if ($added > 0) {
            setFlash('success', "$added টি পণ্য রিসেলে যোগ হয়েছে" . ($skipped > 0 ? ", $skipped টি আগে থেকেই ছিল" : ""));
        } else {
            setFlash('warning', 'কোনো নতুন পণ্য যোগ হয়নি। সবগুলো আগে থেকেই রিসেলে আছে।');
        }
        redirect(adminUrl('resell-products.php'));
    }
}

// Get search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$categoryFilter = sanitize($_GET['category'] ?? '');

// Get retail products not in resell with search and filter
$where = "WHERE p.status = 1";
$params = [];

if ($search) {
    $where .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

if ($categoryFilter) {
    $where .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

$query = "
    SELECT p.*, c.name as category_name,
           (SELECT id FROM resell_products WHERE retail_product_id = p.id) as resell_id
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where 
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<style>
    .product-checkbox {
        width: 100%;
        cursor: pointer;
    }
    
    .product-checkbox input {
        display: none;
    }
    
    .product-checkbox .card {
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .product-checkbox input:checked + .card {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), 0.05);
    }
    
    .product-checkbox .check-icon {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .product-checkbox input:checked + .card .check-icon {
        background: var(--bs-primary);
        border-color: var(--bs-primary);
        color: #fff;
    }
    
    .already-added {
        opacity: 0.5;
        pointer-events: none;
    }
    
    .already-added .card::after {
        content: 'ইতিমধ্যে যোগ করা';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.7);
        color: #fff;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-plus-lg me-2"></i>রিটেইল থেকে রিসেলে যোগ করুন</h4>
        <a href="<?php echo adminUrl('resell-products.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ফিরে যান
        </a>
    </div>
    
    <?php showFlash(); ?>
    
    <!-- Search and Filter -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">পণ্য খুঁজুন</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">পণ্যের নাম</label>
                    <input type="text" class="form-control" name="search" placeholder="পণ্যের নাম দিয়ে খুঁজুন..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ক্যাটেগরি</label>
                    <select class="form-select" name="category">
                        <option value="">সব ক্যাটেগরি</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo $cat['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search me-1"></i> খুঁজুন
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <form method="POST" id="addForm">
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <strong id="selectedCount">0</strong> টি পণ্য সিলেক্ট করা হয়েছে
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll()">সব সিলেক্ট</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAll()">সব বাতিল</button>
                    <button type="button" class="btn btn-primary" onclick="showPriceModal()">
                        <i class="bi bi-plus-lg me-1"></i> রিসেলে যোগ করুন
                    </button>
                </div>
            </div>
        </div>
        
        <div class="row g-3">
            <?php foreach ($products as $product): ?>
            <?php $price = $product['sale_price'] ?: $product['regular_price']; ?>
            <div class="col-6 col-md-4 col-lg-3">
                <label class="product-checkbox <?php echo $product['resell_id'] ? 'already-added' : ''; ?>" 
                       data-id="<?php echo $product['id']; ?>"
                       data-name="<?php echo htmlspecialchars($product['name']); ?>"
                       data-price="<?php echo $price; ?>">
                    <input type="checkbox" name="products[]" value="<?php echo $product['id']; ?>" <?php echo $product['resell_id'] ? 'disabled' : ''; ?> onchange="updateCount()">
                    <div class="card h-100 position-relative">
                        <div class="check-icon">
                            <i class="bi bi-check"></i>
                        </div>
                        <img src="<?php echo imageUrl($product['image']); ?>" class="card-img-top" alt="" style="height: 150px; object-fit: cover;">
                        <div class="card-body p-2">
                            <h6 class="card-title mb-1" style="font-size: 0.85rem;"><?php echo sanitize($product['name']); ?></h6>
                            <p class="card-text mb-0">
                                <small class="text-muted"><?php echo $product['category_name'] ?? 'N/A'; ?></small><br>
                                <strong class="text-primary"><?php echo formatPrice($price); ?></strong>
                            </p>
                        </div>
                    </div>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<!-- Price Setup Modal -->
<div class="modal fade" id="priceModal" tabindex="-1" aria-labelledby="priceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="priceModalLabel">রিসেল প্রাইস সেট করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>পণ্যের নাম</th>
                                <th width="150">রিটেইল প্রাইস</th>
                                <th width="200">রিসেল প্রাইস</th>
                            </tr>
                        </thead>
                        <tbody id="modalProductList">
                            <!-- Selected products will be listed here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-primary" onclick="submitFinalForm()">নিশ্চিত করুন</button>
            </div>
        </div>
    </div>
</div>

<script>
function updateCount() {
    const checked = document.querySelectorAll('input[name="products[]"]:checked').length;
    document.getElementById('selectedCount').textContent = checked;
}

function selectAll() {
    document.querySelectorAll('input[name="products[]"]:not(:disabled)').forEach(cb => cb.checked = true);
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('input[name="products[]"]').forEach(cb => cb.checked = false);
    updateCount();
}

function showPriceModal() {
    const selected = document.querySelectorAll('input[name="products[]"]:checked');
    if (selected.length === 0) {
        alert('কমপক্ষে একটি পণ্য সিলেক্ট করুন');
        return;
    }

    const modalBody = document.getElementById('modalProductList');
    modalBody.innerHTML = '';

    selected.forEach(checkbox => {
        const label = checkbox.closest('.product-checkbox');
        const id = label.dataset.id;
        const name = label.dataset.name;
        const price = label.dataset.price;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${name}</td>
            <td>${price}</td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">৳</span>
                    <input type="number" class="form-control reseller-price-input" 
                           name="reseller_prices[${id}]" 
                           value="${price}" 
                           step="0.01" required>
                </div>
            </td>
        `;
        modalBody.appendChild(tr);
    });

    const priceModal = new bootstrap.Modal(document.getElementById('priceModal'));
    priceModal.show();
}

function submitFinalForm() {
    // Append hidden fields for reseller prices to the main form
    const form = document.getElementById('addForm');
    const modalInputs = document.querySelectorAll('.reseller-price-input');
    
    // Clear any existing hidden reseller price inputs first
    const existingHiddenInputs = form.querySelectorAll('input[name^="reseller_prices"]');
    existingHiddenInputs.forEach(input => input.remove());

    modalInputs.forEach(input => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = input.name;
        hiddenInput.value = input.value;
        form.appendChild(hiddenInput);
    });

    // Add the add_products flag to trigger the POST handler
    const submitFlag = document.createElement('input');
    submitFlag.type = 'hidden';
    submitFlag.name = 'add_products';
    submitFlag.value = '1';
    form.appendChild(submitFlag);

    form.submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>
