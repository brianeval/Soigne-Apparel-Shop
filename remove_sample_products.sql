USE soigne_apparel;

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_sample_products AS
SELECT product_id
FROM products
WHERE name IN (
  'Airy Cotton Crew Neck T-Shirt',
  'Premium Linen Long Sleeve Shirt',
  'Relaxed Ankle Chino Pants',
  'Lightweight Packable Jacket',
  'Quick-Dry Sports Shorts'
);

CREATE TEMPORARY TABLE tmp_sample_variants AS
SELECT v.variant_id
FROM product_variants AS v
JOIN product_colors AS pc ON pc.product_color_id = v.product_color_id
JOIN tmp_sample_products AS target ON target.product_id = pc.product_id;

DELETE c
FROM cart AS c
JOIN tmp_sample_variants AS target ON target.variant_id = c.variant_id;

DELETE oi
FROM order_items AS oi
JOIN tmp_sample_variants AS target ON target.variant_id = oi.variant_id;

DELETE ri
FROM restock_items AS ri
JOIN tmp_sample_variants AS target ON target.variant_id = ri.variant_id;

DELETE v
FROM product_variants AS v
JOIN tmp_sample_variants AS target ON target.variant_id = v.variant_id;

DELETE pc
FROM product_colors AS pc
JOIN tmp_sample_products AS target ON target.product_id = pc.product_id;

DELETE p
FROM products AS p
JOIN tmp_sample_products AS target ON target.product_id = p.product_id;

COMMIT;

DROP TEMPORARY TABLE tmp_sample_variants;
DROP TEMPORARY TABLE tmp_sample_products;
