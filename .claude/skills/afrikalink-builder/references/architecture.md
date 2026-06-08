# Architecture — AfrikaLink

## Sommaire
1. Principes
2. Arborescence détaillée
3. Front controller & routage
4. Couche données (PDO)
5. Middleware
6. Internationalisation (i18n)
7. Conventions de code
8. Déploiement Hostinger
9. Composer & dépendances

---

## 1. Principes

- **Un seul dossier exposé au web** : `public/`. Tout le reste (code, secrets, uploads, logs) est
  au-dessus du webroot et inaccessible par URL.
- **MVC léger maison** : pas de framework lourd, mais une séparation nette Controllers / Models /
  Views / Services. Objectif : compréhensible, maintenable en solo, sans magie.
- **Tout passe par le front controller** `public/index.php` → routeur → middleware → contrôleur.
- **Sécurité par défaut** : chaque requête traverse les middlewares Locale → RateLimit → Csrf →
  Auth avant d'atteindre un contrôleur protégé.
- **Stateless autant que possible** ; l'état partagé vit en base, pas en mémoire process.

## 2. Arborescence détaillée

```
afrikalink/
├── public/
│   ├── index.php            # front controller (unique point d'entrée)
│   ├── .htaccess            # réécriture vers index.php + en-têtes sécurité
│   ├── assets/css|js|img/
│   └── uploads/             # SEULEMENT si tu ne peux pas mettre hors webroot ;
│                            # alors interdire l'exécution PHP via .htaccess
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── VendorController.php
│   │   ├── ProductController.php
│   │   ├── RestaurantController.php
│   │   ├── SalonController.php
│   │   ├── ServiceController.php   # métiers
│   │   ├── CartController.php
│   │   ├── OrderController.php
│   │   ├── BookingController.php
│   │   └── WebhookController.php    # Stripe
│   ├── Models/                      # une classe par table principale
│   ├── Services/
│   │   ├── StripeService.php
│   │   ├── MailService.php
│   │   ├── CurrencyService.php
│   │   ├── BookingService.php
│   │   └── ShippingService.php
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   └── LocaleMiddleware.php
│   ├── Views/
│   │   ├── layouts/
│   │   └── ...
│   ├── Router.php
│   └── helpers.php
├── config/
│   ├── app.php              # lit $_ENV
│   └── routes.php
├── database/
│   └── migrations/          # 2026_06_08_120000_create_users.sql ...
├── lang/  (fr.php, en.php)
├── storage/ (logs/, uploads/, cache/)
├── vendor/
├── composer.json
├── .env
└── .env.example
```

## 3. Front controller & routage

`public/index.php` : charge l'autoload Composer, charge `.env`, démarre une session sécurisée,
construit le routeur, applique les middlewares globaux, dispatch.

Routeur minimal : table de routes `méthode + chemin → [Controller, action, middlewares]`.
Supporter les paramètres (`/boutique/{slug}`). Renvoyer 404/405 propres. Ne jamais exposer de
trace/exception en prod (`display_errors=Off`, log dans `storage/logs/`).

## 4. Couche données (PDO)

Utiliser `assets/php/db.php` : singleton PDO configuré avec
`ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`, `FETCH_ASSOC`, charset `utf8mb4`. Les Models
encapsulent les requêtes ; **aucune** requête préparée à la main dans les contrôleurs.

Compte MySQL applicatif : droits `SELECT, INSERT, UPDATE, DELETE` uniquement en prod. Les
migrations sont jouées avec un compte séparé ayant les droits DDL.

## 5. Middleware

- **LocaleMiddleware** : détermine la langue (param URL > cookie > `Accept-Language` > défaut) et
  la devise d'affichage.
- **RateLimitMiddleware** : limite par IP + par compte sur les routes sensibles.
- **CsrfMiddleware** : vérifie le token sur toute méthode non idempotente.
- **AuthMiddleware** : exige une session valide ; vérifie le rôle (buyer/vendor/admin) et la
  propriété de la ressource (un vendeur ne touche que SES produits).

Ordre recommandé : Locale → RateLimit → Csrf → Auth.

## 6. Internationalisation (i18n)

- Fichiers `lang/fr.php`, `lang/en.php` : tableaux clé → texte. Helper `t('key')`.
- Démarrer FR + EN, prévoir l'ajout d'autres langues (structure clé/valeur, pas de texte en dur
  dans les vues).
- Séparer **langue d'interface** et **devise** : un utilisateur peut naviguer en français et payer
  en EUR, ou en anglais et payer en XOF/NGN/USD.
- Stocker `locale` et `country_code` sur le profil utilisateur.
- Dates/nombres/monnaies formatés via `IntlDateFormatter` / `NumberFormatter` (extension `intl`).

## 7. Conventions de code

- `declare(strict_types=1);` en tête de chaque fichier PHP.
- Typage strict des propriétés/paramètres/retours (PHP 8.4).
- PSR-12 pour le style, PSR-4 pour l'autoload (`App\` → `app/`).
- Noms de tables au pluriel `snake_case` ; clés étrangères `xxx_id`.
- Toujours `created_at` / `updated_at` ; soft delete (`deleted_at`) sur les entités importantes.
- Une migration = un changement atomique, nommée par date.

## 8. Déploiement Hostinger

- Pointer le **document root** sur `public/`, pas sur la racine du projet (réglage hPanel).
- Forcer PHP 8.4 dans hPanel. Activer extensions : `pdo_mysql`, `intl`, `mbstring`, `openssl`,
  `fileinfo`, `curl`.
- HTTPS : certificat actif + redirection 301 HTTP→HTTPS (Cloudflare "Full (strict)").
- `.env` hors `public/`, permissions `600`. Désactiver l'indexation de répertoires.
- Sauvegardes : export SQL quotidien + fichiers ; tester la restauration.
- Déploiement via Git + `composer install --no-dev --optimize-autoloader`, ou SSH/SFTP.

## 9. Composer & dépendances

Dépendances minimales et bien maintenues :
- `stripe/stripe-php` — paiements & Connect.
- `phpmailer/phpmailer` — e-mails transactionnels (ou API d'un fournisseur).
- `ramsey/uuid` — identifiants publics non devinables.
- `vlucas/phpdotenv` — chargement `.env`.

Garder les dépendances à jour (`composer outdated`) et surveiller les CVE.
