-- Ügyfélkapcsolat-történet (hívás / e-mail / találkozó / jegyzet napló)
CREATE TABLE IF NOT EXISTS partner_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id INT UNSIGNED NOT NULL,
    type ENUM('call', 'email', 'meeting', 'note', 'offer') NOT NULL DEFAULT 'note',
    subject VARCHAR(200) NOT NULL,
    note TEXT NULL,
    activity_date DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_paa_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE CASCADE,
    CONSTRAINT fk_paa_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
