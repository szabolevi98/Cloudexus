-- Barcode support and minimum stock level (reorder alert threshold) for products.
ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode VARCHAR(64) NULL AFTER sku;
ALTER TABLE products ADD COLUMN IF NOT EXISTS min_stock DECIMAL(14, 3) NOT NULL DEFAULT 0 AFTER price;
