<?php
/**
 * Kilagbo NOTE 13 - Central site lock, scheduled expiry and owner console.
 * One runtime file. Integrates through the central config/config.php bootstrap.
 * PHP 8.0+; no database tables or third-party dependencies.
 */
declare(strict_types=1);

const KLG_LOCK_HOOK = "// NOTE13_SITE_LOCK_HOOK\nrequire_once __DIR__ . '/../site-control.php';\n// END_NOTE13_SITE_LOCK_HOOK\n";

function klgLockFile(): string
{
    // A PHP-return-only state file prevents a direct HTTP request from displaying the hash.
    // Stored outside the application directory where typical document roots allow it.
    $suffix = substr(hash('sha256', __DIR__), 0, 16);
    return dirname(__DIR__) . '/.kilagbo_site_lock_' . $suffix . '.php';
}

function klgLockRead(): ?array
{
    $path = klgLockFile();
    if (!is_file($path)) {
        return null;
    }
    // Read bytes (not PHP include): opcache must never cache lock status.
    $raw = file_get_contents($path);
    $marker = "__halt_compiler();\n";
    $position = $raw === false ? false : strpos($raw, $marker);
    $data = $position === false ? null : json_decode(substr($raw, $position + strlen($marker)), true);
    if (!is_array($data) || !isset($data['owner_hash']) || !is_string($data['owner_hash'])) {
        throw new RuntimeException('Site control state is invalid; recover it via the owner CLI.');
    }
    $data['mode'] = in_array(($data['mode'] ?? ''), ['never', 'scheduled', 'locked'], true)
        ? $data['mode'] : 'locked';
    $data['expires_at'] = (int)($data['expires_at'] ?? 0);
    $data['failures'] = is_array($data['failures'] ?? null) ? $data['failures'] : [];
    $data['auth_epoch'] = is_string($data['auth_epoch'] ?? null) ? $data['auth_epoch'] : '';
    return $data;
}

function klgLockSave(array $state): void
{
    $path = klgLockFile();
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Cannot write owner state outside site root: ' . $dir);
    }
    $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    // Safe when directly requested over HTTP; never evaluated to obtain state.
    $content = "<?php http_response_code(404); exit; __halt_compiler();\n" . $json;
    $temp = tempnam($dir, '.klglock-');
    if ($temp === false) {
        throw new RuntimeException('Unable to create temporary state file.');
    }
    try {
        @chmod($temp, 0600);
        if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('Unable to write control state.');
        }
        if (!rename($temp, $path)) {
            throw new RuntimeException('Unable to atomically save control state.');
        }
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
}

function klgLockActive(?array $state): bool
{
    if ($state === null) {
        return false; // Installation is opt-in: do not lock a site before setup.
    }
    if ($state['mode'] === 'locked') {
        return true;
    }
    if ($state['mode'] === 'scheduled') {
        return $state['expires_at'] <= time(); // No cron required.
    }
    return false;
}

function klgLockEsc(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function klgLockWebHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
}

function klgLockBlock(): void
{
    klgLockWebHeaders();
    http_response_code(503);
    header('Retry-After: 3600');
    $path = (string)($_SERVER['REQUEST_URI'] ?? '');
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    $json = str_contains($accept, 'application/json')
        || str_contains($path, '/ajax/')
        || str_contains($path, '/api/')
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'site_locked', 'message' => 'Website is temporarily unavailable.']);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>ওয়েবসাইট সাময়িক বন্ধ</title><style>body{min-height:100vh;margin:0;display:grid;place-items:center;background:#f3f4f6;'
        . 'font-family:system-ui,sans-serif;color:#111827}.panel{background:#fff;border-radius:16px;padding:38px;max-width:450px;text-align:center;'
        . 'box-shadow:0 12px 40px #00000012}.panel h1{font-size:25px}.panel p{color:#6b7280;line-height:1.7}</style></head><body>'
        . '<main class="panel"><h1>ওয়েবসাইট সাময়িকভাবে বন্ধ আছে</h1><p>অনুগ্রহ করে পরে আবার চেষ্টা করুন।</p></main></body></html>';
    exit;
}

