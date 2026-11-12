import { defineConfig, devices } from '@playwright/test';

/**
 * Pruebas E2E en local: `npm run build` y luego `npm run test:e2e`. Usan el Chrome instalado,
 * una base SQLite con datos ficticios (tests/e2e/preparar.php) y nunca la base real.
 */
const PUERTO = 8123;

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './storage/framework/testing/e2e-resultados',
    fullyParallel: false,
    workers: 1,
    timeout: 60000,
    reporter: 'list',
    use: {
        baseURL: `http://127.0.0.1:${PUERTO}`,
        channel: 'chrome',
    },
    projects: [
        { name: 'escritorio', use: { viewport: { width: 1280, height: 800 } } },
        { name: 'tableta', use: { viewport: { width: 768, height: 1024 }, hasTouch: true } },
        { name: 'celular', use: { ...devices['Pixel 5'], viewport: { width: 360, height: 740 } } },
    ],
    webServer: {
        command: `php tests/e2e/preparar.php && php artisan serve --env=e2e --port=${PUERTO} --no-reload`,
        url: `http://127.0.0.1:${PUERTO}/login`,
        reuseExistingServer: false,
        timeout: 60000,
    },
});
