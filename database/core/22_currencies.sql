-- Pénznemek. Az elsődleges pénznem kódját a settings.currency.primary tartalmazza,
-- annak a value-ja mindig 1. A többinél value = 1 elsődleges egység ennyi az adott
-- pénznemben (OpenCart-logika), tehát a megjelenítéshez: összeg * value.
CREATE TABLE IF NOT EXISTS currencies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(64) NOT NULL,
    code CHAR(3) NOT NULL,
    symbol VARCHAR(8) NULL,
    value DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_currency_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A forint az induló elsődleges pénznem, a másik kettő váltószáma az első
-- MNB-szinkronig csak közelítő induló érték.
INSERT IGNORE INTO currencies (title, code, symbol, value) VALUES
    ('Forint', 'HUF', 'Ft', 1.00000000),
    ('Euró', 'EUR', '€', 0.00250000),
    ('USA dollár', 'USD', '$', 0.00270000);

INSERT IGNORE INTO settings (setting_key, setting_value, updated_at)
VALUES ('currency.primary', 'HUF', NOW());
