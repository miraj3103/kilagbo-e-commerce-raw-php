<?php
/** NOTE 11 - Superadmin Role & Permission Management migration. */
require_once __DIR__ . '/../config/config.php';

function note11TableExists(PDO $pdo, string $table): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $s->execute([$table]);
    return (int)$s->fetchColumn() > 0;
}
function note11ColumnExists(PDO $pdo, string $table, string $column): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $column]);
    return (int)$s->fetchColumn() > 0;
}
function note11IndexExists(PDO $pdo, string $table, string $index): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
    $s->execute([$table, $index]);
    return (int)$s->fetchColumn() > 0;
}

try {
    // DDL is intentionally executed outside a transaction because MySQL implicitly commits ALTER/CREATE TABLE.
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_roles (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL,
        description VARCHAR(255) NULL,
        is_superadmin TINYINT(1) NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_admin_roles_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        permission_key VARCHAR(120) NOT NULL,
        name VARCHAR(150) NOT NULL,
        group_name VARCHAR(100) NOT NULL,
        is_superadmin_only TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_admin_permissions_key (permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_role_permissions (
        role_id INT UNSIGNED NOT NULL,
        permission_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (role_id, permission_id),
        CONSTRAINT fk_arp_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
        CONSTRAINT fk_arp_permission FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!note11ColumnExists($pdo, 'admins', 'role_id')) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role_id INT UNSIGNED NULL AFTER id");
        echo "Added admins.role_id\n";
    } else {
        echo "Exists admins.role_id\n";
    }
    if (!note11IndexExists($pdo, 'admins', 'idx_admins_role_id')) {
        $pdo->exec("ALTER TABLE admins ADD INDEX idx_admins_role_id (role_id)");
        echo "Added admins role index\n";
    }

    $pdo->beginTransaction();

    $roles = [
        ['Super Admin', 'super-admin', 'পূর্ণ সিস্টেম অ্যাক্সেস; Courier ও Settings সহ।', 1],
        ['Administrator', 'administrator', 'সাধারণ Admin role; Super Admin-only অংশ ছাড়া।', 0],
    ];
    $roleStmt = $pdo->prepare("INSERT INTO admin_roles (name, slug, description, is_superadmin, status) VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), is_superadmin=VALUES(is_superadmin), status=1");
    foreach ($roles as $r) $roleStmt->execute($r);

    $permissions = [
        ['dashboard.view', 'ড্যাশবোর্ড', 'সাধারণ', 0],
        ['retail.manage', 'খুচরা বিক্রয় পরিচালনা', 'খুচরা', 0],
        ['wholesale.manage', 'পাইকারি পরিচালনা', 'পাইকারি', 0],
        ['resell.manage', 'রিসেলিং পরিচালনা', 'রিসেলিং', 0],
        ['affiliate.manage', 'অ্যাফিলিয়েট পরিচালনা', 'অ্যাফিলিয়েট', 0],
        ['seller.manage', 'সেলার পরিচালনা', 'সেলার', 0],
        ['site.manage', 'পিক্সেল/সোশ্যাল/যোগাযোগ পরিচালনা', 'সাইট', 0],
        ['payments.manage', 'পেমেন্ট একাউন্ট পরিচালনা', 'পেমেন্ট', 0],
        ['admin_users.manage', 'Admin User পরিচালনা', 'Admin Access', 0],
        ['roles.manage', 'Role ও Permission পরিচালনা', 'Admin Access', 0],
        ['courier.manage', 'কুরিয়ার সেটআপ পরিচালনা', 'কুরিয়ার', 0],
        ['settings.manage', 'সেটিংস পরিচালনা', 'সেটিংস', 0],
    ];
    $permStmt = $pdo->prepare("INSERT INTO admin_permissions (permission_key, name, group_name, is_superadmin_only) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE name=VALUES(name), group_name=VALUES(group_name), is_superadmin_only=VALUES(is_superadmin_only)");
    foreach ($permissions as $p) $permStmt->execute($p);

    $superId = (int)$pdo->query("SELECT id FROM admin_roles WHERE slug='super-admin' LIMIT 1")->fetchColumn();
    $adminRoleId = (int)$pdo->query("SELECT id FROM admin_roles WHERE slug='administrator' LIMIT 1")->fetchColumn();

    // Existing admins retain full access after migration so nobody is unexpectedly locked out.
    $stmt = $pdo->prepare("UPDATE admins SET role_id=? WHERE role_id IS NULL");
    $stmt->execute([$superId]);

    // Seed all permissions to Super Admin role. Administrator keeps its original broad access, but Courier/Settings remain opt-in.
    $pdo->prepare("INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) SELECT ?, id FROM admin_permissions")
        ->execute([$superId]);
    $pdo->prepare("INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) SELECT ?, id FROM admin_permissions WHERE permission_key NOT IN ('courier.manage','settings.manage')")
        ->execute([$adminRoleId]);

    $pdo->commit();
    echo "NOTE 11 migration complete: Admin RBAC schema and default roles are ready.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "NOTE 11 migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
