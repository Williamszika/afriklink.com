import { test, expect } from '@playwright/test';

/**
 * Internationalisation & RTL — /lang/{locale} pose un cookie de langue ;
 * l'arabe doit basculer la page en écriture droite-à-gauche (dir="rtl").
 */
test.describe('Internationalisation & RTL', () => {
  test('bascule en arabe : <html dir="rtl" lang="ar">', async ({ page }) => {
    await page.goto('/lang/ar'); // pose le cookie de langue puis redirige
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  });

  test('retour au français : <html dir="ltr" lang="fr">', async ({ page }) => {
    await page.goto('/lang/fr');
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  });
});
