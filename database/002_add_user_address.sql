
-- VulnShop
-- Add shipping address columns to users table
--
-- This migration is for environments where
-- the address columns do not yet exist.
-- Do not rerun on an already-migrated database.

ALTER TABLE users
    ADD COLUMN postal_code VARCHAR(10) NULL,
    ADD COLUMN address VARCHAR(255) NULL,
    ADD COLUMN address_detail VARCHAR(255) NULL;
