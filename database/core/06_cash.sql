CREATE TABLE IF NOT EXISTS cash_vouchers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_number VARCHAR(32) NOT NULL,
    type ENUM('bevetel', 'kiadas') NOT NULL,
    amount DECIMAL(14, 2) NOT NULL,
    partner_id INT UNSIGNED NULL,
    invoice_id INT UNSIGNED NULL,
    incoming_invoice_id INT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    voucher_date DATE NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_voucher_number (voucher_number),
    CONSTRAINT fk_cash_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE SET NULL,
    CONSTRAINT fk_cash_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
    CONSTRAINT fk_cash_incoming_invoice FOREIGN KEY (incoming_invoice_id) REFERENCES incoming_invoices (id) ON DELETE SET NULL,
    CONSTRAINT fk_cash_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
