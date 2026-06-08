# Paiements & International — AfrikaLink

## Sommaire
1. Modèle marketplace (Stripe Connect)
2. Flux de paiement sécurisé
3. Multi-devises
4. Multi-langues
5. Livraison internationale
6. Taxes / TVA

---

## 1. Modèle marketplace (Stripe Connect)

AfrikaLink encaisse pour le compte de vendeurs tiers → c'est un **modèle marketplace**, donc
**Stripe Connect** (et pas un Stripe standard).

- Chaque vendeur a un **compte connecté** (`vendors.stripe_account_id`). Type **Express**
  recommandé : onboarding/KYC géré par Stripe, moins de charge réglementaire pour toi.
- L'acheteur paie le montant total ; AfrikaLink prélève une **commission** (`application_fee`),
  le reste est reversé au vendeur (`transfer`/`destination`).
- Onboarding vendeur : générer un lien d'onboarding Stripe, attendre que `charges_enabled` et
  `payouts_enabled` soient vrais avant d'autoriser la vente réelle.
- **Important** : l'encaissement pour compte de tiers a des implications réglementaires (agrément
  de paiement / statut d'agent). Tant que tu n'es pas prêt côté légal, garder les transactions
  réelles désactivées. Voir `references/compliance.md`.

## 2. Flux de paiement sécurisé

1. Le client valide son panier / sa réservation.
2. **Le serveur recalcule** le total depuis la base (jamais le montant envoyé par le client).
3. Création d'un `PaymentIntent` (montant, devise, `application_fee_amount`, `transfer_data` →
   compte connecté du vendeur). Clé d'idempotence Stripe sur la création.
4. Confirmation côté client (Stripe.js / Elements) — la carte ne touche jamais ton serveur (PCI).
5. **Webhook** `payment_intent.succeeded` : signature vérifiée → marquer commande/booking payé,
   enregistrer `payment`, déclencher e-mail. Idempotent via `provider_event_id`.
6. Remboursement / litige : gérer `charge.refunded`, `charge.dispute.created`.

Ne **jamais** marquer une commande payée sur le simple retour navigateur. Source de vérité = le
webhook signé.

## 3. Multi-devises

- Stocker chaque prix avec sa **devise d'origine** (celle du vendeur) en centimes.
- Table `exchange_rates` rafraîchie périodiquement (tâche cron + API de change).
- Distinguer **devise d'affichage** (confort acheteur) et **devise de règlement** (celle qui part
  chez Stripe). Afficher clairement le taux et la devise réellement débitée.
- Formater via `NumberFormatter` (`intl`) selon la locale.
- Attention aux devises **zéro-décimale** (ex. XOF, JPY) : pas de centimes — gérer le facteur par
  devise, ne pas diviser/multiplier par 100 aveuglément.
- Arrondis : toujours arrondir en entier de la plus petite unité de la devise.

## 4. Multi-langues

- Tout texte d'interface dans `lang/<locale>.php` (clé → valeur), helper `t()`. Aucun texte en dur
  dans les vues.
- Démarrer FR + EN ; structure prête pour d'autres langues (pertinent pour le pont Afrique-Europe).
- Le **contenu vendeur** (titres produits, menus) est saisi par le vendeur : prévoir éventuellement
  des champs multilingues ou une traduction assistée, sinon afficher dans la langue d'origine.
- SEO international : `hreflang`, URLs par langue, `lang` sur `<html>`.
- Séparer locale (langue) et country_code (pays) et currency (devise) : ce sont 3 axes distincts.

## 5. Livraison internationale

- `shipping_zones` (groupes de pays) + `shipping_rates` (par poids / par seuil de commande).
- Calculer les frais au panier selon la zone de l'adresse de livraison et le poids cumulé.
- Distinguer local (même pays/ville) et international ; proposer retrait sur place pour
  restaurants/produits locaux.
- Numéro de suivi sur la commande, notifications de statut.
- Mentionner que droits de douane / taxes d'import peuvent s'appliquer à l'acheteur international.

## 6. Taxes / TVA

- `tax_rules` par pays/type. Taux stockés en points de base (entier) pour éviter les flottants.
- L'UE impose des règles spécifiques aux marketplaces (TVA à l'import, guichet IOSS pour les
  envois ≤ 150 €, responsabilité de la plateforme dans certains cas). **Ne pas improviser** :
  c'est un sujet légal/fiscal — voir `references/compliance.md` et faire valider par un
  professionnel avant d'activer les ventes réelles transfrontalières.
- Afficher prix TTC/HT selon le marché ; conserver une trace fiscale (montants de taxe figés sur
  la commande).
