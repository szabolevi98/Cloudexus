-- Paraméternevek (törzs) — a termékparaméterek nevei ebből választhatók
CREATE TABLE IF NOT EXISTS parameter_names (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_param_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parameter_names (name, created_at) VALUES
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
