-- Run after selecting the soigne_apparel database.
-- Replaces the order_totals view with a single transaction-details view.
-- This changes views only; it does not modify table data.

DROP VIEW IF EXISTS order_transaction_details;
DROP VIEW IF EXISTS order_totals;

CREATE VIEW order_transaction_details AS
SELECT
  o.order_id AS transaction_id,
  o.order_id,
  o.user_id,
  o.fulfillment_status,
  o.payment_status,
  o.shipping_address,
  o.created_at AS transaction_date,
  oi.order_item_id,
  p.product_id,
  p.name AS product_name,
  p.department,
  pc.product_color_id,
  pc.color,
  v.variant_id,
  v.size,
  oi.quantity,
  oi.price AS unit_price,
  oi.quantity * oi.price AS line_subtotal,
  ot.total AS order_total
FROM orders AS o
JOIN order_items AS oi ON oi.order_id = o.order_id
JOIN product_variants AS v ON v.variant_id = oi.variant_id
JOIN product_colors AS pc ON pc.product_color_id = v.product_color_id
JOIN products AS p ON p.product_id = pc.product_id
JOIN (
  SELECT order_id, SUM(quantity * price) AS total
  FROM order_items
  GROUP BY order_id
) AS ot ON ot.order_id = o.order_id;
