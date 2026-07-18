CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(32) NOT NULL,
    partner_id INT UNSIGNED NOT NULL,
    status ENUM('draft', 'confirmed', 'invoiced', 'cancelled') NOT NULL DEFAULT 'draft',
    order_date DATE NOT NULL,
    total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_order_number (order_number),
    CONSTRAINT fk_orders_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
    CONSTRAINT fk_orders_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14, 3) NOT NULL,
    unit_price DECIMAL(14, 2) NOT NULL,
    line_total DECIMAL(14, 2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(32) NOT NULL,
    order_id INT UNSIGNED NULL,
    partner_id INT UNSIGNED NOT NULL,
    status ENUM('unpaid', 'paid', 'cancelled') NOT NULL DEFAULT 'unpaid',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_invoice_number (invoice_number),
    CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
    CONSTRAINT fk_invoices_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14, 3) NOT NULL,
    unit_price DECIMAL(14, 2) NOT NULL,
    line_total DECIMAL(14, 2) NOT NULL,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
