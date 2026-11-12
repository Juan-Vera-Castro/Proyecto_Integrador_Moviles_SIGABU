import { expect, iniciarSesion, revisarPantalla, test } from './ayudas.js';

/**
 * H5: recurso y plantilla en el navegador. Usa el caso sin consentimiento de persona.e2e, que
 * admite filas nuevas en cada tamaño de pantalla.
 */
const sinDesborde = (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

test('el profesional consulta el recurso sin desbordar la pantalla', async ({ page }) => {
    await iniciarSesion(page, 'inclusion.e2e@uceva.edu.co');
    await expect(page.locator('a[href$="/profesional/inclusion/recursos"]')).toHaveCount(1);
    await page.goto('/profesional/inclusion/recursos');
    await page.getByRole('link', { name: /Estrategias de Accesibilidad/ }).click();

    await expect(page.getByRole('heading', { name: '1. Introducción' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Índice del recurso' })).toBeVisible();
    expect(await sinDesborde(page)).toBe(true);

});

test('el profesional agrega una fila con evidencia y retoma el borrador', async ({ page }) => {
    await iniciarSesion(page, 'inclusion.e2e@uceva.edu.co');
    await page.goto('/profesional/expedientes?q=900000001');
    await page.getByRole('link', { name: /IN-[0-9]{4}/ }).first().click();
    await page.locator('article', { has: page.getByRole('heading', { name: 'Plantilla de ajustes', exact: true }) }).getByRole('link', { name: /^(Abrir|Ver)$/ }).click();
    await expect(page.getByRole('heading', { name: 'Plantilla de ajustes pedagógicos' })).toBeVisible();

    await page.getByLabel('Descripción de la barrera').fill('Barrera ficticia');
    await page.getByRole('button', { name: 'Guardar borrador' }).click();
    await expect(page.getByText(/Borrador guardado a las/)).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Descripción de la barrera')).toHaveValue('Barrera ficticia');

    await page.getByRole('button', { name: 'Agregar fila' }).click();
    await expect(page.getByText('Selecciona el área o barrera.')).toBeVisible();
    await expect(page.getByLabel('Descripción de la barrera')).toHaveValue('Barrera ficticia');

    await page.getByLabel('Área / Barrera').selectOption('Evaluación');
    await page.getByLabel('Responsable').fill('Docente ficticio');
    await page.getByLabel('Ajuste o estrategia pedagógica implementada').fill('Ajuste ficticio');
    await page.locator('#ajuste-enlace').fill('https://drive.invalid/evidencia');
    await page.getByRole('button', { name: 'Agregar link' }).click();
    await expect(page.getByRole('button', { name: 'Quitar https://drive.invalid/evidencia' })).toBeVisible();
    await page.getByRole('button', { name: 'Agregar fila' }).click();

    await expect(page.getByText('La fila quedó registrada en la plantilla.')).toBeVisible();
    await expect(page.getByRole('article', { name: 'Ajuste de Evaluación' }).first().getByRole('link', { name: /drive\.invalid/ })).toBeVisible();
    await revisarPantalla(page, 'P5 Plantilla con filas');

    // El caso no tiene consentimiento vigente: se avisa antes de compartir, sin bloquear (RN-39).
    await expect(page.getByText('Antes de compartir:')).toBeVisible();
    page.once('dialog', (dialogo) => dialogo.accept());
    const descarga = page.waitForEvent('download');
    await page.getByRole('link', { name: 'PDF para compartir' }).click();
    expect((await descarga).suggestedFilename()).toMatch(/^Plantilla-ajustes-IN-.+\.pdf$/);
});
