import { expect, iniciarSesion, test } from './ayudas.js';

/**
 * T-206 y T-207: el lienzo es más alto en celular y la firma exportada conserva su proporción,
 * para que el trazo no se deforme.
 */
test('la firma se dibuja, se borra y se exporta con la proporción del lienzo', async ({ page }, info) => {
    await iniciarSesion(page, 'persona.e2e@uceva.edu.co');
    await page.goto('/estudiante/mis-citas');
    await page.getByRole('link', { name: 'Firmar consentimiento' }).click();

    const lienzo = page.locator('canvas[x-ref="canvas"]');
    await lienzo.scrollIntoViewIfNeeded();
    const caja = await lienzo.boundingBox();

    const proporcionEsperada = info.project.name === 'celular' ? 2 : 8 / 3;
    expect(caja.width / caja.height).toBeCloseTo(proporcionEsperada, 1);

    if (info.project.name === 'celular') {
        expect(caja.height).toBeGreaterThanOrEqual(140);
    }

    await page.mouse.move(caja.x + 20, caja.y + 20);
    await page.mouse.down();
    await page.mouse.move(caja.x + caja.width / 2, caja.y + caja.height - 20, { steps: 8 });
    await page.mouse.move(caja.x + caja.width - 20, caja.y + 20, { steps: 8 });
    await page.mouse.up();

    const firma = await lienzo.evaluate(async (canvas) => {
        const datos = window.Alpine.$data(canvas);
        const imagen = new Image();
        imagen.src = datos.exportar();
        await imagen.decode();

        return { hayTrazo: datos.hayTrazo, ancho: imagen.width, alto: imagen.height };
    });

    expect(firma.hayTrazo).toBe(true);
    expect(firma.ancho).toBe(800);
    expect(firma.ancho / firma.alto).toBeCloseTo(caja.width / caja.height, 1);

    await page.getByRole('button', { name: 'Borrar' }).click();
    expect(await lienzo.evaluate((canvas) => window.Alpine.$data(canvas).hayTrazo)).toBe(false);
});

/**
 * T-704: firma con el dedo. En tableta y celular el trazo se dibuja con eventos táctiles reales
 * de Chrome (no con el mouse), y debe llegar como puntero de tipo "touch" sin mover la página.
 */
test('la firma se dibuja con el dedo en pantallas táctiles', async ({ page }, info) => {
    test.skip(info.project.name === 'escritorio', 'El escritorio firma con mouse: lo cubre la prueba anterior.');

    await iniciarSesion(page, 'persona.e2e@uceva.edu.co');
    await page.goto('/estudiante/mis-citas');
    await page.getByRole('link', { name: 'Firmar consentimiento' }).click();

    const lienzo = page.locator('canvas[x-ref="canvas"]');
    await lienzo.scrollIntoViewIfNeeded();
    const caja = await lienzo.boundingBox();
    await lienzo.evaluate((canvas) => {
        window.tiposDePuntero = [];
        canvas.addEventListener('pointerdown', (evento) => window.tiposDePuntero.push(evento.pointerType));
    });
    const desplazamientoAntes = await page.evaluate(() => window.scrollY);

    const cdp = await page.context().newCDPSession(page);
    const tocar = (type, x, y) => cdp.send('Input.dispatchTouchEvent', {
        type,
        touchPoints: type === 'touchEnd' ? [] : [{ x: caja.x + x, y: caja.y + y }],
    });

    await tocar('touchStart', 15, 15);
    for (let paso = 1; paso <= 12; paso++) {
        await tocar('touchMove', 15 + (caja.width - 30) * paso / 12, 15 + (caja.height - 30) * (paso % 2));
    }
    await tocar('touchEnd');

    expect(await page.evaluate(() => window.tiposDePuntero)).toEqual(['touch']);
    expect(await lienzo.evaluate((canvas) => window.Alpine.$data(canvas).hayTrazo)).toBe(true);
    expect(await page.evaluate(() => window.scrollY)).toBe(desplazamientoAntes);

    await page.getByRole('button', { name: 'Borrar' }).click();
    expect(await lienzo.evaluate((canvas) => window.Alpine.$data(canvas).hayTrazo)).toBe(false);
});
