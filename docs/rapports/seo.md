# Rapport SEO — AfrikaLink

*Compétence appliquée : `seo-audit` · Date : 2026-07-04 · Audit du site en local + corrections appliquées*

L'audit note chaque page de **0 à 100** sur le référencement « on-page » (titre, description,
titres H1/H2, images, liens, longueur, mobile). J'ai audité la page d'accueil et les pages
inscription/connexion, **corrigé les vrais problèmes**, puis relancé l'audit.

---

## 1. Résultats

| Page | Avant | Après | Ce qui a été corrigé |
|---|:---:|:---:|---|
| **Accueil** (`/`) | **58** | **90** ✅ | Titre, méta-description, H1, données structurées |
| Connexion (`/login`) | 72 | 72 | Déjà correcte (H1 présent) |
| Inscription (`/register/particulier`) | 70 | 70 | Déjà correcte (H1 présent) |

> Le point restant sur l'accueil (« plus de liens externes qu'internes ») est un **artefact de
> l'outil** : AfrikaLink écrit ses liens en adresse complète (`https://afriklink.com/…`) et le
> vérificateur compte toute adresse complète comme « externe ». Ce n'est **pas** un vrai défaut —
> Google gère parfaitement les liens internes en adresse complète.

---

## 2. Corrections appliquées (accueil)

1. **Titre de page** — était juste « Afriklink » (9 caractères, trop court).
   → Devient **« La marketplace qui relie l'Afrique et le monde — Afriklink »** (58 car., idéal).
   *(`HomeController` passe désormais `page_title`.)*

2. **Méta-description** — enrichie à ~155 caractères dans les **8 langues**, pour un meilleur aperçu
   dans les résultats Google. *(nouvelle clé i18n `home.seo_desc`.)*

3. **Titre principal H1** — la page d'accueil n'avait **aucun H1** (seulement des H2). Ajout d'un H1
   unique, **masqué visuellement** (`class="sr-only"`) pour ne pas toucher au design : il reste lu
   par Google et les lecteurs d'écran (accessibilité). Au passage, l'utilitaire CSS `.sr-only`
   manquait alors qu'il était déjà référencé ailleurs — il est désormais défini.

4. **Données structurées (JSON-LD)** — voir le rapport AEO : un bloc `Organization` + `WebSite`
   (avec barre de recherche Google « Sitelinks Searchbox ») est ajouté sur **toutes** les pages.

Tout reste **conforme à la CSP stricte** (le JSON-LD est un bloc de *données*, pas de JavaScript) —
les deux scanners maison (`security_scan.php`, `design_scan.php`) restent **verts**.

---

## 3. Pistes pour aller plus loin (optionnel)

- **`<title>` et description propres par page** sur les vitrines (boutique, produit, restaurant,
  annonce) : plusieurs les passent déjà via `meta`; à généraliser sur les pages catégories.
- **Sitemap.xml** + `robots.txt` à jour pour accélérer l'indexation.
- **Texte d'introduction** court et riche en mots-clés sur l'accueil et les pages catégories
  (aujourd'hui l'accueil est surtout visuel).
- **Balises `alt`** sur les images produits (voir `design_scan.php` — ~80 images sans `alt`).

---

## 4. Comment relancer l'audit

```bash
# 1) lancer le serveur local
php -S 127.0.0.1:8080 -t public public/index.php
# 2) auditer une page
python3 .claude/skills/seo-audit/scripts/seo_checker.py --url http://127.0.0.1:8080/ --json
```
