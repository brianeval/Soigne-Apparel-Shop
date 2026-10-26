-- When both a source size and its normalized size exist, combine their stock.
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_variant_size_merges;

CREATE TEMPORARY TABLE tmp_variant_size_merges AS
SELECT target.variant_id AS target_variant_id,
       SUM(source.stock) AS source_stock
FROM product_variants AS source
JOIN product_variants AS target
  ON target.product_color_id = source.product_color_id
 AND target.size = TRIM(SUBSTRING(source.size, 4))
WHERE UPPER(LEFT(source.size, 3)) = 'US '
GROUP BY target.variant_id;

UPDATE product_variants AS target
JOIN tmp_variant_size_merges AS merges
  ON merges.target_variant_id = target.variant_id
SET target.stock = target.stock + merges.source_stock;

DELETE source
FROM product_variants AS source
JOIN product_variants AS target
  ON target.product_color_id = source.product_color_id
 AND target.size = TRIM(SUBSTRING(source.size, 4))
WHERE UPPER(LEFT(source.size, 3)) = 'US ';

DROP TEMPORARY TABLE tmp_variant_size_merges;

UPDATE product_variants
SET size = TRIM(SUBSTRING(size, 4))
WHERE UPPER(LEFT(size, 3)) = 'US ';

DROP TEMPORARY TABLE IF EXISTS tmp_variant_size_merges;

CREATE TEMPORARY TABLE tmp_variant_size_merges AS
SELECT source.variant_id AS source_variant_id,
       target.variant_id AS target_variant_id,
       source.stock AS source_stock
FROM product_variants AS source
JOIN product_colors AS source_color
  ON source_color.product_color_id = source.product_color_id
JOIN products AS p ON p.product_id = source_color.product_id
JOIN suppliers AS s ON s.supplier_id = p.supplier_id
JOIN product_variants AS target
  ON target.product_color_id = source.product_color_id
 AND target.size = 'XXS'
WHERE s.name = 'PENSHOPPE'
  AND p.name = 'Essentials Classic Straight Fit Jeans'
  AND source_color.color = 'Light Blue'
  AND source.size = 'SIZE 28';

UPDATE product_variants AS target
JOIN tmp_variant_size_merges AS merges
  ON merges.target_variant_id = target.variant_id
SET target.stock = target.stock + merges.source_stock;

DELETE source
FROM product_variants AS source
JOIN tmp_variant_size_merges AS merges
  ON merges.source_variant_id = source.variant_id;

DROP TEMPORARY TABLE tmp_variant_size_merges;

UPDATE product_variants AS v
JOIN product_colors AS pc ON pc.product_color_id = v.product_color_id
JOIN products AS p ON p.product_id = pc.product_id
JOIN suppliers AS s ON s.supplier_id = p.supplier_id
SET v.size = 'XXS'
WHERE s.name = 'PENSHOPPE'
  AND p.name = 'Essentials Classic Straight Fit Jeans'
  AND pc.color = 'Light Blue'
  AND v.size = 'SIZE 28';

COMMIT;
