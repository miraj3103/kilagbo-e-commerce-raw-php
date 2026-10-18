<?php
$pageTitle = 'Role Management';
$currentPage = 'roles';
require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermission('roles.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '') {
            setFlash('error', 'Role-এর নাম দিন');
        } else {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            if ($slug === '') $slug = 'role-' . time();
            $base = $slug; $n = 2;
            $check = $pdo->prepare("SELECT COUNT(*) FROM admin_roles WHERE slug=?");
            $check->execute([$slug]);
            while ($check->fetchColumn()) {
                $slug = $base . '-' . $n++;
                $check->execute([$slug]);
            }
            $stmt = $pdo->prepare("INSERT INTO admin_roles (name, slug, description, is_superadmin, status) VALUES (?, ?, ?, 0, 1)");
            $stmt->execute([$name, $slug, $description]);
            setFlash('success', 'নতুন Role তৈরি হয়েছে');
            redirect(adminUrl('role-edit.php?id=' . $pdo->lastInsertId()));
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['role_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM admin_roles WHERE id=?"); $stmt->execute([$id]); $role = $stmt->fetch();
        if (!$role) {
            setFlash('error', 'Role পাওয়া যায়নি');
        } elseif ((int)$role['is_superadmin'] === 1) {
            setFlash('error', 'Super Admin role ডিলিট করা যাবে না');
        } else {
            $c = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE role_id=?"); $c->execute([$id]);
            if ((int)$c->fetchColumn() > 0) {
                setFlash('error', 'এই Role-এ Admin User আছে; আগে তাদের অন্য Role দিন');
            } else {
                $pdo->prepare("DELETE FROM admin_roles WHERE id=?")->execute([$id]);
                setFlash('success', 'Role ডিলিট হয়েছে');
            }
        }
        redirect(adminUrl('roles.php'));
    }
}

$roles = $pdo->query("SELECT ar.*, COUNT(a.id) admin_count FROM admin_roles ar LEFT JOIN admins a ON a.role_id=ar.id GROUP BY ar.id ORDER BY ar.is_superadmin DESC, ar.name")->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
    <?php showFlash(); ?>
    <div class="d-flex justify-content-between align-items-center mb-4"><h4><i class="bi bi-shield-lock me-2"></i>Role & Permission Management</h4></div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card"><div class="card-header"><strong>নতুন Role</strong></div><div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="form-label">Role Name *</label><input class="form-control" name="name" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
                    <button class="btn btn-dark w-100"><i class="bi bi-plus-lg me-1"></i>Role তৈরি করুন</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card"><div class="card-header"><strong>Role তালিকা</strong></div><div class="card-body table-responsive">
                <table class="table align-middle"><thead><tr><th>Role</th><th>Admin</th><th>স্ট্যাটাস</th><th class="text-end">অ্যাকশন</th></tr></thead><tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><strong><?php echo sanitize($role['name']); ?></strong><?php if ($role['is_superadmin']): ?> <span class="badge bg-danger">Super Admin</span><?php endif; ?><br><small class="text-muted"><?php echo sanitize($role['description'] ?? ''); ?></small></td>
                        <td><?php echo (int)$role['admin_count']; ?></td>
                        <td><span class="badge <?php echo $role['status'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $role['status'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td class="text-end">
                            <?php if (!$role['is_superadmin'] || adminIsSuperAdmin()): ?><a class="btn btn-sm btn-outline-primary" href="<?php echo adminUrl('role-edit.php?id=' . $role['id']); ?>"><i class="bi bi-pencil"></i> Permission</a><?php endif; ?>
                            <?php if (!$role['is_superadmin']): ?><form method="post" class="d-inline" onsubmit="return confirm('Role ডিলিট করবেন?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="role_id" value="<?php echo $role['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div></div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
