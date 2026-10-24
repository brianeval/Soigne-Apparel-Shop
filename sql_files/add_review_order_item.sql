-- Run once against an existing soigne_apparel database.
-- Associates each review with the variant purchased for that review.
ALTER TABLE product_reviews
  ADD COLUMN order_item_id INT NULL AFTER user_id,
  ADD INDEX idx_product_reviews_order_item (order_item_id),
  ADD CONSTRAINT fk_product_reviews_order_item
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id)
    ON DELETE SET NULL;

UPDATE product_reviews r
SET r.order_item_id = (
  SELECT oi.order_item_id
  FROM order_items oi
  JOIN orders o ON o.order_id = oi.order_id
  JOIN product_variants v ON v.variant_id = oi.variant_id
  JOIN product_colors pc ON pc.product_color_id = v.product_color_id
  WHERE o.user_id = r.user_id
    AND pc.product_id = r.product_id
    AND o.status IN ('paid', 'shipped', 'delivered')
  ORDER BY o.order_id DESC, oi.order_item_id DESC
  LIMIT 1
)
WHERE r.order_item_id IS NULL
  AND EXISTS (
    SELECT 1
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    JOIN product_variants v ON v.variant_id = oi.variant_id
    JOIN product_colors pc ON pc.product_color_id = v.product_color_id
    WHERE o.user_id = r.user_id
      AND pc.product_id = r.product_id
      AND o.status IN ('paid', 'shipped', 'delivered')
  );
