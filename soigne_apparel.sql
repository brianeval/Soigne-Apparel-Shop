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
DROP VIEW  IF EXISTS all_costs, restock_totals, order_totals;
DROP TABLE IF EXISTS restock_items, restocks, expenses,
                     order_items, orders, cart,
                     product_variants, product_colors, products,
                     suppliers, customers, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 2. Users and customers
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id    INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  email      VARCHAR(120) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,              -- password_hash() output
  role       ENUM('customer','admin') NOT NULL DEFAULT 'customer',
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

-- ---------------------------------------------------------------------
-- 3. Suppliers (each supplier is also the brand)
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
  supplier_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Products, colors (one image per color) and variants (size + stock)
-- ---------------------------------------------------------------------
CREATE TABLE products (
  product_id  INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  name        VARCHAR(150)  NOT NULL,
  category    VARCHAR(50),
  price       DECIMAL(10,2) NOT NULL,
  description TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
) ENGINE=InnoDB;

CREATE TABLE product_colors (
  product_color_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id       INT         NOT NULL,
  color            VARCHAR(30) NOT NULL,
  image            VARCHAR(255),
  FOREIGN KEY (product_id) REFERENCES products(product_id),
  UNIQUE KEY uniq_product_color (product_id, color)
) ENGINE=InnoDB;

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
  status           ENUM('pending','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
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
CREATE VIEW order_totals AS
SELECT order_id, SUM(quantity * price) AS total
FROM order_items
GROUP BY order_id;

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

INSERT INTO customers (user_id, customer_name, phone, address) VALUES
(2, 'Juan Dela Cruz', '09171234567', '123 Sample St., Quezon City, Metro Manila');

-- Suppliers / brands
INSERT INTO suppliers (name) VALUES ('Soigné Basics'), ('Northline Outdoors');

-- Products (supplier 1 = Soigné Basics, supplier 2 = Northline Outdoors)
INSERT INTO products (supplier_id, name, category, price, description) VALUES
(1, 'Airy Cotton Crew Neck T-Shirt', 'T-shirts',  590.00,  'Lightweight everyday tee in breathable cotton.'),
(1, 'Premium Linen Long Sleeve Shirt', 'Shirts',  1490.00, 'Soft linen shirt that stays cool in the heat.'),
(1, 'Relaxed Ankle Chino Pants',  'Pants',        1290.00, 'Relaxed fit chinos with a cropped ankle.'),
(2, 'Lightweight Packable Jacket', 'Outerwear',   1790.00, 'Water-resistant jacket that folds into its own pocket.'),
(2, 'Quick-Dry Sports Shorts',    'Shorts',       790.00,  'Fast-drying shorts for training and travel.');

-- One image per color (put matching files in your images/ folder)
INSERT INTO product_colors (product_id, color, image) VALUES
(1, 'Red',   'tee-red.jpg'),
(1, 'Black', 'tee-black.jpg'),
(2, 'White', 'linen-shirt-white.jpg'),
(2, 'Navy',  'linen-shirt-navy.jpg'),
(3, 'Beige', 'chino-beige.jpg'),
(3, 'Navy',  'chino-navy.jpg'),
(4, 'Olive', 'jacket-olive.jpg'),
(4, 'Black', 'jacket-black.jpg'),
(5, 'Black', 'shorts-black.jpg'),
(5, 'Gray',  'shorts-gray.jpg');

-- Variants: sizes S, M, L for every color, starting with 0 stock
-- (stock is added by the sample restocks below)
INSERT INTO product_variants (product_color_id, size, stock)
SELECT pc.product_color_id, s.size, 0
FROM product_colors pc
CROSS JOIN (SELECT 'S' AS size UNION SELECT 'M' UNION SELECT 'L') s;

-- Restock 1: Soigné Basics, 10 of every variant at 40% of the selling price
INSERT INTO restocks (batch_number, supplier_id, restock_date)
VALUES ('RS-2026-00001', 1, '2026-09-01');

INSERT INTO restock_items (restock_id, variant_id, quantity, unit_cost)
SELECT 1, v.variant_id, 10, ROUND(p.price * 0.40, 2)
FROM product_variants v
JOIN product_colors pc ON pc.product_color_id = v.product_color_id
JOIN products p        ON p.product_id = pc.product_id
WHERE p.supplier_id = 1;

-- Restock 2: Northline Outdoors, 10 of every variant at 45% of the selling price
INSERT INTO restocks (batch_number, supplier_id, restock_date)
VALUES ('RS-2026-00002', 2, '2026-09-03');

INSERT INTO restock_items (restock_id, variant_id, quantity, unit_cost)
SELECT 2, v.variant_id, 10, ROUND(p.price * 0.45, 2)
FROM product_variants v
JOIN product_colors pc ON pc.product_color_id = v.product_color_id
JOIN products p        ON p.product_id = pc.product_id
WHERE p.supplier_id = 2;

-- Add the restocked quantities to stock
UPDATE product_variants v
JOIN restock_items ri ON ri.variant_id = v.variant_id
SET v.stock = v.stock + ri.quantity;

-- One sample paid order: Juan buys 2 Red / M tees and 1 Black / L jacket
INSERT INTO orders (user_id, status, shipping_address, created_at)
VALUES (2, 'paid', '123 Sample St., Quezon City, Metro Manila', '2026-09-20 14:30:00');

INSERT INTO order_items (order_id, variant_id, quantity, price)
SELECT 1, v.variant_id, 2, 590.00
FROM product_variants v
JOIN product_colors pc ON pc.product_color_id = v.product_color_id
WHERE pc.product_id = 1 AND pc.color = 'Red' AND v.size = 'M';

INSERT INTO order_items (order_id, variant_id, quantity, price)
SELECT 1, v.variant_id, 1, 1790.00
FROM product_variants v
JOIN product_colors pc ON pc.product_color_id = v.product_color_id
WHERE pc.product_id = 4 AND pc.color = 'Black' AND v.size = 'L';

-- Reduce stock for the sold items
UPDATE product_variants v
JOIN order_items oi ON oi.variant_id = v.variant_id
SET v.stock = v.stock - oi.quantity
WHERE oi.order_id = 1;

-- One sample monthly bill
INSERT INTO expenses (expense_date, billing_month, category, description, amount)
VALUES ('2026-09-05', '2026-09-01', 'electricity', 'September electricity', 3200.00);
