# 🛡️ Votre équipe de sécurité automatique — guide du propriétaire

Ce document explique, en clair, ce qui protège désormais le code d'AfrikaLink,
et **comment activer le verrou** pour que personne d'autre que vous ne puisse
faire entrer du code sur le site.

---

## 1. Ce que vous avez maintenant

Une « équipe de sécurité » **automatique** qui inspecte le code à **chaque
changement** (et une fois par semaine), sans fatigue et sans oubli. Elle vit dans
GitHub et tourne toute seule. Elle sait :

| Elle cherche… | Comment |
|---|---|
| 🕳️ **Les failles** (injection, XSS, contrôle d'accès…) | semgrep (OWASP Top 10) |
| 🧨 **Les codes cassés** | lint PHP (erreurs de syntaxe) |
| 🔌 **Les intégrations inconnues** (nouvel appel externe non déclaré) | scanner maison + `allowed_hosts.txt` |
| 🐛 **Les codes suspects ajoutés** (eval, code obscurci, malware) | scanner maison |
| 🔑 **Les secrets en dur** (clés, mots de passe commités) | scanner maison + gitleaks |
| 📦 **Les dépendances vulnérables** | composer audit + dependency-review |

Si l'un de ces contrôles trouve un problème, il **bloque** : le code ne peut pas
être fusionné dans le site tant que ce n'est pas corrigé.

> **« Corriger »** : la machine **détecte et bloque** automatiquement. La
> *correction* elle-même est faite par vous (ou par moi, Claude, sur votre
> demande) après revue — corriger automatiquement du code sensible sans regard
> humain serait dangereux.

---

## 2. 🔒 Le verrou : « seul le propriétaire peut changer le code »

C'est le point le plus important de votre demande. Il s'active en **5 minutes**
dans GitHub (une seule fois). Tant que ce n'est pas fait, les contrôles tournent
mais n'empêchent pas techniquement un merge.

### Étapes (à faire une fois)

1. Ouvrez votre dépôt sur GitHub : **github.com/Williamszika/afriklink.com**
2. Onglet **Settings** (Réglages) → menu de gauche **Branches**.
3. Cliquez **Add branch protection rule** (Ajouter une règle).
4. **Branch name pattern** : tapez `main`.
5. Cochez :
   - ☑️ **Require a pull request before merging** (exiger une PR avant fusion)
     - ☑️ **Require approvals** → 1
     - ☑️ **Require review from Code Owners** ← *c'est ce qui rend VOTRE approbation obligatoire (fichier `.github/CODEOWNERS`)*
   - ☑️ **Require status checks to pass before merging**
     - Cherchez et cochez **Garde (lint + scanner maison)** ← *le verrou fiable,
       zéro faux positif : il ne devient rouge que si un vrai danger est ajouté.*
     - *(semgrep et composer audit tournent en « rapport » au départ : visibles
       mais non bloquants, le temps de trier leurs premiers résultats. Vous
       pourrez les rendre bloquants ensuite — voir `.github/workflows/security.yml`.)*
     - ☑️ **Require branches to be up to date before merging**
   - ☑️ **Do not allow bypassing the above settings** (personne ne contourne, même admin)
   - ☑️ **Restrict who can push** → ajoutez **uniquement vous**
   - ☑️ **Block force pushes** (empêche la réécriture de l'historique)
6. **Create / Save changes**.

Résultat : **plus aucun code n'entre dans `main` sans une Pull Request que VOUS
approuvez**, et seulement si tous les contrôles de sécurité sont verts. 🔒

### Option avancée (encore plus fort)
- ☑️ **Require signed commits** : chaque changement doit être signé
  cryptographiquement. Même un jeton volé ne pourrait pas se faire passer pour
  vous. (Un peu plus technique à configurer — optionnel.)

### En complément (onglet **Settings → Code security and analysis**)
- Activez **Secret scanning** et **Push protection** (bloque l'envoi accidentel
  d'une clé). Gratuit sur les dépôts publics.

---

## 3. Ce que ça change concrètement

Aujourd'hui, je (Claude) pousse parfois directement sur `main`. **Une fois le
verrou activé**, la façon de travailler devient :

1. Le changement est poussé sur une **branche** (pas `main`).
2. Une **Pull Request** est ouverte automatiquement.
3. La sécurité s'exécute (✅ ou ❌).
4. **Vous** lisez, et **vous** cliquez sur **Merge** si c'est vert et que vous êtes d'accord.

C'est exactement le comportement que vous demandez : **rien ne passe sans votre
feu vert.**

---

## 4. Comment lire les résultats

- Onglet **Actions** du dépôt : chaque changement affiche une ligne.
  - ✅ vert = tout va bien.
  - ❌ rouge = un problème ; cliquez pour voir *lequel* et *où* (fichier + ligne).
- Onglet **Security** : les alertes de dépendances et de secrets s'y accumulent.
- Dans une **Pull Request** : les contrôles apparaissent en bas ; le bouton
  *Merge* reste bloqué tant que ce n'est pas vert + approuvé.

---

## 5. Déclarer une nouvelle intégration légitime

Le scanner **bloque tout appel vers un service externe non déclaré** (c'est ainsi
qu'on repère une intégration inconnue ou du code malveillant). Si vous ajoutez un
vrai service (ex. un nouveau transporteur), il faut ajouter son domaine dans :

```
scripts/allowed_hosts.txt
```

Ce simple ajout, visible dans la Pull Request, est une **décision consciente** que
vous validez — impossible qu'une intégration se glisse en douce.

---

## 6. Vérifier en local (optionnel, pour un développeur)

```bash
php scripts/security_scan.php      # lance le scan à la main
bash scripts/install-hooks.sh      # scan automatique avant chaque 'git push'
```

---

## 7. Honnêteté

Aucun système ne bloque **100 %** des attaques — quiconque le promet ment. Ce
dispositif applique la **défense en profondeur** : plusieurs couches qui rendent
une attaque difficile, la détectent tôt, et limitent les dégâts. Combiné aux
protections déjà en place dans l'application (CSP, CSRF, requêtes préparées,
chiffrement, rate limiting) et à Cloudflare devant le site, votre code est
sérieusement protégé — et surtout, **verrouillé à votre seule autorité**.

*Voir aussi : `SECURITY.md` (politique publique) et `.github/workflows/security.yml`
(le détail des contrôles).*
