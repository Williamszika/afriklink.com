# Afriklink — Audit du site (A → Z)

> Photographie de l'application telle qu'elle existe dans le dépôt.
> Objectif : **comment le site fonctionne** et **de quoi il est constitué**.

---

## 1. Vue d'ensemble

**Afriklink** est une **marketplace internationale multi-verticale** reliant l'Afrique et l'Europe.
Une seule base de données alimente **trois interfaces** (vitrine publique, tableau de bord vendeur,
espace client) pour **plusieurs métiers** (« verticales »).

| | |
|---|---|
| **Langage** | PHP 8.4, `declare(strict_types=1)` partout |
| **Base de données** | MySQL 8.4 / TiDB (PDO, `utf8mb4`, InnoDB) |
| **Hébergement** | Vercel (serverless) — compatible Hostinger ; Cloudflare devant |
| **Médias** | Cloudinary (envoi direct navigateur signé) |
| **E-mail** | API transactionnelle Brevo (driver `log` si non configuré) |
| **Front** | PHP rendu serveur + JS vanilla (un seul `app.js`), CSS maison |
| **Taille** | ~22 000 lignes PHP · 26 contrôleurs · 26 modèles (~33 tables) · 18 services · 151 routes · 1384 clés i18n (fr=en) |

**Principe d'architecture** : MVC léger « maison », sans framework. Sécurité intégrée à chaque
fonctionnalité (pas une phase finale). Argent **toujours en centimes** (entiers).

---

## 2. Comment le site fonctionne (architecture technique)

### 2.1 Cycle d'une requête
```
Navigateur
  → public/index.php          (UNIQUE point d'entrée exposé au web)
  → app/bootstrap.php          (paths, autoload PSR-4, .env, helpers, erreurs,
                                 en-têtes de sécurité, session durcie, locale/devise)
  → App\Router                 (table config/routes.php : [méthode, chemin, [Ctrl, action], [middlewares]])
  → LocaleMiddleware (global)  puis middlewares de la route (guest/auth/csrf/throttle/staff)
  → Contrôleur::action         (logique) → Modèles (PDO) / Services
  → view('template', $data)    → layouts/app.php enveloppe le contenu
  → HTML (Github-flavored, terminal)
```

### 2.2 Bootstrap (`app/bootstrap.php`) — ordre strict
1. Constantes de chemins (`BASE_PATH`, `APP_PATH`, `CONFIG_PATH`…).
2. **Autoload PSR-4** maison (`App\ → app/`) — fonctionne **sans Composer**.
3. Chargement `.env` (loader minimal) + backfill depuis les vraies variables d'env (Vercel).
4. Helpers procéduraux (`Support/db.php`, `csrf.php`, `validation.php`, `rate_limit.php`,
   `security_headers.php`, `helpers.php`).
5. Gestion d'erreurs : deprecations/notices loguées (jamais fatales), reste → exceptions.
6. **En-têtes de sécurité + session durcie** (web uniquement, jamais en CLI).
7. Résolution **locale + devise d'affichage** de la requête (cookies).

### 2.3 Base de données
- **`db()`** : singleton PDO (`app/Support/db.php`), `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`.
  TLS pour TiDB Cloud (bundle CA embarqué `config/cacert.pem`).
- **Migrations idempotentes** : chaque modèle porte un `ensureTable()` (`CREATE TABLE IF NOT EXISTS`)
  et des micro-migrations (`try{ SELECT col }catch{ ALTER TABLE ADD COLUMN }`). Pas de runner daté.
