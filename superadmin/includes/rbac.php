<?php
/**
 * Kilagbo Superadmin RBAC helper.
 * NOTE 11 - Role & Permission Management.
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/config.php';
}

function adminRbacTableExists(string $table): bool
{
    global $pdo;
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function adminRbacReady(): bool
{
    return adminRbacTableExists('admin_roles')
        && adminRbacTableExists('admin_permissions')
        && adminRbacTableExists('admin_role_permissions');
}

function adminCurrentRole(): ?array
{
    global $pdo;
    static $loaded = false;
    static $role = null;

    if ($loaded) {
        return $role;
    }
    $loaded = true;

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0 || !adminRbacReady()) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT ar.* FROM admins a LEFT JOIN admin_roles ar ON ar.id = a.role_id WHERE a.id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        $role = $found ?: null;
    } catch (Throwable $e) {
        $role = null;
    }
    return $role;
}

function adminIsSuperAdmin(): bool
{
    // Backward-compatible during the very short window before migration is run.
    if (!adminRbacReady()) {
        return true;
    }
    $role = adminCurrentRole();
    return $role && (int)($role['is_superadmin'] ?? 0) === 1 && (int)($role['status'] ?? 0) === 1;
}

function adminHasPermission(string $permissionKey): bool
{
    global $pdo;
    if (!isAdminLoggedIn()) {
        return false;
    }
    if (!adminRbacReady()) {
        return true;
    }
    if (adminIsSuperAdmin()) {
        return true;
    }

    $role = adminCurrentRole();
    if (!$role || (int)($role['status'] ?? 0) !== 1) {
        return false;
    }

    static $cache = [];
    $cacheKey = (int)$role['id'] . ':' . $permissionKey;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM admin_role_permissions arp\n        JOIN admin_permissions ap ON ap.id = arp.permission_id\n        WHERE arp.role_id = ? AND ap.permission_key = ?\n    ");
    $stmt->execute([(int)$role['id'], $permissionKey]);
    return $cache[$cacheKey] = ((int)$stmt->fetchColumn() > 0);
}

function adminCan(string $permissionKey): bool
{
    return adminHasPermission($permissionKey);
}

function adminFirstAllowedUrl(): string
{
    if (adminIsSuperAdmin()) {
        return adminUrl('dashboard');
    }
    $choices = [
        'dashboard.view' => 'dashboard',
        'retail.manage' => 'products',
        'wholesale.manage' => 'wholesale-products',
        'resell.manage' => 'resell-products',
        'affiliate.manage' => 'affiliate-products',
        'seller.manage' => 'seller-products',
        'site.manage' => 'contacts',
        'payments.manage' => 'payment-accounts',
        'admin_users.manage' => 'admin-users.php',
        'roles.manage' => 'roles.php',
        'courier.manage' => 'courier',
        'settings.manage' => 'settings',
    ];
    foreach ($choices as $permission => $route) {
        if (adminHasPermission($permission)) {
            return adminUrl($route);
        }
    }
    return adminUrl('dashboard');
}

function adminPermissionDenied(string $message = 'এই পেইজে প্রবেশের অনুমতি আপনার নেই।'): never
{
    http_response_code(403);
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $dashboard = function_exists('adminFirstAllowedUrl') ? adminFirstAllowedUrl() : (function_exists('adminUrl') ? adminUrl('dashboard') : 'dashboard.php');
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>অনুমতি নেই</title><style>body{font-family:Arial,sans-serif;background:#f8f9fa;margin:0;display:grid;place-items:center;min-height:100vh}.box{max-width:560px;background:#fff;padding:32px;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.08);text-align:center}a{display:inline-block;margin-top:15px;padding:10px 18px;background:#111;color:#fff;border-radius:8px;text-decoration:none}</style></head><body>';
    echo '<div class="box"><h2>403 — অনুমতি নেই</h2><p>' . $safe . '</p><a href="' . htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8') . '">ড্যাশবোর্ডে ফিরুন</a></div></body></html>';
    exit;
}

function requireAdminPermission(string $permissionKey): void
{
    requireAdmin();
    if (!adminHasPermission($permissionKey)) {
        adminPermissionDenied();
    }
}

function requireSuperAdminAccess(): void
{
    requireAdmin();
    if (!adminIsSuperAdmin()) {
        adminPermissionDenied('এই অংশটি শুধুমাত্র Super Admin ব্যবহার করতে পারবেন।');
    }
}

function adminRoutePermissionMap(): array
{
    return [
        'dashboard.php' => 'dashboard.view',

        'products.php' => 'retail.manage',
        'product-add.php' => 'retail.manage',
        'product-edit.php' => 'retail.manage',
        'orders.php' => 'retail.manage',
        'order-view.php' => 'retail.manage',
        'categories.php' => 'retail.manage',
        'carousel.php' => 'retail.manage',
        'retail-delivery.php' => 'retail.manage',
        'services.php' => 'retail.manage',
        'service-add.php' => 'retail.manage',
        'service-edit.php' => 'retail.manage',
        'service-orders.php' => 'retail.manage',
        'digital-products.php' => 'retail.manage',
        'digital-product-add.php' => 'retail.manage',
        'digital-product-edit.php' => 'retail.manage',
        'digital-orders.php' => 'retail.manage',

        'wholesale-products.php' => 'wholesale.manage',
        'wholesale-requests.php' => 'wholesale.manage',
        'wholesale-add.php' => 'wholesale.manage',
        'wholesale-add-new.php' => 'wholesale.manage',
        'wholesale-edit.php' => 'wholesale.manage',
        'wholesale-orders.php' => 'wholesale.manage',
        'wholesale-order-view.php' => 'wholesale.manage',
        'wholesale-delivery.php' => 'wholesale.manage',
        'wholesale-categories.php' => 'wholesale.manage',

        'resell-products.php' => 'resell.manage',
        'resell-add.php' => 'resell.manage',
        'resell-add-new.php' => 'resell.manage',
        'resell-edit.php' => 'resell.manage',
        'resell-orders.php' => 'resell.manage',
        'resell-order-view.php' => 'resell.manage',
        'resell-delivery.php' => 'resell.manage',
        'resell-payments.php' => 'resell.manage',
        'resell-balance-requests.php' => 'resell.manage',
        'resellers.php' => 'resell.manage',
        'reseller-view.php' => 'resell.manage',

        'affiliate-products.php' => 'affiliate.manage',
        'affiliate-add.php' => 'affiliate.manage',
        'affiliate-add-new.php' => 'affiliate.manage',
        'affiliate-add-wholesale.php' => 'affiliate.manage',
        'affiliate-edit.php' => 'affiliate.manage',
        'affiliate-orders.php' => 'affiliate.manage',
        'affiliate-order-view.php' => 'affiliate.manage',
        'affiliate-delivery.php' => 'affiliate.manage',
        'affiliate-payments.php' => 'affiliate.manage',
        'affiliators.php' => 'affiliate.manage',
        'affiliator-view.php' => 'affiliate.manage',

        'seller-products.php' => 'seller.manage',
        'seller-orders.php' => 'seller.manage',
        'seller-activity.php' => 'seller.manage',
        'sellers.php' => 'seller.manage',
        'seller-view.php' => 'seller.manage',

        'payment-accounts.php' => 'payments.manage',
        'pixel-gtm.php' => 'site.manage',
        'social-login.php' => 'site.manage',
        'contacts.php' => 'site.manage',

        'admin-users.php' => 'admin_users.manage',
        'roles.php' => 'roles.manage',
        'role-edit.php' => 'roles.manage',
        'courier.php' => 'courier.manage',
        'settings.php' => 'settings.manage',
    ];
}

function requireAdminPermissionForCurrentPage(): void
{
    requireAdmin();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');

    if (in_array($script, ['login.php', 'logout.php', 'index.php'], true)) {
        return;
    }

    $map = adminRoutePermissionMap();
    if (isset($map[$script])) {
        requireAdminPermission($map[$script]);
        return;
    }

    // Fail closed for future/unmapped admin routes, while Super Admin retains access.
    if (!adminIsSuperAdmin()) {
        adminPermissionDenied('এই Admin route-এর জন্য কোনো permission mapping নির্ধারিত নেই।');
    }
}
