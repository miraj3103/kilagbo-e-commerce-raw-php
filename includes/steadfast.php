<?php
/**
 * Shared Steadfast courier helpers for Kilagbo admin order pages.
 * NOTE 07: bulk send + delivery-status sync.
 */

require_once __DIR__ . '/resell-shortfall.php';

if (!function_exists('steadfastOrderConfig')) {
    function steadfastOrderConfig(string $type): ?array
    {
        $configs = [
            'retail' => [
                'table' => 'orders',
                'status_column' => 'status',
                'number_column' => 'order_number',
                'name_column' => 'customer_name',
                'phone_column' => 'customer_phone',
                'address_column' => 'customer_address',
                'total_column' => 'total',
                'note_column' => 'notes',
            ],
            'wholesale' => [
                'table' => 'wholesale_orders',
                'status_column' => 'order_status',
                'number_column' => 'order_number',
                'name_column' => 'customer_name',
                'phone_column' => 'customer_phone',
                'address_column' => 'shipping_address',
                'total_column' => 'total',
                'note_column' => 'notes',
            ],
            'resell' => [
                'table' => 'resell_orders',
                'status_column' => 'status',
                'number_column' => 'order_number',
                'name_column' => 'customer_name',
                'phone_column' => 'customer_phone',
                'address_column' => 'shipping_address',
                'total_column' => 'reseller_price',
                'note_column' => 'notes',
            ],
            'affiliate' => [
                'table' => 'affiliate_orders',
                'status_column' => 'status',
                'number_column' => 'order_number',
                'name_column' => 'customer_name',
                'phone_column' => 'customer_phone',
                'address_column' => 'shipping_address',
                'total_column' => 'total_amount',
                'note_column' => 'notes',
            ],
        ];

        return $configs[$type] ?? null;
    }

    function steadfastIsReady(): bool
    {
        return getSettings('steadfast_active') == '1'
            && !empty(getSettings('steadfast_api_key'))
            && !empty(getSettings('steadfast_secret_key'));
    }

    function steadfastRequest(string $method, string $path, ?array $payload = null): array
    {
        $apiKey = getSettings('steadfast_api_key');
        $secretKey = getSettings('steadfast_secret_key');

        if (!steadfastIsReady()) {
            return ['ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'Steadfast কুরিয়ার সেটআপ করা হয়নি বা সক্রিয় নেই'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim(STEADFAST_BASE_URL, '/') . '/' . ltrim($path, '/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Api-Key: ' . $apiKey,
            'Secret-Key: ' . $secretKey,
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?? [], JSON_UNESCAPED_UNICODE));
        }

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $curlError) {
            return ['ok' => false, 'http_code' => $httpCode, 'data' => null, 'error' => $curlError ?: 'Courier request failed'];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'http_code' => $httpCode, 'data' => null, 'error' => 'Courier API থেকে invalid response এসেছে'];
        }

        return [
            'ok' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $decoded,
            'error' => $decoded['message'] ?? null,
        ];
    }

    function steadfastGetOrder(PDO $pdo, string $type, int $orderId): ?array
    {
        $config = steadfastOrderConfig($type);
        if (!$config || $orderId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT * FROM `{$config['table']}` WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return $order ?: null;
    }

    function steadfastBuildPayload(string $type, array $order): ?array
    {
        $config = steadfastOrderConfig($type);
        if (!$config) {
            return null;
        }

        $invoice = trim((string)($order[$config['number_column']] ?? ''));
        $name = trim((string)($order[$config['name_column']] ?? ''));
        $phone = trim((string)($order[$config['phone_column']] ?? ''));
        $address = trim((string)($order[$config['address_column']] ?? ''));
        $amount = (float)($order[$config['total_column']] ?? 0);
        $note = trim((string)($order[$config['note_column']] ?? ''));

        if ($invoice === '' || $name === '' || $phone === '' || $address === '') {
            return null;
        }

        return [
            'invoice' => $invoice,
            'recipient_name' => mb_substr($name, 0, 100),
            'recipient_phone' => $phone,
            'recipient_address' => mb_substr($address, 0, 250),
            'cod_amount' => max(0, $amount),
            'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
        ];
    }

    function steadfastMarkSent(PDO $pdo, string $type, int $orderId, $consignmentId, string $trackingCode, string $courierStatus = 'in_review'): void
    {
        $config = steadfastOrderConfig($type);
        if (!$config) {
            return;
        }

        $statusColumn = $config['status_column'];
        $sql = "UPDATE `{$config['table']}`
                SET steadfast_consignment_id = ?, steadfast_tracking_code = ?, courier_status = ?,
                    courier_status_updated_at = NOW(), courier_last_sync_at = NOW(), `{$statusColumn}` = 'processing'";

        if ($type === 'wholesale') {
            $sql .= ", courier_name = 'Steadfast', tracking_id = ?";
            $params = [$consignmentId, $trackingCode, $courierStatus, $trackingCode, $orderId];
        } else {
            $params = [$consignmentId, $trackingCode, $courierStatus, $orderId];
        }

        $sql .= " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);
    }

    function steadfastSendOrders(PDO $pdo, array $refs, bool $allowResend = false): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

        if (!steadfastIsReady()) {
            $result['failed'] = count($refs);
            $result['errors'][] = 'Steadfast কুরিয়ার সেটআপ করা হয়নি বা সক্রিয় নেই';
            return $result;
        }

        $prepared = [];
        $seen = [];

        foreach ($refs as $ref) {
            $type = (string)($ref['type'] ?? '');
            $id = (int)($ref['id'] ?? 0);
            $key = $type . ':' . $id;

            if ($id <= 0 || isset($seen[$key]) || !steadfastOrderConfig($type)) {
                continue;
            }
            $seen[$key] = true;

            $order = steadfastGetOrder($pdo, $type, $id);
            if (!$order) {
                $result['failed']++;
                $result['errors'][] = "$key অর্ডার পাওয়া যায়নি";
                continue;
            }

            $config = steadfastOrderConfig($type);
            $localStatus = (string)($order[$config['status_column']] ?? '');
            if (in_array($localStatus, ['cancelled', 'returned'], true)) {
                $result['skipped']++;
                continue;
            }

            if (!$allowResend && !empty($order['steadfast_consignment_id'])) {
                $result['skipped']++;
                continue;
            }

            $payload = steadfastBuildPayload($type, $order);
            if (!$payload) {
                $result['failed']++;
                $result['errors'][] = ($order[$config['number_column']] ?? $key) . ' - কাস্টমার/ঠিকানা/ফোন তথ্য অসম্পূর্ণ';
                continue;
            }

            $prepared[] = [
                'type' => $type,
                'id' => $id,
                'invoice' => $payload['invoice'],
                'payload' => $payload,
            ];
        }

        if (!$prepared) {
            return $result;
        }

        foreach (array_chunk($prepared, 500) as $chunk) {
            if (count($chunk) === 1) {
                $item = $chunk[0];
                $api = steadfastRequest('POST', '/create_order', $item['payload']);
                $consignment = $api['data']['consignment'] ?? null;

                if ($api['ok'] && is_array($consignment) && !empty($consignment['consignment_id'])) {
                    steadfastMarkSent(
                        $pdo,
                        $item['type'],
                        $item['id'],
                        $consignment['consignment_id'],
                        (string)($consignment['tracking_code'] ?? ''),
                        (string)($consignment['status'] ?? 'in_review')
                    );
                    $result['sent']++;
                } else {
                    $result['failed']++;
                    $result['errors'][] = $item['invoice'] . ' - ' . ($api['error'] ?: 'Steadfast send failed');
                }
                continue;
            }

            $payloads = array_map(fn($item) => $item['payload'], $chunk);
            $api = steadfastRequest('POST', '/create_order/bulk-order', ['data' => json_encode($payloads, JSON_UNESCAPED_UNICODE)]);
            $rows = [];
            if (isset($api['data']['data']) && is_array($api['data']['data'])) {
                $rows = $api['data']['data'];
            } elseif (is_array($api['data']) && array_is_list($api['data'])) {
                $rows = $api['data'];
            }

            $byInvoice = [];
            foreach ($chunk as $item) {
                $byInvoice[$item['invoice']] = $item;
            }

            if (!$api['ok'] || !$rows) {
                foreach ($chunk as $item) {
                    $result['failed']++;
                    $result['errors'][] = $item['invoice'] . ' - ' . ($api['error'] ?: 'Steadfast bulk send failed');
                }
                continue;
            }

            foreach ($rows as $row) {
                $invoice = (string)($row['invoice'] ?? '');
                $item = $byInvoice[$invoice] ?? null;
                if (!$item) {
                    continue;
                }

                if (($row['status'] ?? '') === 'success' && !empty($row['consignment_id'])) {
                    steadfastMarkSent(
                        $pdo,
                        $item['type'],
                        $item['id'],
                        $row['consignment_id'],
                        (string)($row['tracking_code'] ?? ''),
                        'in_review'
                    );
                    $result['sent']++;
                } else {
                    $result['failed']++;
                    $result['errors'][] = $invoice . ' - Steadfast item rejected';
                }
                unset($byInvoice[$invoice]);
            }

            foreach ($byInvoice as $item) {
                $result['failed']++;
                $result['errors'][] = $item['invoice'] . ' - response item missing';
            }
        }

        return $result;
    }

    function steadfastCourierStatusLabel(?string $status): string
    {
        $labels = [
            'in_review' => 'রিভিউতে',
            'pending' => 'কুরিয়ারে পেন্ডিং',
            'delivered_approval_pending' => 'ডেলিভারি অনুমোদন অপেক্ষায়',
            'partial_delivered_approval_pending' => 'আংশিক ডেলিভারি অনুমোদন অপেক্ষায়',
            'cancelled_approval_pending' => 'ক্যানসেল অনুমোদন অপেক্ষায়',
            'unknown_approval_pending' => 'স্ট্যাটাস যাচাই অপেক্ষায়',
            'delivered' => 'ডেলিভার্ড',
            'partial_delivered' => 'আংশিক ডেলিভার্ড',
            'cancelled' => 'ক্যানসেল/রিটার্ন',
            'hold' => 'হোল্ড',
            'unknown' => 'অজানা',
        ];
        return $labels[$status ?? ''] ?? ($status ?: '—');
    }

    function steadfastApplyResellStatus(PDO $pdo, int $orderId, string $newStatus): void
    {
        // NOTE 08: use the same idempotent shortfall/profit/refund transition
        // logic as manual admin status changes.
        resellApplyOrderStatusFinancials($pdo, $orderId, $newStatus);
    }

    function steadfastApplyAffiliateStatus(PDO $pdo, int $orderId, string $newStatus): void
    {
        $stmt = $pdo->prepare('SELECT * FROM affiliate_orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || $order['status'] === $newStatus) {
            return;
        }

        $pdo->prepare('UPDATE affiliate_orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $orderId]);

        $affiliatorId = (int)$order['affiliator_id'];
        $commission = (float)$order['commission_amount'];

        if ($newStatus === 'delivered' && empty($order['commission_credited'])) {
            $pdo->prepare('UPDATE affiliators SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?')->execute([$commission, $commission, $affiliatorId]);
            $stmt = $pdo->prepare('SELECT balance FROM affiliators WHERE id = ?');
            $stmt->execute([$affiliatorId]);
            $newBalance = $stmt->fetchColumn();
            $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'credit', ?, ?, 'order', ?, ?)")
                ->execute([$affiliatorId, $commission, 'অর্ডার #' . $order['order_number'] . ' থেকে কমিশন', $orderId, $newBalance]);
            $pdo->prepare('UPDATE affiliate_orders SET commission_credited = 1, delivered_at = NOW() WHERE id = ?')->execute([$orderId]);
            if (!empty($order['affiliate_link_id'])) {
                $pdo->prepare('UPDATE affiliate_links SET total_earned = total_earned + ? WHERE id = ?')->execute([$commission, $order['affiliate_link_id']]);
            }
        } elseif ($newStatus !== 'delivered' && !empty($order['commission_credited'])) {
            $pdo->prepare('UPDATE affiliators SET balance = balance - ?, total_earned = total_earned - ? WHERE id = ?')->execute([$commission, $commission, $affiliatorId]);
            $stmt = $pdo->prepare('SELECT balance FROM affiliators WHERE id = ?');
            $stmt->execute([$affiliatorId]);
            $newBalance = $stmt->fetchColumn();
            $pdo->prepare("INSERT INTO affiliator_balance_history (affiliator_id, type, amount, description, reference_type, reference_id, balance_after) VALUES (?, 'debit', ?, ?, 'order', ?, ?)")
                ->execute([$affiliatorId, $commission, 'অর্ডার #' . $order['order_number'] . ' স্ট্যাটাস পরিবর্তন - কমিশন ফেরত', $orderId, $newBalance]);
            $pdo->prepare('UPDATE affiliate_orders SET commission_credited = 0, delivered_at = NULL WHERE id = ?')->execute([$orderId]);
            if (!empty($order['affiliate_link_id'])) {
                $pdo->prepare('UPDATE affiliate_links SET total_earned = total_earned - ? WHERE id = ?')->execute([$commission, $order['affiliate_link_id']]);
            }
        }
    }

    function steadfastApplyCourierStatus(PDO $pdo, string $type, int $orderId, string $courierStatus): void
    {
        $config = steadfastOrderConfig($type);
        if (!$config) {
            return;
        }

        $pdo->prepare("UPDATE `{$config['table']}` SET courier_status = ?, courier_status_updated_at = NOW(), courier_last_sync_at = NOW() WHERE id = ?")
            ->execute([$courierStatus, $orderId]);

        // Only terminal states are allowed to trigger financial/status transitions.
        // Pending/hold/approval-pending remain visible as courier_status without premature balance changes.
        $localStatus = null;
        if ($courierStatus === 'delivered') {
            $localStatus = 'delivered';
        } elseif ($courierStatus === 'cancelled') {
            $localStatus = 'cancelled';
        }

        if ($localStatus === null) {
            return;
        }

        if ($type === 'resell') {
            steadfastApplyResellStatus($pdo, $orderId, $localStatus);
        } elseif ($type === 'affiliate') {
            steadfastApplyAffiliateStatus($pdo, $orderId, $localStatus);
        } else {
            $pdo->prepare("UPDATE `{$config['table']}` SET `{$config['status_column']}` = ? WHERE id = ?")
                ->execute([$localStatus, $orderId]);
        }
    }

    function steadfastSyncOrder(PDO $pdo, string $type, int $orderId, bool $force = false): array
    {
        $order = steadfastGetOrder($pdo, $type, $orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found'];
        }

        if (!$force && !empty($order['courier_last_sync_at'])) {
            $last = strtotime((string)$order['courier_last_sync_at']);
            if ($last && (time() - $last) < 120) {
                return ['ok' => true, 'skipped' => true, 'status' => $order['courier_status'] ?? null];
            }
        }

        $path = null;
        if (!empty($order['steadfast_consignment_id'])) {
            $path = '/status_by_cid/' . rawurlencode((string)$order['steadfast_consignment_id']);
        } elseif (!empty($order['steadfast_tracking_code'])) {
            $path = '/status_by_trackingcode/' . rawurlencode((string)$order['steadfast_tracking_code']);
        }

        if (!$path) {
            return ['ok' => false, 'message' => 'Courier ID নেই'];
        }

        $api = steadfastRequest('GET', $path);
        $status = $api['data']['delivery_status'] ?? null;
        if ($api['ok'] && $status) {
            steadfastApplyCourierStatus($pdo, $type, $orderId, (string)$status);
            return ['ok' => true, 'status' => (string)$status];
        }

        // Still stamp the sync attempt to avoid hammering the API on repeated refreshes.
        $config = steadfastOrderConfig($type);
        $pdo->prepare("UPDATE `{$config['table']}` SET courier_last_sync_at = NOW() WHERE id = ?")->execute([$orderId]);
        return ['ok' => false, 'message' => $api['error'] ?: 'Courier status sync failed'];
    }

    function steadfastSyncOrders(PDO $pdo, array $refs, bool $force = true): array
    {
        $summary = ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        $seen = [];
        foreach ($refs as $ref) {
            $type = (string)($ref['type'] ?? '');
            $id = (int)($ref['id'] ?? 0);
            $key = $type . ':' . $id;
            if ($id <= 0 || isset($seen[$key]) || !steadfastOrderConfig($type)) {
                continue;
            }
            $seen[$key] = true;
            $res = steadfastSyncOrder($pdo, $type, $id, $force);
            if (!empty($res['skipped'])) {
                $summary['skipped']++;
            } elseif (!empty($res['ok'])) {
                $summary['synced']++;
            } else {
                $summary['failed']++;
            }
        }
        return $summary;
    }

    function steadfastAutoSyncRecent(PDO $pdo, string $type, int $limit = 20): void
    {
        if (!steadfastIsReady()) {
            return;
        }

        $config = steadfastOrderConfig($type);
        if (!$config) {
            return;
        }

        $limit = max(1, min(50, $limit));
        $sql = "SELECT id FROM `{$config['table']}`
                WHERE steadfast_consignment_id IS NOT NULL
                  AND steadfast_consignment_id <> ''
                  AND (courier_last_sync_at IS NULL OR courier_last_sync_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE))
                ORDER BY id DESC LIMIT {$limit}";

        try {
            $ids = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                steadfastSyncOrder($pdo, $type, (int)$id, false);
            }
        } catch (PDOException $e) {
            // Migration may not have been applied yet. Keep the order page usable.
        }
    }

    function steadfastRefsFromIds(string $type, array $ids): array
    {
        $refs = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $refs[] = ['type' => $type, 'id' => $id];
            }
        }
        return $refs;
    }

    function steadfastRefsFromMixed(array $values): array
    {
        $refs = [];
        foreach ($values as $value) {
            $parts = explode(':', (string)$value, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $type = $parts[0];
            $id = (int)$parts[1];
            if ($id > 0 && steadfastOrderConfig($type)) {
                $refs[] = ['type' => $type, 'id' => $id];
            }
        }
        return $refs;
    }

    function steadfastFlashSendResult(array $result): void
    {
        $message = "Steadfast: {$result['sent']} টি পাঠানো হয়েছে";
        if (!empty($result['skipped'])) {
            $message .= ", {$result['skipped']} টি স্কিপ";
        }
        if (!empty($result['failed'])) {
            $message .= ", {$result['failed']} টি ব্যর্থ";
            if (!empty($result['errors'][0])) {
                $message .= ' — ' . $result['errors'][0];
            }
        }
        setFlash($result['failed'] > 0 && $result['sent'] === 0 ? 'error' : 'success', $message);
    }

    function steadfastFlashSyncResult(array $result): void
    {
        $message = "Courier status: {$result['synced']} টি sync হয়েছে";
        if (!empty($result['skipped'])) {
            $message .= ", {$result['skipped']} টি recent হওয়ায় skip";
        }
        if (!empty($result['failed'])) {
            $message .= ", {$result['failed']} টি sync ব্যর্থ";
        }
        setFlash($result['failed'] > 0 && $result['synced'] === 0 ? 'error' : 'success', $message);
    }
}
