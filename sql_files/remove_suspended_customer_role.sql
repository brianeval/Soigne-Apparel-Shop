-- Run once against an existing soigne_apparel database.
-- Previously suspended customers become regular customers. The active/inactive
-- account status continues to control whether an account can sign in.
ALTER TABLE users
  MODIFY role ENUM('customer','admin','vip','banned') NOT NULL DEFAULT 'customer';

UPDATE users
SET role = 'customer', vip_expires_at = NULL
WHERE role = 'banned';

ALTER TABLE users
  MODIFY role ENUM('customer','admin','vip') NOT NULL DEFAULT 'customer';
