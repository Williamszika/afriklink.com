import { defineConfig, devices } from '@playwright/test';

/**
 * Configuration Playwright — tests end-to-end AfrikaLink.
 *
 * - `BASE_URL`   : URL cible (défaut : serveur PHP local sur 8080).
 * - `PW_CHROME`  : chemin d'un Chromium déjà installé (utile en CI/conteneur où
 *                  le navigateur est pré-installé) ; sinon Playwright gère le sien.
 *
 * Bonnes pratiques appliquées (compétence playwright-pro) :
 *   baseURL en config · retries 2 en CI / 0 en local · trace on-first-retry ·
 *   isolation par test · démarrage automatique du serveur PHP.
 */
const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8080';
const executablePath = process.env.PW_CHROME || undefined;

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI
    ? [['github'], ['html', { open: 'never' }]]
    : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    locale: 'fr-FR',
    ...(executablePath ? { launchOptions: { executablePath } } : {}),
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    // Décommenter pour couvrir le mobile :
    // { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
  // Démarre le serveur PHP local si aucun n'écoute déjà (réutilisé hors CI).
  webServer: {
    command: 'php -S 127.0.0.1:8080 -t public public/index.php',
    url: BASE_URL,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
});
