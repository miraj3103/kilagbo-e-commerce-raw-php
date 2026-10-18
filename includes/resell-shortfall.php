<?php
/**
 * Kilagbo NOTE 08 - Reseller below-cost/zero-sale balance handling.
 *
 * Shortfall is charged when an order is created and refunded when the order is
 * cancelled/returned. Re-opening a refunded order re-charges the shortfall.
 */

if (!function_exists('resellShortfallAmount')) {
    function resellShortfallAmount(float $originalPrice, float $deliveryCharge, float $customerSaleTotal): float
    {
        return round(max(0, ($originalPrice + $deliveryCharge) - $customerSaleTotal), 2);
    }

    function resellBalanceOnlyDebit(
        PDO $pdo,
        int $resellerId,
        float $amount,
        string $description,
        string $referenceType,
        ?int $referenceId = null,
        bool $requireAvailableBalance = true
    ): float {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            $stmt = $pdo->prepare('SELECT balance FROM resellers WHERE id = ?');
            $stmt->execute([$resellerId]);
            return (float)($stmt->fetchColumn() ?: 0);
        }

        if ($requireAvailableBalance) {
            $stmt = $pdo->prepare('UPDATE resellers SET balance = balance - ? WHERE id = ? AND balance >= ?');
            $stmt->execute([$amount, $resellerId, $amount]);
            if ($stmt->rowCount() !== 1) {
                throw new Exception('এই অর্ডারের ঘাটতি মেটাতে পর্যাপ্ত রিসেলার ব্যালেন্স নেই। প্রয়োজন: ' . formatPrice($amount));
            }
        } else {
            $pdo->prepare('UPDATE resellers SET balance = balance - ? WHERE id = ?')->execute([$amount, $resellerId]);
        }

        $stmt = $pdo->prepare('SELECT balance FROM resellers WHERE id = ?');
        $stmt->execute([$resellerId]);
        $newBalance = (float)($stmt->fetchColumn() ?: 0);

        $pdo->prepare("INSERT INTO reseller_balance_history
            (reseller_id, type, amount, description, reference_type, reference_id, balance_after)
            VALUES (?, 'debit', ?, ?, ?, ?, ?)")
            ->execute([$resellerId, $amount, $description, $referenceType, $referenceId, $newBalance]);

        return $newBalance;
    }

    function resellBalanceOnlyCredit(
        PDO $pdo,
        int $resellerId,
        float $amount,
        string $description,
        string $referenceType,
        ?int $referenceId = null
    ): float {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            $stmt = $pdo->prepare('SELECT balance FROM resellers WHERE id = ?');
            $stmt->execute([$resellerId]);
            return (float)($stmt->fetchColumn() ?: 0);
        }

        $pdo->prepare('UPDATE resellers SET balance = balance + ? WHERE id = ?')->execute([$amount, $resellerId]);

        $stmt = $pdo->prepare('SELECT balance FROM resellers WHERE id = ?');
        $stmt->execute([$resellerId]);
        $newBalance = (float)($stmt->fetchColumn() ?: 0);

        $pdo->prepare("INSERT INTO reseller_balance_history
            (reseller_id, type, amount, description, reference_type, reference_id, balance_after)
            VALUES (?, 'credit', ?, ?, ?, ?, ?)")
            ->execute([$resellerId, $amount, $description, $referenceType, $referenceId, $newBalance]);

        return $newBalance;
    }

    function resellDeductOrderShortfall(PDO $pdo, array $order, float $amount, string $referenceType = 'order'): void
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        resellBalanceOnlyDebit(
            $pdo,
            (int)$order['reseller_id'],
            $amount,
            'অর্ডার #' . $order['order_number'] . ' ঘাটতি ব্যালেন্স থেকে কর্তন',
            $referenceType,
            (int)$order['id'],
            true
        );
    }

    function resellRefundOrderShortfall(PDO $pdo, array $order, float $amount, string $referenceType = 'refund'): void
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return;
        }

        resellBalanceOnlyCredit(
            $pdo,
            (int)$order['reseller_id'],
            $amount,
            'অর্ডার #' . $order['order_number'] . ' ঘাটতি ফেরত',
            $referenceType,
            (int)$order['id']
        );
    }

    /**
     * Apply one reseller order status transition with all balance side effects.
     * This is shared by admin manual status updates and courier status sync.
     */
    function resellApplyOrderStatusFinancials(PDO $pdo, int $orderId, string $newStatus): void
    {
        $newStatus = $newStatus === 'return' ? 'returned' : $newStatus;
        $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new Exception('অবৈধ অর্ডার স্ট্যাটাস');
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM resell_orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new Exception('অর্ডার পাওয়া যায়নি');
            }

            $oldStatus = (string)$order['status'];
            if ($oldStatus === $newStatus) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return;
            }

            $shortfall = round((float)($order['shortfall_amount'] ?? 0), 2);
            $shortfallDeducted = !empty($order['shortfall_deducted']);
            $shortfallRefunded = !empty($order['shortfall_refunded']);
            $profit = round((float)$order['profit'], 2);
            $profitCredited = !empty($order['profit_credited']);
            $returnDeducted = !empty($order['is_return_deducted']);

            $oldTerminal = in_array($oldStatus, ['cancelled', 'returned'], true);
            $newTerminal = in_array($newStatus, ['cancelled', 'returned'], true);

            // If a returned order is re-opened/moved elsewhere, first refund the
            // one-time return delivery charge so the order can be re-evaluated cleanly.
            if ($oldStatus === 'returned' && $newStatus !== 'returned' && $returnDeducted) {
                addResellerBalance(
                    $order['reseller_id'],
                    $order['delivery_charge'],
                    'অর্ডার #' . $order['order_number'] . ' স্ট্যাটাস পরিবর্তন - ডেলিভারি চার্জ রিফান্ড',
                    'order',
                    $orderId
                );
                $returnDeducted = false;
            }

            // Leaving delivered reverses only a previously credited positive profit.
            if ($oldStatus === 'delivered' && $newStatus !== 'delivered' && $profitCredited && $profit > 0) {
                deductResellerBalance(
                    $order['reseller_id'],
                    $profit,
                    'অর্ডার #' . $order['order_number'] . ' স্ট্যাটাস পরিবর্তন - প্রফিট ফেরত',
                    'order',
                    $orderId
                );
                $profitCredited = false;
            }

            // Cancellation/return refunds an active below-cost shortfall exactly once.
            if ($newTerminal && !$oldTerminal && $shortfall > 0 && $shortfallDeducted && !$shortfallRefunded) {
                resellRefundOrderShortfall($pdo, $order, $shortfall, 'refund');
                $shortfallRefunded = true;
            }

            // Re-opening a cancelled/returned shortfall order charges it again.
            if (!$newTerminal && $oldTerminal && $shortfall > 0 && (!$shortfallDeducted || $shortfallRefunded)) {
                resellDeductOrderShortfall($pdo, $order, $shortfall, 'order');
                $shortfallDeducted = true;
                $shortfallRefunded = false;
            }

            // Delivered orders only credit positive profit. Negative profit has already
            // been covered by the shortfall deduction made at order creation/re-open.
            if ($newStatus === 'delivered' && $profit > 0 && !$profitCredited) {
                addResellerBalance(
                    $order['reseller_id'],
                    $profit,
                    'অর্ডার #' . $order['order_number'] . ' থেকে প্রফিট',
                    'order',
                    $orderId
                );
                $profitCredited = true;
            }

            // Returned order: charge delivery once after the shortfall was refunded.
            if ($newStatus === 'returned' && !$returnDeducted) {
                deductResellerBalance(
                    $order['reseller_id'],
                    $order['delivery_charge'],
                    'অর্ডার #' . $order['order_number'] . ' রিটার্ন - ডেলিভারি চার্জ কর্তন',
                    'order',
                    $orderId
                );
                $returnDeducted = true;
            }

            $deliveredAt = $newStatus === 'delivered' ? 'NOW()' : 'NULL';
            $sql = "UPDATE resell_orders
                    SET status = ?,
                        profit_credited = ?,
                        shortfall_deducted = ?,
                        shortfall_refunded = ?,
                        is_return_deducted = ?,
                        delivered_at = {$deliveredAt},
                        updated_at = NOW()
                    WHERE id = ?";
            $pdo->prepare($sql)->execute([
                $newStatus,
                $profitCredited ? 1 : 0,
                $shortfallDeducted ? 1 : 0,
                $shortfallRefunded ? 1 : 0,
                $returnDeducted ? 1 : 0,
                $orderId,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reconcile financial effects when admin changes the delivery charge.
     * Caller must already hold a FOR UPDATE lock and an active transaction.
     */
    function resellReconcileOrderPricing(PDO $pdo, array $order, float $newProfit, float $newShortfall): array
    {
        $orderId = (int)$order['id'];
        $oldProfit = round((float)$order['profit'], 2);
        $oldShortfall = round((float)($order['shortfall_amount'] ?? 0), 2);
        $profitCredited = !empty($order['profit_credited']);
        $shortfallDeducted = !empty($order['shortfall_deducted']);
        $shortfallRefunded = !empty($order['shortfall_refunded']);
        $status = (string)$order['status'];
        $terminal = in_array($status, ['cancelled', 'returned'], true);

        // Neutralize old positive profit effect, if any.
        if ($profitCredited && $oldProfit > 0) {
            deductResellerBalance(
                $order['reseller_id'],
                $oldProfit,
                'অর্ডার #' . $order['order_number'] . ' ডেলিভারি চার্জ পরিবর্তন - পুরনো প্রফিট সমন্বয়',
                'adjustment',
                $orderId
            );
            $profitCredited = false;
        }

        // Neutralize an active old shortfall before applying the new one.
        if ($oldShortfall > 0 && $shortfallDeducted && !$shortfallRefunded) {
            resellBalanceOnlyCredit(
                $pdo,
                (int)$order['reseller_id'],
                $oldShortfall,
                'অর্ডার #' . $order['order_number'] . ' ডেলিভারি চার্জ পরিবর্তন - পুরনো ঘাটতি সমন্বয়',
                'adjustment',
                $orderId
            );
            $shortfallRefunded = true;
        }

        $newProfit = round($newProfit, 2);
        $newShortfall = round(max(0, $newShortfall), 2);

        // Active/delivered orders must carry the new shortfall immediately.
        if (!$terminal && $newShortfall > 0) {
            $tempOrder = $order;
            $tempOrder['shortfall_amount'] = $newShortfall;
            resellDeductOrderShortfall($pdo, $tempOrder, $newShortfall, 'adjustment');
            $shortfallDeducted = true;
            $shortfallRefunded = false;
        } elseif ($terminal && $newShortfall > 0) {
            // Terminal orders keep the shortfall in refunded state. If reopened,
            // the shared status handler will charge it again.
            $shortfallRefunded = true;
        } else {
            $shortfallDeducted = false;
            $shortfallRefunded = false;
        }

        if ($status === 'delivered' && $newProfit > 0) {
            addResellerBalance(
                $order['reseller_id'],
                $newProfit,
                'অর্ডার #' . $order['order_number'] . ' ডেলিভারি চার্জ পরিবর্তন - নতুন প্রফিট সমন্বয়',
                'adjustment',
                $orderId
            );
            $profitCredited = true;
        }

        return [
            'profit_credited' => $profitCredited ? 1 : 0,
            'shortfall_deducted' => $shortfallDeducted ? 1 : 0,
            'shortfall_refunded' => $shortfallRefunded ? 1 : 0,
        ];
    }
}
