-- Egy partnernek több szerkezetes cím is tartozhat (ország, város, irányítószám,
-- utca-házszám egybe, opcionális megjegyzés pl. emelet/ajtó)
CREATE TABLE IF NOT EXISTS partner_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id INT UNSIGNED NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Magyarország',
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    street VARCHAR(255) NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_partner_addresses_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rendelésnél kiválasztható szállítási/számlázási cím a partner cím-listájából,
-- plusz tetszőleges szállítási és fizetési (utánvét stb.) költség
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address_id INT UNSIGNED NULL AFTER partner_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS billing_address_id INT UNSIGNED NULL AFTER shipping_address_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(14, 2) NOT NULL DEFAULT 0 AFTER total_amount;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_cost DECIMAL(14, 2) NOT NULL DEFAULT 0 AFTER shipping_cost;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND CONSTRAINT_NAME = 'fk_orders_shipping_address'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_shipping_address FOREIGN KEY (shipping_address_id) REFERENCES partner_addresses (id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND CONSTRAINT_NAME = 'fk_orders_billing_address'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_billing_address FOREIGN KEY (billing_address_id) REFERENCES partner_addresses (id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
