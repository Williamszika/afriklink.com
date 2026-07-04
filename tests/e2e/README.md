# Tests end-to-end (Playwright)

Suite de **démarrage** générée avec la compétence `playwright-pro`. Elle vérifie, dans un vrai
navigateur, les parcours et garde-fous clés d'AfrikaLink.

| Fichier | Ce qu'il garde |
|---|---|
| `smoke.spec.ts` | Accueil : titre descriptif, **1 seul H1**, **JSON-LD** (Organization + WebSite), langue `<html>` |
| `auth.spec.ts` | Connexion ; **portillon de consentement** (bouton « Créer mon compte » bloqué tant que la case légale n'est pas cochée) ; documents légaux accessibles |
| `search.spec.ts` | La recherche mène à `/explorer?q=…` |
| `i18n-rtl.spec.ts` | Bascule de langue ; **arabe ⇒ `dir="rtl"`** |

## Lancer les tests

```bash
# 1) installer Playwright (une fois)
npm install
npx playwright install chromium   # télécharge le navigateur si besoin

# 2) lancer (le serveur PHP démarre tout seul via playwright.config.ts)
npm run test:e2e
npm run test:e2e:headed     # avec navigateur visible
npm run test:e2e:report     # rapport HTML du dernier run
```

### Environnement à navigateur pré-installé (CI/conteneur)

Si un Chromium est déjà présent (ex. `/opt/pw-browsers/chromium/chrome-linux/chrome`), pointe-le
sans rien télécharger :

```bash
export PW_CHROME=/opt/pw-browsers/chromium/chrome-linux/chrome
BASE_URL=http://127.0.0.1:8080 npm run test:e2e
```

### Cibler un autre environnement

```bash
BASE_URL=https://afriklink.com npm run test:e2e   # ne démarre pas de serveur local
```

## Notes

- Ces tests sont **sans identifiants** : ils valident structure, navigation et garde-fous, donc ne
  nécessitent pas de compte de test. Pour tester un login réel de bout en bout, ajoute un compte de
  test dédié et un fichier `.env` de test (jamais de secret de production).
- Ils ne sont **pas** branchés sur la CI de sécurité (qui n'a pas de base de données) : ce sont des
  tests **locaux/manuels** pour l'instant. Pour les passer en CI, il faudra un service MySQL + un
  `.env` de test.
- Conventions playwright-pro respectées : `getByRole` en priorité, assertions « web-first » (pas de
  `waitForTimeout`), `baseURL` en config, isolation par test, trace à la 1re nouvelle tentative.
