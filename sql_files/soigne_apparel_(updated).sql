-- =====================================================================
-- Soigné Apparel Shop: full database script
-- Creates the database, all tables, views and sample data.
-- WARNING: drops and recreates every table in soigne_apparel.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS soigne_apparel
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE soigne_apparel;

-- ---------------------------------------------------------------------
-- 1. Clean slate
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS all_costs, restock_totals, order_transaction_details, order_totals;
DROP TABLE IF EXISTS product_reviews, restock_items, restocks, expenses,
                     order_items, orders, cart,
                     product_variants, product_color_images, product_colors, products,
                     categories, suppliers, customers, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 2. Users and customers
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id    INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  email      VARCHAR(120) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,              -- password_hash() output
  role       ENUM('customer','admin','vip') NOT NULL DEFAULT 'customer',
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  vip_expires_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customers (
  customer_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL UNIQUE,
  customer_name VARCHAR(120) NOT NULL,
  phone         VARCHAR(30),
  address       VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE premium_payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
  receipt_filename VARCHAR(64) NOT NULL,
  receipt_mime VARCHAR(30) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  reviewed_by INT NULL,
  INDEX idx_premium_payments_user_status (user_id, status),
  INDEX idx_premium_payments_status_submitted (status, submitted_at),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Suppliers (each supplier is also the brand)
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
  supplier_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL UNIQUE,
  image       VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
  ('T-shirts'), ('Shirts'), ('Polos'), ('Hoodies'), ('Sweaters'),
  ('Pants'), ('Jeans'), ('Chinos'), ('Shorts'), ('Outerwear');

-- ---------------------------------------------------------------------
-- 4. Products, colors (one image per color) and variants (size + stock)
-- ---------------------------------------------------------------------
CREATE TABLE products (
  product_id  INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  name        VARCHAR(150)  NOT NULL,
  category_id INT NULL,
  department  ENUM('men','women','kids') NOT NULL DEFAULT 'men',
  price       DECIMAL(10,2) NOT NULL,
  description TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_department (department),
  INDEX idx_products_category_id (category_id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE product_colors (
  product_color_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id       INT         NOT NULL,
  color            VARCHAR(80) NOT NULL,
  FOREIGN KEY (product_id) REFERENCES products(product_id),
  UNIQUE KEY uniq_product_color (product_id, color)
) ENGINE=InnoDB;

CREATE TABLE product_color_images (
  image_id INT AUTO_INCREMENT PRIMARY KEY,
  product_color_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_product_color_image (product_color_id, image_path),
  INDEX idx_product_color_images_order (product_color_id, sort_order, image_id),
  CONSTRAINT fk_product_color_images_color
    FOREIGN KEY (product_color_id) REFERENCES product_colors(product_color_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  variant_id       INT AUTO_INCREMENT PRIMARY KEY,
  product_color_id INT         NOT NULL,
  size             VARCHAR(20) NOT NULL,
  stock            INT         NOT NULL DEFAULT 0,
  FOREIGN KEY (product_color_id) REFERENCES product_colors(product_color_id),
  UNIQUE KEY uniq_variant (product_color_id, size)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Cart and orders
-- ---------------------------------------------------------------------
CREATE TABLE cart (
  cart_id    INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  variant_id INT NOT NULL,
  quantity   INT NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id)    REFERENCES users(user_id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id),
  UNIQUE KEY uniq_cart_item (user_id, variant_id)
) ENGINE=InnoDB;

CREATE TABLE orders (
  order_id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  fulfillment_status ENUM('pending','shipped','delivered','failed_delivery','cancelled') NOT NULL DEFAULT 'pending',
  payment_status   ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  shipping_address VARCHAR(255) NOT NULL,        -- snapshot at checkout
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL,
  variant_id    INT NOT NULL,
  quantity      INT NOT NULL,
  price         DECIMAL(10,2) NOT NULL,          -- price at time of sale
  FOREIGN KEY (order_id)   REFERENCES orders(order_id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id),
  UNIQUE KEY uniq_order_item (order_id, variant_id)
) ENGINE=InnoDB;

CREATE TABLE product_reviews (
  review_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  order_item_id INT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text TEXT NOT NULL,
  status ENUM('pending','published','deleted') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  published_at DATETIME NULL,
  UNIQUE KEY uniq_product_review_user (product_id, user_id),
  INDEX idx_product_reviews_order_item (order_item_id),
  INDEX idx_product_reviews_display (product_id, status, created_at),
  INDEX idx_product_reviews_moderation (status, created_at),
  CONSTRAINT fk_product_reviews_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_product_reviews_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_product_reviews_order_item
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Restocks (one batch = one delivery from one brand)
-- ---------------------------------------------------------------------
CREATE TABLE restocks (
  restock_id   INT AUTO_INCREMENT PRIMARY KEY,
  batch_number VARCHAR(20) NULL UNIQUE,          -- e.g. RS-2026-00001, set by PHP
  supplier_id  INT NOT NULL,
  restock_date DATE NOT NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
) ENGINE=InnoDB;

CREATE TABLE restock_items (
  restock_item_id INT AUTO_INCREMENT PRIMARY KEY,
  restock_id      INT NOT NULL,
  variant_id      INT NOT NULL,
  quantity        INT NOT NULL,
  unit_cost       DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (restock_id) REFERENCES restocks(restock_id),
  FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id),
  UNIQUE KEY uniq_restock_item (restock_id, variant_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. Monthly bills (rent, utilities, salaries): one per category per month
-- ---------------------------------------------------------------------
CREATE TABLE expenses (
  expense_id    INT AUTO_INCREMENT PRIMARY KEY,
  expense_date  DATE NOT NULL,                   -- the day it was paid
  billing_month DATE NOT NULL,                   -- always the 1st, e.g. 2026-09-01
  category      ENUM('rent','electricity','water','internet','salaries') NOT NULL,
  description   VARCHAR(255),
  amount        DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uniq_category_month (category, billing_month),
  CHECK (DAYOFMONTH(billing_month) = 1)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. Views (calculated, never stored)
-- ---------------------------------------------------------------------
CREATE VIEW order_transaction_details AS
SELECT
  o.order_id AS transaction_id,
  o.order_id,
  o.user_id,
  o.fulfillment_status,
  o.payment_status,
  o.shipping_address,
  o.created_at AS transaction_date,
  oi.order_item_id,
  p.product_id,
  p.name AS product_name,
  p.department,
  pc.product_color_id,
  pc.color,
  v.variant_id,
  v.size,
  oi.quantity,
  oi.price AS unit_price,
  oi.quantity * oi.price AS line_subtotal,
  ot.total AS order_total
FROM orders o
JOIN order_items oi ON oi.order_id = o.order_id
JOIN product_variants v ON v.variant_id = oi.variant_id
JOIN product_colors pc ON pc.product_color_id = v.product_color_id
JOIN products p ON p.product_id = pc.product_id
JOIN (
  SELECT order_id, SUM(quantity * price) AS total
  FROM order_items
  GROUP BY order_id
) ot ON ot.order_id = o.order_id;

CREATE VIEW restock_totals AS
SELECT restock_id, SUM(quantity * unit_cost) AS total
FROM restock_items
GROUP BY restock_id;

CREATE VIEW all_costs AS
SELECT DATE_FORMAT(r.restock_date, '%Y-%m-01') AS cost_month, 'restock' AS category, t.total AS amount
FROM restocks r
JOIN restock_totals t ON t.restock_id = r.restock_id
UNION ALL
SELECT billing_month, category, amount FROM expenses;

-- =====================================================================
-- SAMPLE DATA
-- =====================================================================

-- Users. Passwords: admin -> Admin123!   customer -> Customer123!
-- (change them after your first login)
INSERT INTO users (username, email, password, role) VALUES
('admin',    'admin@soigne.test',    '$2b$10$V9o3eRnHsI4prmOLfNGmBe/ld7ISyKmZCn7eTO5kxh1LMr5yjc16e', 'admin'),
('juandc',   'juan@example.com',     '$2b$10$Q2EiBPqJDIdS4WoAESA29umbyGe1ecsDKXmlGQSC9QA03223OIZ6C', 'customer');

INSERT INTO customers (user_id, customer_name, phone, address)
SELECT user_id, 'Juan Dela Cruz', '09171234567', '123 Sample St., Quezon City, Metro Manila'
FROM users
WHERE username = 'juandc';

-- Suppliers / brands
INSERT INTO suppliers (name, image) VALUES ('H&M', 'h-m-logo.png');

SET @supplier_hm = (SELECT supplier_id FROM suppliers WHERE name = 'H&M');

-- Products
-- department: men / women / kids
INSERT INTO products (supplier_id, name, category_id, department, price, description) VALUES
(@supplier_hm, 'Felted Button-Detail Jacket', (SELECT category_id FROM categories WHERE name = 'Outerwear'), 'women', 2880.00, 'Short jacket in soft, felted fabric. Band collar and an open front with decorative trim and spherical buttons.');


-- Add each color and its primary image (put matching files in your images/ folder).
INSERT INTO product_colors (product_id, color)
SELECT p.product_id, colors.color
FROM products p
JOIN suppliers s ON s.supplier_id = p.supplier_id
JOIN (
 SELECT 'Felted Button-Detail Jacket' AS product_name, 'Dark Khaki Green' AS color
) AS colors ON colors.product_name = p.name
WHERE p.name = 'Felted Button-Detail Jacket'
 AND s.name = 'H&M';

INSERT IGNORE INTO product_color_images (product_color_id, image_path)
SELECT pc.product_color_id, 'h&m_jacket_khaki.png'
FROM product_colors AS pc
JOIN products AS p ON p.product_id = pc.product_id
JOIN suppliers AS s ON s.supplier_id = p.supplier_id
WHERE s.name = 'H&M'
  AND p.name = 'Felted Button-Detail Jacket'
  AND pc.color = 'Dark Khaki Green';

-- Variants: sizes S, M, L for every color, starting with 0 stock
-- (stock is added by the sample restocks below)
INSERT INTO product_variants (product_color_id, size, stock)
SELECT pc.product_color_id, s.size, 0
FROM product_colors pc
CROSS JOIN (SELECT 'S' AS size UNION SELECT 'M' UNION SELECT 'L') s;

-- Ensure the H&M jacket has S, M, and L variants (safe if already inserted above).
INSERT IGNORE INTO product_variants (product_color_id, size, stock)
SELECT pc.product_color_id, s.size, 0
FROM product_colors pc
JOIN products p ON p.product_id = pc.product_id
JOIN suppliers supplier ON supplier.supplier_id = p.supplier_id
CROSS JOIN (SELECT 'S' AS size UNION ALL SELECT 'M' UNION ALL SELECT 'L') s
WHERE supplier.name = 'H&M'
  AND p.name = 'Felted Button-Detail Jacket'
  AND pc.color = 'Dark Khaki Green';

-- Restock only M and L of the H&M jacket; S remains out of stock
INSERT INTO restocks (batch_number, supplier_id, restock_date)
VALUES ('RS-2026-00003', @supplier_hm, '2026-09-04');
SET @restock_hm = LAST_INSERT_ID();

INSERT INTO restock_items (restock_id, variant_id, quantity, unit_cost)
SELECT @restock_hm, v.variant_id, 10, ROUND(p.price * 0.45, 2)
FROM product_variants v
JOIN product_colors pc ON pc.product_color_id = v.product_color_id
JOIN products p ON p.product_id = pc.product_id
WHERE p.name = 'Felted Button-Detail Jacket'
  AND p.supplier_id = @supplier_hm
  AND pc.color = 'Dark Khaki Green'
  AND v.size IN ('M', 'L');

-- Add the restocked quantities to stock
UPDATE product_variants v
JOIN restock_items ri ON ri.variant_id = v.variant_id
SET v.stock = v.stock + ri.quantity;

-- One sample monthly bill
INSERT INTO expenses (expense_date, billing_month, category, description, amount)
VALUES ('2026-09-05', '2026-09-01', 'electricity', 'September electricity', 3200.00);
