-- Adatok nyelvesítése OpenCart-mintára: a fordítható szövegek külön
-- *_description táblákba kerülnek, nyelvenként egy sorral.
--
--  1. languages törzs + alapnyelv a settings-ben
--  2. description táblák
--  3. a meglévő szövegek átvétele az alapnyelvre
--  4. a szállított törzsadatok (mértékegység, paraméter) neve magyarul és angolul
--  5. bizonylat-tételsorok névmásolata (a kiállított bizonylat ne változzon)
--  6. product_parameters nyelvenkénti érték
--  7. categories.is_active
--  8. az átkerült oszlopok és a rájuk tett UNIQUE kulcsok eltávolítása
--
-- Mint minden migráció, ez is idempotens: a migrate.php mindent újrafuttat.
-- A 12-es és 15-es migráció ezért már nem seedeli a units.name / parameters.name
-- értékeket — az a fordítás bevezetése után nem létező oszlopra hivatkozna.

-- ---------------------------------------------------------------------------
-- 1. Nyelvek
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS languages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    code VARCHAR(5) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_language_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A kód a src/Language/<kód>/ mappákkal egyezik, így a felület fordítása is illeszkedik.
INSERT IGNORE INTO languages (name, code, sort_order) VALUES
    ('Magyar', 'hu', 10),
    ('English', 'en', 20);

INSERT IGNORE INTO settings (setting_key, setting_value, updated_at)
VALUES ('language.default', 'hu', NOW());

SET @def_lang := (
    SELECT l.id FROM languages l
    JOIN settings s ON s.setting_key = 'language.default' AND s.setting_value = l.code
    LIMIT 1
);
SET @def_lang := COALESCE(@def_lang, (SELECT MIN(id) FROM languages));
SET @hu_lang := (SELECT id FROM languages WHERE code = 'hu');
SET @en_lang := (SELECT id FROM languages WHERE code = 'en');

