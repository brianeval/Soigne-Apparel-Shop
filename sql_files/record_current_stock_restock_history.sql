USE soigne_apparel;

DROP TEMPORARY TABLE IF EXISTS tmp_current_stock_restock;
DROP TEMPORARY TABLE IF EXISTS tmp_current_stock_batches;

CREATE TEMPORARY TABLE tmp_current_stock_restock (
    variant_id INT NOT NULL PRIMARY KEY,
    supplier_id INT NOT NULL,
    current_stock INT NOT NULL,
    unit_cost DECIMAL(10, 2) NOT NULL
) ENGINE=InnoDB;

CREATE TEMPORARY TABLE tmp_current_stock_batches (
    supplier_id INT NOT NULL PRIMARY KEY,
    temporary_batch_number VARCHAR(20) NOT NULL UNIQUE,
    restock_id INT NULL
) ENGINE=InnoDB;

START TRANSACTION;

-- Record each available variant's current stock and 80% of its product price.
INSERT INTO tmp_current_stock_restock (variant_id, supplier_id, current_stock, unit_cost)
SELECT v.variant_id,
       p.supplier_id,
       v.stock,
       ROUND(p.price * 0.80, 2)
FROM product_variants AS v
JOIN product_colors AS pc ON pc.product_color_id = v.product_color_id
JOIN products AS p ON p.product_id = pc.product_id
WHERE v.stock > 0;

-- Create one history batch for each supplier.
INSERT INTO tmp_current_stock_batches (supplier_id, temporary_batch_number)
SELECT supplier_id,
       CONCAT('T', RIGHT(CAST(UUID_SHORT() AS CHAR), 19))
FROM (
    SELECT DISTINCT supplier_id
    FROM tmp_current_stock_restock
) AS suppliers;

INSERT INTO restocks (batch_number, supplier_id, restock_date)
SELECT temporary_batch_number, supplier_id, CURDATE()
FROM tmp_current_stock_batches;

-- Match each new history batch to its restock record.
UPDATE tmp_current_stock_batches AS batches
JOIN restocks AS restock
    ON restock.batch_number = batches.temporary_batch_number
SET batches.restock_id = restock.restock_id;

-- Save the historical quantities and costs without changing product stock.
INSERT INTO restock_items (restock_id, variant_id, quantity, unit_cost)
SELECT batches.restock_id,
       stock.variant_id,
       stock.current_stock,
       stock.unit_cost
FROM tmp_current_stock_restock AS stock
JOIN tmp_current_stock_batches AS batches
    ON batches.supplier_id = stock.supplier_id;

-- Use the same batch number format as the inventory page.
UPDATE restocks AS restock
JOIN tmp_current_stock_batches AS batches
    ON batches.restock_id = restock.restock_id
SET restock.batch_number = CONCAT(
    'RS-',
    YEAR(CURDATE()),
    '-',
    LPAD(restock.restock_id, 5, '0')
);

-- Show the history rows created by this script.
SELECT COUNT(*) AS variants_recorded,
       COALESCE(SUM(current_stock), 0) AS units_recorded,
       COALESCE(ROUND(SUM(current_stock * unit_cost), 2), 0.00) AS recorded_cost
FROM tmp_current_stock_restock;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_current_stock_restock;
DROP TEMPORARY TABLE IF EXISTS tmp_current_stock_batches;
