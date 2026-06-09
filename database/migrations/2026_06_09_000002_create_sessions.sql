-- Phase 0 (Vercel/serverless) — database-backed PHP sessions.
-- Required when SESSION_DRIVER=database (serverless filesystem is ephemeral).
-- Compatible MySQL 8.4 / TiDB Cloud Serverless.

SET NAMES utf8mb4;

CREATE TABLE sessions (
  id            VARCHAR(128) NOT NULL,
  payload       MEDIUMTEXT NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
