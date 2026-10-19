<?php
/** NOTE15: CLI-only reseller courier status polling & API payment-shape inspection. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/steadfast.php';

$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--run', '--inspect-payments'], true)) {
    fwrite(STDERR, "Usage: php cron/steadfast-resell-sync.php --dry-run|--run|--inspect-payments\n");
    exit(2);
}
if (!steadfastIsReady()) {
    fwrite(STDERR, "Steadfast disabled or API credentials missing. No requests made.\n");
    exit(1);
}

// Keys/types only: never print customer, phone, tracking codes, API credentials or payments.
function note15KeysOnly($value, int $depth = 0): array
{
    if (!is_array($value) || $depth > 3) return [];
    $result = [];
    foreach (array_slice($value, 0, 30, true) as $key => $child) {
        if (is_int($key)) {
            $result['[item]'] = is_array($child) ? note15KeysOnly($child, $depth + 1) : gettype($child);
            break;
        }
        $result[(string)$key] = is_array($child) ? note15KeysOnly($child, $depth + 1) : gettype($child);
    }
    return $result;
}

if ($mode === '--inspect-payments') {
    $res = steadfastRequest('GET', '/payments');
    if (!$res['ok']) { fwrite(STDERR, "Payment list API unavailable (HTTP {$res['http_code']}).\n"); exit(1); }
    echo "Payments list response keys/types (no values):\n";
    echo json_encode(note15KeysOnly($res['data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    $data = $res['data'];
    $list = $data['data'] ?? [];
    if (is_array($list) && isset($list['data'])) $list = $list['data'];
    $first = is_array($list) ? reset($list) : null;
    if (is_array($first) && isset($first['id']) && ctype_digit((string)$first['id'])) {
        $detail = steadfastRequest('GET', '/payments/' . rawurlencode((string)$first['id']));
        if ($detail['ok']) {
            echo "Payment details keys/types (no values):\n";
            echo json_encode(note15KeysOnly($detail['data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "Payment detail endpoint unavailable (HTTP {$detail['http_code']}).\n";
        }
    }
    exit(0);
}

$lockName = sys_get_temp_dir() . '/klg-note15-' . substr(hash('sha256', __DIR__), 0, 16) . '.lock';
$lock = fopen($lockName, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Another courier sync is running. Skipping.\n";
    exit(0);
}

try {
    $q = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resell_orders' AND COLUMN_NAME = 'partial_delivery_deducted'");
    if (!(int)$q->fetchColumn()) throw new RuntimeException('Run NOTE15 database migration before sync.');
    $limit = 25;
    // Fair polling: oldest last-sync first. Only orders that have a consignment.
    $s = $pdo->query("SELECT id FROM resell_orders
        WHERE (steadfast_consignment_id IS NOT NULL AND steadfast_consignment_id <> ''
               OR steadfast_tracking_code IS NOT NULL AND steadfast_tracking_code <> '')
          AND (courier_last_sync_at IS NULL OR courier_last_sync_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
        ORDER BY COALESCE(courier_last_sync_at, '1970-01-01') ASC, id ASC LIMIT {$limit}");
    $ids = $s->fetchAll(PDO::FETCH_COLUMN);
    echo 'Eligible for sync: ' . count($ids) . " (max {$limit} per run)\n";
    if ($mode === '--dry-run') exit(0);

    $ok = 0; $failed = 0;
    foreach ($ids as $id) {
        try {
            $res = steadfastSyncOrder($pdo, 'resell', (int)$id, true);
            if (!empty($res['ok'])) $ok++; else $failed++;
        } catch (Throwable $e) {
            $failed++;
            // Do not expose customer data/API response in scheduled command output.
            error_log('NOTE15 courier sync error for order ID ' . (int)$id . ': ' . $e->getMessage());
        }
    }
    echo "Courier sync: OK={$ok}, failed={$failed}; no unverified financial deductions.\n";
    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Courier sync aborted: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
