<?php
$pageTitle = 'Admin Users';
$currentPage = 'admin-users';
require_once __DIR__ . '/../config/config.php';
requireAdmin();
require_once __DIR__ . '/includes/rbac.php';
requireAdminPermission('admin_users.manage');

$currentAdminId = (int)$_SESSION['admin_id'];
$currentIsSuper = adminIsSuperAdmin();

function note11GetRole(PDO $pdo, int $id): ?array { $s=$pdo->prepare("SELECT * FROM admin_roles WHERE id=?"); $s->execute([$id]); $r=$s->fetch(); return $r?:null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? ''); $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? ''; $roleId=(int)($_POST['role_id']??0);
        $role=note11GetRole($pdo,$roleId);
        if ($username==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<6 || !$role) setFlash('error','সঠিক নাম, ইমেইল, কমপক্ষে ৬ অক্ষরের পাসওয়ার্ড এবং Role দিন');
        elseif ((int)$role['is_superadmin']===1 && !$currentIsSuper) setFlash('error','Super Admin role assign করার অনুমতি নেই');
        else {
            $c=$pdo->prepare("SELECT COUNT(*) FROM admins WHERE email=?");$c->execute([$email]);
            if($c->fetchColumn()) setFlash('error','এই ইমেইল ইতিমধ্যে ব্যবহার হচ্ছে');
            else { $pdo->prepare("INSERT INTO admins (role_id,username,email,password) VALUES (?,?,?,?)")->execute([$roleId,$username,$email,password_hash($password,PASSWORD_DEFAULT)]); setFlash('success','Admin User তৈরি হয়েছে'); }
        }
        redirect(adminUrl('admin-users.php'));
    }
    if ($action === 'update') {
        $id=(int)($_POST['admin_id']??0); $username=trim($_POST['username']??''); $email=trim($_POST['email']??''); $password=$_POST['password']??''; $roleId=(int)($_POST['role_id']??0);
        $s=$pdo->prepare("SELECT a.*, ar.is_superadmin FROM admins a LEFT JOIN admin_roles ar ON ar.id=a.role_id WHERE a.id=?");$s->execute([$id]);$target=$s->fetch();$role=note11GetRole($pdo,$roleId);
        if(!$target || !$role) setFlash('error','Admin/Role পাওয়া যায়নি');
        elseif((int)$target['is_superadmin']===1 && !$currentIsSuper) setFlash('error','Super Admin account edit করার অনুমতি নেই');
        elseif((int)$role['is_superadmin']===1 && !$currentIsSuper) setFlash('error','Super Admin role assign করার অনুমতি নেই');
        elseif((int)$target['is_superadmin']===1 && (int)$role['is_superadmin']===0 && (int)$pdo->query("SELECT COUNT(*) FROM admins a JOIN admin_roles ar ON ar.id=a.role_id WHERE ar.is_superadmin=1")->fetchColumn() <= 1) setFlash('error','শেষ Super Admin-কে অন্য Role-এ পরিবর্তন করা যাবে না');
        else {
            $dup=$pdo->prepare("SELECT COUNT(*) FROM admins WHERE email=? AND id<>?");$dup->execute([$email,$id]);
            if($dup->fetchColumn()) setFlash('error','এই ইমেইল অন্য Admin ব্যবহার করছে');
            else {
                if($password!=='') $pdo->prepare("UPDATE admins SET username=?,email=?,role_id=?,password=? WHERE id=?")->execute([$username,$email,$roleId,password_hash($password,PASSWORD_DEFAULT),$id]);
                else $pdo->prepare("UPDATE admins SET username=?,email=?,role_id=? WHERE id=?")->execute([$username,$email,$roleId,$id]);
                if($id===$currentAdminId){$_SESSION['admin_name']=$username;$_SESSION['admin_email']=$email;}
                setFlash('success','Admin User আপডেট হয়েছে');
            }
        }
        redirect(adminUrl('admin-users.php'));
    }
    if ($action === 'delete') {
        $id=(int)($_POST['admin_id']??0);
        if($id===$currentAdminId) setFlash('error','নিজের Admin account ডিলিট করা যাবে না');
        else {
            $s=$pdo->prepare("SELECT a.id, ar.is_superadmin FROM admins a LEFT JOIN admin_roles ar ON ar.id=a.role_id WHERE a.id=?");$s->execute([$id]);$target=$s->fetch();
            if(!$target) setFlash('error','Admin পাওয়া যায়নি');
            elseif((int)$target['is_superadmin']===1 && !$currentIsSuper) setFlash('error','Super Admin account ডিলিট করা যাবে না');
            else {
                if((int)$target['is_superadmin']===1){$count=(int)$pdo->query("SELECT COUNT(*) FROM admins a JOIN admin_roles ar ON ar.id=a.role_id WHERE ar.is_superadmin=1")->fetchColumn(); if($count<=1){setFlash('error','শেষ Super Admin account ডিলিট করা যাবে না');redirect(adminUrl('admin-users.php'));}}
                $pdo->prepare("DELETE FROM admins WHERE id=?")->execute([$id]); setFlash('success','Admin User ডিলিট হয়েছে');
            }
        }
        redirect(adminUrl('admin-users.php'));
    }
}

