---
name: afrikalink-builder
description: >-
  Construire et faire évoluer AfrikaLink — une marketplace internationale multi-verticale
  (boutiques en ligne, restaurants, salons de coiffure, métiers/services) en PHP 8.4 + MySQL 8.4
  LTS, hébergée sur Hostinger, avec la sécurité intégrée par défaut. Utilise ce skill DÈS QUE
  l'utilisateur parle d'AfrikaLink, ou demande quoi que ce soit autour de : créer une boutique /
  un restaurant / un salon en ligne, enregistrer un métier ou un service, vendre ou acheter en
  local et à l'international, multi-devises, multi-langues, paiements vendeurs (Stripe Connect),
  réservations, panier/commandes, ou durcissement sécurité de la plateforme — même si le mot
  "skill" ou "AfrikaLink" n'est pas prononcé explicitement.
---

# AfrikaLink Builder

AfrikaLink est une **marketplace internationale multi-verticale**. Quatre types de vendeurs
("verticales") cohabitent sur une seule plateforme :

1. **Boutiques** — vente de produits physiques (panier, stock, livraison locale + internationale).
2. **Restaurants** — menus en ligne, commande de plats, retrait / livraison.
3. **Salons de coiffure** — prestations + **réservation de créneaux** (booking).
4. **Métiers / Services** — profils de professionnels (plombier, couturier, coach…) avec devis
   ou réservation.

Tout le monde peut **vendre et acheter, localement et internationalement**.

Ce skill est le **plan de construction** : il dit quoi bâtir, dans quel ordre, avec quelle stack,
et impose une sécurité non-négociable. Les détails vivent dans `references/` et les fichiers
prêts à copier dans `assets/`.

---

## Stack technique (à jour — vérifier avant de figer)

- **Langage** : PHP **8.4** (support actif jusqu'à fin 2028). PHP 8.5 dispo sur Hostinger si tu
  veux les dernières features ; pour une marketplace en prod, 8.4 est le bon défaut.
- **Base de données** : MySQL **8.4 LTS** (ou MariaDB 11.4+ équivalent côté Hostinger).
  Toujours `utf8mb4` / `utf8mb4_0900_ai_ci`, moteur `InnoDB`.
- **Hébergement** : Hostinger (mutualisé ou VPS selon la charge). HTTPS obligatoire.
- **CDN / WAF** : Cloudflare devant le domaine (DNS proxifié, règles WAF, rate limiting bord).
- **Paiements** : Stripe + **Stripe Connect** (modèle marketplace : paiement acheteur →
  commission plateforme → reversement vendeur).
- **Front** : PHP server-rendered + un peu de JS vanilla / Alpine.js. Pas de framework lourd au
  départ. Tailwind via CDN ou build léger pour l'UI.
- **Pas de framework imposé**, mais une architecture MVC légère maison (voir
  `references/architecture.md`). Composer pour les libs (Stripe SDK, PHPMailer, ramsey/uuid…).

Avant de citer un numéro de version comme "le dernier", **fais une recherche web** : les versions
PHP/MySQL/Stripe bougent. Les chiffres ci-dessus datent de mi-2026.

---

## Règles d'or (non négociables)

La sécurité n'est pas une phase finale, elle est **intégrée à chaque fonctionnalité**. Avant de
livrer du code, vérifier systématiquement :

1. **Jamais de SQL concaténé.** Toujours PDO + requêtes préparées (`assets/php/db.php`).
2. **Jamais d'entrée utilisateur affichée brute.** Échapper en sortie (`htmlspecialchars`) ;
   valider/normaliser en entrée (`assets/php/validation.php`).
3. **CSRF token sur tout POST/PUT/DELETE** (`assets/php/csrf.php`).
4. **Mots de passe** : `password_hash()` (bcrypt/argon2id), jamais en clair, jamais de MD5/SHA1.
5. **Sessions sécurisées** : cookies `HttpOnly`, `Secure`, `SameSite=Lax`, régénération d'ID au
   login, expiration.
6. **Rate limiting** sur login, inscription, reset password, paiement, API
   (`assets/php/rate_limit.php`).
7. **Webhooks Stripe** : vérifier la signature, traiter en idempotent, ne jamais faire confiance
   au client pour le montant payé.
8. **Uploads** : type MIME réel vérifié, extension whitelistée, renommage, stockage hors webroot
   ou via Cloudflare R2 / dossier non exécutable.
9. **En-têtes HTTP de sécurité** (CSP, HSTS, X-Content-Type-Options…) via `assets/.htaccess`.
10. **Secrets hors du code** : `.env` (jamais commité), `.env.example` versionné.
11. **Argent en entiers** : stocker les montants en **centimes** (`INT`/`BIGINT`) + `currency`,
    jamais en `FLOAT`.
12. **Moindre privilège** : compte MySQL applicatif sans droits DDL en prod ; rôles utilisateurs
    cloisonnés (buyer / vendor / admin).

Détails et code dans `references/security.md`. Ne jamais écrire de code offensif : ce skill sert
**uniquement à défendre et durcir** la plateforme de l'utilisateur.

---

## Quand on te demande de construire une fonctionnalité

Workflow standard :

1. **Identifier la verticale concernée** (boutique / restaurant / salon / métier) et si ça touche
   au cœur commun (utilisateurs, paiement, commande, message, avis).
2. **Lire le bon fichier de référence** avant d'écrire la moindre ligne :
   - structure & conventions → `references/architecture.md`
   - tables & relations → `references/database-schema.md`
   - tout ce qui touche sécurité → `references/security.md`
   - paiement, devises, langues, livraison → `references/payments-and-international.md`
   - obligations légales (UE/international) → `references/compliance.md`
3. **Réutiliser les helpers** de `assets/php/` plutôt que réinventer (DB, CSRF, validation, rate
   limit, en-têtes).
4. **Écrire le code** en respectant les Règles d'or, puis **relire avec la checklist sécurité**.
5. **Migration SQL** : toute évolution de schéma = un fichier de migration daté, jamais de
   modification manuelle silencieuse.
6. **Tester** le chemin nominal + au moins un cas d'abus (entrée malformée, accès non autorisé,
   double soumission).

