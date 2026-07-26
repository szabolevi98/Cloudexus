-- Cloudexus — alap adatbázis-séma.
--
-- Ez az egyetlen fájl hozza létre a teljes sémát és a szállított
-- alapadatokat (mértékegységek, paraméterek, nyelvek, pénznemek).
-- A korábbi, sorszámozott migrációkból (01–24) lett összevonva, mert azok
-- egymásra épülő ALTER TABLE-es rétegek voltak — friss telepítésen ezt az
-- egy fájlt kell csak lefuttatni, a réteges történet innentől nem kell.
--
-- Mint minden migráció, ez is idempotens (IF NOT EXISTS / INSERT IGNORE),
-- így a database/migrate.php bármikor újra lefuttathatja.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Felhasználók
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_username (username),
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Nyelvek
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS languages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL,
  code VARCHAR(5) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_language_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Törzsadatok: kategóriák, termékek, partnerek
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY fk_categories_parent (parent_id),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(64) NOT NULL,
  barcode VARCHAR(64) DEFAULT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  unit_id INT UNSIGNED DEFAULT NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  sale_price DECIMAL(14,2) DEFAULT NULL,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 27.00,
  min_stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_webshop TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  width_mm INT UNSIGNED DEFAULT NULL,
  height_mm INT UNSIGNED DEFAULT NULL,
  depth_mm INT UNSIGNED DEFAULT NULL,
  weight_g INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_sku (sku),
  KEY fk_products_category (category_id),
  KEY fk_products_unit (unit_id),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('customer','supplier','both') NOT NULL DEFAULT 'customer',
  customer_group_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(200) NOT NULL,
  tax_number VARCHAR(32) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_partners_customer_group (customer_group_id),
  CONSTRAINT fk_partners_customer_group FOREIGN KEY (customer_group_id) REFERENCES customer_groups (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Vevőcsoportok és termékenkénti csoportár
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_customer_group_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_group_prices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  customer_group_id INT UNSIGNED NOT NULL,
  price DECIMAL(14,2) NOT NULL,
  sale_price DECIMAL(14,2) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_product_group (product_id,customer_group_id),
  KEY fk_pgp_group (customer_group_id),
  CONSTRAINT fk_pgp_group FOREIGN KEY (customer_group_id) REFERENCES customer_groups (id) ON DELETE CASCADE,
  CONSTRAINT fk_pgp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Partner-aladatok: cím és kapcsolattörténet
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS partner_addresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id INT UNSIGNED NOT NULL,
  country VARCHAR(100) NOT NULL DEFAULT 'Magyarország',
  city VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NOT NULL,
  street VARCHAR(255) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_partner_addresses_partner (partner_id),
  CONSTRAINT fk_partner_addresses_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_activities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id INT UNSIGNED NOT NULL,
  type ENUM('call','email','meeting','note','offer') NOT NULL DEFAULT 'note',
  subject VARCHAR(200) NOT NULL,
  note TEXT DEFAULT NULL,
  activity_date DATETIME NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_paa_partner (partner_id),
  KEY fk_paa_user (created_by),
  CONSTRAINT fk_paa_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE CASCADE,
  CONSTRAINT fk_paa_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Raktárak és tárhelyek
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS warehouses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouse_locations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  warehouse_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(120) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_wh_code (warehouse_id,code),
  CONSTRAINT fk_wl_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Készletmozgás
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  warehouse_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED DEFAULT NULL,
  product_id INT UNSIGNED NOT NULL,
  type ENUM('in','out') NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_stock_movements_warehouse (warehouse_id),
  KEY fk_stock_movements_product (product_id),
  KEY fk_stock_movements_user (created_by),
  KEY fk_sm_location (location_id),
  CONSTRAINT fk_sm_location FOREIGN KEY (location_id) REFERENCES warehouse_locations (id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_stock_movements_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_movements_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Leltározás
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stocktakings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  stocktaking_number VARCHAR(32) NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  item_count INT UNSIGNED NOT NULL DEFAULT 0,
  diff_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_stocktaking_number (stocktaking_number),
  KEY fk_stocktaking_warehouse (warehouse_id),
  KEY fk_stocktaking_user (created_by),
  CONSTRAINT fk_stocktaking_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_stocktaking_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stocktaking_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  stocktaking_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  book_quantity DECIMAL(14,3) NOT NULL,
  counted_quantity DECIMAL(14,3) NOT NULL,
  diff DECIMAL(14,3) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_sti_stocktaking (stocktaking_id),
  KEY fk_sti_product (product_id),
  CONSTRAINT fk_sti_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_sti_stocktaking FOREIGN KEY (stocktaking_id) REFERENCES stocktakings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Értékesítés: rendelés és számla
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(32) NOT NULL,
  partner_id INT UNSIGNED NOT NULL,
  shipping_address_id INT UNSIGNED DEFAULT NULL,
  billing_address_id INT UNSIGNED DEFAULT NULL,
  status ENUM('draft','confirmed','invoiced','cancelled') NOT NULL DEFAULT 'draft',
  order_date DATE NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  shipping_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  payment_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_order_number (order_number),
  KEY fk_orders_partner (partner_id),
  KEY fk_orders_user (created_by),
  KEY fk_orders_shipping_address (shipping_address_id),
  KEY fk_orders_billing_address (billing_address_id),
  CONSTRAINT fk_orders_billing_address FOREIGN KEY (billing_address_id) REFERENCES partner_addresses (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
  CONSTRAINT fk_orders_shipping_address FOREIGN KEY (shipping_address_id) REFERENCES partner_addresses (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  -- A termék akkori neve/cikkszáma/mértékegysége — a bizonylat rögzítés után
  -- se változzon meg egy átnevezéstől vagy nyelvváltástól.
  product_name VARCHAR(200) DEFAULT NULL,
  product_sku VARCHAR(64) DEFAULT NULL,
  unit_code VARCHAR(16) DEFAULT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_order_items_order (order_id),
  KEY fk_order_items_product (product_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_number VARCHAR(32) NOT NULL,
  order_id INT UNSIGNED DEFAULT NULL,
  partner_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED DEFAULT NULL,
  status ENUM('unpaid','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  shipping_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  payment_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_invoice_number (invoice_number),
  KEY fk_invoices_order (order_id),
  KEY fk_invoices_partner (partner_id),
  KEY fk_invoices_user (created_by),
  CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
  CONSTRAINT fk_invoices_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(200) DEFAULT NULL,
  product_sku VARCHAR(64) DEFAULT NULL,
  unit_code VARCHAR(16) DEFAULT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_invoice_items_invoice (invoice_id),
  KEY fk_invoice_items_product (product_id),
  CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_items_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Beszerzés: szállítói rendelés és bejövő számla
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_number VARCHAR(32) NOT NULL,
  partner_id INT UNSIGNED NOT NULL,
  status ENUM('draft','confirmed','invoiced','cancelled') NOT NULL DEFAULT 'draft',
  order_date DATE NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_po_number (po_number),
  KEY fk_po_partner (partner_id),
  KEY fk_po_user (created_by),
  CONSTRAINT fk_po_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
  CONSTRAINT fk_po_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(200) DEFAULT NULL,
  product_sku VARCHAR(64) DEFAULT NULL,
  unit_code VARCHAR(16) DEFAULT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_poi_order (purchase_order_id),
  KEY fk_poi_product (product_id),
  CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incoming_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_number VARCHAR(32) NOT NULL,
  purchase_order_id INT UNSIGNED DEFAULT NULL,
  partner_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED DEFAULT NULL,
  status ENUM('unpaid','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_incoming_invoice_number (invoice_number),
  KEY fk_ii_po (purchase_order_id),
  KEY fk_ii_partner (partner_id),
  KEY fk_ii_warehouse (warehouse_id),
  KEY fk_ii_user (created_by),
  CONSTRAINT fk_ii_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
  CONSTRAINT fk_ii_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_ii_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_ii_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incoming_invoice_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  incoming_invoice_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(200) DEFAULT NULL,
  product_sku VARCHAR(64) DEFAULT NULL,
  unit_code VARCHAR(16) DEFAULT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_iii_invoice (incoming_invoice_id),
  KEY fk_iii_product (product_id),
  CONSTRAINT fk_iii_invoice FOREIGN KEY (incoming_invoice_id) REFERENCES incoming_invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_iii_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Pénztár
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cash_vouchers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  voucher_number VARCHAR(32) NOT NULL,
  type ENUM('bevetel','kiadas') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  partner_id INT UNSIGNED DEFAULT NULL,
  invoice_id INT UNSIGNED DEFAULT NULL,
  incoming_invoice_id INT UNSIGNED DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  voucher_date DATE NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_voucher_number (voucher_number),
  KEY fk_cash_partner (partner_id),
  KEY fk_cash_invoice (invoice_id),
  KEY fk_cash_incoming_invoice (incoming_invoice_id),
  KEY fk_cash_user (created_by),
  CONSTRAINT fk_cash_incoming_invoice FOREIGN KEY (incoming_invoice_id) REFERENCES incoming_invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Teendők
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS todos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  due_date DATE DEFAULT NULL,
  partner_id INT UNSIGNED DEFAULT NULL,
  assigned_to INT UNSIGNED DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  completed_at DATETIME DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_todos_partner (partner_id),
  KEY fk_todos_assigned (assigned_to),
  KEY fk_todos_creator (created_by),
  CONSTRAINT fk_todos_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_todos_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_todos_partner FOREIGN KEY (partner_id) REFERENCES partners (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Mennyiségi egységek (a kód nem fordítható, a megnevezés a unit_description-ben)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS units (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_unit_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unit_description (
  unit_id INT UNSIGNED NOT NULL,
  language_id INT UNSIGNED NOT NULL,
  name VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (unit_id,language_id),
  KEY fk_ud_language (language_id),
  CONSTRAINT fk_ud_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE,
  CONSTRAINT fk_ud_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Paraméterek és termékparaméterek (a paraméter neve a parameter_description-ben,
-- az érték a product_parameters-en, nyelvenként külön sorral)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parameters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parameter_description (
  parameter_id INT UNSIGNED NOT NULL,
  language_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (parameter_id,language_id),
  UNIQUE KEY uniq_parameter_name_per_language (language_id,name),
  CONSTRAINT fk_prd_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE,
  CONSTRAINT fk_prd_parameter FOREIGN KEY (parameter_id) REFERENCES parameters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_parameters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  parameter_id INT UNSIGNED NOT NULL,
  language_id INT UNSIGNED NOT NULL,
  value VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_product_parameter_language (product_id,parameter_id,language_id),
  KEY fk_pp_parameter (parameter_id),
  KEY fk_pp_language (language_id),
  CONSTRAINT fk_pp_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_parameter FOREIGN KEY (parameter_id) REFERENCES parameters (id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Termék kapcsolótáblák: kategória, kép, kapcsolódó/helyettesítő
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_categories (
  product_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id,category_id),
  KEY fk_pc_category (category_id),
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_pi_product (product_id),
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_links (
  product_id INT UNSIGNED NOT NULL,
  linked_product_id INT UNSIGNED NOT NULL,
  link_type ENUM('related','substitute') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id,linked_product_id,link_type),
  KEY fk_pl_linked (linked_product_id),
  CONSTRAINT fk_pl_linked FOREIGN KEY (linked_product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pl_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Fordítható szövegek: termék, kategória (OpenCart-mintás description táblák)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_description (
  product_id INT UNSIGNED NOT NULL,
  language_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  short_description VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id,language_id),
  KEY idx_pd_name (name),
  KEY fk_pd_language (language_id),
  CONSTRAINT fk_pd_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_description (
  category_id INT UNSIGNED NOT NULL,
  language_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (category_id,language_id),
  KEY idx_cd_name (name),
  KEY fk_cd_language (language_id),
  CONSTRAINT fk_cd_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
  CONSTRAINT fk_cd_language FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- API-hozzáférés
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  token VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_api_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Pénznemek
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS currencies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(64) NOT NULL,
  code CHAR(3) NOT NULL,
  symbol VARCHAR(8) DEFAULT NULL,
  value DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_currency_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Alkalmazás-beállítások (kulcs-érték)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Alapadatok
-- =============================================================================

-- Nyelvek. A kód a src/Language/<kód>/ mappákkal egyezik, így a felület
-- fordítása is illeszkedik. A magyar az alapnyelv.
INSERT IGNORE INTO languages (name, code, sort_order) VALUES
    ('Magyar', 'hu', 10),
    ('English', 'en', 20);

INSERT IGNORE INTO settings (setting_key, setting_value, updated_at)
VALUES ('language.default', 'hu', NOW());

SET @hu_lang := (SELECT id FROM languages WHERE code = 'hu');
SET @en_lang := (SELECT id FROM languages WHERE code = 'en');

-- Mennyiségi egységek: a kód nem fordítható, a megnevezés magyarul és angolul.
INSERT IGNORE INTO units (code, sort_order) VALUES
    ('db', 10), ('doboz', 20), ('csomag', 30), ('szett', 40), ('karton', 50),
    ('raklap', 60), ('zsak', 70), ('palack', 80), ('par', 90), ('tekercs', 100),
    ('kg', 110), ('g', 120), ('l', 130), ('ml', 140), ('m', 150),
    ('cm', 160), ('m2', 170), ('m3', 180), ('ora', 190), ('alkalom', 200);

INSERT IGNORE INTO unit_description (unit_id, language_id, name)
SELECT u.id, l.id, m.name
FROM (
    SELECT 'db' AS code, 'hu' AS lang, 'darab' AS name UNION ALL SELECT 'db', 'en', 'piece' UNION ALL
    SELECT 'doboz', 'hu', 'doboz' UNION ALL SELECT 'doboz', 'en', 'box' UNION ALL
    SELECT 'csomag', 'hu', 'csomag' UNION ALL SELECT 'csomag', 'en', 'pack' UNION ALL
    SELECT 'szett', 'hu', 'szett' UNION ALL SELECT 'szett', 'en', 'set' UNION ALL
    SELECT 'karton', 'hu', 'karton' UNION ALL SELECT 'karton', 'en', 'carton' UNION ALL
    SELECT 'raklap', 'hu', 'raklap' UNION ALL SELECT 'raklap', 'en', 'pallet' UNION ALL
    SELECT 'zsak', 'hu', 'zsák' UNION ALL SELECT 'zsak', 'en', 'bag' UNION ALL
    SELECT 'palack', 'hu', 'palack' UNION ALL SELECT 'palack', 'en', 'bottle' UNION ALL
    SELECT 'par', 'hu', 'pár' UNION ALL SELECT 'par', 'en', 'pair' UNION ALL
    SELECT 'tekercs', 'hu', 'tekercs' UNION ALL SELECT 'tekercs', 'en', 'roll' UNION ALL
    SELECT 'kg', 'hu', 'kilogramm' UNION ALL SELECT 'kg', 'en', 'kilogram' UNION ALL
    SELECT 'g', 'hu', 'gramm' UNION ALL SELECT 'g', 'en', 'gram' UNION ALL
    SELECT 'l', 'hu', 'liter' UNION ALL SELECT 'l', 'en', 'litre' UNION ALL
    SELECT 'ml', 'hu', 'milliliter' UNION ALL SELECT 'ml', 'en', 'millilitre' UNION ALL
    SELECT 'm', 'hu', 'méter' UNION ALL SELECT 'm', 'en', 'metre' UNION ALL
    SELECT 'cm', 'hu', 'centiméter' UNION ALL SELECT 'cm', 'en', 'centimetre' UNION ALL
    SELECT 'm2', 'hu', 'négyzetméter' UNION ALL SELECT 'm2', 'en', 'square metre' UNION ALL
    SELECT 'm3', 'hu', 'köbméter' UNION ALL SELECT 'm3', 'en', 'cubic metre' UNION ALL
    SELECT 'ora', 'hu', 'óra' UNION ALL SELECT 'ora', 'en', 'hour' UNION ALL
    SELECT 'alkalom', 'hu', 'alkalom' UNION ALL SELECT 'alkalom', 'en', 'occasion'
) m
JOIN units u ON u.code = m.code
JOIN languages l ON l.code = m.lang;

-- Alapértelmezett paraméterek, rögzített id-kal (a magyar/angol nevekhez).
INSERT IGNORE INTO parameters (id, created_at) VALUES
    (1,NOW()),(2,NOW()),(3,NOW()),(4,NOW()),(5,NOW()),(6,NOW()),(7,NOW()),
    (8,NOW()),(9,NOW()),(10,NOW()),(11,NOW()),(12,NOW()),(13,NOW()),(14,NOW());

INSERT IGNORE INTO parameter_description (parameter_id, language_id, name) VALUES
    (1, @hu_lang, 'Gyártó'),            (1, @en_lang, 'Manufacturer'),
    (2, @hu_lang, 'Márka'),             (2, @en_lang, 'Brand'),
    (3, @hu_lang, 'Garancia'),          (3, @en_lang, 'Warranty'),
    (4, @hu_lang, 'Származási ország'), (4, @en_lang, 'Country of origin'),
    (5, @hu_lang, 'Szín'),              (5, @en_lang, 'Colour'),
    (6, @hu_lang, 'Méret'),             (6, @en_lang, 'Size'),
    (7, @hu_lang, 'Anyag'),             (7, @en_lang, 'Material'),
    (8, @hu_lang, 'Tömeg'),             (8, @en_lang, 'Weight'),
    (9, @hu_lang, 'Teljesítmény'),      (9, @en_lang, 'Power'),
    (10, @hu_lang, 'Feszültség'),       (10, @en_lang, 'Voltage'),
    (11, @hu_lang, 'Energiaosztály'),   (11, @en_lang, 'Energy class'),
    (12, @hu_lang, 'Kiszerelés'),       (12, @en_lang, 'Packaging'),
    (13, @hu_lang, 'Modell'),           (13, @en_lang, 'Model'),
    (14, @hu_lang, 'Típus'),            (14, @en_lang, 'Type');

-- Pénznemek: a forint az induló elsődleges pénznem (value = 1), a másik kettő
-- váltószáma közelítő induló érték — az "MNB közép árfolyam lekérése" gomb
-- (vagy a bin/sync_currency_rates.php cron szkript) felülírja őket.
INSERT IGNORE INTO currencies (title, code, symbol, value) VALUES
    ('Forint', 'HUF', 'Ft', 1.00000000),
    ('Euró', 'EUR', '€', 0.00250000),
    ('USA dollár', 'USD', '$', 0.00270000);

INSERT IGNORE INTO settings (setting_key, setting_value, updated_at)
VALUES ('currency.primary', 'HUF', NOW());
