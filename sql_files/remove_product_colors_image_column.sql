-- Run once against soigne_apparel after deploying code that reads/writes
-- product_color_images. Back up the database before running this migration.

INSERT IGNORE INTO product_color_images (product_color_id, image_path)
SELECT product_color_id, image
FROM product_colors
WHERE image IS NOT NULL
  AND image <> '';

ALTER TABLE product_colors
  DROP COLUMN image;