$rolesSql = $currentIsSuper ? "SELECT * FROM admin_roles WHERE status=1 ORDER BY is_superadmin DESC,name" : "SELECT * FROM admin_roles WHERE status=1 AND is_superadmin=0 ORDER BY name";
$roles=$pdo->query($rolesSql)->fetchAll();
$admins=$pdo->query("SELECT a.*, ar.name role_name, ar.is_superadmin FROM admins a LEFT JOIN admin_roles ar ON ar.id=a.role_id ORDER BY a.id")->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
<?php showFlash(); ?>
<div class="d-flex justify-content-between align-items-center mb-4"><h4><i class="bi bi-person-gear me-2"></i>Admin Users</h4><a class="btn btn-outline-dark" href="<?php echo adminUrl('roles.php'); ?>"><i class="bi bi-shield-lock me-1"></i>Roles</a></div>
<div class="card mb-4"><div class="card-header"><strong>নতুন Admin User</strong></div><div class="card-body"><form method="post" class="row g-3"><input type="hidden" name="action" value="create"><div class="col-md-3"><label class="form-label">নাম *</label><input class="form-control" name="username" required></div><div class="col-md-3"><label class="form-label">ইমেইল *</label><input type="email" class="form-control" name="email" required></div><div class="col-md-2"><label class="form-label">পাসওয়ার্ড *</label><input type="password" class="form-control" name="password" minlength="6" required></div><div class="col-md-2"><label class="form-label">Role *</label><select class="form-select" name="role_id" required><option value="">সিলেক্ট</option><?php foreach($roles as $r):?><option value="<?php echo $r['id'];?>"><?php echo sanitize($r['name']);?></option><?php endforeach;?></select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-dark w-100">তৈরি করুন</button></div></form></div></div>
<div class="card"><div class="card-body table-responsive"><table class="table align-middle"><thead><tr><th>Admin</th><th>Role</th><th>Created</th><th class="text-end">অ্যাকশন</th></tr></thead><tbody>
<?php foreach($admins as $a): $locked=((int)$a['is_superadmin']===1 && !$currentIsSuper); ?>
<tr><td><strong><?php echo sanitize($a['username']);?></strong><br><small class="text-muted"><?php echo sanitize($a['email']);?></small><?php if((int)$a['id']===$currentAdminId):?> <span class="badge bg-info">আপনি</span><?php endif;?></td><td><span class="badge <?php echo $a['is_superadmin']?'bg-danger':'bg-secondary';?>"><?php echo sanitize($a['role_name']??'No Role');?></span></td><td><?php echo date('d M Y',strtotime($a['created_at']));?></td><td class="text-end">
<?php if(!$locked):?><button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#editAdmin<?php echo $a['id'];?>"><i class="bi bi-pencil"></i></button><?php endif;?>
<?php if((int)$a['id']!==$currentAdminId && !$locked):?><form method="post" class="d-inline" onsubmit="return confirm('Admin User ডিলিট করবেন?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="admin_id" value="<?php echo $a['id'];?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif;?>
</td></tr>
<?php if(!$locked):?><tr class="collapse" id="editAdmin<?php echo $a['id'];?>"><td colspan="4"><form method="post" class="row g-2 p-2 bg-light"><input type="hidden" name="action" value="update"><input type="hidden" name="admin_id" value="<?php echo $a['id'];?>"><div class="col-md-3"><input class="form-control" name="username" value="<?php echo sanitize($a['username']);?>" required></div><div class="col-md-3"><input type="email" class="form-control" name="email" value="<?php echo sanitize($a['email']);?>" required></div><div class="col-md-2"><select class="form-select" name="role_id" required><?php foreach($roles as $r): if(!$currentIsSuper && $r['is_superadmin']) continue;?><option value="<?php echo $r['id'];?>" <?php echo (int)$a['role_id']===(int)$r['id']?'selected':'';?>><?php echo sanitize($r['name']);?></option><?php endforeach;?></select></div><div class="col-md-2"><input type="password" class="form-control" name="password" minlength="6" placeholder="নতুন password (optional)"></div><div class="col-md-2"><button class="btn btn-success w-100">আপডেট</button></div></form></td></tr><?php endif;?>
<?php endforeach;?>
</tbody></table></div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
