import { test, expect } from '@playwright/test';

/**
 * Fumée — garde les acquis SEO/AEO de la page d'accueil :
 * titre descriptif, H1 unique, données structurées JSON-LD, langue déclarée.
 */
test.describe('Fumée — page d\'accueil', () => {
  test('se charge avec un titre descriptif', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Afriklink/);
    // Le titre ne doit plus être le simple « Afriklink » (9 car.).
    expect((await page.title()).length).toBeGreaterThan(20);
  });

  test('expose exactement un H1 (SEO + accessibilité)', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('h1')).toHaveCount(1);
  });

  test('publie des données structurées JSON-LD valides (Organization + WebSite)', async ({ page }) => {
    await page.goto('/');
    const blocks = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(blocks.length).toBeGreaterThan(0);
    const types = blocks
      .map((b) => JSON.parse(b))
      .flatMap((d: any) => (d['@graph'] ? d['@graph'].map((n: any) => n['@type']) : [d['@type']]));
    expect(types).toContain('Organization');
    expect(types).toContain('WebSite');
  });

  test('déclare la langue sur <html>', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('lang', /.+/);
  });
});
