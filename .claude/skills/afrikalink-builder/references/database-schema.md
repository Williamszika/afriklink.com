# Schéma de base de données — AfrikaLink

> Vue commentée. Le fichier exécutable de départ est `assets/schema.sql`.
> Conventions : `InnoDB`, `utf8mb4`, montants en **centimes** (`INT`/`BIGINT`) + `currency`
> (ISO 4217, ex. `EUR`, `USD`, `XOF`, `NGN`), identifiants publics via `public_id` (UUID/CHAR(36))
> pour ne pas exposer les `id` auto-incrémentés dans les URLs.

## Sommaire
1. Cœur commun
2. Vendeurs & verticales
3. Boutiques (produits)
4. Restaurants (menus)
5. Salons & métiers (prestations + réservations)
6. Commerce (panier, commandes, paiements)
7. International (adresses, livraison, taxes)
8. Confiance (avis, messagerie, modération)
9. Sécurité & technique

---

## 1. Cœur commun

**users** — comptes (un compte peut être acheteur ET vendeur)
- `id` BIGINT PK, `public_id` CHAR(36) UNIQUE
- `email` UNIQUE, `email_verified_at` NULL
- `phone` NULL, `password_hash`
- `role` ENUM('user','vendor','admin') défaut 'user'
- `locale` CHAR(5) défaut 'fr', `country_code` CHAR(2), `preferred_currency` CHAR(3)
- `status` ENUM('active','suspended','pending') défaut 'active'
- `created_at`, `updated_at`, `deleted_at` NULL

**user_addresses** — adresses (livraison/facturation, internationales)
- `id`, `user_id` FK
- `label`, `recipient_name`, `line1`, `line2`, `city`, `region`, `postal_code`,
  `country_code` CHAR(2), `phone`
- `is_default` BOOL

## 2. Vendeurs & verticales

**vendors** — un profil de vendeur appartient à un user ; son `type` détermine la verticale
- `id`, `public_id` CHAR(36) UNIQUE, `owner_user_id` FK → users
- `type` ENUM('shop','restaurant','salon','service')
- `name`, `slug` UNIQUE, `description`
- `country_code` CHAR(2), `city`, `currency` CHAR(3)
- `logo_path`, `cover_path`
- `kyc_status` ENUM('none','pending','verified','rejected') défaut 'none'
- `stripe_account_id` NULL (Stripe Connect)
- `status` ENUM('draft','active','suspended') défaut 'draft'
- `created_at`, `updated_at`, `deleted_at`
- INDEX sur (`type`,`country_code`,`status`), (`slug`)

**vendor_locations** — adresse(s) physique(s) d'un vendeur (utile restaurants/salons)
- `id`, `vendor_id` FK, champs adresse, `lat` DECIMAL, `lng` DECIMAL

**categories** — taxonomie hiérarchique (produits ET métiers)
- `id`, `parent_id` NULL (auto-réf), `name`, `slug`, `type` ENUM('product','service')

## 3. Boutiques (produits)

**products**
- `id`, `public_id`, `vendor_id` FK, `category_id` FK NULL
- `title`, `slug`, `description`
- `price_cents` INT, `currency` CHAR(3)
- `stock` INT défaut 0, `condition` ENUM('new','used','refurbished')
- `weight_grams` INT NULL (calcul livraison)
- `status` ENUM('draft','active','out_of_stock','archived')
- `created_at`, `updated_at`, `deleted_at`
- INDEX (`vendor_id`,`status`), FULLTEXT(`title`,`description`) pour la recherche

**product_images** — `id`, `product_id` FK, `path`, `position` INT

## 4. Restaurants (menus)

**menus** — `id`, `vendor_id` FK, `name`, `is_active` BOOL

**menu_categories** — `id`, `menu_id` FK, `name`, `position`

**menu_items**
- `id`, `menu_category_id` FK, `name`, `description`
- `price_cents` INT, `currency` CHAR(3)
- `image_path`, `is_available` BOOL, `allergens` JSON NULL
- `position` INT

## 5. Salons & métiers (prestations + réservations)

> Salons (coiffure) et métiers/services partagent le même socle : des **prestations** réservables.

**services** — prestation proposée (coupe, plomberie, cours…)
- `id`, `public_id`, `vendor_id` FK
- `name`, `description`
- `duration_min` INT (durée du créneau)
- `price_cents` INT, `currency` CHAR(3)
- `pricing_type` ENUM('fixed','quote') — prix fixe ou sur devis
- `is_active` BOOL

**staff** — intervenant(s) optionnels d'un vendeur (un salon a plusieurs coiffeurs)
- `id`, `vendor_id` FK, `name`, `is_active`

**availability_rules** — horaires d'ouverture récurrents
- `id`, `vendor_id` FK, `staff_id` FK NULL
- `weekday` TINYINT (0–6), `start_time` TIME, `end_time` TIME

**availability_exceptions** — fermetures/ouvertures ponctuelles
- `id`, `vendor_id` FK, `date`, `is_closed` BOOL, `start_time` NULL, `end_time` NULL

