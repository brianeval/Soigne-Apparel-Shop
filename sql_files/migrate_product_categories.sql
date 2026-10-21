-- One-time migration for an existing soigne_apparel database.
-- Run this file after selecting the soigne_apparel database.
-- The legacy products.category column is retained temporarily for compatibility.

CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
  ('T-shirts'), ('Shirts'), ('Polos'), ('Hoodies'), ('Sweaters'),
  ('Pants'), ('Jeans'), ('Chinos'), ('Shorts'), ('Outerwear');

INSERT INTO categories (name)
SELECT DISTINCT
  CASE LOWER(TRIM(category))
    WHEN 't-shirt' THEN 'T-shirts'
    WHEN 't-shirts' THEN 'T-shirts'
    WHEN 't shirts' THEN 'T-shirts'
    WHEN 'shirt' THEN 'Shirts'
    WHEN 'shirts' THEN 'Shirts'
    WHEN 'polo' THEN 'Polos'
    WHEN 'polos' THEN 'Polos'
    WHEN 'hoodie' THEN 'Hoodies'
    WHEN 'hoodies' THEN 'Hoodies'
    WHEN 'sweater' THEN 'Sweaters'
    WHEN 'sweaters' THEN 'Sweaters'
    WHEN 'pant' THEN 'Pants'
    WHEN 'pants' THEN 'Pants'
    WHEN 'jean' THEN 'Jeans'
    WHEN 'jeans' THEN 'Jeans'
    WHEN 'chino' THEN 'Chinos'
    WHEN 'chinos' THEN 'Chinos'
    WHEN 'short' THEN 'Shorts'
    WHEN 'shorts' THEN 'Shorts'
    WHEN 'outerwear' THEN 'Outerwear'
    ELSE TRIM(category)
  END
FROM products
WHERE category IS NOT NULL
  AND TRIM(category) <> ''
ON DUPLICATE KEY UPDATE name = VALUES(name);

ALTER TABLE products
  ADD COLUMN category_id INT NULL AFTER category,
  ADD INDEX idx_products_category_id (category_id),
  ADD CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;

UPDATE products AS p
JOIN categories AS c
  ON c.name = CASE LOWER(TRIM(p.category))
    WHEN 't-shirt' THEN 'T-shirts'
    WHEN 't-shirts' THEN 'T-shirts'
    WHEN 't shirts' THEN 'T-shirts'
    WHEN 'shirt' THEN 'Shirts'
    WHEN 'shirts' THEN 'Shirts'
    WHEN 'polo' THEN 'Polos'
    WHEN 'polos' THEN 'Polos'
    WHEN 'hoodie' THEN 'Hoodies'
    WHEN 'hoodies' THEN 'Hoodies'
    WHEN 'sweater' THEN 'Sweaters'
    WHEN 'sweaters' THEN 'Sweaters'
    WHEN 'pant' THEN 'Pants'
    WHEN 'pants' THEN 'Pants'
    WHEN 'jean' THEN 'Jeans'
    WHEN 'jeans' THEN 'Jeans'
    WHEN 'chino' THEN 'Chinos'
    WHEN 'chinos' THEN 'Chinos'
    WHEN 'short' THEN 'Shorts'
    WHEN 'shorts' THEN 'Shorts'
    WHEN 'outerwear' THEN 'Outerwear'
    ELSE TRIM(p.category)
  END
SET p.category_id = c.category_id
WHERE p.category IS NOT NULL
  AND TRIM(p.category) <> '';

SELECT c.name AS category, COUNT(p.product_id) AS product_count
FROM categories AS c
LEFT JOIN products AS p ON p.category_id = c.category_id
GROUP BY c.category_id, c.name
ORDER BY c.name;
