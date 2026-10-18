<?php
/** NOTE 12: Customer wholesale approval checks. Public retail auth remains unchanged. */
if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/config.php';
}

function wholesaleRequestsReady(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wholesale_access_requests'");
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function wholesaleAccessStatus(PDO $pdo): string
{
    if (!isUserLoggedIn()) {
        return 'guest';
    }
    if (!wholesaleRequestsReady($pdo)) {
        return 'unavailable';
    }
    try {
        $stmt = $pdo->prepare("SELECT status FROM wholesale_access_requests WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
        return in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'none';
    } catch (Throwable $e) {
        return 'unavailable';
    }
}

function wholesaleIsApproved(PDO $pdo): bool
{
    return wholesaleAccessStatus($pdo) === 'approved';
}

function wholesaleRequireApproval(PDO $pdo): void
{
    // Prevent caches from exposing member-only prices to other sessions.
    header('Cache-Control: private, no-store, max-age=0');
    if (!wholesaleIsApproved($pdo)) {
        redirect(baseUrl('wholesale/access.php'));
    }
}

function wholesaleSubmitRequest(PDO $pdo, int $userId): bool
{
    if ($userId <= 0 || !wholesaleRequestsReady($pdo)) {
        throw new RuntimeException('Wholesale approval migration is required.');
    }
    // Inserting is idempotent. A rejected customer may submit a fresh request.
    // An approved/pending customer's existing status is never reset.
    $stmt = $pdo->prepare("INSERT INTO wholesale_access_requests (user_id, status)
        VALUES (?, 'pending')
        ON DUPLICATE KEY UPDATE
            reviewed_by = IF(status = 'rejected', NULL, reviewed_by),
            reviewed_at = IF(status = 'rejected', NULL, reviewed_at),
            status = IF(status = 'rejected', 'pending', status)");
    $stmt->execute([$userId]);
    return $stmt->rowCount() > 0;
}

function wholesaleNotifyNewRequest(int $userId): void
{
    // Non-critical: request remains persisted if a notification cannot be delivered.
    try {
        addNotification('wholesale_access', 'নতুন পাইকারি অনুমোদন আবেদন',
            'কাস্টমার #' . $userId . ' পাইকারি অ্যাক্সেস চেয়েছেন।',
            adminUrl('wholesale-requests.php'));
    } catch (Throwable $e) {
        error_log('Wholesale request notification failed');
    }
}

function wholesaleRequestCsrf(): string
{
    if (empty($_SESSION['wholesale_request_csrf'])) {
        $_SESSION['wholesale_request_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['wholesale_request_csrf'];
}

function wholesaleCheckRequestCsrf(string $provided): bool
{
    return $provided !== '' && hash_equals(wholesaleRequestCsrf(), $provided);
}
