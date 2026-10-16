<?php
/**
 * Floating Contact Icons Component
 * Include this file in footer to show floating contact button
 */

// Get active contacts
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
    animation: floatingPulse 2s infinite;
    z-index: -1;
}

@keyframes floatingPulse {
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
