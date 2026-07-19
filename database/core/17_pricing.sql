-- Akciós ár a termékeken (egyszerű: ha van kitöltve, az az aktív ár)
ALTER TABLE products ADD COLUMN IF NOT EXISTS sale_price DECIMAL(14, 2) NULL AFTER price;

-- Vevőcsoportok (pl. Viszonteladó, VIP, Nagykereskedő)
CREATE TABLE IF NOT EXISTS customer_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_customer_group_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Egy partner (vevő) egy vevőcsoporthoz tartozhat
ALTER TABLE partners ADD COLUMN IF NOT EXISTS customer_group_id INT UNSIGNED NULL AFTER type;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'partners'
      AND CONSTRAINT_NAME = 'fk_partners_customer_group'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE partners ADD CONSTRAINT fk_partners_customer_group FOREIGN KEY (customer_group_id) REFERENCES customer_groups (id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Vevőcsoportos ár termékenként: fix nettó ár + opcionális akciós ár
CREATE TABLE IF NOT EXISTS product_group_prices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    customer_group_id INT UNSIGNED NOT NULL,
    price DECIMAL(14, 2) NOT NULL,
    sale_price DECIMAL(14, 2) NULL,
    UNIQUE KEY uniq_product_group (product_id, customer_group_id),
    CONSTRAINT fk_pgp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pgp_group FOREIGN KEY (customer_group_id) REFERENCES customer_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
