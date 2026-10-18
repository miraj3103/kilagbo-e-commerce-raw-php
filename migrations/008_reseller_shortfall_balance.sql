-- Kilagbo NOTE 08 - Reseller zero/below-cost sale shortfall tracking
-- Apply only if these columns do not already exist. The companion PHP migration
-- performs idempotent INFORMATION_SCHEMA checks automatically.

ALTER TABLE `resell_orders`
    ADD COLUMN `shortfall_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `profit`,
    ADD COLUMN `shortfall_deducted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `shortfall_amount`,
    ADD COLUMN `shortfall_refunded` TINYINT(1) NOT NULL DEFAULT 0 AFTER `shortfall_deducted`;
