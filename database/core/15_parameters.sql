-- Paraméterek (törzs). A paraméter neve fordítható, ezért a
-- parameter_description táblában van; a törzs feltöltése is ott történik
-- (24_languages_and_translations.sql), amikor már léteznek nyelvek.
CREATE TABLE IF NOT EXISTS parameters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Termékparaméterek: a paraméter neve a parameters törzsből jön, az érték
-- nyelvenként külön sor (mint az OpenCart product_attribute táblájában).
-- A language_id oszlopot a 24-es migráció adja hozzá a kulccsal együtt.
CREATE TABLE IF NOT EXISTS product_parameters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    parameter_id INT UNSIGNED NOT NULL,
    value VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pp_parameter FOREIGN KEY (parameter_id) REFERENCES parameters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