function klgLockCli(): void
{
    global $argv;
    $cmd = $argv[1] ?? 'help';
    $config = __DIR__ . '/config/config.php';
    try {
        if ($cmd === 'install') {
            if (!is_file($config)) {
                throw new RuntimeException('config/config.php was not found.');
            }
            $old = file_get_contents($config);
            if ($old === false) {
                throw new RuntimeException('Could not read config/config.php.');
            }
            if (str_contains($old, 'NOTE13_SITE_LOCK_HOOK')) {
                echo "Site lock hook already installed.\n";
                return;
            }
            $match = '// Helper functions';
            if (substr_count($old, $match) !== 1 || !str_contains($old, 'new PDO(')) {
                throw new RuntimeException('Config layout differs from inspected version. No files changed; request a manual review.');
            }
            $backupDir = dirname(__DIR__);
            if (!is_writable($backupDir)) {
                throw new RuntimeException('Project parent directory is not writable for the safety backup.');
            }
            $backup = $backupDir . '/.kilagbo_config_before_lock_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.php';
            if (!copy($config, $backup)) {
                throw new RuntimeException('Unable to back up configuration. No files changed.');
            }
            @chmod($backup, 0600);
            $eol = str_contains($old, "\r\n") ? "\r\n" : "\n";
            $new = str_replace($match, str_replace("\n", $eol, KLG_LOCK_HOOK) . $eol . $match, $old);
            if (file_put_contents($config, $new, LOCK_EX) !== strlen($new)) {
                copy($backup, $config);
                throw new RuntimeException('Could not update config; attempted recovery using backup.');
            }
            echo "Central guard installed. Original config backup: $backup\n";
            echo "The existing database settings were not altered.\n";
            return;
        }
        if ($cmd === 'setup' || $cmd === 'reset-password') {
            $previous = klgLockRead();
            if ($cmd === 'setup' && $previous !== null) {
                throw new RuntimeException('Owner password already configured. Use reset-password with filesystem access to recover it.');
            }
            if ($cmd === 'reset-password' && $previous === null) {
                throw new RuntimeException('No configuration exists. Use setup.');
            }
            if (!in_array('--stdin', $argv, true)) {
                throw new RuntimeException('Use --stdin and pipe a hidden, private password. Do not put it in shell history.');
            }
            $pass = rtrim((string)fgets(STDIN), "\r\n");
            if (strlen($pass) < 12 || strlen($pass) > 256) {
                throw new RuntimeException('Owner password must be between 12 and 256 bytes.');
            }
            $state = $previous ?? [
                'mode' => 'never',
                'expires_at' => 0,
                'failures' => [],
            ];
            $state['owner_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            $state['auth_epoch'] = bin2hex(random_bytes(16));
            klgLockSave($state);
            echo "Owner password configured securely. Default mode: " . $state['mode'] . "\n";
            return;
        }
        if ($cmd === 'status') {
            $state = klgLockRead();
            if (!$state) {
                echo "NOT CONFIGURED: website remains unlocked.\n";
                return;
            }
            echo 'Mode: ' . $state['mode'] . PHP_EOL;
            if ($state['mode'] === 'scheduled') {
                echo 'Expiry (UTC): ' . gmdate('Y-m-d H:i:s', $state['expires_at']) . PHP_EOL;
            }
            echo 'Website: ' . (klgLockActive($state) ? 'LOCKED' : 'OPEN') . PHP_EOL;
            return;
        }
        if ($cmd === 'uninstall') {
            if (!is_file($config)) {
                throw new RuntimeException('Config not found.');
            }
            $old = file_get_contents($config);
            if ($old === false || !str_contains($old, 'NOTE13_SITE_LOCK_HOOK')) {
                echo "No site lock hook found.\n";
                return;
            }
            $new = str_replace(str_replace("\n", "\r\n", KLG_LOCK_HOOK) . "\r\n", '', $old);
            $new = str_replace(KLG_LOCK_HOOK . "\n", '', $new);
            if ($new === $old || file_put_contents($config, $new, LOCK_EX) !== strlen($new)) {
                throw new RuntimeException('Could not safely uninstall hook. Restore the configuration backup manually.');
            }
            echo "Site lock hook removed. Owner state preserved.\n";
            return;
        }
        echo "Available commands: install, setup --stdin, reset-password --stdin, status, uninstall\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}

// CLI operations do not load the application or database; migrations remain runnable while locked.
if (PHP_SAPI === 'cli') {
    if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
        klgLockCli();
    }
    return;
}

// When reached via config/config.php this is a shared per-request security gate.
// The owner route is the sole necessary exception: otherwise the owner could never unlock the site.
$isOwnerRoute = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__;
try {
    $klgState = klgLockRead();
} catch (Throwable $error) {
    // Invalid state means the installation is not trustworthy: fail closed for site routes.
    if (!$isOwnerRoute) {
        klgLockBlock();
    }
    klgLockWebHeaders();
    http_response_code(503);
    exit('Owner control state unreadable. Recover using the CLI.');
}
if (!$isOwnerRoute) {
    if (klgLockActive($klgState)) {
        klgLockBlock();
    }
    return;
}

klgLockWebHeaders();
header('Content-Type: text/html; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['klg_lock_csrf'])) {
    $_SESSION['klg_lock_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['klg_lock_csrf'];
$message = '';
$error = '';
$authenticated = $klgState !== null
    && !empty($_SESSION['klg_lock_authenticated_until'])
    && (int)$_SESSION['klg_lock_authenticated_until'] > time()
    && !empty($_SESSION['klg_lock_auth_epoch'])
    && hash_equals((string)$klgState['auth_epoch'], (string)$_SESSION['klg_lock_auth_epoch']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Refresh and retry.';
    } elseif ($klgState === null) {
        $error = 'Owner password is not configured. Run CLI setup first.';
    } elseif (($_POST['action'] ?? '') === 'login') {
        $ipKey = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        // Remove stale rate-limit entries so the owner file stays small over time.
        foreach ($klgState['failures'] as $key => $times) {
            $recent = array_values(array_filter((array)$times,
                static fn($t): bool => is_int($t) && $t > time() - 900));
            if ($recent === []) {
                unset($klgState['failures'][$key]);
            } else {
                $klgState['failures'][$key] = $recent;
            }
        }
        $attempts = $klgState['failures'][$ipKey] ?? [];
        if (count($attempts) >= 8) {
            $error = 'Too many attempts. Try again after 15 minutes.';
        } elseif (password_verify((string)($_POST['owner_password'] ?? ''), $klgState['owner_hash'])) {
            session_regenerate_id(true);
            $_SESSION['klg_lock_authenticated_until'] = time() + 900;
            $_SESSION['klg_lock_auth_epoch'] = $klgState['auth_epoch'];
            $_SESSION['klg_lock_csrf'] = bin2hex(random_bytes(32));
            $csrf = $_SESSION['klg_lock_csrf'];
            $authenticated = true;
            unset($klgState['failures'][$ipKey]);
            klgLockSave($klgState);
            $message = 'Owner verification successful.';
        } else {
            $attempts[] = time();
            $klgState['failures'][$ipKey] = $attempts;
            klgLockSave($klgState);
            $error = 'Invalid owner password.';
        }
    } elseif (($_POST['action'] ?? '') === 'logout') {
        unset($_SESSION['klg_lock_authenticated_until'], $_SESSION['klg_lock_auth_epoch']);
        $authenticated = false;
        $message = 'Signed out.';
    } elseif ($authenticated && ($_POST['action'] ?? '') === 'save') {
        $selected = (string)($_POST['mode'] ?? '');
        if ($selected === 'locked') {
            $klgState['mode'] = 'locked';
            $klgState['expires_at'] = 0;
        } elseif ($selected === 'never') {
            $klgState['mode'] = 'never';
            $klgState['expires_at'] = 0;
        } elseif ($selected === 'scheduled') {
            $days = filter_var($_POST['days'] ?? null, FILTER_VALIDATE_INT);
            if ($days === false || $days < 1 || $days > 3650) {
                $error = 'Enter between 1 and 3650 days.';
            } else {
                $klgState['mode'] = 'scheduled';
                $klgState['expires_at'] = time() + (86400 * $days);
            }
        } else {
            $error = 'Unknown action.';
        }
        if ($error === '') {
            klgLockSave($klgState);
            $message = 'Site control saved. Changes take effect on the next request.';
        }
    } else {
        $error = 'Owner authentication required.';
    }
}

$status = $klgState === null ? 'NOT CONFIGURED' : (klgLockActive($klgState) ? 'LOCKED' : 'OPEN');
$mode = (string)($klgState['mode'] ?? 'never');
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Owner Site Control | Kilagbo</title>
  <style>
    *{box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f2f4f8;margin:0;padding:24px;color:#171923}
    .wrap{max-width:620px;margin:6vh auto;background:white;padding:30px;border-radius:16px;box-shadow:0 14px 40px #10182812}
    h1{font-size:25px;margin-top:0}p{line-height:1.6}.muted{color:#667085;font-size:14px}
    .status{padding:13px;border-radius:8px;background:#f2f4f7;font-weight:700;margin:15px 0}.error{color:#b42318}.success{color:#027a48}
    label{display:block;margin:12px 0 7px;font-weight:550}input[type=password],input[type=number]{width:100%;padding:12px;border:1px solid #d0d5dd;border-radius:8px}
    .choice{border:1px solid #eaecf0;border-radius:10px;padding:12px;margin:10px 0}.choice label{margin:0;cursor:pointer}
    button{border:0;background:#101828;color:#fff;padding:12px 18px;border-radius:8px;cursor:pointer;margin-top:12px}
    button.secondary{background:#e4e7ec;color:#344054}.note{font-size:13px;color:#667085}
  </style>
</head>
<body><main class="wrap">
  <h1>Site Lock — Owner Control</h1>
  <p class="muted">এই প্যানেলটি সাধারণ Super Admin permission-এর বাইরে আলাদা Owner Password দিয়ে সুরক্ষিত।</p>
  <div class="status">Website status: <?= klgLockEsc($status) ?></div>
  <?php if ($message !== ''): ?><p class="success"><?= klgLockEsc($message) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="error"><?= klgLockEsc($error) ?></p><?php endif; ?>
  <?php if ($klgState === null): ?>
    <p>Owner password এখনো সেট করা হয়নি। Server CLI-তে <code>php site-control.php setup --stdin</code> চালিয়ে সেট করুন।</p>
  <?php elseif (!$authenticated): ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= klgLockEsc($csrf) ?>">
      <input type="hidden" name="action" value="login">
      <label for="ownerpass">Owner Password</label>
      <input id="ownerpass" type="password" name="owner_password" required autofocus autocomplete="current-password">
      <button type="submit">Unlock Control Panel</button>
    </form>
  <?php else: ?>
    <?php if ($mode === 'scheduled'): ?>
      <p class="muted">Scheduled lock (UTC): <strong><?= klgLockEsc(gmdate('Y-m-d H:i:s', (int)$klgState['expires_at'])) ?></strong></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= klgLockEsc($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <div class="choice"><label><input type="radio" name="mode" value="locked" <?= $mode === 'locked' ? 'checked' : '' ?>> এখনই সম্পূর্ণ লক করুন</label></div>
      <div class="choice"><label><input type="radio" name="mode" value="scheduled" <?= $mode === 'scheduled' ? 'checked' : '' ?>> নির্দিষ্ট দিন পর অটোমেটিক লক</label>
        <label for="days" class="muted">কত দিন পর?</label><input id="days" type="number" name="days" min="1" max="3650" value="7"></div>
      <div class="choice"><label><input type="radio" name="mode" value="never" <?= $mode === 'never' ? 'checked' : '' ?>> Never Expire — কখনো অটোমেটিক লক হবে না</label></div>
      <button type="submit">Save Control</button>
    </form>
    <form method="post"><input type="hidden" name="csrf" value="<?= klgLockEsc($csrf) ?>"><input type="hidden" name="action" value="logout">
      <button class="secondary" type="submit">Owner Logout</button></form>
  <?php endif; ?>
  <p class="note">Lock হলে সকল app PHP route-এর জন্য HTTP 503 দেওয়া হয়। এই সুরক্ষিত Owner URL-টি Unlock করার জন্য ব্যতিক্রম। Server static files ও independently bootstrapped scripts-এর জন্য আলাদা web-server coverage প্রয়োজন।</p>
</main></body></html>
