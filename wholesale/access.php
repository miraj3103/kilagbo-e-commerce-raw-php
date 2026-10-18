<?php
/** NOTE 12: public access/registration/pending landing page. */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/access.php';
$currentPage = 'wholesale';
$pageTitle = 'পাইকারি সদস্য অনুমোদন';
header('Cache-Control: private, no-store, max-age=0');
$status = wholesaleAccessStatus($pdo);
if ($status === 'approved') {
    redirect(baseUrl('wholesale'));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isUserLoggedIn()) {
        redirect(baseUrl('login.php?wholesale=1'));
    }
    if (!wholesaleCheckRequestCsrf((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        $error = 'অনুগ্রহ করে পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
    } elseif ($status === 'unavailable') {
        http_response_code(503);
        $error = 'পাইকারি অনুমোদন ব্যবস্থা এখনও চালু হয়নি। প্রশাসকের সাথে যোগাযোগ করুন।';
    } elseif (in_array($status, ['none', 'rejected'], true)) {
        try {
            if (wholesaleSubmitRequest($pdo, (int)$_SESSION['user_id'])) {
                wholesaleNotifyNewRequest((int)$_SESSION['user_id']);
            }
            setFlash('success', 'আপনার পাইকারি আবেদন জমা হয়েছে। Super Admin অনুমোদন দিলে প্রবেশ করতে পারবেন।');
            redirect(baseUrl('wholesale/access.php'));
        } catch (Throwable $e) {
            http_response_code(503);
            $error = 'আবেদন জমা দেওয়া যায়নি। পরে চেষ্টা করুন।';
        }
    }
    $status = wholesaleAccessStatus($pdo);
}
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="min-height:60vh;display:flex;align-items:center;justify-content:center">
    <div class="card border-0 shadow-sm" style="max-width:580px;width:100%;border-radius:16px">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="mb-3"><i class="bi bi-shop-window" style="font-size:3rem"></i></div>
            <h2 class="h4 mb-3">পাইকারি পণ্য ও দাম</h2>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php showFlash(); ?>
            <?php if ($status === 'guest'): ?>
                <p class="text-muted">পাইকারি দাম দেখতে প্রথমে রেজিস্ট্রেশন করুন। Super Admin অনুমোদনের পরই পাইকারি পণ্য ও অর্ডারের সুবিধা পাবেন।</p>
                <a class="btn btn-dark w-100 mb-2" href="<?php echo baseUrl('registration.php?wholesale=1'); ?>">পাইকারি রেজিস্ট্রেশন করুন</a>
                <a class="btn btn-outline-dark w-100" href="<?php echo baseUrl('login.php?wholesale=1'); ?>">আগে থেকে অ্যাকাউন্ট আছে? লগইন</a>
            <?php elseif ($status === 'pending'): ?>
                <div class="alert alert-warning">আপনার পাইকারি আবেদন Pending আছে। Super Admin অনুমোদনের জন্য অপেক্ষা করুন।</div>
                <a href="<?php echo baseUrl('account'); ?>" class="btn btn-outline-dark">আমার অ্যাকাউন্ট</a>
            <?php elseif ($status === 'rejected'): ?>
                <div class="alert alert-danger">আপনার আগের পাইকারি আবেদন অনুমোদিত হয়নি। চাইলে আবার আবেদন করতে পারেন।</div>
                <form method="post"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars(wholesaleRequestCsrf(), ENT_QUOTES, 'UTF-8'); ?>"><button class="btn btn-dark w-100">পুনরায় আবেদন করুন</button></form>
            <?php elseif ($status === 'none'): ?>
                <p class="text-muted">আপনার Retail অ্যাকাউন্ট আছে। পাইকারি দাম দেখতে Super Admin-এর অনুমোদন প্রয়োজন।</p>
                <form method="post"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars(wholesaleRequestCsrf(), ENT_QUOTES, 'UTF-8'); ?>"><button class="btn btn-dark w-100">পাইকারি অ্যাক্সেসের আবেদন করুন</button></form>
            <?php else: ?>
                <div class="alert alert-warning">পাইকারি অনুমোদন ব্যবস্থা সেটআপ করা হয়নি। অনুগ্রহ করে প্রশাসকের সাথে যোগাযোগ করুন।</div>
            <?php endif; ?>
            <a class="d-block mt-4 text-secondary" href="<?php echo baseUrl('shop'); ?>">খুচরা পণ্য দেখুন</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