-- ---------------------------------------------------------------------------
-- 2. Fordítás-táblák
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_description (
    product_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    short_description VARCHAR(255) NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, language_id),
    KEY idx_pd_name (name),
    CONSTRAINT fk_pd_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_description (
    category_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id, language_id),
    KEY idx_cd_name (name),
    CONSTRAINT fk_cd_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_cd_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unit_description (
    unit_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    name VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (unit_id, language_id),
    CONSTRAINT fk_ud_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE CASCADE,
    CONSTRAINT fk_ud_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A paraméternév nyelven belül egyedi (a régi uniq_param_name ezt váltja ki).
CREATE TABLE IF NOT EXISTS parameter_description (
    parameter_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (parameter_id, language_id),
    UNIQUE KEY uniq_parameter_name_per_language (language_id, name),
    CONSTRAINT fk_prd_parameter FOREIGN KEY (parameter_id) REFERENCES parameters (id) ON DELETE CASCADE,
    CONSTRAINT fk_prd_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. A meglévő szövegek átvétele az alapnyelvre
-- ---------------------------------------------------------------------------
-- Csak akkor fut, ha a régi oszlop még megvan; egy nem létező oszlopra
-- hivatkozó SELECT végleges hiba lenne, ezért PREPARE/EXECUTE mögé kerül.
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'name');
SET @sql := IF(@has > 0,
    'INSERT IGNORE INTO product_description (product_id, language_id, name, short_description, description)
     SELECT id, @def_lang, name, short_description, description FROM products',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'name');
SET @sql := IF(@has > 0,
    'INSERT IGNORE INTO category_description (category_id, language_id, name)
     SELECT id, @def_lang, name FROM categories',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' AND COLUMN_NAME = 'name');
SET @sql := IF(@has > 0,
    'INSERT IGNORE INTO unit_description (unit_id, language_id, name)
     SELECT id, @def_lang, name FROM units',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parameters' AND COLUMN_NAME = 'name');
SET @sql := IF(@has > 0,
    'INSERT IGNORE INTO parameter_description (parameter_id, language_id, name)
     SELECT id, @def_lang, name FROM parameters',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 4. A szállított törzsadatok neve magyarul és angolul
-- ---------------------------------------------------------------------------
-- Friss telepítésen a 15-es migráció csak a szerkezetet hozza létre, ezért a
-- 14 alapértelmezett paramétert itt vesszük fel (rögzített id-kal, hogy a
-- névhez rendelés kiszámítható legyen). Meglévő telepítésen a sorok már
-- megvannak, így az INSERT IGNORE nem csinál semmit.
SET @cnt := (SELECT COUNT(*) FROM parameters);
SET @sql := IF(@cnt = 0,
    'INSERT IGNORE INTO parameters (id, created_at) VALUES
       (1,NOW()),(2,NOW()),(3,NOW()),(4,NOW()),(5,NOW()),(6,NOW()),(7,NOW()),
       (8,NOW()),(9,NOW()),(10,NOW()),(11,NOW()),(12,NOW()),(13,NOW()),(14,NOW())',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Magyar nevek (csak a hiányzókat pótolja)
INSERT IGNORE INTO parameter_description (parameter_id, language_id, name)
SELECT m.id, @hu_lang, m.hu
FROM (
    SELECT 1 AS id, 'Gyártó' AS hu UNION ALL
    SELECT 2, 'Márka' UNION ALL
    SELECT 3, 'Garancia' UNION ALL
    SELECT 4, 'Származási ország' UNION ALL
    SELECT 5, 'Szín' UNION ALL
    SELECT 6, 'Méret' UNION ALL
    SELECT 7, 'Anyag' UNION ALL
    SELECT 8, 'Tömeg' UNION ALL
    SELECT 9, 'Teljesítmény' UNION ALL
    SELECT 10, 'Feszültség' UNION ALL
    SELECT 11, 'Energiaosztály' UNION ALL
    SELECT 12, 'Kiszerelés' UNION ALL
    SELECT 13, 'Modell' UNION ALL
    SELECT 14, 'Típus'
) m
JOIN parameters p ON p.id = m.id
WHERE @hu_lang IS NOT NULL;

-- Angol nevek: a magyar név alapján párosítva, így meglévő telepítésen is
-- megtalálja a saját id-jait (nem a fenti rögzített id-kra épül).
INSERT IGNORE INTO parameter_description (parameter_id, language_id, name)
SELECT d.parameter_id, @en_lang, m.en
FROM parameter_description d
JOIN (
    SELECT 'Gyártó' AS hu, 'Manufacturer' AS en UNION ALL
    SELECT 'Márka', 'Brand' UNION ALL
    SELECT 'Garancia', 'Warranty' UNION ALL
    SELECT 'Származási ország', 'Country of origin' UNION ALL
    SELECT 'Szín', 'Colour' UNION ALL
    SELECT 'Méret', 'Size' UNION ALL
    SELECT 'Anyag', 'Material' UNION ALL
    SELECT 'Tömeg', 'Weight' UNION ALL
    SELECT 'Teljesítmény', 'Power' UNION ALL
    SELECT 'Feszültség', 'Voltage' UNION ALL
    SELECT 'Energiaosztály', 'Energy class' UNION ALL
    SELECT 'Kiszerelés', 'Packaging' UNION ALL
    SELECT 'Modell', 'Model' UNION ALL
    SELECT 'Típus', 'Type'
) m ON m.hu = d.name
WHERE d.language_id = @hu_lang AND @en_lang IS NOT NULL;

-- Mértékegységek: a kód nem fordítható, így az a stabil párosítási kulcs.
INSERT IGNORE INTO unit_description (unit_id, language_id, name)
SELECT u.id, l.id, m.name
FROM (
    SELECT 'db' AS code, 'hu' AS lang, 'darab' AS name UNION ALL SELECT 'db', 'en', 'piece' UNION ALL
    SELECT 'doboz', 'hu', 'doboz' UNION ALL SELECT 'doboz', 'en', 'box' UNION ALL
    SELECT 'csomag', 'hu', 'csomag' UNION ALL SELECT 'csomag', 'en', 'pack' UNION ALL
    SELECT 'szett', 'hu', 'szett' UNION ALL SELECT 'szett', 'en', 'set' UNION ALL
    SELECT 'karton', 'hu', 'karton' UNION ALL SELECT 'karton', 'en', 'carton' UNION ALL
    SELECT 'raklap', 'hu', 'raklap' UNION ALL SELECT 'raklap', 'en', 'pallet' UNION ALL
    SELECT 'zsak', 'hu', 'zsák' UNION ALL SELECT 'zsak', 'en', 'bag' UNION ALL
    SELECT 'palack', 'hu', 'palack' UNION ALL SELECT 'palack', 'en', 'bottle' UNION ALL
    SELECT 'par', 'hu', 'pár' UNION ALL SELECT 'par', 'en', 'pair' UNION ALL
    SELECT 'tekercs', 'hu', 'tekercs' UNION ALL SELECT 'tekercs', 'en', 'roll' UNION ALL
    SELECT 'kg', 'hu', 'kilogramm' UNION ALL SELECT 'kg', 'en', 'kilogram' UNION ALL
    SELECT 'g', 'hu', 'gramm' UNION ALL SELECT 'g', 'en', 'gram' UNION ALL
    SELECT 'l', 'hu', 'liter' UNION ALL SELECT 'l', 'en', 'litre' UNION ALL
    SELECT 'ml', 'hu', 'milliliter' UNION ALL SELECT 'ml', 'en', 'millilitre' UNION ALL
    SELECT 'm', 'hu', 'méter' UNION ALL SELECT 'm', 'en', 'metre' UNION ALL
    SELECT 'cm', 'hu', 'centiméter' UNION ALL SELECT 'cm', 'en', 'centimetre' UNION ALL
    SELECT 'm2', 'hu', 'négyzetméter' UNION ALL SELECT 'm2', 'en', 'square metre' UNION ALL
    SELECT 'm3', 'hu', 'köbméter' UNION ALL SELECT 'm3', 'en', 'cubic metre' UNION ALL
    SELECT 'ora', 'hu', 'óra' UNION ALL SELECT 'ora', 'en', 'hour' UNION ALL
    SELECT 'alkalom', 'hu', 'alkalom' UNION ALL SELECT 'alkalom', 'en', 'occasion'
) m
JOIN units u ON u.code = m.code
JOIN languages l ON l.code = m.lang;

-- ---------------------------------------------------------------------------
-- 5. Bizonylat-tételsorok névmásolata
-- ---------------------------------------------------------------------------
-- A kiállított bizonylat tartalma nem változhat utólag sem átnevezéstől, sem
-- nyelvváltástól, ezért a tételsor rögzíti a termék akkori nevét, cikkszámát
-- és mértékegységét. A készletmozgás és a leltár szándékosan élőben oldja fel:
-- azok belső üzemi nyilvántartások, nem kiadott dokumentumok.
ALTER TABLE order_items            ADD COLUMN IF NOT EXISTS product_name VARCHAR(200) NULL AFTER product_id;
ALTER TABLE order_items            ADD COLUMN IF NOT EXISTS product_sku VARCHAR(64) NULL AFTER product_name;
ALTER TABLE order_items            ADD COLUMN IF NOT EXISTS unit_code VARCHAR(16) NULL AFTER product_sku;
ALTER TABLE invoice_items          ADD COLUMN IF NOT EXISTS product_name VARCHAR(200) NULL AFTER product_id;
ALTER TABLE invoice_items          ADD COLUMN IF NOT EXISTS product_sku VARCHAR(64) NULL AFTER product_name;
ALTER TABLE invoice_items          ADD COLUMN IF NOT EXISTS unit_code VARCHAR(16) NULL AFTER product_sku;
ALTER TABLE purchase_order_items   ADD COLUMN IF NOT EXISTS product_name VARCHAR(200) NULL AFTER product_id;
ALTER TABLE purchase_order_items   ADD COLUMN IF NOT EXISTS product_sku VARCHAR(64) NULL AFTER product_name;
ALTER TABLE purchase_order_items   ADD COLUMN IF NOT EXISTS unit_code VARCHAR(16) NULL AFTER product_sku;
ALTER TABLE incoming_invoice_items ADD COLUMN IF NOT EXISTS product_name VARCHAR(200) NULL AFTER product_id;
ALTER TABLE incoming_invoice_items ADD COLUMN IF NOT EXISTS product_sku VARCHAR(64) NULL AFTER product_name;
ALTER TABLE incoming_invoice_items ADD COLUMN IF NOT EXISTS unit_code VARCHAR(16) NULL AFTER product_sku;

-- Visszatöltés a már felvett alapnyelvi nevekből (a 3. lépés után).
UPDATE order_items i
    JOIN products p ON p.id = i.product_id
    LEFT JOIN product_description d ON d.product_id = p.id AND d.language_id = @def_lang
    LEFT JOIN units u ON u.id = p.unit_id
    SET i.product_name = d.name, i.product_sku = p.sku, i.unit_code = u.code
    WHERE i.product_name IS NULL AND d.name IS NOT NULL;
UPDATE invoice_items i
    JOIN products p ON p.id = i.product_id
    LEFT JOIN product_description d ON d.product_id = p.id AND d.language_id = @def_lang
    LEFT JOIN units u ON u.id = p.unit_id
    SET i.product_name = d.name, i.product_sku = p.sku, i.unit_code = u.code
    WHERE i.product_name IS NULL AND d.name IS NOT NULL;
UPDATE purchase_order_items i
    JOIN products p ON p.id = i.product_id
    LEFT JOIN product_description d ON d.product_id = p.id AND d.language_id = @def_lang
    LEFT JOIN units u ON u.id = p.unit_id
    SET i.product_name = d.name, i.product_sku = p.sku, i.unit_code = u.code
    WHERE i.product_name IS NULL AND d.name IS NOT NULL;
UPDATE incoming_invoice_items i
    JOIN products p ON p.id = i.product_id
    LEFT JOIN product_description d ON d.product_id = p.id AND d.language_id = @def_lang
    LEFT JOIN units u ON u.id = p.unit_id
    SET i.product_name = d.name, i.product_sku = p.sku, i.unit_code = u.code
    WHERE i.product_name IS NULL AND d.name IS NOT NULL;

-- ---------------------------------------------------------------------------
-- 6. Termékparaméter-érték nyelvenként
-- ---------------------------------------------------------------------------
ALTER TABLE product_parameters ADD COLUMN IF NOT EXISTS language_id INT UNSIGNED NULL AFTER parameter_id;
UPDATE product_parameters SET language_id = @def_lang WHERE language_id IS NULL;

-- A régi (termék, paraméter) kulcs helyére a nyelvet is tartalmazó kulcs kerül.
-- Előbb az új kulcs jön létre: a régit a fk_pp_product külső kulcs használja
-- (product_id a bal oldali oszlopa), és az InnoDB csak akkor engedi eldobni, ha
-- már van másik index, ami ki tudja szolgálni. Az új kulcs is product_id-val
-- kezdődik, ezért átveszi ezt a szerepet.
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_parameters'
               AND INDEX_NAME = 'uniq_product_parameter_language');
SET @sql := IF(@has = 0,
    'ALTER TABLE product_parameters
       ADD UNIQUE KEY uniq_product_parameter_language (product_id, parameter_id, language_id)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_parameters'
               AND INDEX_NAME = 'uniq_product_parameter');
SET @sql := IF(@has > 0, 'ALTER TABLE product_parameters DROP INDEX uniq_product_parameter', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE product_parameters MODIFY language_id INT UNSIGNED NOT NULL;
ALTER TABLE product_parameters ADD CONSTRAINT fk_pp_language
    FOREIGN KEY IF NOT EXISTS (language_id) REFERENCES languages (id) ON DELETE CASCADE;

-- ---------------------------------------------------------------------------
-- 7. Kategória állapot
-- ---------------------------------------------------------------------------
-- A ház konvenciója is_active (products, partners, warehouses), ezért nem status.
ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- ---------------------------------------------------------------------------
-- 8. Az átkerült oszlopok és kulcsaik eltávolítása
-- ---------------------------------------------------------------------------
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parameters'
               AND INDEX_NAME = 'uniq_param_name');
SET @sql := IF(@has > 0, 'ALTER TABLE parameters DROP INDEX uniq_param_name', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- A régi 15-es migráció default nélkül hozta létre; egységesítjük, hogy a friss
-- telepítés és a frissített telepítés sémája bitre azonos legyen.
ALTER TABLE parameters MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE products   DROP COLUMN IF EXISTS name;
ALTER TABLE products   DROP COLUMN IF EXISTS short_description;
ALTER TABLE products   DROP COLUMN IF EXISTS description;
ALTER TABLE categories DROP COLUMN IF EXISTS name;
ALTER TABLE units      DROP COLUMN IF EXISTS name;
ALTER TABLE parameters DROP COLUMN IF EXISTS name;
-- A product_parameters.value marad a base táblán: ott a language_id választja
-- szét a nyelveket, nincs külön description tábla (az OpenCart is így teszi).
