-- ═══════════════════════════════════════════════════════════════
-- Migration: 001_requirements_gaps.sql
-- Database:  hobbyloop_db
-- Purpose:   Backfill 5 missing columns identified in requirements audit
--
-- Gaps addressed:
--   G1  users           → username VARCHAR(50) UNIQUE DEFAULT NULL
--   G2  user_addresses  → address_type ENUM('shipping','billing') DEFAULT 'shipping'
--   G3  payments        → billing_address, billing_city, billing_zip
--   G4  product_suppliers → lead_time_days INT DEFAULT 7
--   G6  promo_codes     → valid_from DATE DEFAULT NULL
--
-- Idempotency note: MySQL does not support IF NOT EXISTS on ALTER TABLE
-- column additions prior to 8.0. Run this migration only once, or wrap
-- in a stored procedure that checks INFORMATION_SCHEMA before altering.
-- ═══════════════════════════════════════════════════════════════

USE hobbyloop_db;

-- ── G1: users — add username ──
ALTER TABLE users
    ADD COLUMN username VARCHAR(50) UNIQUE DEFAULT NULL
    AFTER email;

-- Backfill username from email prefix for all existing users
UPDATE users
    SET username = SUBSTRING_INDEX(email, '@', 1)
    WHERE username IS NULL;

-- ── G2: user_addresses — add address_type ──
ALTER TABLE user_addresses
    ADD COLUMN address_type ENUM('shipping','billing') DEFAULT 'shipping'
    AFTER label;

-- ── G3: payments — add billing address fields ──
ALTER TABLE payments
    ADD COLUMN billing_address VARCHAR(255) DEFAULT NULL AFTER billing_name,
    ADD COLUMN billing_city    VARCHAR(100) DEFAULT NULL AFTER billing_address,
    ADD COLUMN billing_zip     VARCHAR(10)  DEFAULT NULL AFTER billing_city;

-- ── G4: product_suppliers — add lead_time_days ──
ALTER TABLE product_suppliers
    ADD COLUMN lead_time_days INT DEFAULT 7
    AFTER reorder_level;

-- ── G6: promo_codes — add valid_from ──
ALTER TABLE promo_codes
    ADD COLUMN valid_from DATE DEFAULT NULL
    BEFORE expires_at;
