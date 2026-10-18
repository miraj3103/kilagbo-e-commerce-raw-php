<?php
/**
 * Kilagbo - Admin Contacts Management
 * Floating Contact Icons Configuration
 * Developed by Webnify Bangladesh
 */

$pageTitle = 'যোগাযোগ আইকন';
$currentPage = 'contacts';

require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermissionForCurrentPage();

// Contact types configuration
$contactTypes = [
    'whatsapp' => [
        'label' => 'WhatsApp',
        'icon' => 'bi-whatsapp',
        'color' => '#25D366',
        'placeholder' => '8801XXXXXXXXX'
    ],
    'messenger' => [
        'label' => 'Messenger',
        'icon' => 'bi-messenger',
        'color' => '#0084FF',
        'placeholder' => 'facebook_username'
    ],
    'imo' => [
        'label' => 'IMO',
        'icon' => 'bi-chat-dots-fill',
        'color' => '#00B0FF',
        'placeholder' => '8801XXXXXXXXX'
    ],
    'facebook' => [
        'label' => 'Facebook',
        'icon' => 'bi-facebook',
        'color' => '#1877F2',
        'placeholder' => 'facebook_page_url'
    ],
    'call' => [
        'label' => 'Call',
        'icon' => 'bi-telephone-fill',
        'color' => '#0d6efd',
        'placeholder' => '01XXXXXXXXX'
    ],
    'telegram' => [
        'label' => 'Telegram',
        'icon' => 'bi-telegram',
        'color' => '#0088CC',
        'placeholder' => 'telegram_username'
    ],
    'whatsapp_business' => [
        'label' => 'WhatsApp Business',
        'icon' => 'bi-whatsapp',
        'color' => '#128C7E',
        'placeholder' => '8801XXXXXXXXX'
    ],
    'linkedin' => [
        'label' => 'LinkedIn',
        'icon' => 'bi-linkedin',
        'color' => '#0A66C2',
        'placeholder' => 'linkedin_profile_url'
    ]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_contacts') {
        $activeCount = 0;
        
        foreach ($contactTypes as $key => $type) {
            $value = sanitize($_POST['contact_' . $key] ?? '');
            $isActive = isset($_POST['active_' . $key]) ? 1 : 0;
            
            if ($isActive) {
                $activeCount++;
            }
            
            // Check if more than 4 are active
            if ($activeCount > 4) {
                setFlash('error', 'সর্বোচ্চ ৪টি আইকন একটিভ করা যাবে');
                redirect(adminUrl('contacts'));
            }
            
            updateSetting('contact_' . $key, $value);
            updateSetting('contact_' . $key . '_active', $isActive);
        }
        
        setFlash('success', 'যোগাযোগ তথ্য সেভ করা হয়েছে');
        redirect(adminUrl('contacts'));
    }
}

// Get current values
$contactValues = [];
$hasAnyContact = false;

foreach ($contactTypes as $key => $type) {
    $value = getSettings('contact_' . $key);
    $activeVal = getSettings('contact_' . $key . '_active');
    
    if ($value !== null || $activeVal !== null) {
        $hasAnyContact = true;
    }
    
    $contactValues[$key] = [
        'value' => $value ?? '',
        'active' => $activeVal == 1
    ];
}

// If no contacts are set, set default demo values
if (!$hasAnyContact) {
    // Set default demo contacts with real working values
    $defaultContacts = [
        'whatsapp' => ['value' => '8801629923301', 'active' => 1],
        'messenger' => ['value' => 'kilagbo', 'active' => 1],
        'imo' => ['value' => '8801629923301', 'active' => 1],
        'call' => ['value' => '01629923301', 'active' => 0],
        'facebook' => ['value' => '', 'active' => 0],
        'telegram' => ['value' => '', 'active' => 0],
        'whatsapp_business' => ['value' => '', 'active' => 0],
        'linkedin' => ['value' => '', 'active' => 0]
    ];
    
    foreach ($defaultContacts as $key => $data) {
        updateSetting('contact_' . $key, $data['value']);
        updateSetting('contact_' . $key . '_active', $data['active']);
        $contactValues[$key] = [
            'value' => $data['value'],
            'active' => $data['active'] == 1
        ];
    }
}

// Count active contacts
$activeCount = 0;
foreach ($contactValues as $contact) {
    if ($contact['active']) {
        $activeCount++;
    }
}

require_once 'includes/header.php';
?>

<?php showFlash(); ?>

