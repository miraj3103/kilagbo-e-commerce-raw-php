<?php
/**
 * Kilagbo Reseller - Products Page
 * Developed by Webnify Bangladesh
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'পণ্য সমূহ';
$currentPage = 'products';

// Mark products notifications as seen
markResellerNotificationsSeen(getResellerId(), 'products');

// Get filters
$search = sanitize($_GET['search'] ?? '');
$category = intval($_GET['category'] ?? 0);
$hotDeals = isset($_GET['hot_deals']);

// Build query
$where = "WHERE rp.status = 1";
$params = [];

if ($search) {
    $where .= " AND rp.name LIKE ?";
    $params[] = "%$search%";
}

if ($category) {
    $where .= " AND rp.category_id = ?";
    $params[] = $category;
}

if ($hotDeals) {
    $where .= " AND rp.is_hot_deal = 1";
}

// Get products
$stmt = $pdo->prepare("SELECT rp.*, c.name as category_name FROM resell_products rp LEFT JOIN categories c ON rp.category_id = c.id $where ORDER BY rp.is_hot_deal DESC, rp.created_at DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories
$categories = $pdo->query("SELECT DISTINCT c.* FROM categories c INNER JOIN resell_products rp ON c.id = rp.category_id WHERE rp.status = 1")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
    .filter-bar {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        align-items: stretch;
    }

    .product-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .product-card .image {
        position: relative;
        width: 100%;
        height: 250px;
        overflow: hidden;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .product-card .image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }

    .product-card .hot-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: linear-gradient(135deg, #f093fb, #f5576c);
        color: #fff;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .product-card .delivery-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: #fff;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .product-card .info {
        padding: 15px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .product-card .name {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
        line-height: 1.45;
        height: 46px;
        min-height: 46px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }

    .product-card .price {
        display: flex;
        align-items: flex-start;
        align-content: flex-start;
        flex-wrap: wrap;
        gap: 6px 10px;
        margin-bottom: 12px;
        min-height: 48px;
    }

    .product-card .price .current {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }

    .product-card .price .suggested {
        font-size: 0.85rem;
        color: #28a745;
    }

    .btn-order {
        width: 100%;
        padding: 14px 25px;
        background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
        border: none;
        color: #fff !important;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
        min-height: 48px;
        margin-top: auto;
    }

    .btn-order.disabled, .btn-order:disabled {
        opacity: 0.85;
        cursor: not-allowed;
        box-shadow: none;
    }

    .btn-order:hover:not(.disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        color: #fff !important;
    }

    .product-actions {
        margin-top: auto;
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .product-actions .btn-order { margin-top: 0; }

    .btn-cart {
        background: #fff;
        color: #667eea !important;
        border: 1px solid #667eea;
        box-shadow: none;
    }

    .btn-cart:hover:not(.disabled) {
        background: #667eea;
        color: #fff !important;
        box-shadow: 0 6px 18px rgba(99, 102, 241, 0.22);
    }

    .stock-out {
        opacity: 0.6;
    }

    .stock-out .btn-order {
        background: #6c757d;
        pointer-events: none;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border-radius: 12px;
    }

    .empty-state i {
        font-size: 4rem;
        color: #ddd;
    }

    /* Modal Styles */
    .modal-product-image {
        width: 100%;
        border-radius: 12px;
        margin-bottom: 15px;
    }

    .gallery-thumbnails {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .gallery-thumbnails img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.2s;
    }

    .gallery-thumbnails img.active {
        border-color: var(--primary);
    }

    .product-details-content .price-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .product-details-content .price-info .reseller-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        display: block;
    }

    .product-details-content .price-info .suggested-price {
        color: #28a745;
        font-weight: 500;
    }

    .product-description {
        font-size: 0.95rem;
        line-height: 1.6;
        color: #555;
    }

    .modal-fullscreen-sm-down .modal-content {
        border-radius: 15px;
    }

    @media (min-width: 576px) and (max-width: 991.98px) {
        .product-card .image {
            height: 220px;
        }
    }

    @media (max-width: 575.98px) {
        .modal-fullscreen-sm-down .modal-content {
            border-radius: 0;
        }

        .product-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .product-card .image {
            height: 170px;
        }

        .product-card .info {
            padding: 10px;
        }

        .product-card .name {
            font-size: 0.85rem;
            margin-bottom: 5px;
            line-height: 1.4;
            height: 38px;
            min-height: 38px;
        }

        .product-card .price {
            flex-direction: column;
            gap: 2px;
            align-items: flex-start;
            margin-bottom: 10px;
            min-height: 45px;
        }

        .product-card .price .current {
            font-size: 1rem;
        }

        .product-card .price .suggested {
            font-size: 0.7rem;
        }

        .btn-order {
            padding: 8px 10px;
            font-size: 0.85rem;
            border-radius: 8px;
            min-height: 40px;
        }

        .product-card .hot-badge, .product-card .delivery-badge {
            padding: 2px 6px;
            font-size: 0.65rem;
            top: 5px;
        }

        .product-card .hot-badge { left: 5px; }
        .product-card .delivery-badge { right: 5px; }
    }

    .clickable-card {
        cursor: pointer;
    }
