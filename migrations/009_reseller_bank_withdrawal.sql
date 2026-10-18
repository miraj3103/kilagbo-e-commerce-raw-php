-- NOTE 09 - Reseller Bank Withdrawal
-- Run through migrations/009_reseller_bank_withdrawal.php for idempotent execution.

ALTER TABLE `reseller_withdrawals`
    MODIFY `method_type` ENUM('bkash','nagad','rocket','bank') NOT NULL;

ALTER TABLE `reseller_withdrawals`
    ADD COLUMN `bank_name` VARCHAR(150) NULL AFTER `account_name`,
    ADD COLUMN `bank_district` VARCHAR(100) NULL AFTER `bank_name`,
    ADD COLUMN `bank_branch` VARCHAR(150) NULL AFTER `bank_district`;
