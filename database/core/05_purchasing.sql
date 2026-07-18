CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(32) NOT NULL,
    partner_id INT UNSIGNED NOT NULL,
    status ENUM('draft', 'confirmed', 'invoiced', 'cancelled') NOT NULL DEFAULT 'draft',
    order_date DATE NOT NULL,
    total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_po_number (po_number),
    CONSTRAINT fk_po_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
    CONSTRAINT fk_po_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14, 3) NOT NULL,
    unit_price DECIMAL(14, 2) NOT NULL,
    line_total DECIMAL(14, 2) NOT NULL,
    CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incoming_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(32) NOT NULL,
    purchase_order_id INT UNSIGNED NULL,
    partner_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    status ENUM('unpaid', 'paid', 'cancelled') NOT NULL DEFAULT 'unpaid',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_incoming_invoice_number (invoice_number),
    CONSTRAINT fk_ii_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_ii_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
    CONSTRAINT fk_ii_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    CONSTRAINT fk_ii_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incoming_invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incoming_invoice_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14, 3) NOT NULL,
    unit_price DECIMAL(14, 2) NOT NULL,
    line_total DECIMAL(14, 2) NOT NULL,
    CONSTRAINT fk_iii_invoice FOREIGN KEY (incoming_invoice_id) REFERENCES incoming_invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_iii_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
