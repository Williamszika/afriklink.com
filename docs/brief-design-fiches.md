# Brief design — Fiches publiques AfrikaLink

> Document à transmettre au designer. Objectif : **(re)concevoir les fiches publiques**
> (produit, restaurant, annonce/service) d'une marketplace multi‑verticale, en
> respectant une identité de marque existante et des contraintes techniques précises.
> Le site est **rendu côté serveur (PHP)** et **componentisé** : on attend un
> **système de composants**, pas des écrans figés.

---

## 1. Le produit en deux phrases

AfrikaLink est une **place de marché internationale multi‑verticale** : sur une seule
plateforme cohabitent **Boutiques** (produits physiques), **Restaurants** (menus),
**Salons** et **Métiers / services**. Tout le monde peut **vendre et acheter, en local
et à l'international**, en **plusieurs langues (FR/EN)** et **plusieurs devises**
(F CFA, €, £, $, ₦…).

**Public** : diaspora africaine en Europe + clients en Afrique de l'Ouest. **Conséquence
majeure : mobile‑first**. Beaucoup d'utilisateurs sont sur téléphone, réseau parfois
lent, et **commandent volontiers via WhatsApp** — ces éléments doivent être traités en
priorité, pas comme des détails.

---

## 2. Objectif du brief

Concevoir les **3 gabarits de fiche publique** ci‑dessous (+ 1 optionnel à décider), en
**desktop ET mobile**, avec **tous leurs états**, sous forme d'un **système de
composants réutilisables** et de **design tokens** alignés sur la marque.

---

## 3. Système de marque (à respecter)

**Esprit** : africain contemporain, chaleureux, premium mais accessible. Motifs **wax**
(tissu) et **cauri** (coquillage) en signes discrets, jamais envahissants.

**Couleurs (tokens existants)**
| Rôle | Hex |
|---|---|
| Vert forêt (primaire) | `#103D30` (foncé `#0B2C22`, clair `#3C7A66`) |
| Or (accent/CTA) | `#E5A02E` (doux `#F5D699`) |
| Crème (fonds) | `#FBF7EF` / `#F2EBDD` |
| Hibiscus (promo/alerte) | `#C8447A` |
| Encre (texte) | `#16241F` · Plume (texte secondaire) `#5B6B62` |

**Typographies**
- Titres / display : **Bricolage Grotesque** (700/800)
- Texte courant : **Inter** (400/500/600)
- Accents techniques (prix, étiquettes) : **Space Mono**

**Rayons** : 10 / 14 / 18 / 26 px · **pilule** 100px. Ombres douces, beaucoup d'air.

---

## 4. Contraintes transverses (toutes les fiches)

1. **Mobile‑first** : concevoir d'abord le mobile (≤ 420px), puis desktop. Le **bouton
   d'achat et le bouton WhatsApp doivent rester accessibles** au pouce (barre d'action
   collante en bas sur mobile, à proposer).
2. **Multi‑devises** : le prix s'affiche dans la **devise du visiteur** + parfois une
   **conversion** à côté. Prévoir des prix **longs** (« 1 250 000 F CFA ») sans casser
   la mise en page.
3. **Bilingue FR/EN** : textes de **longueur variable**. Pas de largeur figée sur les
   libellés.
4. **Accessibilité** : contrastes AA, focus visibles, cibles tactiles ≥ 44px, images
   avec alt, navigation clavier.
5. **Performance** : images **lazy‑loadées**, peu de polices, animations sobres
   (respecter `prefers-reduced-motion`).
6. **États** à dessiner pour CHAQUE composant concerné : normal · survol · **rupture de
   stock** · **promo (−X%)** · **vendeur vérifié** · **chargement** · **vide** ·
   **brouillon (aperçu propriétaire)**.

---

## 5. Les fiches à concevoir

### 5.1 — FICHE PRODUIT (Boutiques) — *la plus riche, prioritaire*

Mise en page type : **2 colonnes desktop** (galerie à gauche, achat à droite), **empilée
mobile**. Sections, de haut en bas :

1. **Fil d'Ariane** — Boutique › rayon › produit (+ marque).
2. **Galerie** — photo principale + miniatures, **agrandissement** (lightbox). Détail
   important : **la galerie change selon la couleur choisie** (chaque couleur peut avoir
   ses propres photos).
3. **Titre** du produit + petites étiquettes (marque, rayon, public…).
4. **Prix** — devise locale **+ conversion** optionnelle ; **prix barré + badge −X%** si
   promo ; mention « en promo jusqu'au … » ; cas spécial **« prix au mètre »** (tissus).
5. **Disponibilité** — « En stock » / « Plus que N ! » / **« Rupture »**.
6. **Déclinaisons** (le point technique clé) — l'acheteur **choisit chaque
   caractéristique** : **Taille** (boutons) **et/ou Couleur** (pastilles).
   - **Multi‑axes** : un produit peut combiner Taille × Couleur.
   - Les combinaisons **indisponibles sont grisées/barrées** et non cliquables.
   - **Prix, stock et galerie s'actualisent** selon la combinaison choisie.
   - Tant que tout n'est pas choisi : bouton en état « Choisir les options ».
7. **Quantité** + **Ajouter au panier** + **⚡ Acheter (achat direct)**.
8. **Actions secondaires** — Ajouter aux **favoris**, **Comparer**.
9. **Canaux de contact/achat** — **Commander sur WhatsApp** (très visible),
   **Partage** (WhatsApp / Facebook / TikTok / copier le lien), éventuel **lien
   d'affiliation** (gagner une commission en partageant).
