<?php
/**
 * Kilagbo - Configuration File
 * Developed by Webnify Bangladesh (https://www.webnifybd.com)
 */

// Start output buffering to prevent header issues
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(0);
ini_set('display_errors', 0);

// Database and Site configuration
// Local Laragon hosts (e.g. kilagbo.test) must use the local database and must NOT redirect to production.
$host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
$host = preg_replace('/:\d+$/', '', $host);

$is_localhost = (
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    $host === 'kilagbo.test' ||
    $host === 'www.kilagbo.test' ||
    substr($host, -5) === '.test'
);

if ($is_localhost) {
    // Local Laragon configuration
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'kilagbo');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    // Auto-detect base URL.
    // With Laragon vhost this becomes http://kilagbo.test
    // With localhost subfolder usage this becomes http://localhost/kilagbo
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
    $relativePath = '';

    if ($docRoot !== '' && stripos($projectRoot, $docRoot) === 0) {
        $relativePath = substr($projectRoot, strlen($docRoot));
    }

    $relativePath = '/' . trim($relativePath, '/');
    if ($relativePath === '/') {
        $relativePath = '';
    }

    define('SITE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? $host) . $relativePath);
} else {
    // LIVE CONFIG REQUIRED BEFORE DEPLOYMENT.
    // Replace these placeholders with the real production database values.
    define('DB_HOST', 'localhost');
    define('DB_NAME', '__LIVE_DB_NAME__');
    define('DB_USER', '__LIVE_DB_USER__');
    define('DB_PASS', '__LIVE_DB_PASS__');
    define('SITE_URL', 'https://www.kilagbo.com');
}

define('ADMIN_URL', SITE_URL . '/superadmin');
define('UPLOADS_URL', SITE_URL . '/uploads');
define('UPLOADS_PATH', __DIR__ . '/../uploads');

