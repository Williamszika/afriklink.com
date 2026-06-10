-- Phase 1 (start) — "Particulier" profile fields on users.
-- Compatible MySQL 8.4 / TiDB Cloud Serverless. Separate ADD COLUMN statements
-- for maximum compatibility. Run after the core schema (idempotent on a fresh DB).

SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN account_type ENUM('particulier','professionnel') NOT NULL DEFAULT 'particulier' AFTER role;
ALTER TABLE users ADD COLUMN full_name VARCHAR(191) NULL AFTER account_type;
ALTER TABLE users ADD COLUMN nickname  VARCHAR(64)  NULL AFTER full_name;
ALTER TABLE users ADD COLUMN birthdate DATE NULL AFTER nickname;
ALTER TABLE users ADD COLUMN gender ENUM('homme','femme','autre') NULL AFTER birthdate;
ALTER TABLE users ADD COLUMN city VARCHAR(128) NULL AFTER gender;