- **Conventions** : `id` BIGINT, `public_id` CHAR(36) UUID exposé (jamais l'auto-incrément),
  pas de clés étrangères (compat TiDB), montants en `BIGINT` centimes + `currency`.

### 2.4 Routage & middlewares
- `config/routes.php` : **151 routes** déclaratives.
- Middlewares : `guest`, `auth` (+ `auth:role`), `csrf`, `staff`, `throttle:bucket,max,window`,
  `LocaleMiddleware` (global).

### 2.5 Sessions & rendu
- Session fichier en local ; **base de données** (`DbSessionHandler`) sur serverless si DB
  configurée (le FS Vercel est éphémère). Cookies `Secure`/`HttpOnly`/`SameSite=Lax`,
  régénération d'ID toutes les 30 min.
- Vues : `view()` rend un template + `layouts/app.php` (en-tête, recherche, panier/favoris/
  comparateur, **cloche de notifications**, sélecteur **langue · devise**, géo-chip, pied de page).

---

## 3. Sécurité (Règles d'or appliquées)

| Domaine | Mise en œuvre |
|---|---|
| **CSP stricte** | `default-src 'self'` ; `script-src 'self' js.stripe.com (+Turnstile)` ; **pas de `font-src`** → polices **auto-hébergées** (woff2) ; `style-src 'self' 'unsafe-inline'` ; `connect-src` limité (Stripe, Cloudinary, bigdatacloud). |
| **En-têtes** | HSTS (preload), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy: geolocation/camera/microphone=(self)`, `X-Powered-By` retiré. |
| **CSRF** | Token sur **tout POST** mutant (`csrf_field()` + `CsrfMiddleware`). |
| **Rate limiting** | Per-IP par seau (`RateLimitMiddleware`) : login, register, reset, paiement, panier, POS, etc. |
| **Mots de passe** | `password_hash()` (bcrypt/argon2id), longueur min 12. Suivi des tentatives (`login_attempts`). |
| **Sessions** | durcies (cf. §2.5). |
| **Anti-IDOR** | vérification de **propriété** (un vendeur ne touche que SES ressources). |
| **Uploads** | via **Cloudinary signé** (signature serveur `/api/media/sign`, `/api/kyc/sign`). |
| **Argent** | entiers centimes ; recalcul **serveur** des montants ; jamais le montant du client. |
| **Anti-survente** | décrément de stock **atomique et borné** (`UPDATE … WHERE stock >= :q`). |
| **Rôles** | `guest` / `auth` (membre) / `staff` (admins/modérateurs via `ADMIN_EMAILS`). |
| **Audit** | `AuditLog` (acteur, action, cible, **IP**, méta) sur les actions sensibles. |
| **Anti double-soumission** | `[data-submit-once]` (caisse POS, ouverture/clôture, commande). |

---

## 4. De quoi le site est constitué

### 4.1 Les quatre verticales
| Verticale | État | Cœur |
|---|---|---|
| **Boutique** (produits) | ✅ livré | produits + **variantes/SKU** + stock + panier + checkout + **POS caisse** |
| **Restaurant** | ✅ livré | menu (catégories, plats, boissons à contenances) + commandes + caisse |
| **Annonces (C2C)** | ✅ livré | « Vendre un article » entre particuliers (photos, vidéo, catégories) |
| **Salon / Métiers-Services** | 🔜 « bientôt » | cartes d'amorçage seulement (`/bientot/{feature}`) — réservation à venir |

### 4.2 Les trois faces (sur une seule base)
1. **Vitrine publique** — accueil (catégories **vivantes**), `/explorer` (filtres), `/boutique/{slug}`,
   fiche produit, `/restaurant/{slug}`, panier, commande (checkout **invité** possible).
2. **Tableau de bord vendeur** (`/vendeur/*`, `/boutique/*`, `/restaurant/*`) — vitrines, catalogue,
   commandes, POS, publicité, affiliation, vérification (KYC), profil, réglages.
3. **Espace membre** — profil, préférences (langue/devise), photo, messagerie, notifications.

### 4.3 Contrôleurs (26)
`Home` (accueil, explorer, mise-en-avant, sitemap, lang/devise) · `Auth` · `ProRegistration` ·
`Dashboard` · `Profile` · `SellerProfile` · `Seller` (vitrines, gains, pub, affiliation, KYC) ·
`Boutique` · `Product` · `Cart` · `Order` · `Pos` · `Restaurant` · `Listing` · `Payment` ·
`Message` · `Notification` · `Review`(via Boutique) · `Wishlist` · `Compare` · `Kyc` · `AdminKyc` ·
`Media` (signatures Cloudinary) · `Geo` · `Affiliate` · `Legal` · `Report`.

### 4.4 Modèles & tables (26 modèles → ~33 tables)
- **Identité** : `users`, `user_avatars`, `pro_profiles`, `email_verifications`, `password_resets`, `login_attempts`.
- **Boutique** : `boutiques`, `boutique_banners`, `products`, `product_variants`, `product_photos`, `discounts`, `reviews`, `stock_alerts`, `shop_views`.
- **Restaurant** : `restaurants`, `menu_categories`, `menu_items`, `restaurant_orders`, `restaurant_order_items`.
- **Annonces** : `listings`, `listing_photos`.
- **Commande/paiement** : `orders`, `order_items`, `order_tenders`, `payments`.
- **POS (caisse)** : `registers`, `register_sessions`, `cash_movements`.
- **Relation** : `conversations`, `messages`, `notifications`.
- **KYC** : `kyc_submissions`, `kyc_documents`.
- **Affiliation** : `affiliate_codes`, `affiliate_clicks`, `affiliate_conversions`.

### 4.5 Services (18)
`Cart` / `Wishlist` / `Compare` (paniers de session) · `Recommender` (reco par cookie de navigation) ·
**`Categories`** (catégories vivantes : tendance + cache) · `CloudinaryService` · `MailService` (Brevo/log) ·
`Notifier` (SMS/WhatsApp) · `OrderNotifier` (e-mails de commande client + vendeur) · `Payment`
(résolution multi-fournisseurs + simulation) · `AuditLog` · `Captcha` (Cloudflare Turnstile) ·
`GeoService` · `ContactChannels` · `QrCode` · `StockForecast` · `StorefrontAlert` · `Assistant`.

### 4.6 Internationalisation & monnaie
- **Langues** : FR/EN, **1384 clés** chacune (**parité stricte**) ; tout texte via `t('clé')`.
- **Devises** : EUR, USD, XOF, NGN, GBP — affichage acheteur indépendant de la devise de règlement.
- **3 axes indépendants** : langue ⟂ pays ⟂ devise. Gestion des devises **sans décimale** (XOF/XAF/JPY/KRW).
- Bandeau du haut : **langue · devise** toujours accessibles + position détectée (drapeau + ville).

### 4.7 Paiement (`config/payment.php`)
Ossature **multi-fournisseurs**, chacun activé dès que ses clés d'env existent :
- **`simulation`** (toujours actif, bac à sable sans argent réel) — permet de tester tout le parcours.
- **`cinetpay`** (Mobile Money Wave/Orange/MTN/Moov + cartes, Afrique de l'Ouest).
- **`stripe`** (cartes / Apple Pay / Google Pay, Europe).
- **`paypal`**.
- Commission plateforme prélevée par transaction. **Encaissement réel gardé derrière un flag**
  (mode démo) tant que le légal/PSP n'est pas bouclé. POS : l'argent reste **chez le vendeur**.

### 4.8 Médias & géolocalisation
- **Cloudinary** : photos produits/annonces/bannières, vidéos d'annonce, documents KYC — envoi
  **direct navigateur** via **signature serveur** (la clé secrète ne fuit jamais).
- **Géo** : détection IP (immédiate) puis **GPS** (avec consentement), géocodage inverse
  (bigdatacloud), **ville + pays verrouillés** dans tout le site, **indicatif téléphonique**
  priorisé selon le pays détecté.

### 4.9 Identité visuelle
- **Design System v1.0** : forêt `#103D30` / or `#E5A02E` / crème `#FBF7EF`, motif wax, polices
  **auto-hébergées** (Bricolage Grotesque / Inter / Space Mono) — conformes à la CSP.
- **Famille d'icônes outline** maison : 47 icônes SVG **inline** (`config/icons.php` + helper
  `icon()`), CSP-safe, `currentColor`. Tout le chrome de l'app est en icônes ; les emojis ne
  subsistent que comme marqueurs de catégories/verticales et glyphes typographiques.

---

## 5. Parcours clés (flows)

1. **Inscription / connexion** : choix particulier/vendeur → vérification e-mail → login (throttlé,
   suivi des tentatives) → reset mot de passe.
2. **Mise en route vendeur** : créer une vitrine (boutique/restaurant) → ajouter produits/plats →
   publier. Le **tableau de bord adaptatif** guide selon le stade (A mise en route / B prêt à vendre /
   C actif) et met en avant **la prochaine action utile**.
3. **Achat** : accueil (catégories vivantes) / explorer → vitrine → fiche → panier → **checkout
   (invité possible)** → commande → paiement (simulation/PSP) → page de confirmation + reçu e-mail.
4. **Cycle de commande** : `nouvelle → confirmée → expédiée → livrée` ; **chaque étape notifie le
   client** (e-mail + SMS/WhatsApp + in-app si compte) et écrit dans l'audit.
5. **Caisse POS** : ouverture (fond) → ventes (**stock partagé** online/POS, décrément atomique,
   anti-survente strict) → mouvements d'espèces → clôture (comptage → écart) → **rapport X/Z (CSV)**.
6. **Vérification (KYC)** : le vendeur soumet ses pièces (Cloudinary signé) → un modérateur
   approuve → badge « Vendeur vérifié ».
7. **Affiliation** : lien de parrainage → clics → conversions → commissions.
8. **Confiance** : avis **vérifiés** (liés à un achat), messagerie acheteur↔vendeur, signalement.

---

## 6. Constat — points forts & points d'attention

### Points forts
- **Sécurité par défaut** réellement appliquée (CSP stricte, CSRF, throttle, anti-IDOR, audit,
  argent en centimes, décrément atomique anti-survente). Validé par tests d'intégration.
- **Architecture lisible** : MVC maison sans dépendances lourdes, conventions homogènes
  (public_id, ensureTable, montants centimes), i18n complet à parité.
- **Stock unique** partagé online ↔ POS (variantes = source de vérité), réconciliation Z testée.
- **Internationalisation native** (langue/devise/pays) — l'avantage produit du corridor.
- **Catégories vivantes** (tendance + cache) au lieu d'une liste figée.

### Points d'attention (à traiter)
1. ~~Incohérence de commission~~ → ✅ **résolu** : source unique = `config/payment.php`
   (`platform_commission_pct`, défaut **5 %**, env `PLATFORM_COMMISSION_PCT`), calcul via le helper
   `platform_commission_cents()` ; le doublon `platform_fee_bps` (app.php + `.env.example`) a été retiré.
2. **PSP — Stripe branché (webhook = vérité)** ✅ : `StripeProvider` réel (Checkout Session +
   montants zéro-décimale) et **webhook signé idempotent** (`POST /webhooks/stripe`) qui constate
   le paiement — signature HMAC-SHA256, anti-rejeu temporel, **idempotence** (`payment_events`),
   **contrôle du montant**, confirmation via `PaymentSettlement` — testé (14/14). **Reste** :
   valider l'appel API en conditions réelles (clés live + URL webhook chez Stripe), **Stripe
   Connect** (reversement vendeur + `application_fee_amount`), puis CinetPay/PayPal au même modèle.
3. **Notifications client incomplètes** (cahier §6) : `expédiée`/`livrée` faites ; manquent
   `payout`, `remboursement`, changement de statut KYC.
4. **Verticales Salon / Services** : non construites (placeholders « bientôt »).
5. **Hors-ligne POS** (cache IndexedDB + file de synchro) : non commencé.
6. **Pas de clés étrangères** (choix TiDB) : l'intégrité référentielle repose sur le code —
   à surveiller lors des suppressions en cascade.
7. **Cache catégories** = fichier temp TTL 120 s : suffisant en mono-serveur ; prévoir Redis si
   montée en charge / multi-instances.

---

## 7. Inventaire chiffré

| Élément | Compte |
|---|---|
| Routes | 151 |
| Contrôleurs | 26 |
| Modèles | 26 (~33 tables) |
| Services | 18 |
| Middlewares | 7 |
| Vues (.php) | ~64 (dont 19 partials) |
| Icônes outline | 47 |
| Clés i18n | 1384 (fr) = 1384 (en) |
| Devises | 5 · Langues : 2 |
| LOC PHP (app/) | ~22 161 |

---

*Document généré par audit du dépôt. Pour la vision « cible » (plans produit), voir les cahiers
des charges fournis ; ce fichier décrit l'existant.*
