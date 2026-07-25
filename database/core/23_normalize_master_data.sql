-- Törzsadat-hivatkozások normalizálása és egységes időbélyegek.
--
-- 1. parameter_names  -> parameters                (átnevezés, adatátvétellel)
-- 2. product_attributes -> product_parameters      (attr_name -> parameter_id FK, attr_value -> value)
-- 3. products.unit (szöveg) -> products.unit_id    (a units törzsre mutató FK)
-- 4. minden táblán legyen created_at ÉS updated_at is
--
-- FONTOS: a database/migrate.php minden migrációt minden futtatáskor újra lefuttat,
-- ezért itt minden művelet idempotens. A MariaDB IF [NOT] EXISTS kiterjesztései ehhez
-- elegendők; ahol egy már eldobott táblára kellene hivatkozni (SELECT), ott a
-- lekérdezést information_schema-ellenőrzés mögé tett PREPARE/EXECUTE védi, mert
-- a nem létező tábla a SELECT-ben végleges hiba lenne.

-- ---------------------------------------------------------------------------
-- 1. parameter_names -> parameters
-- ---------------------------------------------------------------------------
-- A parameters táblát a 15-es migráció már létrehozta. Ha a régi tábla még megvan,
-- a benne lévő (esetleg kézzel felvett) neveket átvesszük, majd eldobjuk.
SET @legacy := (SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parameter_names');
SET @sql := IF(@legacy > 0,
    'INSERT IGNORE INTO parameters (name, created_at) SELECT name, created_at FROM parameter_names',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. product_attributes -> product_parameters
-- ---------------------------------------------------------------------------
-- Az attr_name szabad szöveg volt, ezért előbb minden előfordulást felvesszük a
-- törzsbe (így egyetlen paraméter sem veszik el), utána képezzük le id-ra.
SET @legacy := (SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_attributes');

SET @sql := IF(@legacy > 0,
    'INSERT IGNORE INTO parameters (name, created_at)
     SELECT DISTINCT TRIM(attr_name), NOW() FROM product_attributes WHERE TRIM(attr_name) <> ''''',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Az INSERT IGNORE az uniq_product_parameter kulcs miatt a duplikált
-- (termék, paraméter) párokból az elsőt tartja meg.
SET @sql := IF(@legacy > 0,
    'INSERT IGNORE INTO product_parameters (product_id, parameter_id, value, sort_order)
     SELECT pa.product_id, p.id, pa.attr_value, pa.sort_order
     FROM product_attributes pa
     JOIN parameters p ON p.name = TRIM(pa.attr_name)
     ORDER BY pa.id',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. products.unit -> products.unit_id
-- ---------------------------------------------------------------------------
ALTER TABLE products ADD COLUMN IF NOT EXISTS unit_id INT UNSIGNED NULL AFTER category_id;

SET @legacy := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'unit');

-- Ha volt olyan mértékegység a termékeken, ami nincs a törzsben, azt is felvesszük,
-- hogy a leképezés adatvesztés nélküli legyen.
SET @sql := IF(@legacy > 0,
    'INSERT IGNORE INTO units (code, name, sort_order)
     SELECT DISTINCT TRIM(unit), TRIM(unit), 900 FROM products
     WHERE TRIM(COALESCE(unit, '''')) <> ''''
       AND TRIM(unit) NOT IN (SELECT code FROM units)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@legacy > 0,
    'UPDATE products p JOIN units u ON u.code = TRIM(p.unit)
     SET p.unit_id = u.id WHERE p.unit_id IS NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE products DROP COLUMN IF EXISTS unit;
ALTER TABLE products ADD CONSTRAINT fk_products_unit
    FOREIGN KEY IF NOT EXISTS (unit_id) REFERENCES units (id) ON DELETE SET NULL;

-- A régi táblák csak a sikeres adatátvétel után tűnnek el.
DROP TABLE IF EXISTS product_attributes;
DROP TABLE IF EXISTS parameter_names;

-- ---------------------------------------------------------------------------
-- 4. created_at + updated_at minden táblán
-- ---------------------------------------------------------------------------
-- Az újonnan felvett created_at DEFAULT CURRENT_TIMESTAMP-ot kap, hogy a meglévő
-- kód beszúrásai se törjenek el; az updated_at ON UPDATE-tel önműködő.

ALTER TABLE categories             ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE units                  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE parameters             ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE settings               ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE product_images         ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE product_group_prices   ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE product_parameters     ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE product_parameters     ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE product_categories     ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE product_categories     ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE product_links          ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE product_links          ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE order_items            ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE order_items            ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE invoice_items          ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE invoice_items          ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE purchase_order_items   ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE purchase_order_items   ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE incoming_invoice_items ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE incoming_invoice_items ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE stocktaking_items      ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE stocktaking_items      ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- A users.updated_at még a kézi, NULL-olható formában maradt; egységesítjük.
UPDATE users SET updated_at = created_at WHERE updated_at IS NULL;
ALTER TABLE users MODIFY updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- A tételsorok időbélyegét a bizonylat dátumára húzzuk, hogy ne a migráció
-- időpontja legyen a "létrehozás". Csak az induló (migrációkori) értékeket írja át.
UPDATE order_items i JOIN orders o ON o.id = i.order_id
    SET i.created_at = o.created_at, i.updated_at = o.created_at
    WHERE i.created_at > o.created_at;
UPDATE invoice_items i JOIN invoices v ON v.id = i.invoice_id
    SET i.created_at = v.created_at, i.updated_at = v.created_at
    WHERE i.created_at > v.created_at;
UPDATE purchase_order_items i JOIN purchase_orders o ON o.id = i.purchase_order_id
    SET i.created_at = o.created_at, i.updated_at = o.created_at
    WHERE i.created_at > o.created_at;
UPDATE incoming_invoice_items i JOIN incoming_invoices v ON v.id = i.incoming_invoice_id
    SET i.created_at = v.created_at, i.updated_at = v.created_at
    WHERE i.created_at > v.created_at;
UPDATE stocktaking_items i JOIN stocktakings s ON s.id = i.stocktaking_id
    SET i.created_at = s.created_at, i.updated_at = s.created_at
    WHERE i.created_at > s.created_at;
UPDATE product_images i JOIN products p ON p.id = i.product_id
    SET i.updated_at = i.created_at
    WHERE i.updated_at > i.created_at;