// Steadfast API Base URL
define('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1');

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions
function baseUrl($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

function adminUrl($path = '') {
    return ADMIN_URL . '/' . ltrim($path, '/');
}

function uploadUrl($path = '') {
    return UPLOADS_URL . '/' . ltrim($path, '/');
}

function imageUrl($path = '') {
    if (empty($path)) {
        return baseUrl('assets/images/placeholder.png');
    }
    // Check if it's already a full URL (external image)
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    // Otherwise, it's a local upload
    return UPLOADS_URL . '/' . ltrim($path, '/');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($data) {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function generateSlug($text) {
    $text = trim($text);
    if (empty($text)) {
        return 'item-' . time();
    }
    
    // Check if text contains Bangla characters (Unicode range 0980-09FF)
    if (preg_match('/[\x{0980}-\x{09FF}]/u', $text)) {
        // For Bangla: just replace spaces with hyphens, keep everything else
        $slug = preg_replace('/\s+/', '-', $text);
        $slug = trim($slug, '-');
        return $slug ?: 'item-' . time();
    }
    
    // For English/ASCII: standard slug generation
    $text = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    $text = trim($text, '-');
    return strtolower($text) ?: 'item-' . time();
}

function generateOrderNumber() {
    return 'KLG' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function formatPrice($price) {
    if ($price == floor($price)) {
        return '৳' . number_format($price, 0);
    }
    return '৳' . number_format($price, 2);
}

function getSettings($key = null) {
    global $pdo;
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    if ($key) {
        return $settings[$key] ?? null;
    }
    return $settings;
}

function updateSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    return $stmt->execute([$key, $value]);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        redirect(baseUrl('superadmin/login'));
    }
}

function requireUser() {
    if (!isUserLoggedIn()) {
        redirect(baseUrl('login'));
    }
}

function getSessionId() {
    if (!isset($_SESSION['cart_session'])) {
        $_SESSION['cart_session'] = session_id();
    }
    return $_SESSION['cart_session'];
}

function getCartCount() {
    global $pdo;
    $sessionId = getSessionId();
    $userId = $_SESSION['user_id'] ?? null;
    
    if ($userId) {
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
    return $stmt->fetchColumn() ?: 0;
}

function getWishlistCount() {
    global $pdo;
    $sessionId = getSessionId();
    $userId = $_SESSION['user_id'] ?? null;
    
    if ($userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
    return $stmt->fetchColumn() ?: 0;
}

function addNotification($type, $title, $message = null, $link = null, $userType = 'admin', $userId = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_type, user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$userType, $userId, $type, $title, $message, $link]);
}

function getUnreadNotificationCount() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    return $stmt->fetchColumn() ?: 0;
}

// Get admin reselling notification counts
function getAdminResellingNotifications() {
    global $pdo;
    
    // Pending reseller orders (new orders from resellers)
    $stmt = $pdo->query("SELECT COUNT(*) FROM resell_orders WHERE status = 'pending'");
    $pendingOrders = $stmt->fetchColumn() ?: 0;
    
    // Pending reseller approvals
    $stmt = $pdo->query("SELECT COUNT(*) FROM resellers WHERE status = 'pending'");
    $pendingResellers = $stmt->fetchColumn() ?: 0;
    
    // Pending withdrawal requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM reseller_withdrawals WHERE status = 'pending'");
    $pendingWithdrawals = $stmt->fetchColumn() ?: 0;
    
    // Pending balance requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM resell_balance_requests WHERE status = 'pending'");
    $pendingBalanceRequests = $stmt->fetchColumn() ?: 0;
    
    return [
        'orders' => $pendingOrders,
        'resellers' => $pendingResellers,
        'payments' => $pendingWithdrawals,
        'balance_requests' => $pendingBalanceRequests,
        'total' => $pendingOrders + $pendingResellers + $pendingWithdrawals + $pendingBalanceRequests
    ];
}

// Get reseller notification counts
function getResellerNotifications($resellerId) {
    global $pdo;
    
    // Get reseller's last seen timestamps
    $stmt = $pdo->prepare("SELECT last_products_seen, last_orders_seen, last_withdrawals_seen FROM resellers WHERE id = ?");
    $stmt->execute([$resellerId]);
    $reseller = $stmt->fetch();
    
    // New products added since last seen (or in last 7 days if never seen)
    $lastProductsSeen = $reseller['last_products_seen'] ?? date('Y-m-d H:i:s', strtotime('-7 days'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM resell_products 
        WHERE status = 1 AND created_at > ?
    ");
    $stmt->execute([$lastProductsSeen]);
    $newProducts = $stmt->fetchColumn() ?: 0;
    
    // Order status changes since last seen (or in last 24 hours if never seen)
    $lastOrdersSeen = $reseller['last_orders_seen'] ?? date('Y-m-d H:i:s', strtotime('-24 hours'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM resell_orders 
        WHERE reseller_id = ? 
        AND updated_at > ?
        AND updated_at != created_at
    ");
    $stmt->execute([$resellerId, $lastOrdersSeen]);
    $orderUpdates = $stmt->fetchColumn() ?: 0;
    
    // Completed withdrawals since last seen (or in last 7 days if never seen)
    $lastWithdrawalsSeen = $reseller['last_withdrawals_seen'] ?? date('Y-m-d H:i:s', strtotime('-7 days'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reseller_withdrawals 
        WHERE reseller_id = ? 
        AND status = 'completed'
        AND updated_at > ?
    ");
    $stmt->execute([$resellerId, $lastWithdrawalsSeen]);
    $completedWithdrawals = $stmt->fetchColumn() ?: 0;
    
    return [
        'products' => $newProducts,
        'orders' => $orderUpdates,
        'withdrawals' => $completedWithdrawals
    ];
}

// Mark reseller notifications as seen
function markResellerNotificationsSeen($resellerId, $type) {
    global $pdo;
    
    $column = '';
    switch ($type) {
        case 'products':
            $column = 'last_products_seen';
            break;
        case 'orders':
            $column = 'last_orders_seen';
            break;
        case 'withdrawals':
            $column = 'last_withdrawals_seen';
            break;
        default:
            return false;
    }
    
    $stmt = $pdo->prepare("UPDATE resellers SET $column = NOW() WHERE id = ?");
    return $stmt->execute([$resellerId]);
}

// Get resell delivery charges (default charges)
function getResellDeliveryCharges() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM resell_delivery_charges WHERE status = 1 ORDER BY charge ASC");
    return $stmt->fetchAll();
}

// Get delivery charge for a specific resell product
// Returns custom charge if set, otherwise returns null (use default)
function getResellProductDeliveryCharge($productId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT custom_charge FROM resell_product_delivery WHERE product_id = ?");
    $stmt->execute([$productId]);
    $result = $stmt->fetch();
    return $result ? $result['custom_charge'] : null;
}

// Check if product has custom delivery charge
function hasCustomDeliveryCharge($productId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM resell_product_delivery WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetch() ? true : false;
}

function uploadImage($file, $folder = 'general') {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $uploadDir = UPLOADS_PATH . '/' . $folder;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $folder . '/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

function deleteImage($filepath) {
    $fullPath = UPLOADS_PATH . '/' . $filepath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

// CSRF Protection
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

// Flash messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo $flash['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// Reseller Balance Functions
function addResellerBalance($resellerId, $amount, $description, $referenceType, $referenceId = null) {
    global $pdo;
    
    // Update balance
    $pdo->prepare("UPDATE resellers SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?")
        ->execute([$amount, $amount, $resellerId]);
    
    // Get new balance
    $stmt = $pdo->prepare("SELECT balance FROM resellers WHERE id = ?");
    $stmt->execute([$resellerId]);
    $newBalance = $stmt->fetchColumn();
    
    // Log history
    $pdo->prepare("INSERT INTO reseller_balance_history (reseller_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'credit', ?, ?, ?, ?, ?)")
        ->execute([$resellerId, $amount, $description, $referenceType, $referenceId, $newBalance]);
    
    return $newBalance;
}

function deductResellerBalance($resellerId, $amount, $description, $referenceType, $referenceId = null) {
    global $pdo;
    
    // Update balance
    $pdo->prepare("UPDATE resellers SET balance = balance - ?, total_withdrawn = total_withdrawn + ? WHERE id = ?")
        ->execute([$amount, $amount, $resellerId]);
    
    // Get new balance
    $stmt = $pdo->prepare("SELECT balance FROM resellers WHERE id = ?");
    $stmt->execute([$resellerId]);
    $newBalance = $stmt->fetchColumn();
    
    // Log history
    $pdo->prepare("INSERT INTO reseller_balance_history (reseller_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'debit', ?, ?, ?, ?, ?)")
        ->execute([$resellerId, $amount, $description, $referenceType, $referenceId, $newBalance]);
    
    return $newBalance;
}
