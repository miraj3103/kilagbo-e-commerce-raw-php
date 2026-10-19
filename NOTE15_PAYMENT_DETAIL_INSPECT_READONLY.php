<?php
/** NOTE 15: read-only shape inspection; does not display personal data or amounts. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/steadfast.php';

function note15ReadOnlyShape($value, int $depth = 0): string|array
{
    if (!is_array($value)) return gettype($value);
    if ($depth >= 5) return '[nested]';
    $out = [];
    foreach (array_slice($value, 0, 35, true) as $key => $child) {
        if (is_int($key)) {
            $out['[item]'] = note15ReadOnlyShape($child, $depth + 1);
            break;
        }
        $out[(string)$key] = note15ReadOnlyShape($child, $depth + 1);
    }
    return $out;
}

$listResponse = steadfastRequest('GET', '/payments');
if (!$listResponse['ok']) {
    fwrite(STDERR, 'Payments API HTTP ' . $listResponse['http_code'] . PHP_EOL);
    exit(1);
}
$payments = $listResponse['data']['payments'] ?? [];
if (!is_array($payments) || !$payments) {
    echo "No payment entries available for inspecting detail.\n";
    exit(0);
}
$paymentId = null;
foreach ($payments as $item) {
    if (is_array($item) && isset($item['payment_id']) && preg_match('/\A[A-Za-z0-9_-]{1,80}\z/', (string)$item['payment_id'])) {
        $paymentId = (string)$item['payment_id'];
        break;
    }
}
if ($paymentId === null) {
    echo "No usable payment_id in payment list.\n";
    exit(0);
}
$detail = steadfastRequest('GET', '/payments/' . rawurlencode($paymentId));
if (!$detail['ok']) {
    echo 'Payment detail API returned HTTP ' . $detail['http_code'] . " (no values displayed).\n";
    exit(0);
}
echo "Payment detail field names and types (values hidden):\n";
echo json_encode(note15ReadOnlyShape($detail['data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
