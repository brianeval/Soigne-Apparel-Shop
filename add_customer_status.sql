-- Run this once against an existing soigne_apparel database.
-- Existing accounts are set to active by the column's default.
ALTER TABLE users
  ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'
  AFTER role;
