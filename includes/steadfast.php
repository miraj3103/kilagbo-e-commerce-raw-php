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

    /**
     * Steadfast 'invoice' must be UNIQUE and limited to letters/digits/-/_;
     * product name alone may repeat or contain Bangla. Display an ASCII product
     * slug + the unique existing order reference, keeping a reliable cross-reference.
     * The exact, UTF-8 product names are also sent as item_description.
     */
    function steadfastResellCourierFields(PDO $pdo, int $orderId, string $orderNumber): array
    {
        $stmt = $pdo->prepare('SELECT product_name, quantity FROM resell_order_items WHERE order_id = ? ORDER BY id');
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            return ['invoice' => $orderNumber, 'item_description' => null, 'total_lot' => null];
        }
        $itemParts = [];
        $lots = 0;
        foreach ($items as $item) {
            $productName = trim((string)($item['product_name'] ?? ''));
            $quantity = max(1, (int)($item['quantity'] ?? 1));
            $lots += $quantity;
            if ($productName !== '') {
                $itemParts[] = $productName . ($quantity > 1 ? ' x' . $quantity : '');
            }
        }
        $firstName = trim((string)($items[0]['product_name'] ?? ''));
        $asciiName = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $firstName);
        if (!is_string($asciiName) || $asciiName === '') $asciiName = $firstName;
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $asciiName);
        $slug = trim((string)$slug, '-');
        if ($slug === '') $slug = 'Product';
        $slug = substr($slug, 0, 40);
        // Suffix disambiguates two orders with the same product name.
        $invoice = $slug . '-' . $orderNumber;
        return [
            'invoice' => $invoice,
            'item_description' => $itemParts ? mb_substr(implode(' | ', $itemParts), 0, 250) : null,
            'total_lot' => $lots ?: null,
        ];
    }

    function steadfastBuildPayload(string $type, array $order, ?PDO $pdo = null): ?array
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

        $itemDescription = null;
        $totalLot = null;
        if ($type === 'resell' && $pdo !== null) {
            $fields = steadfastResellCourierFields($pdo, (int)$order['id'], $invoice);
            $invoice = $fields['invoice'];
            $itemDescription = $fields['item_description'];
            $totalLot = $fields['total_lot'];
            // The stored reseller_price is ITEMS ONLY, not the customer bill.
            // COD customers must pay the single shipment delivery charge too.
            if ((int)($order['pricing_version'] ?? 1) >= 2
                && ($order['payment_method'] ?? 'cod') === 'cod') {
                $amount += (float)($order['delivery_charge'] ?? 0);
            }
        }

        $payload = [
            'invoice' => $invoice,
            'recipient_name' => mb_substr($name, 0, 100),
            'recipient_phone' => $phone,
            'recipient_address' => mb_substr($address, 0, 250),
            'cod_amount' => max(0, $amount),
            'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
        ];
        if ($type === 'resell') {
            if ($itemDescription !== null) $payload['item_description'] = $itemDescription;
            if ($totalLot !== null) $payload['total_lot'] = $totalLot;
        }
        return $payload;
    }

    /**
     * A valid public /tl/ link contains an opaque token that CANNOT be computed
     * from the tracking_code. Never fabricate /t/{tracking_code} as a link.
     * Accept a direct URL only when Steadfast explicitly provides the token.
     */
    function steadfastTrackingUrl(?string $candidate): ?string
    {
        if ($candidate === null) return null;
        $candidate = trim($candidate);
        $parsed = parse_url($candidate);
        if (!is_array($parsed) || strtolower((string)($parsed['scheme'] ?? '')) !== 'https'
            || !in_array(strtolower((string)($parsed['host'] ?? '')), ['steadfast.com.bd', 'www.steadfast.com.bd'], true)
            || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['port'])
            || isset($parsed['query']) || isset($parsed['fragment'])
            || !preg_match('~\A/tl/[A-Za-z0-9_-]{8,180}/?\z~D', (string)($parsed['path'] ?? ''))) {
            return null;
        }
        return $candidate;
    }

    function steadfastTrackingLinkFromResponse(array $response): ?string
    {
        foreach (['tracking_link', 'tracking_url', 'public_tracking_link', 'public_tracking_url'] as $field) {
            if (isset($response[$field]) && is_string($response[$field])) {
                $url = steadfastTrackingUrl($response[$field]);
                if ($url !== null) return $url;
            }
        }
        return null;
    }

    /**
     * Only an explicitly identified, paid DELIVERY CHARGE can be used for
     * reseller deductions. Never treat COD amount, courier fee, payment payout,
     * or the status partial_delivered as proof of what the customer paid.
     */
    function steadfastVerifiedDeliveryChargePaid(array $data): ?float
    {
        foreach (['collected_delivery_charge', 'paid_delivery_charge', 'delivery_charge_collected'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $raw = $data[$field];
            if ((is_int($raw) || is_float($raw) || is_string($raw)) && is_numeric($raw)) {
                $amount = (float)$raw;
                if (is_finite($amount) && $amount >= 0) {
                    return round($amount, 2);
                }
            }
            return null;
        }
        return null;
    }

    function steadfastMarkSent(PDO $pdo, string $type, int $orderId, $consignmentId, string $trackingCode, string $courierStatus = 'in_review', ?string $confirmedTrackingLink = null): void
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
            $params = [$consignmentId, $trackingCode, $courierStatus, $trackingCode];
        } elseif ($type === 'resell') {
            // Replace known fabricated /t/code links ONLY when a real /tl/ URL
            // is explicitly supplied by courier. Never overwrite an admin's /tl/ link.
            $link = steadfastTrackingUrl($confirmedTrackingLink);
            if ($link !== null) {
                $sql .= ", tracking_link = CASE WHEN tracking_link IS NULL OR tracking_link = '' OR tracking_link LIKE 'https://steadfast.com.bd/t/%' THEN ? ELSE tracking_link END";
                $params = [$consignmentId, $trackingCode, $courierStatus, $link];
            } else {
                $params = [$consignmentId, $trackingCode, $courierStatus];
            }
        } elseif ($type === 'affiliate') {
            // Keep affiliate behavior otherwise unchanged; no fabricated links.
            $link = steadfastTrackingUrl($confirmedTrackingLink);
            if ($link !== null) {
                $sql .= ", tracking_link = COALESCE(NULLIF(tracking_link, ''), ?)";
                $params = [$consignmentId, $trackingCode, $courierStatus, $link];
            } else {
                $params = [$consignmentId, $trackingCode, $courierStatus];
            }
        } else {
            $params = [$consignmentId, $trackingCode, $courierStatus];
        }

        $params[] = $orderId;

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

            $payload = steadfastBuildPayload($type, $order, $pdo);
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
                        (string)($consignment['status'] ?? 'in_review'),
                        steadfastTrackingLinkFromResponse($consignment)
                            ?? steadfastTrackingLinkFromResponse((array)($api['data'] ?? []))
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
                        'in_review',
                        steadfastTrackingLinkFromResponse($row)
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

    function steadfastApplyCourierStatus(PDO $pdo, string $type, int $orderId, string $courierStatus, array $apiPayload = []): void
    {
        $config = steadfastOrderConfig($type);
        if (!$config) {
            return;
        }

        $courierStatus = strtolower(trim($courierStatus));
        $known = ['in_review', 'pending', 'hold', 'delivered_approval_pending',
            'partial_delivered_approval_pending', 'cancelled_approval_pending',
            'unknown_approval_pending', 'delivered', 'partial_delivered', 'cancelled', 'unknown'];
        if (!in_array($courierStatus, $known, true)) {
            throw new RuntimeException('Unknown courier status: ' . $courierStatus);
        }

        $pdo->prepare("UPDATE `{$config['table']}` SET courier_status = ?, courier_status_updated_at = NOW(), courier_last_sync_at = NOW() WHERE id = ?")
            ->execute([$courierStatus, $orderId]);

        // A courier partial delivery means SOME GOODS may have been accepted.
        // It does NOT prove a partial delivery-charge payment. Set the local
        // Partial state and deduct only if an authenticated response expressly
        // identifies the customer's paid delivery charge.
        if ($type === 'resell' && $courierStatus === 'partial_delivered') {
            $paid = steadfastVerifiedDeliveryChargePaid($apiPayload);
            if ($paid !== null) {
                resellApplyVerifiedPartialDelivery($pdo, $orderId, $paid, 'steadfast:verified_delivery_charge');
            }
            return;
        }

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
            // Manual Partial is an explicit financial override. Continue syncing
            // courier_status but do not silently reverse an admin's adjustment.
            $st = $pdo->prepare('SELECT status, partial_delivery_source FROM resell_orders WHERE id = ?');
            $st->execute([$orderId]);
            $current = $st->fetch(PDO::FETCH_ASSOC);
            if ($current && $current['status'] === 'partial' &&
                ($current['partial_delivery_source'] ?? '') === 'admin_manual') {
                return;
            }
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
            // Only self-heal with a confirmed courier-provided public /tl/ URL.
            if ($type === 'resell') {
                $url = steadfastTrackingLinkFromResponse((array)($api['data'] ?? []));
                if ($url !== null) {
                    $pdo->prepare("UPDATE resell_orders SET tracking_link = ? WHERE id = ? AND (tracking_link IS NULL OR tracking_link = '' OR tracking_link LIKE 'https://steadfast.com.bd/t/%')")
                        ->execute([$url, $orderId]);
                }
            }
            steadfastApplyCourierStatus($pdo, $type, $orderId, (string)$status, $api['data']);
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
