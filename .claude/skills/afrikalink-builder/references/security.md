# Sécurité — AfrikaLink

Sécurité **défensive uniquement** : durcir la plateforme de l'utilisateur. Aligné OWASP Top 10.
Chaque section = le risque, puis le pattern correct.

## Sommaire
1. Injection SQL
2. XSS (sortie)
3. CSRF
4. Authentification & sessions
5. Contrôle d'accès (autorisation)
6. Rate limiting & brute force
7. Uploads de fichiers
8. Paiements & webhooks
9. En-têtes HTTP & transport
10. Secrets & configuration
11. Logging, audit & alerting
12. Checklist de revue

---

## 1. Injection SQL
**Toujours** PDO + requêtes préparées (`assets/php/db.php`). Les noms de tables/colonnes ne peuvent
pas être paramétrés : les whitelister contre une liste fixe, jamais via entrée utilisateur.

```php
$stmt = db()->prepare('SELECT * FROM products WHERE vendor_id = :vid AND status = :st');
$stmt->execute(['vid' => $vendorId, 'st' => 'active']);
```
`PDO::ATTR_EMULATE_PREPARES => false` pour de vraies requêtes préparées côté serveur.

## 2. XSS (échappement en sortie)
Échapper **tout** ce qui vient de la base/utilisateur à l'affichage HTML :
```php
echo htmlspecialchars($product->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
```
Pour du HTML riche autorisé (descriptions), passer par un sanitizer à liste blanche (ex. HTML
Purifier), jamais accepter du HTML brut. Compléter par une **CSP** stricte (section 9).

## 3. CSRF
Token par session, vérifié sur POST/PUT/PATCH/DELETE (`assets/php/csrf.php`). Champ caché
`csrf_token` dans chaque formulaire ; en-tête `X-CSRF-Token` pour les requêtes JS. Comparaison en
temps constant (`hash_equals`).

## 4. Authentification & sessions
- `password_hash($pwd, PASSWORD_ARGON2ID)` (ou `PASSWORD_DEFAULT`). Vérifier avec
  `password_verify`. Re-hasher si `password_needs_rehash`.
- Politique de mot de passe raisonnable (longueur ≥ 12, pas de liste interdite triviale). Ne pas
  imposer de règles absurdes qui poussent à la réutilisation.
- `session_regenerate_id(true)` après login et après changement de privilège.
- Cookie de session : `HttpOnly`, `Secure`, `SameSite=Lax`, durée limitée, chemin restreint.
- Réinitialisation de mot de passe : token aléatoire (`random_bytes(32)`), **stocké haché**,
  expiration courte, usage unique, et message identique que l'email existe ou non.
- Vérification d'email avant d'autoriser la vente.
- 2FA (TOTP) recommandé pour les comptes vendeurs/admin.

```php
session_set_cookie_params([
  'lifetime' => 0, 'path' => '/', 'secure' => true,
  'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
```

## 5. Contrôle d'accès (autorisation)
La faille la plus fréquente d'une marketplace = **IDOR** (accéder à la ressource d'autrui en
changeant un id). Règles :
- Vérifier le **rôle** ET la **propriété** : un vendeur ne peut modifier que SES produits/commandes.
- Ne jamais se fier à un champ caché de formulaire pour `vendor_id`/`user_id` : le prendre depuis
  la session serveur.
- Exposer des `public_id` (UUID) plutôt que les `id` séquentiels.
```php
$product = Product::findByPublicId($publicId);
if (!$product || $product->vendor_id !== current_vendor_id()) {
    http_response_code(404); exit; // 404 plutôt que 403 pour ne pas révéler l'existence
}
```

## 6. Rate limiting & brute force
- Limiter login/inscription/reset/paiement par IP **et** par compte (`assets/php/rate_limit.php`).
- Journaliser les tentatives (`login_attempts`), verrouillage progressif / backoff.
- Compléter par les règles Cloudflare (rate limiting de bord, challenge bots).
- Protéger les endpoints coûteux (recherche, export) et les webhooks.

## 7. Uploads de fichiers
- Whitelister les extensions ET vérifier le **type MIME réel** (`finfo_file`), pas l'extension.
- Pour les images, re-encoder (GD/Imagick) pour neutraliser un payload caché.
- Renommer (UUID), interdire les noms fournis par l'utilisateur.
- Stocker **hors webroot** (`storage/uploads/`) et servir via un script contrôlé, ou via Cloudflare
  R2 / stockage objet. Si forcé dans `public/uploads/`, interdire l'exécution :
```apache
# public/uploads/.htaccess
php_flag engine off
<FilesMatch "\.(php|phtml|phar|cgi|pl)$">
  Require all denied
</FilesMatch>
```
- Limiter la taille, scanner si possible.

## 8. Paiements & webhooks (Stripe)
- **Ne jamais** faire confiance au montant envoyé par le client : recalculer côté serveur depuis la
  base avant de créer le PaymentIntent.
- **Vérifier la signature** du webhook (`Stripe\Webhook::constructEvent` avec le secret), sinon
  rejeter en 400.
- **Idempotence** : enregistrer `event.id` (`payments.provider_event_id` UNIQUE) ; ignorer un
  événement déjà traité.
- Marquer la commande payée **uniquement** sur l'événement `payment_intent.succeeded` reçu du
  webhook, pas sur le retour navigateur (qui peut être falsifié/abandonné).
- Stripe Connect : voir `references/payments-and-international.md`.

## 9. En-têtes HTTP & transport
Via `assets/php/security_headers.php` et/ou `assets/.htaccess` :
- `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
- `Content-Security-Policy` stricte (limiter `script-src`, pas de `unsafe-inline` à terme).
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `X-Frame-Options: DENY` (anti-clickjacking) / `frame-ancestors 'none'` en CSP.
- `Permissions-Policy` minimal.
- HTTPS forcé partout ; Cloudflare en "Full (strict)".

## 10. Secrets & configuration
- `.env` non commité (clés Stripe, DB, SMTP, sel applicatif). `.env.example` versionné.
- `display_errors = Off` en prod ; erreurs loguées seulement.
- Comptes DB à moindre privilège (cf. architecture).
- Faire tourner les clés régulièrement ; clés Stripe restreintes par périmètre.

## 11. Logging, audit & alerting
- `audit_log` pour les actions sensibles (changement de prix, payout, suspension, accès admin).
- Logs applicatifs dans `storage/logs/` (jamais de secrets/mot de passe/numéro de carte en clair).
- Alerting : sur événements critiques (pics d'échecs login, webhooks invalides, erreurs paiement),
  notifier par e-mail/Telegram. (L'utilisateur a déjà une architecture d'alerte Telegram — la
  réutiliser.)
- Surveiller les CVE des dépendances (`composer audit`).

## 12. Checklist de revue (avant chaque livraison)
- [ ] Toutes les requêtes sont préparées, aucun SQL concaténé.
- [ ] Toute sortie HTML est échappée ; HTML riche purifié.
- [ ] Token CSRF présent et vérifié sur chaque mutation.
- [ ] Rôle **et** propriété vérifiés sur chaque ressource (pas d'IDOR).
- [ ] Mots de passe hachés, sessions régénérées, cookies durcis.
- [ ] Rate limiting sur les routes sensibles.
- [ ] Uploads : MIME vérifié, renommés, non exécutables.
- [ ] Montants recalculés serveur ; webhook signé + idempotent.
- [ ] En-têtes de sécurité actifs ; HTTPS forcé.
- [ ] Aucun secret dans le code ou les logs.
- [ ] Cas d'abus testé (entrée malformée, accès croisé, double soumission).
