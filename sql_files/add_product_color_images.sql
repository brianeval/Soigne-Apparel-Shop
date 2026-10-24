-- Run once against an existing soigne_apparel database.
CREATE TABLE IF NOT EXISTS product_color_images (
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

INSERT IGNORE INTO product_color_images (product_color_id, image_path)
SELECT product_color_id, image
FROM product_colors
WHERE image IS NOT NULL AND image <> '';
