-- Run once against an existing soigne_apparel database.
-- Keep the latest active feedback if the per-purchase version already created
-- multiple reviews for one product/customer; retain a deleted record only when
-- no active review exists for that product/customer.
DELETE old_review
FROM product_reviews AS old_review
JOIN product_reviews AS newer_review
  ON newer_review.product_id = old_review.product_id
 AND newer_review.user_id = old_review.user_id
 AND (
   CASE WHEN newer_review.status = 'deleted' THEN 0 ELSE 1 END
     > CASE WHEN old_review.status = 'deleted' THEN 0 ELSE 1 END
   OR (
     (newer_review.status = 'deleted') = (old_review.status = 'deleted')
     AND (
       newer_review.updated_at > old_review.updated_at
       OR (newer_review.updated_at = old_review.updated_at AND newer_review.review_id > old_review.review_id)
     )
   )
 );

ALTER TABLE product_reviews
  DROP INDEX uniq_product_review_order_item,
  ADD UNIQUE KEY uniq_product_review_user (product_id, user_id),
  ADD INDEX idx_product_reviews_order_item (order_item_id);
