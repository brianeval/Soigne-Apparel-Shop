


ALTER TABLE users
  MODIFY role ENUM('customer','admin','vip','banned') NOT NULL DEFAULT 'customer';

UPDATE users
SET role = 'customer', vip_expires_at = NULL
WHERE role = 'banned';

ALTER TABLE users
  MODIFY role ENUM('customer','admin','vip') NOT NULL DEFAULT 'customer';
