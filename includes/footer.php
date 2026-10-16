    </main>
    
    <?php
    // Floating Contact Icons - Get active contacts
    $floatingContacts = [];
    $contactTypesConfig = [
        'whatsapp' => ['icon' => 'bi-whatsapp', 'color' => '#25D366', 'prefix' => 'https://wa.me/'],
        'messenger' => ['icon' => 'bi-messenger', 'color' => '#0084FF', 'prefix' => 'https://m.me/'],
        'imo' => ['icon' => 'bi-chat-dots-fill', 'color' => '#00B0FF', 'prefix' => 'tel:'],
        'facebook' => ['icon' => 'bi-facebook', 'color' => '#1877F2', 'prefix' => ''],
        'call' => ['icon' => 'bi-telephone-fill', 'color' => '#0d6efd', 'prefix' => 'tel:'],
        'telegram' => ['icon' => 'bi-telegram', 'color' => '#0088CC', 'prefix' => 'https://t.me/'],
        'whatsapp_business' => ['icon' => 'bi-whatsapp', 'color' => '#128C7E', 'prefix' => 'https://wa.me/'],
        'linkedin' => ['icon' => 'bi-linkedin', 'color' => '#0A66C2', 'prefix' => '']
    ];
    
    foreach ($contactTypesConfig as $key => $config) {
        $value = getSettings('contact_' . $key);
        $isActive = getSettings('contact_' . $key . '_active') == 1;
        
        if ($isActive && !empty($value) && count($floatingContacts) < 4) {
            $link = $config['prefix'] . $value;
            // For facebook and linkedin, use the value directly if it's a full URL
            if (in_array($key, ['facebook', 'linkedin']) && (strpos($value, 'http') === 0)) {
                $link = $value;
            }
            $floatingContacts[] = [
                'key' => $key,
                'icon' => $config['icon'],
                'color' => $config['color'],
                'link' => $link
            ];
        }
    }
    
    // Only show floating icon if there are active contacts
    if (!empty($floatingContacts)):
    ?>
    <!-- Floating Contact Button -->
    <div class="floating-contact-wrapper" id="floatingContact">
        <div class="floating-contact-icons" id="floatingIcons">
            <?php foreach ($floatingContacts as $index => $contact): ?>
            <a href="<?php echo $contact['link']; ?>" 
               target="<?php echo in_array($contact['key'], ['call', 'imo']) ? '_self' : '_blank'; ?>" 
               class="floating-contact-icon" 
               style="--i: <?php echo $index; ?>; --color: <?php echo $contact['color']; ?>;"
               title="<?php echo ucfirst(str_replace('_', ' ', $contact['key'])); ?>">
                <i class="bi <?php echo $contact['icon']; ?>"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="floating-contact-main" id="floatingMainBtn" onclick="toggleFloatingContact()">
            <i class="bi bi-chat-dots-fill" id="floatingMainIcon"></i>
            <i class="bi bi-x-lg d-none" id="floatingCloseIcon"></i>
        </button>
    </div>
    
    <style>
    /* Floating Contact Button Styles */
    .floating-contact-wrapper {
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 9999;
    }
    
    .floating-contact-main {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        font-size: 1.6rem;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 10;
    }
    
    .floating-contact-main:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
    }
    
    .floating-contact-main.active {
        transform: rotate(180deg);
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
    }
    
    .floating-contact-icons {
        position: absolute;
        bottom: 70px;
        right: 10px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .floating-contact-icons.active {
        opacity: 1;
        visibility: visible;
    }
    
    .floating-contact-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--color);
        color: white !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        text-decoration: none !important;
        box-shadow: 0 3px 15px rgba(0,0,0,0.25);
        transform: scale(0) translateY(20px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        transition-delay: calc(var(--i) * 0.05s);
    }
    
    .floating-contact-icons.active .floating-contact-icon {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
    
    .floating-contact-icon:hover {
        transform: scale(1.15) !important;
        box-shadow: 0 5px 20px rgba(0,0,0,0.35);
    }
    
    /* Pulse animation for main button */
    .floating-contact-main::before {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: inherit;
        animation: pulse 2s infinite;
        z-index: -1;
    }
    
    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 0.7;
        }
        50% {
            transform: scale(1.3);
            opacity: 0;
        }
        100% {
            transform: scale(1);
            opacity: 0;
        }
    }
    
    .floating-contact-main.active::before {
        animation: none;
    }
    
    /* Mobile adjustments */
    @media (max-width: 576px) {
        .floating-contact-wrapper {
            bottom: 20px;
            right: 20px;
        }
        
        .floating-contact-main {
            width: 55px;
            height: 55px;
            font-size: 1.4rem;
        }
        
        .floating-contact-icon {
            width: 44px;
            height: 44px;
            font-size: 1.2rem;
        }
    }
    </style>
    
    <script>
    function toggleFloatingContact() {
        const mainBtn = document.getElementById('floatingMainBtn');
        const icons = document.getElementById('floatingIcons');
        const mainIcon = document.getElementById('floatingMainIcon');
        const closeIcon = document.getElementById('floatingCloseIcon');
        
        mainBtn.classList.toggle('active');
        icons.classList.toggle('active');
        
        if (mainBtn.classList.contains('active')) {
            mainIcon.classList.add('d-none');
            closeIcon.classList.remove('d-none');
        } else {
            mainIcon.classList.remove('d-none');
            closeIcon.classList.add('d-none');
        }
    }
    
    // Close floating icons when clicking outside
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('floatingContact');
        if (wrapper && !wrapper.contains(e.target)) {
            const mainBtn = document.getElementById('floatingMainBtn');
            const icons = document.getElementById('floatingIcons');
            const mainIcon = document.getElementById('floatingMainIcon');
            const closeIcon = document.getElementById('floatingCloseIcon');
            
            if (mainBtn && mainBtn.classList.contains('active')) {
                mainBtn.classList.remove('active');
                icons.classList.remove('active');
                mainIcon.classList.remove('d-none');
                closeIcon.classList.add('d-none');
            }
        }
    });
    </script>
    <?php endif; ?>
    
    <style>
        /* Footer Contact Icons */
        .contact-icon-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none !important;
            font-size: 1.2rem;
            margin: 0 5px;
        }
        
        .contact-icon-btn:hover {
            transform: translateY(-5px);
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .contact-icon-btn.call { background: linear-gradient(45deg, #0d6efd, #0a58ca); }
        .contact-icon-btn.whatsapp { background: linear-gradient(45deg, #25D366, #128C7E); }
        .contact-icon-btn.imo { background: linear-gradient(45deg, #00b0ff, #0091ea); }
        
        /* Mobile Responsiveness for Footer */
        @media (max-width: 767px) {
            .main-footer {
                text-align: center;
            }
            .main-footer h5, .main-footer h6 {
                margin-top: 15px;
            }
            .main-footer .contact-icons {
                justify-content: center;
            }
            .main-footer .col-lg-4, .main-footer .col-lg-2, .main-footer .col-lg-3 {
                margin-bottom: 2rem !important;
            }
            .footer-credit {
                margin-top: 20px;
            }
        }
    </style>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-3"><?php echo getSettings('site_title') ?: 'কিলাগ্বো'; ?></h5>
                    <p class="mb-2"><?php echo getSettings('site_tagline') ?: 'আপনার বিশ্বস্ত অনলাইন শপ'; ?></p>
                    <p class="mb-3 small text-white-50">
                        আমাদের সাইটে আপনি পাচ্ছেন Retail, Affiliate, Reselling, Wholesale এবং Seller সুবিধা। আজই যুক্ত হোন আমাদের সাথে এবং উপভোগ করুন সেরা অনলাইন শপিং অভিজ্ঞতা।
                    </p>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">দ্রুত লিংক</h6>
                    <ul class="list-unstyled">
                        <!-- Modified menu as per request: retail,affiliate,reselling,wholesale,seller -->
                        <li class="mb-2"><a href="<?php echo baseUrl('shop'); ?>" class="text-white text-decoration-none">Retail</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('affiliate'); ?>" class="text-white text-decoration-none">Affiliate</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('reselling'); ?>" class="text-white text-decoration-none">Reselling</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('wholesale'); ?>" class="text-white text-decoration-none">Wholesale</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('seller/welcome.php'); ?>" class="text-white text-decoration-none">Seller</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">অ্যাকাউন্ট</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?php echo baseUrl('login'); ?>" class="text-white text-decoration-none">লগইন</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('registration'); ?>" class="text-white text-decoration-none">রেজিস্ট্রেশন</a></li>
                        <li class="mb-2"><a href="<?php echo baseUrl('account'); ?>" class="text-white text-decoration-none">আমার অ্যাকাউন্ট</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="mb-3">যোগাযোগ</h6>
                    <p class="mb-3 small">আমাদের সাথে যোগাযোগ করুন</p>
                    <!-- Added Contact Icons -->
                    <div class="contact-icons d-flex mb-3">
                        <a href="tel:01629923301" class="contact-icon-btn call" title="Call Us">
                            <i class="bi bi-telephone-fill"></i>
                        </a>
                        <a href="https://wa.me/8801629923301" target="_blank" class="contact-icon-btn whatsapp" title="WhatsApp">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                        <!-- Imo Link (using tel as fallback or app link if known, but generic tel works for contact) -->
                        <a href="tel:01629923301" class="contact-icon-btn imo" title="IMO">
                            <i class="bi bi-chat-text-fill"></i>
                        </a>
                    </div>

                </div>
            </div>
            
            <div class="footer-credit">
                <p class="mb-0">Developed by <a href="https://www.webnifybd.com" target="_blank">Webnify Bangladesh</a></p>
            </div>
        </div>
    </footer>

<!-- Mobile Sticky Navbar -->
<div class="mobile-sticky-navbar d-lg-none">
    <a href="<?php echo baseUrl('shop'); ?>" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'shop') ? 'active' : ''; ?>">
        <i class="bi bi-shop"></i>
        <span>শপ</span>
    </a>
    <a href="<?php echo baseUrl('wishlist'); ?>" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'wishlist') ? 'active' : ''; ?>">
        <div class="icon-wrapper">
            <i class="bi bi-heart"></i>
            <?php 
            $wishlistCount = function_exists('getWishlistCount') ? getWishlistCount() : 0;
            ?>
            <span class="badge" id="mobile-wishlist-badge" style="<?php echo $wishlistCount > 0 ? '' : 'display:none;'; ?>"><?php echo $wishlistCount; ?></span>
        </div>
        <span>উইশলিস্ট</span>
    </a>
    <a href="<?php echo baseUrl('cart'); ?>" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'cart') ? 'active' : ''; ?>">
        <div class="icon-wrapper">
            <i class="bi bi-bag"></i>
            <?php 
            $cartCount = function_exists('getCartCount') ? getCartCount() : 0;
            ?>
            <span class="badge" id="mobile-cart-badge" style="<?php echo $cartCount > 0 ? '' : 'display:none;'; ?>"><?php echo $cartCount; ?></span>
        </div>
        <span>কার্ট</span>
    </a>
    <a href="<?php echo baseUrl(isUserLoggedIn() ? 'account' : 'login'); ?>" class="nav-item <?php echo (isset($currentPage) && $currentPage == 'account') ? 'active' : ''; ?>">
        <i class="bi bi-person"></i>
        <span>একাউন্ট</span>
    </a>
</div>

<style>
.mobile-sticky-navbar {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: #fff;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    z-index: 9999;
    display: flex;
    justify-content: space-around;
    padding: 10px 0;
    border-top: 1px solid #eee;
    padding-bottom: max(10px, env(safe-area-inset-bottom));
}

.mobile-sticky-navbar .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #666;
    font-size: 0.75rem;
    font-weight: 500;
    width: 25%;
    position: relative;
    transition: all 0.3s ease;
}

.mobile-sticky-navbar .nav-item i {
    font-size: 1.4rem;
    margin-bottom: 2px;
}

.mobile-sticky-navbar .nav-item.active {
    color: var(--primary-color, #000);
    font-weight: 700;
}

.mobile-sticky-navbar .nav-item.active i {
    color: var(--primary-color, #000);
}

.mobile-sticky-navbar .icon-wrapper {
    position: relative;
    display: inline-block;
}

.mobile-sticky-navbar .badge {
    position: absolute;
    top: -5px;
    right: -8px;
    background: #ff6600;
    color: white;
    font-size: 0.6rem;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 16px;
    text-align: center;
}

/* Adjust floating contact to not overlap */
@media (max-width: 991px) {
    .floating-contact-wrapper {
        bottom: 80px !important;
    }
    
    /* Add padding to body/main to prevent content being hidden behind navbar */
    body {
        padding-bottom: 70px;
    }
}
</style>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <script>
        // Mobile Menu Toggle
        function toggleMobileMenu() {
            document.getElementById('mobileNav').classList.toggle('active');
        }
        
        // Close mobile menu on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('mobileNav').classList.remove('active');
            }
        });
        
        // AJAX functions for cart and wishlist
        function addToCart(productId, quantity = 1, variation = null, section = 'retail') {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            formData.append('section', section);
            if (variation) {
                formData.append('variation', JSON.stringify(variation));
            }
            
            fetch('<?php echo baseUrl('ajax/cart.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartCount(data.cartCount);
                    showToast('সফল', data.message);
                } else {
                    showToast('ত্রুটি', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('ত্রুটি', 'কিছু সমস্যা হয়েছে', 'error');
            });
        }
        
        function addToWishlist(productId, section = 'retail') {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('section', section);
            
            fetch('<?php echo baseUrl('ajax/wishlist.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateWishlistCount(data.wishlistCount);
                    showToast('সফল', data.message);
                } else {
                    showToast('ত্রুটি', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        function updateCartCount(count) {
            // Update Desktop Icon
            const icon = document.getElementById('cart-icon');
            if (icon) {
                let badge = icon.querySelector('.badge');
                
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'badge';
                        icon.appendChild(badge);
                    }
                    badge.textContent = count;
                    badge.style.display = 'block';
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }

            // Update Mobile Navbar Icon
            const mobileBadge = document.getElementById('mobile-cart-badge');
            if (mobileBadge) {
                if (count > 0) {
                    mobileBadge.textContent = count;
                    mobileBadge.style.display = 'block';
                } else {
                    mobileBadge.style.display = 'none';
                }
            }
        }
        
        function updateWishlistCount(count) {
            // Update Desktop Icon
            const icon = document.getElementById('wishlist-icon');
            if (icon) {
                let badge = icon.querySelector('.badge');
                
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'badge';
                        icon.appendChild(badge);
                    }
                    badge.textContent = count;
                    badge.style.display = 'block';
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }

            // Update Mobile Navbar Icon
            const mobileBadge = document.getElementById('mobile-wishlist-badge');
            if (mobileBadge) {
                if (count > 0) {
                    mobileBadge.textContent = count;
                    mobileBadge.style.display = 'block';
                } else {
                    mobileBadge.style.display = 'none';
                }
            }
        }
        
        function showToast(title, message, type = 'success') {
            // Create toast container if doesn't exist
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
                document.body.appendChild(toastContainer);
            }
            
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'dark' : 'danger'} alert-dismissible fade show`;
            toast.style.cssText = 'min-width: 250px; margin-bottom: 10px;';
            toast.innerHTML = `
                <strong>${title}</strong><br>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        

    </script>
</body>
</html>
