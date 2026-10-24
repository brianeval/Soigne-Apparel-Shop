-- Run once after migrate_product_categories.sql against an existing database.
-- Category names are now stored only in categories and referenced by products.category_id.
ALTER TABLE products
  DROP COLUMN category;
