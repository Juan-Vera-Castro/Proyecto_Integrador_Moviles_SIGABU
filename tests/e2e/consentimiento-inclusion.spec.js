import { expect, iniciarSesion, revisarPantalla, test } from './ayudas.js';

/**
 * H3: la persona diligencia su consentimiento en el navegador. Cada tamaño de pantalla usa su
 * propia persona ficticia y su caso, porque el consentimiento firmado no se repite.
 */
const CORREOS = {
    escritorio: 'persona.escritorio.e2e@uceva.edu.co',
    tableta: 'persona.tableta.e2e@uceva.edu.co',
    celular: 'persona.celular.e2e@uceva.edu.co',
};

async function firmar(page) {
    const lienzo = page.locator('canvas[x-ref="canvas"]');
    await lienzo.scrollIntoViewIfNeeded();
    const caja = await lienzo.boundingBox();

    await page.mouse.move(caja.x + 20, caja.y + 20);
    await page.mouse.down();
    await page.mouse.move(caja.x + caja.width / 2, caja.y + caja.height - 20, { steps: 8 });
    await page.mouse.move(caja.x + caja.width - 20, caja.y + 20, { steps: 8 });
    await page.mouse.up();
}

test('la persona guarda un borrador, corrige errores, firma y revoca', async ({ page }, info) => {
    await page.clock.install();
    await iniciarSesion(page, CORREOS[info.project.name]);
    await page.goto('/estudiante/mis-citas');
    await page.getByRole('link', { name: 'Firmar consentimiento' }).click();

    await expect(page.getByRole('heading', { name: 'Consentimiento informado', level: 1 })).toBeVisible();
    await expect(page.getByLabel('Nombre de la persona')).toHaveValue(/Persona/);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    // Borrador: se retoma al recargar y no conserva la firma
    await page.getByLabel('Número de contacto').fill('3001234567');
    await page.clock.fastForward(31000);
    await expect(page.getByText(/Borrador guardado a las/)).toBeVisible();

    await page.getByLabel('AUTORIZO el tratamiento de mis datos personales generales.').check();
    await page.getByRole('button', { name: 'Guardar borrador' }).click();
    await expect(page.getByRole('button', { name: 'Guardar borrador' })).toBeEnabled();
    await expect(page.getByText(/Borrador guardado a las/)).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Número de contacto')).toHaveValue('3001234567');
    await expect(page.getByLabel('AUTORIZO el tratamiento de mis datos personales generales.')).toBeChecked();

    // Sin vinculación ni firma: no se guarda y lo escrito sigue ahí
    await page.getByLabel('ACEPTO recibir acompañamiento').check();
    await page.getByRole('button', { name: 'Firmar y enviar' }).click();
    await expect(page.getByText('Selecciona la vinculación con la universidad.')).toBeVisible();
    await expect(page.getByText('Falta la firma. Firma dentro del recuadro.')).toBeVisible();
    await expect(page.getByLabel('Número de contacto')).toHaveValue('3001234567');

    // Los campos de estudiante aparecen según la vinculación
    await expect(page.getByLabel('Programa', { exact: true })).toBeHidden();
    await page.getByLabel('Vinculación').selectOption('Estudiante Diurno');
    await page.getByLabel('Facultad').selectOption({ index: 1 });
    await page.getByLabel('Programa', { exact: true }).fill('Programa Ficticio');
    await page.getByLabel('Semestre').fill('3');

    // Sin la decisión 1 se pide confirmar; al cancelar no se envía nada
    await page.getByLabel('ACEPTO recibir acompañamiento').uncheck();
    await firmar(page);
    const dialogos = [];
    page.once('dialog', (dialogo) => {
        dialogos.push(dialogo.message());
        dialogo.dismiss();
    });
    await page.getByRole('button', { name: 'Firmar y enviar' }).click();
    await expect.poll(() => dialogos.length).toBe(1);
    expect(dialogos[0]).toContain('No marcaste');
    await expect(page).toHaveURL(/inclusion\/consentimiento$/);

    await page.getByLabel('ACEPTO recibir acompañamiento').check();
    await page.getByRole('button', { name: 'Firmar y enviar' }).click();

    await expect(page.getByRole('heading', { name: 'Mi consentimiento' })).toBeVisible();
    await expect(page.getByText('Completado', { exact: true })).toBeVisible();
    await revisarPantalla(page, 'P2 Mi consentimiento');

    // Al cancelar la revocación nada cambia
    page.once('dialog', (dialogo) => dialogo.dismiss());
    await page.getByRole('button', { name: 'Revocar consentimiento' }).click();
    await expect(page.getByText('Completado', { exact: true })).toBeVisible();

    page.once('dialog', (dialogo) => dialogo.accept());
    await page.getByRole('button', { name: 'Revocar consentimiento' }).click();
    await expect(page.getByText(/Revocado el \d/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Firmar un consentimiento nuevo' })).toBeVisible();
});

test('el profesional ve los documentos de Inclusión y el formulario en papel conserva lo escrito', async ({ page }, info) => {
    await iniciarSesion(page, 'inclusion.e2e@uceva.edu.co');
    await page.goto('/profesional/expedientes?q=900000011');
    await page.getByRole('link', { name: /IN-[0-9]{4}/ }).first().click();

    await expect(page.getByRole('heading', { name: 'Documentos de Inclusión' })).toBeVisible();
    await revisarPantalla(page, 'P3 Expediente con consentimiento');

    await page.getByRole('link', { name: 'Cargar en papel' }).first().click();
    await expect(page.getByRole('heading', { name: 'Consentimiento firmado en papel' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    await page.getByLabel('Por qué la persona no firmó en la plataforma').fill('Motivo ficticio');
    await page.getByRole('button', { name: 'Registrar' }).click();

    await expect(page.getByText('Sube el formato firmado.')).toBeVisible();
    await expect(page.getByLabel('Por qué la persona no firmó en la plataforma')).toHaveValue('Motivo ficticio');
});
