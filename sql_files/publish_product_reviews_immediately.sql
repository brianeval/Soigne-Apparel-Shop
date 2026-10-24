-- Run once against an existing soigne_apparel database.
-- Newly submitted reviews are published immediately, without admin approval.
ALTER TABLE product_reviews
  MODIFY status ENUM('pending','published','deleted') NOT NULL DEFAULT 'published';

UPDATE product_reviews
SET status = 'published',
    published_at = COALESCE(published_at, created_at)
WHERE status = 'pending';