---

## Plan de construction par phases

Construire dans cet ordre — chaque phase est livrable et testable.

**Phase 0 — Fondations & sécurité**
Structure projet, `.env`, PDO, sessions sécurisées, en-têtes HTTP, CSRF, layout de base, i18n
(FR/EN au minimum), schéma `users` + auth (inscription, login, reset, vérification email).

**Phase 1 — Vendeurs & verticales**
Création de profil vendeur (type = shop/restaurant/salon/service), KYC léger, page publique de
boutique, catégories. Modèle de données commun + spécifique par verticale.

**Phase 2 — Catalogue & contenu**
Boutiques : produits + images + stock. Restaurants : menus + plats. Salons/Métiers : prestations
+ disponibilités.

**Phase 3 — Transactions**
Panier & commande (boutiques/restaurants), réservation de créneau (salons/métiers), Stripe Connect
(paiement → commission → payout vendeur), webhooks, statuts de commande, e-mails transactionnels.

**Phase 4 — International**
Multi-devises (affichage + règlement), multi-langues complet, zones & frais de livraison, TVA /
taxes, adresses internationales.

**Phase 5 — Confiance & croissance**
Avis & notes, messagerie acheteur-vendeur, signalement/modération, tableau de bord vendeur,
back-office admin, analytics.

**Phase 6 — Durcissement & conformité**
Audit sécurité complet, logs/alerting (Telegram/email), conformité légale (voir
`references/compliance.md`), sauvegardes automatisées, plan de réponse incident.

> Conseil de cadrage : tant que l'utilisateur est en apprentissage et que la plateforme n'a pas de
> traction, privilégier un MVP solide et conforme plutôt que d'activer trop tôt les transactions
> réelles et les licences de paiement. Voir `references/compliance.md`.

---

## Structure du projet (vue rapide)

```
afrikalink/
├── public/              # seul dossier exposé au web (webroot)
│   ├── index.php        # front controller
│   ├── assets/          # css/js/img publics
│   └── .htaccess        # routage + en-têtes sécurité
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/        # Stripe, Mail, Booking, Currency...
│   ├── Middleware/      # Auth, Csrf, RateLimit, Locale
│   ├── Views/
│   └── helpers.php
├── config/              # config chargée depuis .env
├── database/
│   └── migrations/      # fichiers SQL datés
├── lang/                # fr.php, en.php, ...
├── storage/             # logs, uploads (hors public/)
├── vendor/              # Composer
├── .env                 # secrets (NON commité)
└── .env.example
```

Détails complets : `references/architecture.md`.

---

## Fichiers de ce skill

- `references/architecture.md` — structure MVC, conventions, routage, i18n, déploiement Hostinger.
- `references/database-schema.md` — schéma complet commenté des 4 verticales + cœur commun.
- `references/security.md` — checklist + patterns de code pour chaque risque (OWASP).
- `references/payments-and-international.md` — Stripe Connect, multi-devises, multi-langues, taxes,
  livraison.
- `references/compliance.md` — obligations légales d'une marketplace internationale (UE focus).
- `assets/schema.sql` — schéma de départ exécutable (cœur + verticales).
- `assets/php/*.php` — helpers prêts à copier (PDO, CSRF, validation, rate limit, en-têtes).
- `assets/.htaccess`, `assets/.env.example` — config de base sécurisée.
