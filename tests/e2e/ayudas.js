import AxeBuilder from '@axe-core/playwright';
import { expect, test as base } from '@playwright/test';

/**
 * Bloquea lo externo (fuentes e imágenes): las pruebas no deben depender de internet, y una
 * respuesta lenta de esos sitios agotaba la espera de carga de la página.
 */
export const test = base.extend({
    page: async ({ page }, usar) => {
        await page.route(/^https?:\/\/(?!127\.0\.0\.1)/, (ruta) => ruta.abort());
        await usar(page);
    },
});

export { expect } from '@playwright/test';

/**
 * Inicia sesión con un usuario ficticio de tests/e2e/preparar.php.
 */
export async function iniciarSesion(page, correo) {
    await page.goto('/login');
    await page.locator('#email').fill(correo);
    await page.locator('#password').fill('Prueba123');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => ! url.pathname.endsWith('/login'));
}

/**
 * Revisión de calidad de una pantalla (T-701, T-702): sin desplazamiento horizontal y sin
 * fallas de WCAG 2.1 A y AA según axe.
 */
export async function revisarPantalla(page, nombre) {
    const desborde = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect.soft(desborde, `${nombre}: desplazamiento horizontal`).toBeLessThanOrEqual(0);

    const resultado = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    const fallas = resultado.violations.map((falla) => `${falla.id} (${falla.impact}): ${falla.nodes.slice(0, 3).map((nodo) => `${nodo.html.slice(0, 160)} → ${(nodo.any[0] || nodo.all[0] || nodo.none[0])?.message ?? ''}`).join(' | ')}`);
    expect.soft(fallas, `${nombre}: fallas de accesibilidad`).toEqual([]);
}
