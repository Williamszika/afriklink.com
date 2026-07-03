---
name: afrikalink-design
description: >-
  Concevoir et modifier l'interface d'AfrikaLink avec un niveau de finition constant
  (méthode inspirée du skill « product-design » de Vercel). Utilise ce skill DÈS QUE
  tu crées ou modifies un écran, une page, un formulaire, un composant, une carte, un
  bouton, un état visuel ou du CSS/HTML pour AfrikaLink — même si le mot « design »
  n'est pas prononcé. Objectif : partir du besoin (pas des pixels), concevoir CHAQUE
  état, respecter l'accessibilité, les 8 langues (dont l'arabe/RTL), la CSP stricte,
  et VÉRIFIER l'écran rendu (captures), jamais depuis le code seul.
---

# AfrikaLink — skill de design produit

Le code qui « marche » ne suffit pas. Chaque décision d'interface doit se justifier
par le **besoin utilisateur**, une **contrainte du projet**, ou un **motif existant
vérifié** — pas par le goût. Ce skill encode nos règles pour que la qualité soit
**automatique et régulière**, pas refaite à la main à chaque fois.

## 1. Principes (dans l'ordre)

1. **Partir du job, pas des pixels.** Qui ? quel but ? quel objet modifié ? quel
   changement système ? Définis le résultat attendu AVANT de choisir des composants.
2. **Concevoir CHAQUE état atteignable** — pas seulement le cas idéal :
   chargement · vide · peu rempli · rempli · validation · **erreur** · permission ·
   désactivé · verrouillé (géoloc) · destructif · **compact (mobile) & large** · **RTL**.
3. **Preuve, pas goût.** Trace chaque choix à un comportement produit, une règle de
   ce dépôt, ou un écran voisin déjà validé.
4. **Vérifier la surface RENDUE.** Ne JAMAIS affirmer un rendu depuis le code :
   lance le serveur + capture (voir §5).
5. **Défauts forts.** Comportement direct et bon par défaut, plutôt que des options
   que l'utilisateur doit régler.

## 2. Contraintes NON négociables d'AfrikaLink

Ces règles priment ; elles viennent de la stack réelle (voir aussi `afrikalink-builder`).

- **CSP stricte** : AUCUN `<script>` inline, AUCUN JS externe (CDN). Toute
  interactivité va dans `public/assets/js/app.js`, pilotée par attributs `data-*`
  (les libellés i18n passent aussi par `data-*`). Pas de `onclick=`/`on…=` inline.
  `style=""` inline toléré (CSP `style-src 'unsafe-inline'`) mais à éviter.
- **Échapper toute sortie** avec `e()` (jamais de variable brute dans le HTML).
- **i18n : 8 langues.** Toute chaîne visible via `t('clé')`, ajoutée aux **8**
  fichiers `lang/*.php`. Les remplacements `:ph` sont enveloppés d'`e()`.
- **RTL (arabe).** `locale_dir()` met `dir="rtl"`. → Utiliser des **propriétés
  logiques** : `margin-inline`, `padding-inline-*`, `inset-inline-*`, `text-align:start`.
  JAMAIS `left/right/padding-left/margin-right…` en dur. Prévoir un override
  `[dir=rtl] …` quand une propriété n'a pas d'équivalent logique (ex. flèche de select).
- **Polices auto-hébergées** : `var(--afk-display)` (Bricolage Grotesque),
  `var(--afk-body)` (Inter), `var(--afk-mono)` (Space Mono). JAMAIS Google Fonts.
- **Couleurs/tokens** : réutiliser les variables (`--afk-foret`, `--accent`, `--a-*`
  du bloc `.authx`, `--border`, `--danger`…). Pas de hex en dur dispersés.
- **Argent** : centimes entiers, jamais de float.

## 3. Règles d'interface (reprises de Vercel, adaptées)

- **Navigation** pour naviguer, **action** pour agir. Ne pas détourner un lien en bouton.
- **2–3 options statiques → boutons radio**, pas un `<select>` (ex. le sélecteur
  e-mail/téléphone est en radios CSS-only). Beaucoup d'options (pays) → `<select>`.
- **CTA destructif = Verbe + Nom** (« Supprimer la boutique »), jamais « Confirmer »/« OK ».
  Action destructive proportionnelle ; proposer une annulation si possible.
- **Accessibilité** : un nom accessible pour chaque contrôle ; `aria-label` sur les
  boutons-icônes (ex. l'œil du mot de passe) ; **focus visible** (`:focus-visible`,
  jeton partagé) ; cibles tactiles **≥ 40 px**.
- **Divulgation progressive** avant les modales ; pas de modale imbriquée.
- **Préserver la saisie** à travers la validation et les erreurs récupérables ; le
  champ en erreur porte une **bordure rouge** + message (pas seulement du texte).
- **Chargement** : libellé **stable** + affordance du composant (spinner
  `.abtn.is-loading`, `data-submit-once`) ; pas de texte qui saute.
- **Pas de nouveauté décorative** (animation, cuivre visuel) sauf si elle clarifie la
  structure ou l'état. Respecter `prefers-reduced-motion`.

## 4. Réutiliser l'existant

- Bloc auth : classes `.authx` (pagehead, `.acard`, fieldsets numérotés, `.afield`,
  `.albl`, `.abtn`, `.phone-row`, `.two`). Partials : `pwd_field`, `legal_consent`,
  `auth_aside`, `geo_lock_controls`, `geo_fields`.
- Ne pas dupliquer un composant : étendre / paramétrer (ex. `auth_aside` a une
  variante `seller`/`member`).

## 5. Vérifier (obligatoire avant de dire « c'est fait »)

1. `php -l` sur les fichiers PHP touchés ; `node --check public/assets/js/app.js`.
2. `php scripts/design_scan.php` (vérificateur design) et `php scripts/security_scan.php`.
3. Lancer le rendu et **capturer** (le serveur relit les fichiers à chaque requête) :
   ```bash
   php -S 127.0.0.1:8080 -t public public/index.php   # APP_URL doit pointer sur 8080
   # puis Playwright (chromium /opt/pw-browsers) → screenshot
   ```
4. Vérifier sur écran rendu : **compact ≈ 390 px** ET **large**, + **`dir="rtl"`**
   (forcer `document.documentElement.dir='rtl'` pour tester l'arabe).
5. Exercer chaque **état** modifié (chargement, erreur, vide, désactivé…).
6. Clavier : ordre de tabulation, focus visible, cibles tactiles.
7. Contenu extrême : texte long, grandes valeurs, largeur contrainte.

## 6. Perfectionner (boucle de revue)

Quand un vrai défaut apparaît (ex. l'œil du mot de passe du mauvais côté en RTL) :
1. le corriger ; 2. en tirer une **règle explicite** ici ; 3. si c'est détectable
mécaniquement, l'ajouter à `scripts/design_scan.php` ; 4. le propriétaire valide la
règle. Les standards se durcissent avec le temps, à partir de preuves.

> Aucune checklist ne remplace le jugement : ce skill guide les décisions, il ne les
> automatise pas. En cas de doute, privilégier la clarté et le défaut le plus simple.
