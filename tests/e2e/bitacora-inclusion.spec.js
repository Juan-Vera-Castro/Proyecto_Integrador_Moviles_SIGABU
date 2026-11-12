import { expect, iniciarSesion, revisarPantalla, test } from './ayudas.js';

/**
 * H4: bitácora en el navegador. Cada tamaño de pantalla usa un caso ficticio propio, porque el
 * flujo termina cerrándolo. Prueba también la carga real de archivos y links (T-204).
 */
const DOCUMENTOS = { escritorio: '900000021', tableta: '900000022', celular: '900000023' };

const sinDesborde = (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

const PDF = { name: 'soporte-ficticio.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 soporte ficticio') };

const PNG = {
    name: 'foto-ficticia.png',
    mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64'),
};

async function abrirBitacora(page, documento) {
    await page.goto(`/profesional/expedientes?q=${documento}`);
    await page.getByRole('link', { name: /IN-[0-9]{4}/ }).first().click();
    await page.locator('article', { has: page.getByRole('heading', { name: 'Bitácora', exact: true }) }).getByRole('link', { name: /^(Abrir|Ver)$/ }).click();
    await expect(page.getByRole('heading', { name: 'Bitácora de orientación y acompañamiento' })).toBeVisible();
}

async function abrirSeccion(page, nombre) {
    const seccion = page.locator('details', { has: page.locator('summary', { hasText: nombre }) }).first();

    if (!(await seccion.evaluate((elemento) => elemento.open))) {
        await seccion.locator('summary').first().click();
    }
}

test('el profesional caracteriza con soportes, agrega entradas, anota y cierra', async ({ page }, info) => {
    await iniciarSesion(page, 'inclusion.e2e@uceva.edu.co');
    await abrirBitacora(page, DOCUMENTOS[info.project.name]);
    await abrirSeccion(page, 'II. Caracterización');
    expect(await sinDesborde(page)).toBe(true);

    // Carga real: tipo no permitido, archivo válido con vista previa, quitar y link
    const archivos = page.locator('#soportes-archivo');
    await archivos.setInputFiles({ name: 'programa.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ') });
    await expect(page.getByText(/Formatos permitidos/)).toBeVisible();

    await archivos.setInputFiles(PNG);
    await expect(page.getByRole('button', { name: 'Quitar foto-ficticia.png' })).toBeVisible();
    await expect(page.locator('img[src^="blob:"]')).toHaveCount(1);
    await page.getByRole('button', { name: 'Quitar foto-ficticia.png' }).click();
    await expect(page.getByRole('button', { name: 'Quitar foto-ficticia.png' })).toHaveCount(0);

    await archivos.setInputFiles(PDF);
    await expect(page.getByRole('button', { name: 'Quitar soporte-ficticio.pdf' })).toBeVisible();

    await page.locator('#soportes-enlace').fill('http://sin-https.invalid');
    await page.locator('#soportes-enlace').press('Enter');
    await expect(page.getByText('El link debe ser una dirección https:// válida.')).toBeVisible();
    await page.locator('#soportes-enlace').fill('https://drive.invalid/ficticio');
    await page.getByRole('button', { name: 'Agregar link' }).first().click();
    await expect(page.getByRole('button', { name: 'Quitar https://drive.invalid/ficticio' })).toBeVisible();

    await page.getByLabel('Rol institucional').selectOption('Estudiante Diurno');
    await page.getByLabel('Programa académico / Dependencia').fill('Programa Ficticio');
    await page.getByLabel('Situación o discapacidad').fill('Situación ficticia');
    await page.getByLabel('Ruta activada por').fill('Ruta ficticia');
    await page.getByRole('button', { name: 'Guardar caracterización' }).click();
    await expect(page.getByText('La bitácora quedó registrada.')).toBeVisible();

    // Primera entrada: solo Atención inicial
    await page.getByText('Agregar entrada N° 1').click();
    await expect(page.locator('#tipo option')).toHaveText(['Atención inicial']);
    await page.getByLabel('Descripción detallada de la orientación').fill('Entrevista ficticia');
    await page.getByRole('button', { name: 'Guardar entrada' }).click();
    await expect(page.getByText('La entrada N° 1 quedó registrada.')).toBeVisible();

    // Anotación: la entrada original sigue igual
    await page.getByRole('article', { name: 'Entrada 1' }).getByText('Anotar', { exact: true }).click();
    await page.getByLabel('Anotación a la entrada N° 1').fill('Corrección ficticia');
    await page.getByRole('button', { name: 'Guardar anotación' }).click();
    await expect(page.getByText('Corrección ficticia')).toBeVisible();
    await expect(page.getByText('Entrevista ficticia')).toBeVisible();

    // Entrada de Cierre y constancia firmada
    await page.getByText('Agregar entrada N° 2').click();
    await page.getByLabel('Tipo de acción').selectOption('Cierre');
    await page.getByLabel('Descripción detallada de la orientación').fill('Cierre ficticio');
    await page.locator('#entrada-archivo').setInputFiles(PDF);
    await expect(page.getByRole('button', { name: 'Quitar soporte-ficticio.pdf' })).toBeVisible();
    await page.getByRole('button', { name: 'Guardar entrada' }).click();
    await expect(page.getByText('La entrada N° 2 quedó registrada.')).toBeVisible();
    await expect(page.getByRole('article', { name: 'Entrada 2' }).getByRole('link', { name: 'soporte-ficticio.pdf' })).toBeVisible();
    await revisarPantalla(page, 'P4 Bitácora con entradas');

    const lienzo = page.locator('canvas[x-ref="canvas"]');
    await lienzo.scrollIntoViewIfNeeded();
    const caja = await lienzo.boundingBox();
    await page.mouse.move(caja.x + 20, caja.y + 20);
    await page.mouse.down();
    await page.mouse.move(caja.x + caja.width / 2, caja.y + caja.height - 20, { steps: 8 });
    await page.mouse.move(caja.x + caja.width - 20, caja.y + 20, { steps: 8 });
    await page.mouse.up();

    page.once('dialog', (dialogo) => dialogo.accept());
    await page.getByRole('button', { name: 'Firmar y cerrar el caso' }).click();
    await expect(page.getByText(/quedó cerrado con su constancia/)).toBeVisible();
    await expect(page.getByText(/^Firmada por/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Guardar entrada' })).toHaveCount(0);
});

test('el administrador busca un caso cerrado sin ver su contenido', async ({ page }) => {
    await iniciarSesion(page, 'admin.e2e@uceva.edu.co');
    await page.goto('/admin/casos/reapertura');
    await page.getByLabel('Código del caso').fill('IN-2026-9999');
    await page.getByRole('button', { name: 'Buscar' }).click();
    await expect(page.getByText('No se encontró un caso de Inclusión con ese código.')).toBeVisible();
    await revisarPantalla(page, 'Reapertura con resultado');
});
