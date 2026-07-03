<!-- Merci de remplir cette checklist avant de demander la fusion.
     La « garde de sécurité » (GitHub Actions) doit être VERTE, et le
     propriétaire doit approuver, avant tout merge dans main. -->

## Que fait ce changement ?

<!-- Décrivez en une phrase ou deux. -->

## Checklist sécurité (obligatoire)

- [ ] Aucun **secret** en dur (clés, mots de passe, jetons) — tout passe par `.env`.
- [ ] Aucune **nouvelle intégration externe** non déclarée ; si un service a été
      ajouté, son domaine est dans `scripts/allowed_hosts.txt` et il est légitime.
- [ ] Toutes les requêtes SQL utilisent des **requêtes préparées** (PDO), jamais de
      concaténation d'entrée utilisateur.
- [ ] Toute sortie affichée est **échappée** (`e()` / `htmlspecialchars`).
- [ ] Les formulaires POST portent un **jeton CSRF**.
- [ ] La **garde de sécurité** (Actions) est verte : lint, scanner, semgrep,
      secrets, dépendances.

## Origine du changement

- [ ] Ce code a été écrit / relu par le propriétaire ou pour son compte.
- [ ] Je comprends que ce merge ne peut être validé que par **@Williamszika**.
