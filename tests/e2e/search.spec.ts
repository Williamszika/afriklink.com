import { test, expect } from '@playwright/test';

/**
 * Recherche — la barre de recherche de l'en-tête mène à /explorer en
 * conservant la requête (même cible que le SearchAction JSON-LD).
 */
test.describe('Recherche', () => {
  test('la barre de recherche mène à /explorer avec la requête', async ({ page }) => {
    await page.goto('/');
    const q = page.locator('input[name="q"]').first();
    await q.fill('savon');
    await q.press('Enter');
    await expect(page).toHaveURL(/\/explorer\?.*q=savon/);
  });

  test('la page /explorer est accessible et affiche le champ de recherche', async ({ page }) => {
    const resp = await page.goto('/explorer?q=');
    expect(resp?.status()).toBeLessThan(400);
    await expect(page.locator('input[name="q"]').first()).toBeVisible();
  });
});
