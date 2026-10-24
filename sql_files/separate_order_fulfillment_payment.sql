-- Run once against an existing soigne_apparel database.
-- Legacy "paid" orders are treated as delivered and paid; other statuses are
-- migrated as fulfillment states with payment still unpaid.
ALTER TABLE orders
  ADD COLUMN fulfillment_status ENUM('pending','shipped','delivered','failed_delivery','cancelled') NOT NULL DEFAULT 'pending' AFTER user_id,
  ADD COLUMN payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid' AFTER fulfillment_status;

UPDATE orders
SET fulfillment_status = CASE status
      WHEN 'shipped' THEN 'shipped'
      WHEN 'delivered' THEN 'delivered'
      WHEN 'cancelled' THEN 'cancelled'
      WHEN 'paid' THEN 'delivered'
      ELSE 'pending'
    END,
    payment_status = CASE WHEN status = 'paid' THEN 'paid' ELSE 'unpaid' END;

ALTER TABLE orders
  DROP COLUMN status;
