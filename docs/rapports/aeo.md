# Rapport AEO — AfrikaLink

*Compétence appliquée : `aeo` (Answer Engine Optimization) · Date : 2026-07-04*

**AEO ≠ SEO.** Le SEO optimise pour **apparaître dans Google**. L'AEO optimise pour être **cité comme
source** par les IA qui répondent aux gens : **ChatGPT, Perplexity, Claude, Gemini, Mistral**. L'outil
mesure les signaux **E-E-A-T** (Expérience, Expertise, Autorité, Confiance) et la structure du contenu.

---

## 1. Résultat (page d'accueil)

| Dimension | Score | Lecture |
|---|:---:|---|
| **Autorité** | 98/100 | ✅ Excellent (marque, identité claire) |
| Expertise | 60/100 | Correct |
| **Confiance** | 38 → **47** | ↑ améliorée par les données structurées ajoutées |
| Structure | 20/100 | Faible — *mais peu pertinent ici (voir ci-dessous)* |
| Expérience | 0/100 | Non pertinent pour une page de ce type |
| **Score global** | **45/100 (F)** | En dessous du seuil « article » (65) |

> **Pourquoi ce score bas est normal — et pas inquiétant.** L'outil AEO évalue si une page est un
> **article citable** (« nous avons testé X », citations `[1] [2]`, FAQ, politique de correction…).
> La page d'**accueil d'une marketplace** n'est pas un article : c'est une **vitrine**. On ne cherche
> pas à la faire citer par ChatGPT — on cherche à vendre. L'AEO devient utile sur les **pages de
> contenu** : Aide, guides, « À propos », articles de blog. C'est là qu'il faut viser 65+.

---

## 2. Ce qui a été appliqué tout de suite

**Données structurées JSON-LD sur tout le site** (`Organization` + `WebSite`). Bénéfice double :

- **SEO** → éligibilité aux résultats enrichis Google + barre de recherche Sitelinks.
- **AEO** → identité de marque lisible par les moteurs de réponse ⇒ **Confiance 38 → 47**.

C'est un bloc de **données** (`type="application/ld+json"`), donc **compatible avec la CSP stricte**
(aucun JavaScript en ligne). Vérifié : rendu comme JSON valide, scanners maison verts.

---

## 3. Recommandations (quand tu créeras du contenu éditorial)

Pour les futures pages **Aide / guides / blog** (là où l'AEO compte vraiment) :

1. **Expérience** — écrire à la première personne du vécu réel (« chez AfrikaLink, nous avons
   constaté que… »), dans les 200 premiers mots.
2. **Structure** — sous-titres H2/H3 clairs, réponses courtes en tête de section (format « question →
   réponse » que les IA adorent citer), et un bloc **FAQ** avec `FAQPage` JSON-LD.
3. **Expertise** — citer des sources primaires, dater et signer les contenus (auteur = signal E-E-A-T).
4. **Confiance** — page « politique de correction / mise à jour » liée depuis le pied de page.
5. **Autorité** — schéma `Article` + `Author` sur chaque contenu long.

---

## 4. Comment relancer l'audit

```bash
python3 .claude/skills/aeo/scripts/aeo_audit.py --url http://127.0.0.1:8080/ --industry ecommerce --output markdown
# Sur une page de contenu locale :
python3 .claude/skills/aeo/scripts/aeo_audit.py --input mon-article.md --industry ecommerce
```

*À noter : l'alerte « passer en HTTPS » vue en local est un **artefact du serveur de test** (http).
En production, AfrikaLink force HTTPS + HSTS — ce point est donc déjà réglé.*
