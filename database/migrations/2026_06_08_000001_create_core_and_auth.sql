-- Phase 0 — Core account + authentication & security tables.
-- AfrikaLink (MySQL 8.4 LTS / MariaDB 11.4+). InnoDB, utf8mb4.
-- Amounts are stored elsewhere in cents (INT) + currency; public ids are UUID CHAR(36).
-- Run with a DDL-capable account (database/migrate.php), NOT the prod app account.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================
-- Accounts
-- ============================================================

CREATE TABLE users (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id          CHAR(36) NOT NULL,
  email              VARCHAR(191) NOT NULL,
  email_verified_at  DATETIME NULL,
  phone              VARCHAR(32) NULL,
  password_hash      VARCHAR(255) NOT NULL,
  role               ENUM('user','vendor','admin') NOT NULL DEFAULT 'user',
  locale             CHAR(5) NOT NULL DEFAULT 'fr',
  country_code       CHAR(2) NULL,
  preferred_currency CHAR(3) NOT NULL DEFAULT 'EUR',
  status             ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_public_id (public_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_addresses (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  label          VARCHAR(64) NULL,
  recipient_name VARCHAR(128) NOT NULL,
  line1          VARCHAR(191) NOT NULL,
  line2          VARCHAR(191) NULL,
  city           VARCHAR(128) NOT NULL,
  region         VARCHAR(128) NULL,
  postal_code    VARCHAR(32) NULL,
  country_code   CHAR(2) NOT NULL,
  phone          VARCHAR(32) NULL,
  is_default     TINYINT(1) NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addr_user (user_id),
  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Security & technical
-- ============================================================

CREATE TABLE login_attempts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(191) NULL,
  ip         VARBINARY(16) NULL,
  success    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_email (email),
  KEY idx_login_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rate_limits (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_key   VARCHAR(191) NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rl_bucket (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pwreset_user (user_id),
  KEY idx_pwreset_token (token_hash),
  CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE email_verifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  verified_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_emailver_user (user_id),
  KEY idx_emailver_token (token_hash),
  CONSTRAINT fk_emailver_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id BIGINT UNSIGNED NULL,
  action        VARCHAR(128) NOT NULL,
  target_type   VARCHAR(64) NULL,
  target_id     BIGINT UNSIGNED NULL,
  ip            VARBINARY(16) NULL,
  meta          JSON NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_actor (actor_user_id),
  KEY idx_audit_action (action),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
