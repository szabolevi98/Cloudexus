-- Extended product fields.
-- A rövid és hosszú leírás fordítható, ezért a product_description táblában van
-- (24_languages_and_translations.sql) — itt szándékosan nem jön létre.
ALTER TABLE products ADD COLUMN IF NOT EXISTS vat_rate DECIMAL(5,2) NOT NULL DEFAULT 27.00 AFTER price;
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_webshop TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;
ALTER TABLE products ADD COLUMN IF NOT EXISTS width_mm INT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS height_mm INT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS depth_mm INT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS weight_g INT UNSIGNED NULL;

-- Mennyiségi egységek. A kód nem fordítható, a megnevezés viszont igen, ezért
-- az a unit_description táblában van — a törzs feltöltése is ott történik
-- (24_languages_and_translations.sql), amikor már léteznek nyelvek.
CREATE TABLE IF NOT EXISTS units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(16) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_unit_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO units (code, sort_order) VALUES
    ('db', 10), ('doboz', 20), ('csomag', 30), ('szett', 40), ('karton', 50),
    ('raklap', 60), ('zsak', 70), ('palack', 80), ('par', 90), ('tekercs', 100),
    ('kg', 110), ('g', 120), ('l', 130), ('ml', 140), ('m', 150),
    ('cm', 160), ('m2', 170), ('m3', 180), ('ora', 190), ('alkalom', 200);

-- Egy termék több kategóriában is szerepelhet (a products.category_id az elsődleges)
CREATE TABLE IF NOT EXISTS product_categories (
    product_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, category_id),
    CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Termékképek (a web/ mappában tárolva, így URL-ből elérhetőek)
CREATE TABLE IF NOT EXISTS product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A termékparaméterek (product_parameters) a 15-es migrációban jönnek létre,
-- mert a parameters törzsre hivatkoznak.

-- Kapcsolódó és helyettesítő termékek (egy táblában, típussal)
CREATE TABLE IF NOT EXISTS product_links (
    product_id INT UNSIGNED NOT NULL,
    linked_product_id INT UNSIGNED NOT NULL,
    link_type ENUM('related', 'substitute') NOT NULL,
    PRIMARY KEY (product_id, linked_product_id, link_type),
    CONSTRAINT fk_pl_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pl_linked FOREIGN KEY (linked_product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
