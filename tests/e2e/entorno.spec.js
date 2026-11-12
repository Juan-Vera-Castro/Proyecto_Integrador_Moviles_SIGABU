import { expect, iniciarSesion, test } from './ayudas.js';

test('la persona ficticia entra a su panel', async ({ page }) => {
    await iniciarSesion(page, 'persona.e2e@uceva.edu.co');

    await expect(page).toHaveURL(/estudiante\/home/);
});

test('el profesional de Inclusión entra a su listado sin desbordar la pantalla', async ({ page }) => {
    await iniciarSesion(page, 'inclusion.e2e@uceva.edu.co');
    await page.goto('/profesional/inclusion');

    await expect(page).toHaveURL(/profesional\/inclusion/);

    const desborde = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(desborde).toBeLessThanOrEqual(0);
});

test('el profesional solo de Psicología no entra al listado de Inclusión', async ({ page }) => {
    await iniciarSesion(page, 'psicologia.e2e@uceva.edu.co');
    await page.goto('/profesional/inclusion');

    await expect(page).not.toHaveURL(/profesional\/inclusion/);
});
