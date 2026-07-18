-- Extended product fields
ALTER TABLE products ADD COLUMN IF NOT EXISTS short_description VARCHAR(255) NULL AFTER name;
ALTER TABLE products ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER short_description;
ALTER TABLE products ADD COLUMN IF NOT EXISTS vat_rate DECIMAL(5,2) NOT NULL DEFAULT 27.00 AFTER price;
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_webshop TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;
ALTER TABLE products ADD COLUMN IF NOT EXISTS width_mm INT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS height_mm INT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS depth_mm INT UNSIGNED NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS weight_g INT UNSIGNED NULL;

-- Mennyiségi egységek (választható lista; nincs admin felület, itt töltjük fel)
CREATE TABLE IF NOT EXISTS units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(16) NOT NULL,
    name VARCHAR(64) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_unit_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO units (code, name, sort_order) VALUES
    ('db', 'darab', 10),
    ('doboz', 'doboz', 20),
    ('csomag', 'csomag', 30),
    ('szett', 'szett', 40),
    ('karton', 'karton', 50),
    ('raklap', 'raklap', 60),
    ('zsak', 'zsák', 70),
    ('palack', 'palack', 80),
    ('par', 'pár', 90),
    ('tekercs', 'tekercs', 100),
    ('kg', 'kilogramm', 110),
    ('g', 'gramm', 120),
    ('l', 'liter', 130),
    ('ml', 'milliliter', 140),
    ('m', 'méter', 150),
    ('cm', 'centiméter', 160),
    ('m2', 'négyzetméter', 170),
    ('m3', 'köbméter', 180),
    ('ora', 'óra', 190),
    ('alkalom', 'alkalom', 200);

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

-- Termékparaméterek (név/érték párok)
CREATE TABLE IF NOT EXISTS product_attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    attr_name VARCHAR(120) NOT NULL,
    attr_value VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pa_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kapcsolódó és helyettesítő termékek (egy táblában, típussal)
CREATE TABLE IF NOT EXISTS product_links (
    product_id INT UNSIGNED NOT NULL,
    linked_product_id INT UNSIGNED NOT NULL,
    link_type ENUM('related', 'substitute') NOT NULL,
    PRIMARY KEY (product_id, linked_product_id, link_type),
    CONSTRAINT fk_pl_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pl_linked FOREIGN KEY (linked_product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
