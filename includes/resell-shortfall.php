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
     * NOTE15 Stage 2. Pure calculation. `deducted` is money ACTUALLY debited,
     * never the desired charge and never the unpaid due amount.
     */
    function resellPartialReconciliation(float $charge, float $paid, float $deducted, float $available): array
    {
        $charge = round(max(0, $charge), 2);
        $paid = round($paid, 2);
        $deducted = round(max(0, $deducted), 2);
        $available = round(max(0, $available), 2);
        if (!is_finite($paid) || $charge <= 0 || $paid <= 0 || $paid >= $charge) {
            throw new InvalidArgumentException('Partial হবে শুধু ডেলিভারি চার্জের কিছু টাকা পরিশোধ করলে (শূন্যের বেশি এবং পুরো চার্জের কম)।');
        }
        $required = round($charge - $paid, 2);
        $refund = round(max(0, $deducted - $required), 2);
        $debit = round(min($available, max(0, $required - $deducted)), 2);
        $actualDeducted = round($deducted - $refund + $debit, 2);
        return [
            'required' => $required,
            'debit' => $debit,
            'refund' => $refund,
            'deducted' => $actualDeducted,
            'due' => round(max(0, $required - $actualDeducted), 2),
        ];
    }

    /**
     * Enter/update Partial using explicitly verified delivery-charge collection.
     * Admin can override an automatically populated amount. Automatic sync must
     * never overwrite the manual value. All changes & balance history are atomic.
     * Returns false only when a courier update is inapplicable/overridden.
     */
    function resellApplyPartialDeliveryPayment(PDO $pdo, int $orderId, float $paid, string $source, bool $courierOnly = false): bool
    {
        if (!is_finite($paid) || $paid < 0) {
            throw new InvalidArgumentException('অবৈধ Partial Amount।');
        }
        $paid = round($paid, 2);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM resell_orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new RuntimeException('অর্ডার পাওয়া যায়নি।');
            }
            if (!in_array((string)$order['status'], ['pending', 'processing', 'shipped', 'partial'], true)) {
                if ($courierOnly) { $pdo->rollBack(); return false; }
                throw new RuntimeException('Delivered/Returned/Cancelled অর্ডারে সরাসরি Partial প্রযোজ্য নয়।');
            }
            if ($courierOnly && ((string)($order['courier_status'] ?? '') !== 'partial_delivered'
                || (string)($order['partial_delivery_source'] ?? '') === 'admin_manual')) {
                $pdo->rollBack();
                return false;
            }
            $charge = round((float)$order['delivery_charge'], 2);
            if ($charge <= 0 || $paid <= 0 || $paid >= $charge) {
                if ($courierOnly) { $pdo->rollBack(); return false; }
                throw new InvalidArgumentException('আদায়কৃত টাকা অবশ্যই ০ টাকার বেশি এবং ডেলিভারি চার্জের কম হতে হবে।');
            }

            // Lock reseller after order. All debit/credit side effects use the
            // same PDO connection and balance history inside this transaction.
            $balStmt = $pdo->prepare('SELECT balance FROM resellers WHERE id = ? FOR UPDATE');
            $balStmt->execute([(int)$order['reseller_id']]);
            $available = $balStmt->fetchColumn();
            if ($available === false) {
                throw new RuntimeException('রিসেলারের ব্যালেন্স অ্যাকাউন্ট পাওয়া যায়নি।');
            }
            $oldPaid = $order['partial_delivery_paid'] === null ? null : round((float)$order['partial_delivery_paid'], 2);
            $oldDeducted = round((float)($order['partial_delivery_deducted'] ?? 0), 2);
            $calc = resellPartialReconciliation($charge, $paid, $oldDeducted, (float)$available);

            if ($calc['refund'] > 0) {
                resellBalanceOnlyCredit($pdo, (int)$order['reseller_id'], $calc['refund'],
                    'অর্ডার #' . $order['order_number'] . ' Partial চার্জ সংশোধন - টাকা ফেরত',
                    'partial_delivery', $orderId);
            }
            if ($calc['debit'] > 0) {
                resellBalanceOnlyDebit($pdo, (int)$order['reseller_id'], $calc['debit'],
                    'অর্ডার #' . $order['order_number'] . ' Partial ডেলিভারি চার্জ ঘাটতি',
                    'partial_delivery', $orderId, true);
            }
            $pdo->prepare("UPDATE resell_orders SET status = 'partial',
                partial_delivery_paid = ?, partial_delivery_deducted = ?, partial_delivery_due = ?,
                partial_delivery_source = ?, partial_delivery_verified_at = NOW(),
                updated_at = NOW() WHERE id = ?")
                ->execute([$paid, $calc['deducted'], $calc['due'], $source, $orderId]);

            // Journal even for zero monetary delta when admin changes an amount.
            if ($oldPaid === null || abs($oldPaid - $paid) > 0.001 || $calc['debit'] > 0 || $calc['refund'] > 0
                || (string)($order['partial_delivery_source'] ?? '') !== $source) {
                $adminId = $source === 'admin_manual' ? (int)($_SESSION['admin_id'] ?? 0) : 0;
                $pdo->prepare('INSERT INTO resell_partial_adjustment_history
                    (order_id, reseller_id, admin_id, source, previous_paid, new_paid,
                     debited, refunded, outstanding_due, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([$orderId, (int)$order['reseller_id'], $adminId > 0 ? $adminId : null,
                        $source, $oldPaid, $paid, $calc['debit'], $calc['refund'], $calc['due']]);
            }

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    function resellApplyManualPartialDelivery(PDO $pdo, int $orderId, float $paid): bool
    {
        return resellApplyPartialDeliveryPayment($pdo, $orderId, $paid, 'admin_manual', false);
    }

    function resellApplyVerifiedPartialDelivery(PDO $pdo, int $orderId, float $paid, string $source): bool
    {
        return resellApplyPartialDeliveryPayment($pdo, $orderId, $paid, $source, true);
    }

    /**
     * Apply one reseller order status transition with all balance side effects.
     * This is shared by admin manual status updates and courier status sync.
     */
    function resellApplyOrderStatusFinancials(PDO $pdo, int $orderId, string $newStatus): void
    {
        $newStatus = $newStatus === 'return' ? 'returned' : $newStatus;
        $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned', 'partial'];
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
            // Selecting Partial manually is safe only after a verified, recorded
            // collection; otherwise courier 'partial_delivered' is ambiguous.
            if ($newStatus === 'partial' && $oldStatus !== 'partial'
                && ($order['partial_delivery_paid'] === null || $order['courier_status'] !== 'partial_delivered')) {
                throw new Exception('Partial করতে অর্ডার বিবরণ পেজ থেকে গ্রাহকের পরিশোধিত ডেলিভারি চার্জ লিখে সেভ করুন।');
            }
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

            // Reverse ONLY NOTE15's courier shortage when switching away from
            // Partial. Existing order shortfall/return-profit logic remains intact.
            if ($oldStatus === 'partial' && $newStatus !== 'partial') {
                $partialDeducted = round((float)($order['partial_delivery_deducted'] ?? 0), 2);
                if ($partialDeducted > 0) {
                    resellBalanceOnlyCredit($pdo, (int)$order['reseller_id'], $partialDeducted,
                        'অর্ডার #' . $order['order_number'] . ' Partial চার্জ ফেরত/স্ট্যাটাস সমন্বয়',
                        'partial_delivery', $orderId);
                }
                $pdo->prepare("UPDATE resell_orders SET partial_delivery_deducted = 0, partial_delivery_due = 0,
                    partial_delivery_paid = NULL, partial_delivery_source = NULL,
                    partial_delivery_verified_at = NULL WHERE id = ?")->execute([$orderId]);
                $pdo->prepare('INSERT INTO resell_partial_adjustment_history
                    (order_id, reseller_id, admin_id, source, previous_paid, new_paid,
                     debited, refunded, outstanding_due, created_at)
                    VALUES (?, ?, NULL, ?, ?, 0, 0, ?, 0, NOW())')
                    ->execute([$orderId, (int)$order['reseller_id'], 'status_clear:' . $newStatus,
                        $order['partial_delivery_paid'], $partialDeducted]);
            }

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
