-- AfrikaLink — schéma de départ (MySQL 8.4 LTS / MariaDB 11.4+)
-- Conventions : InnoDB, utf8mb4, montants en centimes (INT) + currency CHAR(3),
-- public_id (CHAR 36 / UUID) pour les URLs publiques.
-- Jouer ce script avec un compte ayant les droits DDL (pas le compte applicatif de prod).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================
-- 1. CŒUR COMMUN
-- ============================================================

CREATE TABLE users (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id         CHAR(36) NOT NULL,
  email             VARCHAR(191) NOT NULL,
  email_verified_at DATETIME NULL,
  phone             VARCHAR(32) NULL,
  password_hash     VARCHAR(255) NOT NULL,
  role              ENUM('user','vendor','admin') NOT NULL DEFAULT 'user',
  locale            CHAR(5) NOT NULL DEFAULT 'fr',
  country_code      CHAR(2) NULL,
  preferred_currency CHAR(3) NOT NULL DEFAULT 'EUR',
  status            ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at        DATETIME NULL,
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
-- 2. VENDEURS & VERTICALES
-- ============================================================

CREATE TABLE vendors (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id         CHAR(36) NOT NULL,
  owner_user_id     BIGINT UNSIGNED NOT NULL,
  type              ENUM('shop','restaurant','salon','service') NOT NULL,
  name              VARCHAR(191) NOT NULL,
  slug              VARCHAR(191) NOT NULL,
  description       TEXT NULL,
  country_code      CHAR(2) NOT NULL,
  city              VARCHAR(128) NULL,
  currency          CHAR(3) NOT NULL DEFAULT 'EUR',
  logo_path         VARCHAR(255) NULL,
  cover_path        VARCHAR(255) NULL,
  kyc_status        ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none',
  stripe_account_id VARCHAR(64) NULL,
  status            ENUM('draft','active','suspended') NOT NULL DEFAULT 'draft',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at        DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vendors_public_id (public_id),
  UNIQUE KEY uq_vendors_slug (slug),
  KEY idx_vendors_owner (owner_user_id),
  KEY idx_vendors_discovery (type, country_code, status),
  CONSTRAINT fk_vendors_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendor_locations (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id    BIGINT UNSIGNED NOT NULL,
  line1        VARCHAR(191) NOT NULL,
  city         VARCHAR(128) NOT NULL,
  country_code CHAR(2) NOT NULL,
  lat          DECIMAL(10,7) NULL,
  lng          DECIMAL(10,7) NULL,
  PRIMARY KEY (id),
  KEY idx_vloc_vendor (vendor_id),
  CONSTRAINT fk_vloc_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  name      VARCHAR(128) NOT NULL,
  slug      VARCHAR(128) NOT NULL,
  type      ENUM('product','service') NOT NULL DEFAULT 'product',
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent (parent_id),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. BOUTIQUES (produits)
-- ============================================================

CREATE TABLE products (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id    CHAR(36) NOT NULL,
  vendor_id    BIGINT UNSIGNED NOT NULL,
  category_id  BIGINT UNSIGNED NULL,
  title        VARCHAR(191) NOT NULL,
  slug         VARCHAR(191) NOT NULL,
  description  TEXT NULL,
  price_cents  INT UNSIGNED NOT NULL,
  currency     CHAR(3) NOT NULL DEFAULT 'EUR',
  stock        INT NOT NULL DEFAULT 0,
  `condition`  ENUM('new','used','refurbished') NOT NULL DEFAULT 'new',
  weight_grams INT UNSIGNED NULL,
  status       ENUM('draft','active','out_of_stock','archived') NOT NULL DEFAULT 'draft',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_public_id (public_id),
  KEY idx_products_vendor (vendor_id, status),
  KEY idx_products_category (category_id),
  FULLTEXT KEY ft_products (title, description),
  CONSTRAINT fk_products_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_images (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  path       VARCHAR(255) NOT NULL,
  position   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pimg_product (product_id),
  CONSTRAINT fk_pimg_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. RESTAURANTS (menus)
-- ============================================================

CREATE TABLE menus (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id BIGINT UNSIGNED NOT NULL,
  name      VARCHAR(128) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_menus_vendor (vendor_id),
  CONSTRAINT fk_menus_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_categories (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_id  BIGINT UNSIGNED NOT NULL,
  name     VARCHAR(128) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_mcat_menu (menu_id),
  CONSTRAINT fk_mcat_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_items (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_category_id BIGINT UNSIGNED NOT NULL,
  name             VARCHAR(191) NOT NULL,
  description      TEXT NULL,
  price_cents      INT UNSIGNED NOT NULL,
  currency         CHAR(3) NOT NULL DEFAULT 'EUR',
  image_path       VARCHAR(255) NULL,
  is_available     TINYINT(1) NOT NULL DEFAULT 1,
  allergens        JSON NULL,
  position         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_mitem_cat (menu_category_id),
  CONSTRAINT fk_mitem_cat FOREIGN KEY (menu_category_id) REFERENCES menu_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. SALONS & MÉTIERS (prestations + réservations)
-- ============================================================

CREATE TABLE services (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id    CHAR(36) NOT NULL,
  vendor_id    BIGINT UNSIGNED NOT NULL,
  name         VARCHAR(191) NOT NULL,
  description  TEXT NULL,
  duration_min INT UNSIGNED NOT NULL DEFAULT 30,
  price_cents  INT UNSIGNED NULL,
  currency     CHAR(3) NOT NULL DEFAULT 'EUR',
  pricing_type ENUM('fixed','quote') NOT NULL DEFAULT 'fixed',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_public_id (public_id),
  KEY idx_services_vendor (vendor_id, is_active),
  CONSTRAINT fk_services_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE staff (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id BIGINT UNSIGNED NOT NULL,
  name      VARCHAR(128) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_staff_vendor (vendor_id),
  CONSTRAINT fk_staff_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE availability_rules (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id  BIGINT UNSIGNED NOT NULL,
  staff_id   BIGINT UNSIGNED NULL,
  weekday    TINYINT NOT NULL,            -- 0=dimanche ... 6=samedi
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_avail_vendor (vendor_id),
  CONSTRAINT fk_avail_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_avail_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE availability_exceptions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id  BIGINT UNSIGNED NOT NULL,
  date       DATE NOT NULL,
  is_closed  TINYINT(1) NOT NULL DEFAULT 1,
  start_time TIME NULL,
  end_time   TIME NULL,
  PRIMARY KEY (id),
  KEY idx_availx_vendor (vendor_id, date),
  CONSTRAINT fk_availx_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. COMMERCE (commandes, paiements) — bookings après payments (FK)
-- ============================================================

CREATE TABLE orders (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id                CHAR(36) NOT NULL,
  buyer_user_id            BIGINT UNSIGNED NOT NULL,
  vendor_id                BIGINT UNSIGNED NOT NULL,
  order_type               ENUM('product','food','booking') NOT NULL,
  subtotal_cents           INT UNSIGNED NOT NULL DEFAULT 0,
  shipping_cents           INT UNSIGNED NOT NULL DEFAULT 0,
  tax_cents                INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents              INT UNSIGNED NOT NULL DEFAULT 0,
  platform_fee_cents       INT UNSIGNED NOT NULL DEFAULT 0,
  currency                 CHAR(3) NOT NULL DEFAULT 'EUR',
  status                   ENUM('pending','paid','processing','shipped','delivered','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  shipping_address_id      BIGINT UNSIGNED NULL,
  stripe_payment_intent_id VARCHAR(64) NULL,
  created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_public_id (public_id),
  KEY idx_orders_buyer (buyer_user_id),
  KEY idx_orders_vendor (vendor_id, status),
  CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  CONSTRAINT fk_orders_addr FOREIGN KEY (shipping_address_id) REFERENCES user_addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id         BIGINT UNSIGNED NOT NULL,
  item_type        ENUM('product','menu_item','service') NOT NULL,
  item_id          BIGINT UNSIGNED NOT NULL,
  title_snapshot   VARCHAR(191) NOT NULL,
  quantity         INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price_cents INT UNSIGNED NOT NULL,
  currency         CHAR(3) NOT NULL DEFAULT 'EUR',
  PRIMARY KEY (id),
  KEY idx_oitem_order (order_id),
  CONSTRAINT fk_oitem_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id          BIGINT UNSIGNED NULL,
  user_id           BIGINT UNSIGNED NOT NULL,
  provider          ENUM('stripe') NOT NULL DEFAULT 'stripe',
  provider_intent_id VARCHAR(64) NULL,
  provider_event_id VARCHAR(64) NULL,
  amount_cents      INT UNSIGNED NOT NULL,
  currency          CHAR(3) NOT NULL DEFAULT 'EUR',
  status            ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
  raw_payload       JSON NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_event (provider_event_id),  -- idempotence webhook
  KEY idx_payments_order (order_id),
  KEY idx_payments_user (user_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id        CHAR(36) NOT NULL,
  service_id       BIGINT UNSIGNED NOT NULL,
  vendor_id        BIGINT UNSIGNED NOT NULL,
  staff_id         BIGINT UNSIGNED NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  start_at         DATETIME NOT NULL,
  end_at           DATETIME NOT NULL,
  price_cents      INT UNSIGNED NULL,
  currency         CHAR(3) NOT NULL DEFAULT 'EUR',
  status           ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  payment_id       BIGINT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bookings_public_id (public_id),
  KEY idx_bookings_slot (vendor_id, start_at),
  KEY idx_bookings_customer (customer_user_id),
  CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_bookings_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  CONSTRAINT fk_bookings_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_user_id) REFERENCES users(id),
  CONSTRAINT fk_bookings_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payouts (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id           BIGINT UNSIGNED NOT NULL,
  amount_cents        INT UNSIGNED NOT NULL,
  currency            CHAR(3) NOT NULL DEFAULT 'EUR',
  status              ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  provider_transfer_id VARCHAR(64) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payouts_vendor (vendor_id),
  CONSTRAINT fk_payouts_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. INTERNATIONAL (livraison, taxes, change)
-- ============================================================

CREATE TABLE shipping_zones (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id     BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(128) NOT NULL,
  country_codes JSON NOT NULL,
  PRIMARY KEY (id),
  KEY idx_szone_vendor (vendor_id),
  CONSTRAINT fk_szone_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shipping_rates (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shipping_zone_id BIGINT UNSIGNED NOT NULL,
  name             VARCHAR(128) NOT NULL,
  price_cents      INT UNSIGNED NOT NULL,
  currency         CHAR(3) NOT NULL DEFAULT 'EUR',
  min_weight_grams INT UNSIGNED NULL,
  max_weight_grams INT UNSIGNED NULL,
  min_order_cents  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_srate_zone (shipping_zone_id),
  CONSTRAINT fk_srate_zone FOREIGN KEY (shipping_zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tax_rules (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  country_code CHAR(2) NOT NULL,
  rate_bps     INT UNSIGNED NOT NULL,   -- points de base : 2000 = 20%
  applies_to   ENUM('all','product','food','service') NOT NULL DEFAULT 'all',
  PRIMARY KEY (id),
  KEY idx_tax_country (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exchange_rates (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  base       CHAR(3) NOT NULL,
  quote      CHAR(3) NOT NULL,
  rate       DECIMAL(18,8) NOT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fx_pair (base, quote)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. CONFIANCE (avis, messagerie, modération)
-- ============================================================

CREATE TABLE reviews (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  author_user_id BIGINT UNSIGNED NOT NULL,
  target_type    ENUM('vendor','product') NOT NULL,
  target_id      BIGINT UNSIGNED NOT NULL,
  rating         TINYINT UNSIGNED NOT NULL,
  comment        TEXT NULL,
  order_id       BIGINT UNSIGNED NULL,
  status         ENUM('published','pending','removed') NOT NULL DEFAULT 'pending',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reviews_target (target_type, target_id),
  KEY idx_reviews_author (author_user_id),
  CONSTRAINT fk_reviews_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE conversations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buyer_user_id   BIGINT UNSIGNED NOT NULL,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  last_message_at DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conv_pair (buyer_user_id, vendor_id),
  KEY idx_conv_vendor (vendor_id),
  CONSTRAINT fk_conv_buyer FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_user_id  BIGINT UNSIGNED NOT NULL,
  body            TEXT NOT NULL,
  read_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_conv (conversation_id),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reports (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_user_id  BIGINT UNSIGNED NOT NULL,
  target_type       VARCHAR(32) NOT NULL,
  target_id         BIGINT UNSIGNED NOT NULL,
  reason            VARCHAR(255) NOT NULL,
  status            ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reports_target (target_type, target_id),
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. SÉCURITÉ & TECHNIQUE
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
