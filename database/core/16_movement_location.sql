-- Tárhely a készletmozgásokon (opcionális): melyik polcra/helyre könyveltük
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS location_id INT UNSIGNED NULL AFTER warehouse_id;

-- FK csak akkor, ha még nincs (MariaDB nem támogatja az ADD CONSTRAINT IF NOT EXISTS-t,
-- ezért feltételesen adjuk hozzá).
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_movements'
      AND CONSTRAINT_NAME = 'fk_sm_location'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE stock_movements ADD CONSTRAINT fk_sm_location FOREIGN KEY (location_id) REFERENCES warehouse_locations (id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