<div class="admin-card">
    <h5 class="card-title">
        <i class="bi bi-chat-dots me-2"></i>ফ্লোটিং যোগাযোগ আইকন সেটআপ
    </h5>
    
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>নির্দেশনা:</strong> এখানে আপনি ফ্লোটিং মেসেজ আইকনের জন্য যোগাযোগ তথ্য সেট করতে পারবেন। 
        সর্বোচ্চ <strong>৪টি</strong> আইকন একটিভ করা যাবে যা ওয়েবসাইটে ফ্লোটিং বাটনে দেখাবে।
        <br><small class="text-muted">বর্তমানে একটিভ: <strong><?php echo $activeCount; ?>/4</strong></small>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="save_contacts">
        
        <div class="row">
            <?php foreach ($contactTypes as $key => $type): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100" style="border-left: 4px solid <?php echo $type['color']; ?>;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="bi <?php echo $type['icon']; ?> me-2" style="color: <?php echo $type['color']; ?>;"></i>
                                <?php echo $type['label']; ?>
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="active_<?php echo $key; ?>" 
                                       id="active_<?php echo $key; ?>"
                                       <?php echo $contactValues[$key]['active'] ? 'checked' : ''; ?>
                                       onchange="checkActiveCount(this)">
                                <label class="form-check-label" for="active_<?php echo $key; ?>">
                                    <?php echo $contactValues[$key]['active'] ? 'একটিভ' : 'বন্ধ'; ?>
                                </label>
                            </div>
                        </div>
                        <input type="text" 
                               name="contact_<?php echo $key; ?>" 
                               class="form-control" 
                               placeholder="<?php echo $type['placeholder']; ?>"
                               value="<?php echo sanitize($contactValues[$key]['value']); ?>">
                        <small class="text-muted">
                            <?php 
                            switch($key) {
                                case 'whatsapp':
                                case 'whatsapp_business':
                                    echo 'দেশের কোড সহ নম্বর দিন (যেমন: 8801XXXXXXXXX)';
                                    break;
                                case 'messenger':
                                    echo 'Facebook পেজ/প্রোফাইল username দিন';
                                    break;
                                case 'imo':
                                    echo 'IMO নম্বর দিন (দেশের কোড সহ)';
                                    break;
                                case 'facebook':
                                    echo 'Facebook পেজের সম্পূর্ণ URL দিন';
                                    break;
                                case 'call':
                                    echo 'ফোন নম্বর দিন';
                                    break;
                                case 'telegram':
                                    echo 'Telegram username দিন (@ ছাড়া)';
                                    break;
                                case 'linkedin':
                                    echo 'LinkedIn প্রোফাইলের সম্পূর্ণ URL দিন';
                                    break;
                            }
                            ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-4">
            <button type="submit" class="btn btn-admin">
                <i class="bi bi-save me-2"></i>সেভ করুন
            </button>
        </div>
    </form>
</div>

<!-- Preview Section -->
<div class="admin-card">
    <h5 class="card-title">
        <i class="bi bi-eye me-2"></i>প্রিভিউ
    </h5>
    <p class="text-muted mb-4">এইভাবে ফ্লোটিং আইকন ওয়েবসাইটে দেখাবে:</p>
    
    <div class="position-relative" style="height: 200px; background: #f8f9fa; border-radius: 12px; overflow: hidden;">
        <!-- Preview floating button -->
        <div class="floating-preview" id="floatingPreview">
            <div class="floating-main-btn" onclick="togglePreviewIcons()">
                <i class="bi bi-chat-dots-fill"></i>
            </div>
            <div class="floating-icons" id="previewIcons">
                <?php 
                $index = 0;
                foreach ($contactValues as $key => $contact): 
                    if ($contact['active'] && $index < 4):
                        $type = $contactTypes[$key];
                ?>
                <div class="floating-icon-item" style="--i: <?php echo $index; ?>; --color: <?php echo $type['color']; ?>;">
                    <i class="bi <?php echo $type['icon']; ?>"></i>
                </div>
                <?php 
                        $index++;
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
    </div>
</div>

<style>
.floating-preview {
    position: absolute;
    bottom: 20px;
    right: 20px;
}

.floating-main-btn {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
    z-index: 10;
    position: relative;
}

.floating-main-btn:hover {
    transform: scale(1.1);
}

.floating-icons {
    position: absolute;
    bottom: 70px;
    right: 8px;
    display: none;
    flex-direction: column;
    gap: 10px;
}

.floating-icons.active {
    display: flex;
}

.floating-icon-item {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    animation: popIn 0.3s ease forwards;
    animation-delay: calc(var(--i) * 0.05s);
    opacity: 0;
    transform: scale(0);
}

.floating-icons.active .floating-icon-item {
    opacity: 1;
    transform: scale(1);
}

@keyframes popIn {
    from {
        opacity: 0;
        transform: scale(0) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>

<script>
function togglePreviewIcons() {
    document.getElementById('previewIcons').classList.toggle('active');
}

function checkActiveCount(checkbox) {
    const allCheckboxes = document.querySelectorAll('input[type="checkbox"][name^="active_"]');
    let activeCount = 0;
    
    allCheckboxes.forEach(cb => {
        if (cb.checked) activeCount++;
    });
    
    if (activeCount > 4) {
        checkbox.checked = false;
        alert('সর্বোচ্চ ৪টি আইকন একটিভ করা যাবে!');
        return;
    }
    
    // Update label
    const label = checkbox.nextElementSibling;
    label.textContent = checkbox.checked ? 'একটিভ' : 'বন্ধ';
}
</script>

<?php require_once 'includes/footer.php'; ?>
