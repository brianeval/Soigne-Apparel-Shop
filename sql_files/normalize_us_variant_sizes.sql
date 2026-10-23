-- Run after confirming no product-color variants already use the normalized sizes.
START TRANSACTION;

UPDATE product_variants
SET size = TRIM(SUBSTRING(size, 4))
WHERE UPPER(LEFT(size, 3)) = 'US ';

UPDATE product_variants v
JOIN product_colors pc ON pc.product_color_id = v.product_color_id
JOIN products p ON p.product_id = pc.product_id
JOIN suppliers s ON s.supplier_id = p.supplier_id
SET v.size = 'XXS'
WHERE s.name = 'PENSHOPPE'
  AND p.name = 'Essentials Classic Straight Fit Jeans'
  AND pc.color = 'Light Blue'
  AND v.size = 'SIZE 28';

COMMIT;
