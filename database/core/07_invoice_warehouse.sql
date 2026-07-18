-- Adds an optional warehouse to sales invoices: when set, invoicing books the
-- line items as stock-out movements from that warehouse.
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS warehouse_id INT UNSIGNED NULL AFTER partner_id;
