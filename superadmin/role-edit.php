<?php
$pageTitle = 'Role Permission';
$currentPage = 'roles';
require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermission('roles.manage');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM admin_roles WHERE id=?"); $stmt->execute([$id]); $role = $stmt->fetch();
if (!$role) { setFlash('error','Role পাওয়া যায়নি'); redirect(adminUrl('roles.php')); }
if ((int)$role['is_superadmin'] === 1 && !adminIsSuperAdmin()) adminPermissionDenied('Super Admin role শুধুমাত্র Super Admin পরিবর্তন করতে পারবেন।');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? $role['name']);
    $description = trim($_POST['description'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    if ((int)$role['is_superadmin'] === 1) $status = 1;
    $pdo->prepare("UPDATE admin_roles SET name=?, description=?, status=? WHERE id=?")->execute([$name, $description, $status, $id]);

    if ((int)$role['is_superadmin'] === 0) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['permissions'] ?? []))));
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM admin_role_permissions WHERE role_id=?")->execute([$id]);
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $q = $pdo->prepare("SELECT id FROM admin_permissions WHERE id IN ($ph)");
                $q->execute($ids);
                $allowed = $q->fetchAll(PDO::FETCH_COLUMN);
                $ins = $pdo->prepare("INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($allowed as $pid) $ins->execute([$id, $pid]);
            }
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    setFlash('success', 'Role permissions আপডেট হয়েছে');
    redirect(adminUrl('role-edit.php?id=' . $id));
}

$permissions = $pdo->query("SELECT * FROM admin_permissions ORDER BY group_name, id")->fetchAll();
$selectedStmt = $pdo->prepare("SELECT permission_id FROM admin_role_permissions WHERE role_id=?"); $selectedStmt->execute([$id]);
$selected = array_map('intval', $selectedStmt->fetchAll(PDO::FETCH_COLUMN));
$groups=[]; foreach($permissions as $p) $groups[$p['group_name']][]=$p;
require_once __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
    <?php showFlash(); ?>
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h4 class="mb-1"><i class="bi bi-shield-check me-2"></i><?php echo sanitize($role['name']); ?></h4><small class="text-muted">Role permissions নির্ধারণ করুন</small></div><a class="btn btn-outline-secondary" href="<?php echo adminUrl('roles.php'); ?>">Role তালিকা</a></div>
    <form method="post">
        <div class="card mb-4"><div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">Role Name</label><input class="form-control" name="name" value="<?php echo sanitize($role['name']); ?>" required <?php echo $role['is_superadmin'] ? 'readonly' : ''; ?>></div>
            <div class="col-md-6"><label class="form-label">Description</label><input class="form-control" name="description" value="<?php echo sanitize($role['description'] ?? ''); ?>"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="status" id="roleStatus" <?php echo $role['status'] ? 'checked' : ''; ?> <?php echo $role['is_superadmin'] ? 'disabled' : ''; ?>><label class="form-check-label" for="roleStatus">Role Active</label></div></div>
        </div></div>
        <?php if ($role['is_superadmin']): ?>
            <div class="alert alert-danger"><i class="bi bi-lock-fill me-2"></i>Super Admin সব permission স্বয়ংক্রিয়ভাবে পায়।</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($groups as $group => $items): ?>
                    <?php $visible = $items; if (!$visible) continue; ?>
                    <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header"><strong><?php echo sanitize($group); ?></strong></div><div class="card-body">
                    <?php foreach ($visible as $p): ?>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo $p['id']; ?>" id="perm<?php echo $p['id']; ?>" <?php echo in_array((int)$p['id'],$selected,true)?'checked':''; ?>><label class="form-check-label" for="perm<?php echo $p['id']; ?>"><?php echo sanitize($p['name']); ?><small class="d-block text-muted"><?php echo sanitize($p['permission_key']); ?></small></label></div>
                    <?php endforeach; ?>
                    </div></div></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button class="btn btn-dark mt-4"><i class="bi bi-save me-1"></i>সেভ করুন</button>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
