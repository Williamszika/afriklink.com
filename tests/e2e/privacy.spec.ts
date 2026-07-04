import { test, expect } from '@playwright/test';

/**
 * RGPD — les pages « Mes données » et « Supprimer mon compte » sont réservées
 * aux personnes connectées : un visiteur anonyme est renvoyé vers /login.
 * (Le parcours authentifié complet est vérifié côté serveur — voir les services
 * AccountData / AccountEraser.)
 */
test.describe('RGPD — accès protégé', () => {
  for (const path of ['/profile/donnees', '/profile/donnees/export', '/profile/supprimer']) {
    test(`${path} exige une connexion`, async ({ page }) => {
      await page.goto(path);
      await expect(page).toHaveURL(/\/login/);
    });
  }
});