10. **Vendeur** — nom + **badge ✓ Vendeur vérifié** + **Contacter le vendeur** (messagerie).
11. **Description** — texte rédigé (plusieurs paragraphes).
12. **Caractéristiques** — **tableau adaptatif** (voir §6 — c'est crucial).
13. **Avis clients** — note moyenne + avis avec **photos**. ⚠️ Un avis n'est possible
    qu'**après réception** (« achat vérifié »). Dessiner : liste d'avis (auteur, date,
    note, photos, badge vérifié) **et** le formulaire (étoiles + texte + ajout de photos)
    **et** l'état « pas encore d'avis ».
14. **« Vous aimerez aussi »** — rail de produits liés.

### 5.2 — FICHE RESTAURANT (Menus)

`/restaurant/{slug}`. **Pas de page séparée par plat — le menu EST la vitrine.**

1. **En‑tête resto** — nom, bannière/logo, accroche.
2. **Navigation menu** — ancres par catégorie (Entrées, Plats, Boissons…).
3. **Catégories de menu** — pour chaque **plat (ligne riche)** : **photo + nom +
   description + prix** ; variantes possibles (tailles/portions → « à partir de … ») ;
   **ajout au panier** ; état « épuisé ».
4. **Panier & commande** — retrait / livraison.
5. **Infos** — horaires d'ouverture, zones & frais de livraison, **WhatsApp**.

### 5.3 — FICHE ANNONCE / MÉTIER / SERVICE

`/annonce/{id}`. Format **petite annonce**, plus sobre.

1. **Galerie** photos.
2. **Titre + prix**.
3. **Contact WhatsApp** (action principale).
4. **Carte vendeur** (nom, « membre depuis… »).
5. **Description**.

### 5.4 — *(OPTION à décider)* FICHE SALON (Réservation)

Aujourd'hui, salons et métiers utilisent la fiche « annonce » (5.3). Si on veut une
vraie expérience salon, prévoir un **4ᵉ gabarit** : en‑tête salon → **liste de
prestations** (nom, durée, prix) → **sélection d'un créneau** (calendrier/disponibilités)
→ réservation. **À chiffrer/maquetter seulement si validé.**

---

## 6. Le système ADAPTATIF (à comprendre absolument)

La **fiche produit n'est pas figée** : selon la **catégorie de la boutique**, le bloc
**Caractéristiques** et certains champs **changent automatiquement**. Le designer doit
livrer un **tableau de caractéristiques flexible** (paires libellé/valeur) + quelques
**variantes spécifiques** :

| Verticale | Spécificités à prévoir |
|---|---|
| 📱 Téléphone / électronique | RAM, stockage, état (neuf/reconditionné) |
| 🚗 Pièces auto | **compatibilité véhicule**, badge « universel » |
| 💄 Cosmétique / beauté | composition **INCI**, type de peau/cheveux |
| 🧵 Tissu / artisanat | **vente au mètre**, fait main, pièce unique |
| 👶 Bébé | **âge minimum** |
| 👗 Mode | taille, coupe, matière, entretien |
| ⚽ Sport | spécifs techniques (ex. drop d'une chaussure) |

➡️ **Livrable attendu** : un composant « tableau caractéristiques » + la règle visuelle
pour les **étiquettes spéciales** (ex. « Fait main », « Compatible universel », « Vente
au mètre »).

---

## 7. Composants transverses réutilisables (design system)

À livrer comme **bibliothèque de composants** (pas écran par écran) :

- **Bloc Prix** (devise + conversion + prix barré + badge −%) + variante « au mètre ».
- **Sélecteur de déclinaisons** (pastilles couleur, boutons taille, états grisés).
- **Galerie** (principale + miniatures + lightbox + changement par couleur).
- **Barre d'action achat** (quantité + panier + Acheter) — version **collante mobile**.
- **Badge Vendeur vérifié** + **Carte vendeur**.
- **Rangée de partage social** (WhatsApp / Facebook / TikTok / lien).
- **Bouton WhatsApp** (style dédié).
- **Bloc Avis** (liste + formulaire + état vide + photos).
- **Carte produit** (pour les rails « Vous aimerez aussi »).
- **Badges d'état** : en stock / rupture / promo / nouveauté.
- **Ligne de plat** (restaurant).

---

## 8. Livrables attendus du designer

1. **Maquettes** des 3 fiches (4 si Salon validé) en **mobile + desktop**.
2. Tous les **états** listés au §4.6 pour les composants concernés.
3. La **bibliothèque de composants** (§7) + **design tokens** (couleurs, typos, rayons,
   espacements) cohérents avec §3.
4. Les **spécifications responsive** (points de rupture, comportements).
5. Fichiers **Figma** (composants + variantes + auto‑layout), exportables.

---

## 9. Annexes — repères techniques

- **URLs** : produit `/{boutique}/p/{id}` · restaurant `/restaurant/{slug}` · annonce
  `/annonce/{id}`.
- **Montants** stockés en **centimes** ; affichage formaté selon la devise (séparateurs
  variables, parfois sans décimales pour le F CFA).
- **Rendu serveur** (pas de SPA) : privilégier des composants **HTML/CSS** simples,
  performants, sans dépendances lourdes.
- **Identité existante** déjà en place (vert forêt + or, Bricolage/Inter, motifs wax/cauri)
  — la refonte doit **prolonger** cette identité, pas la remplacer.

---

*Questions de cadrage à trancher avant de démarrer :* (a) garde‑t‑on **3 gabarits** ou
ajoute‑t‑on la **fiche Salon** (5.4) ? (b) veut‑on une **barre d'achat collante** sur
mobile ? (c) la **conversion de devise** s'affiche‑t‑elle toujours, ou au survol ?
