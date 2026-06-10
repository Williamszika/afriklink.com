-- Phase 1 — allow signing up with EITHER email OR phone.
-- email becomes optional (NULL allowed); phone gets a UNIQUE index.
-- MySQL/TiDB: a UNIQUE index allows multiple NULLs, so email-less or phone-less
-- accounts coexist. Compatible MySQL 8.4 / TiDB Cloud Serverless.

SET NAMES utf8mb4;

ALTER TABLE users MODIFY COLUMN email VARCHAR(191) NULL;
ALTER TABLE users ADD UNIQUE KEY uq_users_phone (phone);
