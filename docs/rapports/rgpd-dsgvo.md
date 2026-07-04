# Rapport RGPD / DSGVO — AfrikaLink

*Compétence appliquée : `gdpr-dsgvo-expert` · Date : 2026-07-04 · Analyse automatique + revue manuelle*

> **À lire d'abord.** L'outil automatique est un **détecteur de mots-clés** (il cherche des
> motifs dans le code, sans comprendre le sens). Il est utile comme aide-mémoire, mais il produit
> beaucoup de **fausses alertes**. Le score brut de **0/100** qu'il affiche n'a **aucune valeur** :
> il est gonflé par des correspondances absurdes (détaillées ci-dessous). Ce rapport sépare le
> **bruit** du **signal réel**, puis fait le vrai bilan de conformité d'AfrikaLink.

---

## 1. Ce que l'outil a « trouvé » (et pourquoi c'est trompeur)

| Catégorie affichée | Nombre brut | Réalité |
|---|---:|---|
| « Données personnelles » | 50 correspondances | **Quasi toutes fausses** (voir exemples) |
| « Suivi sans consentement » | 46 | **Fausses** — mots de catalogue |
| « Effacement incomplet » | 3 | **Fausses** — code d'interface |
| « Donnée non chiffrée » | 1 | **Fausse** — lecture d'en-tête HTTP |
| Problèmes de **configuration** | **0** | ✅ **Vrai** — rien à signaler |

### Exemples de fausses alertes (vérifiées à la main)

- **« Donnée sensible non chiffrée » — `app/helpers.php:348`** → c'est la fonction `request_header()`
  qui **lit un en-tête HTTP**. L'outil a vu le mot *key* dans `$key = 'HTTP_'…`.
- **« Suivi sans consentement » — `config/alimentation.php:183`** → c'est une ligne de **catalogue
  alimentaire** : *« Biscuits / cookies »*. L'outil a confondu le **biscuit** avec un cookie de navigateur. 🍪
- **« Violation du droit à l'effacement » — `public/assets/js/app.js:64`** → c'est
  `sel.removeAttribute('tabindex')`, du code d'affichage. L'outil a vu le mot *remove*.
- **« Donnée de catégorie particulière (Art. 9) » — `ProductController.php`** → ce sont les appels
  `beauty_clean()` du **rayon cosmétiques**. « Beauty » n'est pas une donnée de santé.
- **« Numéro d'identité allemand » — `composer.json`, `vercel.json`** → ce sont des **numéros de
  version** de librairies, pas des cartes d'identité.

**Conclusion sur l'automatique :** 0 problème de configuration réel, et 0 fuite de secret dans le
rapport (vérifié). Les « milliers » d'alertes sont du bruit de motifs.

---

## 2. Bilan RÉEL de conformité (revue manuelle)

AfrikaLink est **globalement en bonne posture** RGPD/DSGVO. Ce qui est **déjà en place** :

| Exigence RGPD / DSGVO | Statut | Où, dans AfrikaLink |
|---|:---:|---|
| **Consentement cookies** (Art. 6, ePrivacy) | ✅ | Bandeau `partials/cookie_consent.php` + `LegalController` |
| **Consentement légal à l'inscription** (Art. 7) | ✅ | Case obligatoire + 6 documents (`legal_consent.php`) |
| **Information / transparence** (Art. 13-14) | ✅ | Mentions légales, Confidentialité, CGV, Rétractation, À propos, Cookies |
| **Chiffrement au repos** (Art. 32) | ✅ | `Services/Crypto.php` (sodium) — messages chiffrés en base |
| **Chiffrement en transit** (Art. 32) | ✅ | HTTPS + HSTS forcés (en production) |
| **Minimisation des données** (Art. 5) | ✅ | Géoloc IP avec **interrupteur** `GEO_IP_LOOKUP`, pas de tracking publicitaire tiers |
| **Sécurité par conception** (Art. 25) | ✅ | CSP stricte, CSRF, throttling, scanner de sécurité en CI |
| **Base en UE** (souveraineté) | ⚠️ | Vercel (edge) → voir §3 (registre des sous-traitants) |

### Le seul vrai manque identifié

- **Droit d'accès et d'effacement en libre-service (Art. 15 & 17).** Il n'existe pas encore de
  parcours « **supprimer mon compte** » / « **exporter mes données** » que l'utilisateur déclenche
  seul. Aujourd'hui, une demande doit être traitée à la main. Pour un éditeur basé en **Allemagne
  (DSGVO)**, c'est le point à combler en priorité — voir la fiche technique associée si tu veux
  qu'on l'implémente (route `/compte/supprimer` + export JSON, délai d'un mois Art. 12(3)).

---

## 3. Recommandations concrètes (par priorité)

1. **[P1] Parcours DSAR en libre-service** — bouton « Supprimer mon compte » (anonymisation/effacement)
   + « Télécharger mes données » (export). C'est le seul écart réel avec le RGPD.
2. **[P2] Registre des traitements (Art. 30)** — un simple tableau : quelle donnée, pourquoi,
   combien de temps, qui la traite. Utile en cas de contrôle.
3. **[P2] Contrats de sous-traitance (DPA, Art. 28)** avec chaque prestataire qui voit des données :
   **Stripe** (paiement), **Vercel** (hébergement/edge), et selon config **Cloudinary** (images),
   **Brevo** (e-mails), **Cloudflare Turnstile** (anti-bot). La plupart fournissent un DPA standard à signer.
4. **[P3] Politique de rétention** — durée de conservation des comptes inactifs, commandes, messages.
5. **[P3] DPIA** (analyse d'impact) — non obligatoire au vu du traitement actuel, à refaire si tu
   ajoutes du profilage à grande échelle ou des données sensibles. Le générateur
   `dpia_generator.py` de la compétence peut produire le squelette.

> ⚖️ **Rappel de la compétence :** ces constats sont une **aide**, pas un avis juridique. La décision
> finale de conformité revient à un **DPO / conseil juridique**. Le fondateur étant en Allemagne, la
> **DSGVO** s'applique pleinement.

---

## 4. Comment relancer l'analyse

```bash
python3 .claude/skills/gdpr-dsgvo-expert/scripts/gdpr_compliance_checker.py . --output rapport.md
# Générer un modèle de DPIA :
python3 .claude/skills/gdpr-dsgvo-expert/scripts/dpia_generator.py
# Suivre un délai de demande d'accès (1 mois, Art. 12(3)) :
python3 .claude/skills/gdpr-dsgvo-expert/scripts/data_subject_rights_tracker.py
```

*Aucun secret (`.env`) n'est inclus dans ce rapport ni dans les fichiers versionnés — vérifié.*
