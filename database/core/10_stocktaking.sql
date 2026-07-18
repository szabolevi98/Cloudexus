CREATE TABLE IF NOT EXISTS stocktakings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stocktaking_number VARCHAR(32) NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    diff_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_stocktaking_number (stocktaking_number),
    CONSTRAINT fk_stocktaking_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_stocktaking_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stocktaking_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stocktaking_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    book_quantity DECIMAL(14, 3) NOT NULL,
    counted_quantity DECIMAL(14, 3) NOT NULL,
    diff DECIMAL(14, 3) NOT NULL,
    CONSTRAINT fk_sti_stocktaking FOREIGN KEY (stocktaking_id) REFERENCES stocktakings (id) ON DELETE CASCADE,
    CONSTRAINT fk_sti_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
