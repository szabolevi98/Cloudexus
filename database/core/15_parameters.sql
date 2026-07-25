-- Paraméterek (törzs) — a termékparaméterek nevei ebből választhatók
CREATE TABLE IF NOT EXISTS parameters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_param_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parameters (name, created_at) VALUES
    ('Gyártó', NOW()),
    ('Márka', NOW()),
    ('Garancia', NOW()),
    ('Származási ország', NOW()),
    ('Szín', NOW()),
    ('Méret', NOW()),
    ('Anyag', NOW()),
    ('Tömeg', NOW()),
    ('Teljesítmény', NOW()),
    ('Feszültség', NOW()),
    ('Energiaosztály', NOW()),
    ('Kiszerelés', NOW()),
    ('Modell', NOW()),
    ('Típus', NOW());

-- Termékparaméterek: a paraméter neve a parameters törzsből jön, csak az érték
-- szabad szöveg. Egy terméken egy paraméter csak egyszer szerepelhet.
CREATE TABLE IF NOT EXISTS product_parameters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    parameter_id INT UNSIGNED NOT NULL,
    value VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_product_parameter (product_id, parameter_id),
    CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pp_parameter FOREIGN KEY (parameter_id) REFERENCES parameters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
