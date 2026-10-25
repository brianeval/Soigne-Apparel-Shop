-- Run once against an existing soigne_apparel database.
ALTER TABLE users
  MODIFY role ENUM('customer','admin','vip') NOT NULL DEFAULT 'customer',
  ADD COLUMN vip_expires_at DATETIME NULL AFTER status;
