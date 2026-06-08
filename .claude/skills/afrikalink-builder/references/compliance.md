# Conformité légale — AfrikaLink (marketplace internationale)

> Repères, pas un avis juridique. Une marketplace qui encaisse pour des tiers et opère en UE +
> international cumule plusieurs régimes. **Faire valider par un juriste / fiscaliste** avant
> d'activer les transactions réelles. Adapter au pays d'établissement (l'utilisateur est en
> Allemagne).

## 1. Structure juridique & responsabilité
- Une marketplace expose à la responsabilité (contenus, transactions, litiges). Envisager une
  structure à responsabilité limitée (UG puis GmbH en Allemagne) plutôt qu'en nom propre.
- Mentions légales obligatoires : **Impressum (§5 DDG)** en Allemagne, CGU, CGV, politique de
  confidentialité, politique de cookies.

## 2. Protection des données (RGPD / DSGVO)
- Base légale pour chaque traitement, registre des traitements, minimisation des données.
- Droits des personnes (accès, rectification, effacement, portabilité).
- **AVV / DPA** (Auftragsverarbeitungsvertrag) avec chaque sous-traitant : Stripe, hébergeur,
  e-mail, toute API IA, Cloudflare.
- Cookies/traceurs : consentement préalable (bandeau conforme), pas de dépôt avant accord.
- Chiffrement en transit (HTTPS) et au repos pour les données sensibles ; durées de conservation.
- Notification de violation de données sous 72 h le cas échéant.

## 3. Régulation des plateformes (UE)
- **DSA (Digital Services Act)** : obligations de modération, mécanisme de signalement
  (notice-and-action), transparence, point de contact, traitement des réclamations.
- **P2B (Platform-to-Business)** : transparence des conditions vis-à-vis des vendeurs
  professionnels, classement, traitement des données.

## 4. Obligations fiscales & déclaratives
- **DAC7** : les plateformes UE doivent collecter et déclarer les revenus des vendeurs à
  l'administration fiscale. Prévoir la collecte des infos vendeurs dès le départ.
- **TVA / IOSS** : régime TVA des ventes à distance, guichet IOSS pour les imports ≤ 150 €,
  responsabilité TVA de la marketplace dans certains cas (cf.
  `references/payments-and-international.md`).

## 5. Paiements & lutte anti-blanchiment
- Encaisser pour le compte de tiers peut requérir un **agrément/statut d'établissement de
  paiement** (en Allemagne : BaFin) ou le recours à un prestataire agréé. Utiliser **Stripe
  Connect** (Stripe porte une grande partie de la charge réglementaire) réduit fortement le risque,
  mais ne dispense pas de vérifier ta situation.
- **KYC/AML** : Stripe Connect gère l'identification des vendeurs ; conserver la cohérence.

## 6. Verticales spécifiques
- **Restaurants / alimentaire** : règles d'hygiène, information allergènes, mentions obligatoires
  (responsabilité surtout côté vendeur, mais la plateforme doit le cadrer dans ses CGU).
- **Produits importés** : selon catégories — cosmétiques (notification type CPNP en UE), marquage
  **CE**, espèces protégées (**CITES**), produits réglementés. Filtrer les catégories interdites.
- **Salons / services à la personne** : statut des prestataires, assurances ; la plateforme met en
  relation, elle n'emploie pas (à clarifier dans les CGU).
- Une section type "rencontres"/mise en relation personnelle est un risque légal et de modération
  élevé : à éviter ou à traiter avec des garde-fous stricts.

## 7. Consommateurs
- Droit de rétractation (ventes à distance UE), informations précontractuelles, gestion des
  retours/remboursements, résolution des litiges (médiation/RLL).

## 8. Approche recommandée
- Démarrer en **mode vitrine / MVP** (catalogue, profils, démonstration) tant que la structure
  juridique, les CGU/CGV, le RGPD et le statut paiement ne sont pas en place.
- Activer les transactions réelles **par étapes**, marché par marché, après validation juridique.
- Tenir une checklist de conformité par pays cible avant ouverture.