**bookings** — réservation d'un créneau
- `id`, `public_id`, `service_id` FK, `vendor_id` FK, `staff_id` FK NULL
- `customer_user_id` FK
- `start_at` DATETIME, `end_at` DATETIME
- `price_cents` INT, `currency` CHAR(3)
- `status` ENUM('pending','confirmed','completed','cancelled','no_show')
- `payment_id` FK NULL
- `created_at`, `updated_at`
- INDEX (`vendor_id`,`start_at`), contrainte applicative anti-chevauchement (voir BookingService)

## 6. Commerce (panier, commandes, paiements)

**carts** / **cart_items** — panier (boutiques/restaurants)
- carts : `id`, `user_id` FK NULL (ou session token), `created_at`
- cart_items : `id`, `cart_id` FK, `item_type` ENUM('product','menu_item'), `item_id`,
  `quantity`, `unit_price_cents`, `currency`

**orders**
- `id`, `public_id`, `buyer_user_id` FK, `vendor_id` FK
- `order_type` ENUM('product','food','booking')
- `subtotal_cents`, `shipping_cents`, `tax_cents`, `total_cents` (tous INT), `currency`
- `platform_fee_cents` INT — commission AfrikaLink
- `status` ENUM('pending','paid','processing','shipped','delivered','completed','cancelled','refunded')
- `shipping_address_id` FK NULL
- `stripe_payment_intent_id` NULL
- `created_at`, `updated_at`
- INDEX (`buyer_user_id`), (`vendor_id`,`status`)

**order_items** — `id`, `order_id` FK, `item_type`, `item_id`, `title_snapshot`,
`quantity`, `unit_price_cents`, `currency`
> Toujours figer un *snapshot* du prix/titre au moment de la commande.

**payments** — trace des paiements
- `id`, `order_id` FK NULL, `booking_id` FK NULL, `user_id` FK
- `provider` ENUM('stripe'), `provider_intent_id`, `provider_event_id` UNIQUE (idempotence)
- `amount_cents`, `currency`, `status` ENUM('pending','succeeded','failed','refunded')
- `raw_payload` JSON NULL, `created_at`

**payouts** — reversements aux vendeurs (Stripe Connect)
- `id`, `vendor_id` FK, `amount_cents`, `currency`, `status`, `provider_transfer_id`, `created_at`

## 7. International (adresses, livraison, taxes)

**shipping_zones** — `id`, `vendor_id` FK, `name`, `country_codes` JSON (liste)

**shipping_rates** — `id`, `shipping_zone_id` FK, `name`,
`price_cents`, `currency`, `min_weight_grams` NULL, `max_weight_grams` NULL,
`min_order_cents` NULL (livraison gratuite au-dessus d'un seuil)

**tax_rules** — `id`, `country_code`, `rate_bps` INT (taux en points de base, ex. 2000 = 20%),
`applies_to` ENUM('all','product','food','service')
> La TVA marketplace est complexe (IOSS, seuils). Voir `references/compliance.md`.

**currencies** / **exchange_rates** — `code` CHAR(3) PK ; rates : `base`, `quote`, `rate` DECIMAL,
`fetched_at`. Mettre à jour via tâche planifiée depuis une API de change.

## 8. Confiance (avis, messagerie, modération)

**reviews** — `id`, `author_user_id` FK, `target_type` ENUM('vendor','product'), `target_id`,
`rating` TINYINT (1–5), `comment`, `order_id` FK NULL (avis vérifié = lié à un achat),
`status` ENUM('published','pending','removed'), `created_at`

**conversations** / **messages** — messagerie acheteur ↔ vendeur
- conversations : `id`, `buyer_user_id`, `vendor_id`, `last_message_at`
- messages : `id`, `conversation_id` FK, `sender_user_id` FK, `body`, `read_at` NULL, `created_at`

**reports** — signalements (contenu/utilisateur)
- `id`, `reporter_user_id`, `target_type`, `target_id`, `reason`, `status`, `created_at`

## 9. Sécurité & technique

**login_attempts** — `id`, `email`, `ip`, `success` BOOL, `created_at` (rate limiting + détection)

**rate_limits** — `id`, `bucket_key` (ip+route ou user+route), `hits` INT, `window_start` DATETIME

**password_resets** — `id`, `user_id`, `token_hash`, `expires_at`, `used_at` NULL

**email_verifications** — `id`, `user_id`, `token_hash`, `expires_at`, `verified_at` NULL

**audit_log** — `id`, `actor_user_id` NULL, `action`, `target_type`, `target_id`,
`ip`, `meta` JSON, `created_at` (traçabilité admin & vendeurs sensibles)

**sessions** (si stockage DB des sessions) — `id`, `user_id`, `ip`, `user_agent`,
`last_activity`, `expires_at`

---

### Notes de conception
- **Snapshots de prix** sur les `order_items` : indispensable, un prix vendeur peut changer après
  la commande.
- **Idempotence paiement** : `payments.provider_event_id` UNIQUE empêche de traiter deux fois le
  même webhook Stripe.
- **Anti-chevauchement booking** : vérifier en transaction (`SELECT ... FOR UPDATE`) qu'aucun
  booking confirmé ne recouvre le créneau avant insertion.
- **Soft delete** sur users/vendors/products pour préserver l'historique des commandes.
