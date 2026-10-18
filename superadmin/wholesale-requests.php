<?php
/** NOTE 12: Approve / reject / revoke wholesale access, independently from retail account. */
$pageTitle = 'পাইকারি কাস্টমার অনুমোদন';
$currentPage = 'wholesale-requests';
require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermission('wholesale.manage');
require_once __DIR__ . '/../wholesale/includes/access.php';

if (!wholesaleRequestsReady($pdo)) {
    http_response_code(503);
    exit('NOTE 12 migration must be run before managing wholesale approvals.');
}
if (empty($_SESSION['wholesale_admin_csrf'])) {
    $_SESSION['wholesale_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['wholesale_admin_csrf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token');
    }
    $id = (int)($_POST['request_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $statuses = ['approve' => 'approved', 'reject' => 'rejected', 'revoke' => 'rejected'];
    if ($id > 0 && isset($statuses[$action])) {
        // Status changes do not delete users, retail orders, or their account sessions.
        $stmt = $pdo->prepare("UPDATE wholesale_access_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$statuses[$action], (int)$_SESSION['admin_id'], $id]);
        setFlash('success', 'পাইকারি অনুমোদনের অবস্থা আপডেট হয়েছে।');
    }
    redirect(adminUrl('wholesale-requests.php'));
}
$filter = (string)($_GET['status'] ?? 'pending');
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'], true)) $filter = 'pending';
$filterSql = $filter === 'all' ? '' : ' WHERE w.status = ?';
$stmt = $pdo->prepare("SELECT w.id, w.user_id, w.status, w.created_at, w.reviewed_at, u.name, u.email, u.phone
    FROM wholesale_access_requests w INNER JOIN users u ON u.id = w.user_id" . $filterSql . " ORDER BY w.created_at DESC LIMIT 200");
$stmt->execute($filter === 'all' ? [] : [$filter]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header"><h2 class="page-title">পাইকারি কাস্টমার অনুমোদন</h2></div>
<div class="admin-card">
    <div class="mb-3 d-flex flex-wrap gap-2">
        <?php foreach (['pending'=>'Pending', 'approved'=>'Approved', 'rejected'=>'Rejected', 'all'=>'সব'] as $key=>$label): ?>
        <a class="btn btn-sm <?php echo $filter === $key ? 'btn-dark' : 'btn-outline-dark'; ?>" href="<?php echo adminUrl('wholesale-requests.php?status=' . $key); ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>কাস্টমার</th><th>ইমেইল / ফোন</th><th>অবস্থা</th><th>আবেদনের তারিখ</th><th>অ্যাকশন</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $item): ?>
        <tr>
            <td><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <small class="text-muted">#<?php echo (int)$item['user_id']; ?></small></td>
            <td><?php echo htmlspecialchars($item['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($item['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></td>
            <td><span class="badge <?php echo $item['status'] === 'approved' ? 'bg-success' : ($item['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>"><?php echo htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td><?php echo htmlspecialchars($item['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><div class="d-flex flex-wrap gap-1">
                <?php if ($item['status'] !== 'approved'): ?>
                <form method="post"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="request_id" value="<?php echo (int)$item['id']; ?>"><button name="action" value="approve" class="btn btn-sm btn-success">Approve</button></form>
                <?php endif; ?>
                <?php if ($item['status'] === 'pending'): ?>
                <form method="post"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="request_id" value="<?php echo (int)$item['id']; ?>"><button name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button></form>
                <?php elseif ($item['status'] === 'approved'): ?>
                <form method="post"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="request_id" value="<?php echo (int)$item['id']; ?>"><button name="action" value="revoke" class="btn btn-sm btn-outline-danger" onclick="return confirm('পাইকারি অ্যাক্সেস বন্ধ করবেন?')">Revoke</button></form>
                <?php endif; ?>
            </div></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">কোনো আবেদন পাওয়া যায়নি।</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