</style>

<div class="filter-bar">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">অনুসন্ধান</label>
            <input type="text" class="form-control" name="search" placeholder="পণ্যের নাম..." value="<?php echo $search; ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">ক্যাটেগরি</label>
            <select class="form-select" name="category">
                <option value="">সব ক্যাটেগরি</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="hot_deals" id="hotDeals" <?php echo $hotDeals ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hotDeals">শুধু ট্রেন্ডিং পণ্য</label>
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search me-1"></i> খুঁজুন
            </button>
        </div>
    </form>
</div>

<?php if (empty($products)): ?>
<div class="empty-state">
    <i class="bi bi-box-seam"></i>
    <h4 class="mt-3">কোনো পণ্য পাওয়া যায়নি</h4>
    <p class="text-muted">ফিল্টার পরিবর্তন করে আবার চেষ্টা করুন</p>
</div>
<?php else: ?>
<div class="product-grid">
    <?php foreach ($products as $product): ?>
    <?php $customCharge = getResellProductDeliveryCharge($product['id']); ?>
    <div class="product-card clickable-card <?php echo ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0) ? 'stock-out' : ''; ?>" data-product-id="<?php echo $product['id']; ?>">
        <div class="image">
            <img src="<?php echo imageUrl($product['image']); ?>" alt="<?php echo $product['name']; ?>">
            <?php if ($product['is_hot_deal']): ?>
            <span class="hot-badge"><i class="bi bi-fire"></i> ট্রেন্ডিং</span>
            <?php endif; ?>
            <?php if ($customCharge !== null): ?>
            <span class="delivery-badge"><i class="bi bi-truck"></i> <?php echo formatPrice($customCharge); ?></span>
            <?php endif; ?>
        </div>
        <div class="info">
            <h6 class="name"><?php echo sanitize($product['name']); ?></h6>
            <div class="price">
                <span class="current"><?php echo formatPrice($product['reseller_price']); ?></span>
                <?php if ($product['suggested_selling_price']): ?>
                <span class="suggested">সাজেস্টেড: <?php echo formatPrice($product['suggested_selling_price']); ?></span>
                <?php endif; ?>
            </div>
            <div class="product-actions">
            <?php if ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0): ?>
                <button class="btn-order bg-secondary border-0 d-block w-100 text-center" disabled>স্টক শেষ</button>
            <?php else: ?>
                <?php if ($product['has_variations']): ?>
                <button type="button" class="btn-order btn-cart d-block w-100 text-center text-decoration-none card-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                    <i class="bi bi-cart-plus me-1"></i> কার্টে যোগ করুন
                </button>
                <?php else: ?>
                <button type="button" class="btn-order btn-cart d-block w-100 text-center text-decoration-none quick-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                    <i class="bi bi-cart-plus me-1"></i> কার্টে যোগ করুন
                </button>
                <?php endif; ?>

                <?php if ($hasLowBalance): ?>
                <button type="button" class="btn-order d-block w-100 text-center text-decoration-none" data-bs-toggle="modal" data-bs-target="#minBalanceModal">
                    <i class="bi bi-bag-plus me-1"></i> অর্ডার করুন
                </button>
                <?php elseif ($product['has_variations']): ?>
                <button type="button" class="btn-order d-block w-100 text-center text-decoration-none card-order-btn" data-product-id="<?php echo $product['id']; ?>">
                    <i class="bi bi-bag-plus me-1"></i> অর্ডার করুন
                </button>
                <?php else: ?>
                <a href="<?php echo baseUrl('resell/order.php?product=' . $product['id']); ?>" class="btn-order d-block text-center text-decoration-none">
                    <i class="bi bi-bag-plus me-1"></i> অর্ডার করুন
                </a>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Product Detail Modal -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="productDetailModalLabel">পণ্য বিস্তারিত</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="productDetailLoader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">লোডিং হচ্ছে...</p>
                </div>
                <div id="productDetailContent" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <img id="modalMainImage" src="" alt="" class="modal-product-image">
                            <div id="modalGallery" class="gallery-thumbnails"></div>
                        </div>
                        <div class="col-md-6 product-details-content">
                            <h4 id="modalProductName" class="mb-2"></h4>
                            <p class="text-muted mb-3"><i class="bi bi-tag me-1"></i> <span id="modalCategory"></span></p>

                            <div class="price-info">
                                <span class="text-muted d-block mb-1">রিসেলার প্রাইস:</span>
                                <span id="modalResellerPrice" class="reseller-price"></span>
                                <div id="modalSuggestedPriceContainer" class="mt-2">
                                    <span class="text-muted">সাজেস্টেড প্রাইস:</span>
                                    <span id="modalSuggestedPrice" class="suggested-price"></span>
                                </div>
                            </div>

                            <div id="modalVideoContainer" class="mb-4" style="display: none;">
                                <h5 class="mb-3">পণ্যের ভিডিও:</h5>
                                <div class="ratio ratio-16x9">
                                    <iframe id="modalVideoFrame" src="" title="Product Video" allowfullscreen></iframe>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5>বিস্তারিত:</h5>
                                <div id="modalDescription" class="product-description mb-4"></div>

                                <!-- Variations Section -->
                                <div id="productVariations" class="mb-4" style="display: none;">
                                    <h6 class="fw-bold mb-3">সিলেক্ট করুন:</h6>
                                    <div id="variationsContainer"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-grid gap-2">
                <button type="button" id="modalCartBtn" class="btn-order btn-cart d-block text-center text-decoration-none w-100">
                    <i class="bi bi-cart-plus me-1"></i> কার্টে যোগ করুন
                </button>
                <a id="modalOrderBtn" href="#" class="btn-order d-block text-center text-decoration-none w-100">
                    <i class="bi bi-bag-plus me-1"></i> অর্ডার করুন
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productCards = document.querySelectorAll('.clickable-card');
    const modal = new bootstrap.Modal(document.getElementById('productDetailModal'));
    const loader = document.getElementById('productDetailLoader');
    const content = document.getElementById('productDetailContent');
    const orderBtn = document.getElementById('modalOrderBtn');
    const cartBtn = document.getElementById('modalCartBtn');
    const hasLowBalance = <?php echo $hasLowBalance ? 'true' : 'false'; ?>;
    let activeModalProductId = null;
    let activeModalVariation = {};

    // Stop video when modal is closed
    document.getElementById('productDetailModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalVideoFrame').src = '';
    });

    productCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't open modal if clicking on the original order button
            if (e.target.closest('.btn-order')) return;

            const productId = this.dataset.productId;
            openProductModal(productId);
        });
    });

    // Handle card order buttons for products with variations
    const cardOrderBtns = document.querySelectorAll('.card-order-btn');
    cardOrderBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click event
            const productId = this.dataset.productId;
            openProductModal(productId, true);
        });
    });

    document.querySelectorAll('.quick-cart-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            addToResellerCart(this.dataset.productId, {}, this);
        });
    });

    document.querySelectorAll('.card-cart-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            openProductModal(this.dataset.productId, true);
        });
    });

    if (cartBtn) {
        cartBtn.addEventListener('click', function() {
            if (this.dataset.needsVariation === 'true') {
                alert('দয়া করে সব ভেরিয়েশন সিলেক্ট করুন।');
                return;
            }
            if (activeModalProductId) {
                addToResellerCart(activeModalProductId, activeModalVariation, this);
            }
        });
    }

    // Global Order Button Handler for Variation Validation
    if (orderBtn) {
        orderBtn.addEventListener('click', function(e) {
            if (this.dataset.needsVariation === 'true') {
                e.preventDefault();
                alert('দয়া করে সব ভেরিয়েশন সিলেক্ট করুন।');
                const varSection = document.getElementById('productVariations');
                if(varSection && varSection.style.display !== 'none') {
                    varSection.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            }
        });
    }

    function openProductModal(id, scrollVariations = false) {
        modal.show();
        loader.style.display = 'block';
        content.style.display = 'none';

        fetch(`get-product-details.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const p = data.product;
                    activeModalProductId = p.id;
                    activeModalVariation = {};
                    cartBtn.dataset.needsVariation = 'false';
                    cartBtn.disabled = false;
                    document.getElementById('modalProductName').innerText = p.name;
                    document.getElementById('modalCategory').innerText = p.category;
                    document.getElementById('modalResellerPrice').innerText = p.reseller_price;

                    if (p.suggested_selling_price) {
                        document.getElementById('modalSuggestedPriceContainer').style.display = 'block';
                        document.getElementById('modalSuggestedPrice').innerText = p.suggested_selling_price;
                    } else {
                        document.getElementById('modalSuggestedPriceContainer').style.display = 'none';
                    }

                    document.getElementById('modalDescription').innerHTML = p.description || p.short_description || 'কোনো বর্ণনা পাওয়া যায়নি।';

                    // Video logic
                    const videoContainer = document.getElementById('modalVideoContainer');
                    const videoFrame = document.getElementById('modalVideoFrame');

                    if (p.video_url) {
                        let videoId = '';
                        if (p.video_url.includes('youtube.com/watch?v=')) {
                            videoId = p.video_url.split('v=')[1].split('&')[0];
                        } else if (p.video_url.includes('youtu.be/')) {
                            videoId = p.video_url.split('youtu.be/')[1].split('?')[0];
                        }

                        if (videoId) {
                            videoFrame.src = `https://www.youtube.com/embed/${videoId}`;
                            videoContainer.style.display = 'block';
                        } else {
                            videoContainer.style.display = 'none';
                        }
                    } else {
                        videoContainer.style.display = 'none';
                        videoFrame.src = '';
                    }

                    const mainImg = document.getElementById('modalMainImage');
                    mainImg.src = p.image;
                    mainImg.alt = p.name;

                    const gallery = document.getElementById('modalGallery');
                    gallery.innerHTML = '';

                    if (p.gallery && p.gallery.length > 0) {
                        // Add main image to gallery as well
                        const allImages = [p.image, ...p.gallery];
                        allImages.forEach((img, index) => {
                            const thumb = document.createElement('img');
                            thumb.src = img;
                            thumb.classList.toggle('active', index === 0);
                            thumb.addEventListener('click', function() {
                                mainImg.src = img;
                                gallery.querySelectorAll('img').forEach(i => i.classList.remove('active'));
                                thumb.classList.add('active');
                            });
                            gallery.appendChild(thumb);
                        });
                        gallery.style.display = 'flex';
                    } else {
                        gallery.style.display = 'none';
                    }

                    // Order button logic
                    if (p.stock_status === 'out_of_stock') {
                        orderBtn.href = 'javascript:void(0)';
                        orderBtn.classList.remove('bg-secondary');
                        orderBtn.classList.add('disabled');
                        orderBtn.innerHTML = '<i class="bi bi-bag-plus me-1"></i> স্টক শেষ';
                        orderBtn.style.pointerEvents = 'none';
                        orderBtn.removeAttribute('data-bs-toggle');
                        orderBtn.removeAttribute('data-bs-target');
                        orderBtn.dataset.needsVariation = 'false';
                    } else if (hasLowBalance) {
                        orderBtn.href = 'javascript:void(0)';
                        orderBtn.classList.remove('bg-secondary', 'disabled');
                        orderBtn.innerHTML = '<i class="bi bi-bag-plus me-1"></i> অর্ডার করুন';
                        orderBtn.style.pointerEvents = 'auto';
                        orderBtn.setAttribute('data-bs-toggle', 'modal');
                        orderBtn.setAttribute('data-bs-target', '#minBalanceModal');
                        orderBtn.dataset.needsVariation = 'false';
                    } else {
                        orderBtn.href = p.order_url;
                        orderBtn.classList.remove('bg-secondary', 'disabled');
                        orderBtn.innerHTML = '<i class="bi bi-bag-plus me-1"></i> অর্ডার করুন';
                        orderBtn.style.pointerEvents = 'auto';
                        orderBtn.removeAttribute('data-bs-toggle');
                        orderBtn.removeAttribute('data-bs-target');
                        orderBtn.dataset.needsVariation = 'false';
                    }

                    // Cart button availability is independent from the minimum-order-balance check.
                    if (p.stock_status === 'out_of_stock') {
                        cartBtn.disabled = true;
                        cartBtn.classList.add('disabled');
                        cartBtn.innerHTML = '<i class="bi bi-cart-x me-1"></i> স্টক শেষ';
                    } else {
                        cartBtn.disabled = false;
                        cartBtn.classList.remove('disabled');
                        cartBtn.innerHTML = '<i class="bi bi-cart-plus me-1"></i> কার্টে যোগ করুন';
                    }

                    // Handle Variations
                    const variationsSection = document.getElementById('productVariations');
                    const variationsContainer = document.getElementById('variationsContainer');
                    variationsContainer.innerHTML = '';

                    if (p.has_variations && p.variations && p.stock_status !== 'out_of_stock') {
                        variationsSection.style.display = 'block';

                        // If scrollVariations is true, we will scroll after content is visible
                        if (scrollVariations) {
                            setTimeout(() => {
                                variationsSection.scrollIntoView({behavior: 'smooth', block: 'center'});
                            }, 500);
                        }

                        for (const [attrName, values] of Object.entries(p.variations)) {
                            const attrDiv = document.createElement('div');
                            attrDiv.className = 'mb-3';
                            attrDiv.innerHTML = `<label class="form-label fw-semibold">${attrName}</label>`;

                            const optionsDiv = document.createElement('div');
                            optionsDiv.className = 'd-flex flex-wrap gap-2';

                            values.forEach(val => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'btn btn-outline-secondary btn-sm variation-option';
                                btn.innerText = val;
                                btn.dataset.attribute = attrName;
                                btn.dataset.value = val;

                                btn.addEventListener('click', function() {
                                    optionsDiv.querySelectorAll('.variation-option').forEach(b => {
                                        b.classList.remove('btn-primary', 'active');
                                        b.classList.add('btn-outline-secondary');
                                    });
                                    this.classList.remove('btn-outline-secondary');
                                    this.classList.add('btn-primary', 'active');

                                    updateSelectedVariations(p.order_url);
                                });

                                optionsDiv.appendChild(btn);
                            });

                            attrDiv.appendChild(optionsDiv);
                            variationsContainer.appendChild(attrDiv);
                        }

                        // Cart always requires variations. Direct order keeps the existing low-balance modal behavior.
                        orderBtn.dataset.needsVariation = hasLowBalance ? 'false' : 'true';
                        cartBtn.dataset.needsVariation = 'true';
                    } else {
                        variationsSection.style.display = 'none';
                        orderBtn.dataset.needsVariation = 'false';
                        cartBtn.dataset.needsVariation = 'false';
                    }

                    loader.style.display = 'none';
                    content.style.display = 'block';
                } else {
                    alert('Error: ' + data.message);
                    modal.hide();
                }
            })
            .catch(error => {
                console.error('Error fetching product details:', error);
                alert('পণ্য লোড করতে সমস্যা হয়েছে।');
                modal.hide();
            });
    }

    function updateSelectedVariations(baseUrl) {
        const selectedPairs = [];
        const selectedObject = {};
        document.querySelectorAll('.variation-option.active').forEach(btn => {
            selectedPairs.push(`${encodeURIComponent(btn.dataset.attribute)}:${encodeURIComponent(btn.dataset.value)}`);
            selectedObject[btn.dataset.attribute] = btn.dataset.value;
        });

        const allAttributes = Array.from(new Set(Array.from(document.querySelectorAll('.variation-option')).map(b => b.dataset.attribute)));
        const complete = Object.keys(selectedObject).length === allAttributes.length;

        activeModalVariation = complete ? selectedObject : {};
        if (complete) {
            if (!hasLowBalance) {
                const separator = baseUrl.includes('?') ? '&' : '?';
                orderBtn.href = baseUrl + separator + 'variations=' + selectedPairs.join(',');
                orderBtn.dataset.needsVariation = 'false';
            } else {
                orderBtn.dataset.needsVariation = 'false';
            }
            cartBtn.dataset.needsVariation = 'false';
        } else {
            if (!hasLowBalance) {
                orderBtn.href = 'javascript:void(0)';
                orderBtn.dataset.needsVariation = 'true';
            } else {
                orderBtn.dataset.needsVariation = 'false';
            }
            cartBtn.dataset.needsVariation = 'true';
        }
    }

    function addToResellerCart(productId, variation = {}, button = null) {
        const body = new FormData();
        body.append('product_id', productId);
        body.append('quantity', 1);
        if (variation && Object.keys(variation).length) {
            body.append('variation', JSON.stringify(variation));
        }

        const originalHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> যোগ হচ্ছে...';
        }

        fetch('<?php echo baseUrl('resell/cart-action.php'); ?>', { method: 'POST', body })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'কার্টে যোগ করা যায়নি');
                return data;
            })
            .then(data => {
                const badge = document.getElementById('resellerCartCount');
                if (badge) {
                    badge.textContent = data.cartCount;
                    badge.style.display = data.cartCount > 0 ? '' : 'none';
                }
                showToast('সফল', data.message);
            })
            .catch(error => showToast('ত্রুটি', error.message, 'error'))
            .finally(() => {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
