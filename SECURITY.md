# Politique de sécurité — AfrikaLink

La sécurité est intégrée à chaque fonctionnalité d'AfrikaLink, pas ajoutée après coup.
Ce document explique comment signaler une faille et ce qui protège la plateforme.

## Signaler une vulnérabilité

Si vous pensez avoir trouvé une faille de sécurité :

- ✉️ **security@afriklink.com** (contact privé)
- Ou ouvrez une **Security advisory** privée sur GitHub (onglet *Security* → *Report a vulnerability*).

Merci de **ne pas** ouvrir d'issue publique ni de divulguer la faille avant qu'un
correctif ne soit déployé. Nous nous efforçons d'accuser réception sous 72 h.

Merci d'inclure : description, étapes de reproduction, impact estimé, et si
possible une preuve de concept **non destructive**. Ne testez jamais sur des
comptes qui ne sont pas les vôtres et n'accédez pas à des données tierces.

## Versions concernées

La branche `main` (production) et les branches de développement actives.

## Défenses en place (défense en profondeur)

**Au niveau du code (automatisé, à chaque changement — voir `.github/workflows/security.yml`) :**
- Lint PHP + **scanner maison** (`scripts/security_scan.php`) : fonctions dangereuses,
  code obscurci, **secrets en dur**, **intégrations externes inconnues**.
- **Semgrep** (OWASP Top 10, injections, XSS, secrets).
- **gitleaks** (fuite de secrets), **composer audit** (CVE des dépendances),
  **dependency-review** (nouvelles dépendances en PR).
- **Gouvernance** : `CODEOWNERS` + protection de branche → seul le propriétaire
  peut faire entrer du code dans `main` (voir `docs/SECURITE.md`).

**Au niveau de l'application (à l'exécution) :**
- Requêtes **préparées** (PDO) partout — pas de SQL concaténé.
- Sortie **échappée** (`e()`), entrées validées/normalisées.
- **CSRF** sur tout POST/PUT/DELETE ; **rate limiting** (login, inscription, paiement, API).
- Sessions durcies (HttpOnly, Secure, SameSite), régénération d'ID, epoch de session.
- **CSP stricte**, HSTS, X-Content-Type-Options, X-Frame-Options.
- Mots de passe **hachés** (Argon2id) ; messages **chiffrés au repos**.
- Webhooks de paiement à **signature vérifiée** et traitement idempotent.
- Montants en **centimes entiers** ; secrets hors du code (`.env`).

**Au niveau de l'infrastructure :**
- HTTPS obligatoire, **Cloudflare** (WAF + rate limiting de bord) devant le domaine.

> Aucune défense n'est parfaite à 100 %. Cette approche en couches vise à rendre
> une attaque coûteuse, à la détecter tôt et à limiter son impact.

---

## English (summary)

Found a vulnerability? Email **security@afriklink.com** or open a private GitHub
Security advisory. Please do not disclose publicly before a fix ships, and never
test against accounts or data that aren't yours. We aim to acknowledge within 72h.
