import { test, expect } from '@playwright/test';

/**
 * Authentification — garde le formulaire de connexion et surtout le
 * « portillon de consentement » légal de l'inscription : le bouton de création
 * de compte reste bloqué tant que la case obligatoire n'est pas cochée.
 */
test.describe('Authentification', () => {
  test('la page de connexion présente le formulaire', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('input[name="identifier"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /se connecter/i })).toBeVisible();
  });

  test('inscription : la case de consentement légal est obligatoire', async ({ page }) => {
    await page.goto('/register/particulier');
    await expect(page.locator('input[name="accept_legal"]')).toHaveJSProperty('required', true);
  });

  test('inscription : le bouton « Créer mon compte » est bloqué sans consentement', async ({ page }) => {
    await page.goto('/register/particulier');
    const submit = page.locator('[data-consent-submit]');
    const consent = page.locator('input[name="accept_legal"]');

    // Au chargement, le portillon JS désactive le bouton.
    await expect(submit).toBeDisabled();
    // Cocher active le bouton…
    await consent.check();
    await expect(submit).toBeEnabled();
    // …décocher le re-désactive.
    await consent.uncheck();
    await expect(submit).toBeDisabled();
  });

  test('les documents légaux sont accessibles', async ({ page }) => {
    for (const path of ['/mentions-legales', '/confidentialite', '/cgv', '/retractation', '/a-propos']) {
      const resp = await page.goto(path);
      expect(resp?.status(), `page ${path}`).toBeLessThan(400);
    }
  });
});
