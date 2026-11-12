import { expect, iniciarSesion, revisarPantalla, test } from './ayudas.js';

/**
 * H7 (T-701, T-702): cada pantalla nueva de Inclusión, en escritorio, tableta y celular, sin
 * desplazamiento horizontal y sin fallas de accesibilidad AA. Solo consulta: no cambia datos.
 */
test('las pantallas de la persona pasan la revisión', async ({ page }) => {
    await iniciarSesion(page, 'persona.e2e@uceva.edu.co');
    await page.goto('/estudiante/mis-citas');
    await revisarPantalla(page, 'Mis citas');

    await page.getByRole('link', { name: 'Firmar consentimiento' }).click();
    await expect(page.getByRole('heading', { name: 'Consentimiento informado', level: 1 })).toBeVisible();
    await revisarPantalla(page, 'P1 Consentimiento');
});

test('las pantallas del profesional pasan la revisión', async ({ page }) => {
    await iniciarSesion(page, 'inclusion.e2e@uceva.edu.co');
    await page.goto('/profesional/inclusion');
    await revisarPantalla(page, 'Listado de Inclusión');

    await page.goto('/profesional/expedientes?q=900000001');
    await page.getByRole('link', { name: /IN-[0-9]{4}/ }).first().click();
    await expect(page.getByRole('heading', { name: 'Documentos de Inclusión' })).toBeVisible();
    await revisarPantalla(page, 'P3 Expediente');
    const expediente = page.url();

    await page.goto(expediente.replace('/expedientes/', '/casos/'));
    await expect(page.getByRole('link', { name: 'Abrir plantilla de ajustes' })).toBeVisible();
    await revisarPantalla(page, 'Pantalla del caso');

    await page.goto(expediente);

    await page.locator('article', { has: page.getByRole('heading', { name: 'Bitácora', exact: true }) }).getByRole('link', { name: /^(Abrir|Ver)$/ }).click();
    await expect(page.getByRole('heading', { name: 'Bitácora de orientación y acompañamiento' })).toBeVisible();
    await revisarPantalla(page, 'P4 Bitácora');

    await page.goto(expediente);
    await page.locator('article', { has: page.getByRole('heading', { name: 'Plantilla de ajustes', exact: true }) }).getByRole('link', { name: /^(Abrir|Ver)$/ }).click();
    await expect(page.getByRole('heading', { name: 'Plantilla de ajustes pedagógicos' })).toBeVisible();
    await revisarPantalla(page, 'P5 Plantilla');

    await page.goto(expediente);
    const papel = page.getByRole('link', { name: 'Cargar en papel' });
    if (await papel.count()) {
        await papel.first().click();
        await expect(page.getByRole('heading', { name: 'Consentimiento firmado en papel' })).toBeVisible();
        await revisarPantalla(page, 'P6 Papel');
    }

    await page.goto('/profesional/inclusion/recursos');
    await revisarPantalla(page, 'Catálogo de recursos');
    await page.getByRole('link', { name: /Estrategias de Accesibilidad/ }).click();
    await expect(page.getByRole('heading', { name: '1. Introducción' })).toBeVisible();
    await revisarPantalla(page, 'P5 Recurso');
});

test('la pantalla de reapertura del administrador pasa la revisión', async ({ page }) => {
    await iniciarSesion(page, 'admin.e2e@uceva.edu.co');
    await page.goto('/admin/casos/reapertura');
    await revisarPantalla(page, 'Reapertura');
});

test('el consentimiento se diligencia con teclado y el foco se ve', async ({ page }) => {
    await iniciarSesion(page, 'persona.e2e@uceva.edu.co');
    await page.goto('/estudiante/mis-citas');
    await page.getByRole('link', { name: 'Firmar consentimiento' }).click();

    const casilla = page.getByLabel('AUTORIZO el tratamiento de mis datos personales generales.');
    await casilla.focus();
    await page.keyboard.press('Space');
    await expect(casilla).toBeChecked();
    await page.keyboard.press('Space');
    await expect(casilla).not.toBeChecked();

    // Con Tab se llega a los botones y cada control enfocado muestra su anillo de foco.
    const vistos = [];
    for (let i = 0; i < 60 && ! vistos.includes('Firmar y enviar'); i++) {
        await page.keyboard.press('Tab');
        const foco = await page.evaluate(() => {
            const elemento = document.activeElement;
            const estilo = getComputedStyle(elemento);
            const visible = (estilo.outlineStyle !== 'none' && parseFloat(estilo.outlineWidth) > 0) || estilo.boxShadow !== 'none';

            return { texto: (elemento.innerText || elemento.getAttribute('aria-label') || elemento.id || '').trim(), visible, etiqueta: elemento.tagName };
        });
        vistos.push(foco.texto);
        expect.soft(foco.visible, `foco visible en ${foco.etiqueta} "${foco.texto}"`).toBe(true);
    }
    expect(vistos).toContain('Guardar borrador');
    expect(vistos).toContain('Firmar y enviar');
});
