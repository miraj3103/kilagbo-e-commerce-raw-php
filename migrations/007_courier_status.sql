-- Kilagbo NOTE 07: shared Steadfast courier status fields
-- Run against the existing database. Statements are documented here; use the PHP migration for idempotent execution.

ALTER TABLE orders
  ADD COLUMN courier_status VARCHAR(64) NULL,
  ADD COLUMN courier_status_updated_at DATETIME NULL,
  ADD COLUMN courier_last_sync_at DATETIME NULL;

ALTER TABLE wholesale_orders
  ADD COLUMN steadfast_consignment_id VARCHAR(100) NULL,
  ADD COLUMN steadfast_tracking_code VARCHAR(100) NULL,
  ADD COLUMN courier_status VARCHAR(64) NULL,
  ADD COLUMN courier_status_updated_at DATETIME NULL,
  ADD COLUMN courier_last_sync_at DATETIME NULL;

ALTER TABLE resell_orders
  ADD COLUMN steadfast_consignment_id VARCHAR(100) NULL,
  ADD COLUMN steadfast_tracking_code VARCHAR(100) NULL,
  ADD COLUMN courier_status VARCHAR(64) NULL,
  ADD COLUMN courier_status_updated_at DATETIME NULL,
  ADD COLUMN courier_last_sync_at DATETIME NULL;

ALTER TABLE affiliate_orders
  ADD COLUMN steadfast_consignment_id VARCHAR(100) NULL,
  ADD COLUMN steadfast_tracking_code VARCHAR(100) NULL,
  ADD COLUMN tracking_link VARCHAR(500) NULL,
  ADD COLUMN courier_status VARCHAR(64) NULL,
  ADD COLUMN courier_status_updated_at DATETIME NULL,
  ADD COLUMN courier_last_sync_at DATETIME NULL;
