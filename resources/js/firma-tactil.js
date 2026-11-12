/**
 * Firma táctil del consentimiento informado (HU-CI-01).
 *
 * - Pointer events: funciona con dedo, lápiz y mouse; touch-action: none evita que la
 *   página se desplace mientras se firma.
 * - Los trazos se guardan como puntos para redibujarlos si la pantalla gira o cambia de tamaño.
 * - La firma se exporta a 800 px de ancho, con la proporción del lienzo y fondo transparente.
 * - Se envía con fetch: si falla la conexión, el trazo sigue en pantalla para reintentar (RNF-08).
 * - El texto del consentimiento se actualiza en vivo con los datos que se escriben (D-24).
 */
const ANCHO_EXPORTADO = 800;
const VACIO = '____';

/**
 * Estado y dibujo de un lienzo de firma. Lo usan la firma principal (firmaTactil) y la firma
 * adicional del estudiante menor de edad (lienzoFirma), cada una con su propio x-ref="canvas".
 */
const lienzo = () => ({
    trazos: [],
    trazoActual: null,
    hayTrazo: false,

    ajustarCanvas() {
        const canvas = this.$refs.canvas;
        const ratio = window.devicePixelRatio || 1;
        const ancho = canvas.clientWidth;
        const alto = canvas.clientHeight;

        canvas.width = Math.round(ancho * ratio);
        canvas.height = Math.round(alto * ratio);

        const ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.redibujar(ctx, ancho, alto);
    },

    /** Dibuja los trazos guardados (en coordenadas relativas 0..1) sobre un contexto de ancho×alto. */
    redibujar(ctx, ancho, alto, grosor = 2.4) {
        ctx.clearRect(0, 0, ancho, alto);
        ctx.lineWidth = grosor;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';

        for (const trazo of this.trazos) {
            ctx.beginPath();
            trazo.forEach(([x, y], i) => (i === 0 ? ctx.moveTo(x * ancho, y * alto) : ctx.lineTo(x * ancho, y * alto)));

            if (trazo.length === 1) {
                ctx.lineTo(trazo[0][0] * ancho + 0.1, trazo[0][1] * alto);
            }

            ctx.stroke();
        }
    },

    punto(evento) {
        const rect = this.$refs.canvas.getBoundingClientRect();

        return [(evento.clientX - rect.left) / rect.width, (evento.clientY - rect.top) / rect.height];
    },

    empezar(evento) {
        if (this.enviando) {
            return;
        }

        evento.preventDefault();
        this.$refs.canvas.setPointerCapture(evento.pointerId);
        this.trazoActual = [this.punto(evento)];
        this.trazos.push(this.trazoActual);
        this.pintar();
    },

    mover(evento) {
        if (!this.trazoActual) {
            return;
        }

        evento.preventDefault();
        this.trazoActual.push(this.punto(evento));
        this.pintar();
    },

    terminar() {
        if (this.trazoActual) {
            this.hayTrazo = this.trazos.some((trazo) => trazo.length > 2);
        }

        this.trazoActual = null;
    },

    pintar() {
        const canvas = this.$refs.canvas;
        this.redibujar(canvas.getContext('2d'), canvas.clientWidth, canvas.clientHeight);
    },

    borrar() {
        this.trazos = [];
        this.trazoActual = null;
        this.hayTrazo = false;
        this.pintar();
    },

    exportar() {
        const { clientWidth, clientHeight } = this.$refs.canvas;
        const alto = Math.round((ANCHO_EXPORTADO * clientHeight) / clientWidth);
        const destino = document.createElement('canvas');
        destino.width = ANCHO_EXPORTADO;
        destino.height = alto;
        this.redibujar(destino.getContext('2d'), ANCHO_EXPORTADO, alto, 4);

        return destino.toDataURL('image/png');
    },
});

/**
 * Segundo lienzo dentro del formulario (firma del estudiante menor). firmaTactil lo encuentra
 * por data-lienzo y envía su firma junto con la del acudiente.
 */
export const lienzoFirma = () => ({
    ...lienzo(),

    init() {
        this.ajustarCanvas();
        this._alCambiarTamano = () => this.ajustarCanvas();
        window.addEventListener('resize', this._alCambiarTamano);
    },

    destroy() {
        window.removeEventListener('resize', this._alCambiarTamano);
    },
});

export default (config) => ({
    ...lienzo(),
    enviando: false,
    mensaje: '',
    errores: {},
    campos: config.campos ?? {},

    init() {
        this.ajustarCanvas();
        this._alCambiarTamano = () => this.ajustarCanvas();
        window.addEventListener('resize', this._alCambiarTamano);
    },

    destroy() {
        window.removeEventListener('resize', this._alCambiarTamano);
    },

    /** Texto del consentimiento con los marcadores reemplazados por lo que se sabe hasta ahora. */
    get texto() {
        return (config.plantilla ?? '').replace(/\{([a-z_]+)\}/g, (marcador, clave) => {
            if (!(clave in config.marcadores)) {
                return marcador;
            }

            const campo = config.camposDeFirma[clave];
            const valor = campo ? this.campos[campo] : config.datosBase[clave];

            return (valor ?? '').toString().trim() || VACIO;
        });
    },

    /** Lienzo de la firma del estudiante menor de edad, si el formulario lo tiene. */
    lienzoEstudiante() {
        const elemento = this.$root.querySelector('[data-lienzo="firma_estudiante"]');

        return elemento ? window.Alpine.$data(elemento) : null;
    },

    /** Si están todas las firmas que pide el formulario (la del menor, cuando aplica). */
    firmasCompletas() {
        const estudiante = this.lienzoEstudiante();

        return this.hayTrazo && (!estudiante || estudiante.hayTrazo);
    },

    async enviar(decision) {
        if (this.enviando) {
            return;
        }

        if (decision === 'Rechazado' && !window.confirm(config.confirmacionRechazo)) {
            return;
        }

        if (decision === 'Aceptado' && this.hayTrazo && config.confirmacionEnvio && !window.confirm(config.confirmacionEnvio)) {
            return;
        }

        // Con validarEnServidor, la falta de firma vuelve junto con los demás errores.
        if (decision === 'Aceptado' && !this.hayTrazo && !config.validarEnServidor) {
            this.mensaje = 'Falta la firma. Firma dentro del recuadro.';
            return;
        }

        if (config.casillaAConfirmar && !this.$root.elements[config.casillaAConfirmar]?.checked && !window.confirm(config.confirmacionCasilla)) {
            return;
        }

        this.enviando = true;
        this.mensaje = '';
        this.errores = {};

        const datos = new FormData(this.$root);
        datos.set('decision', decision);

        if (decision === 'Aceptado' && this.hayTrazo) {
            datos.set('firma', this.exportar());
        }

        const estudiante = this.lienzoEstudiante();

        if (decision === 'Aceptado' && estudiante?.hayTrazo) {
            datos.set('firma_estudiante', estudiante.exportar());
        }

        try {
            const respuesta = await fetch(this.$root.action, {
                method: 'POST',
                body: datos,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const cuerpo = await respuesta.json().catch(() => ({}));

            if (respuesta.ok && cuerpo.redirect) {
                window.location.assign(cuerpo.redirect);
                return;
            }

            this.errores = cuerpo.errors ?? {};
            this.mensaje = cuerpo.message && !cuerpo.errors
                ? cuerpo.message
                : 'Revisa los datos marcados. La firma sigue en pantalla.';
            this.$nextTick(() => document.getElementById(Object.keys(this.errores)[0])?.focus());
        } catch (error) {
            this.mensaje = 'Sin conexión. La firma sigue en pantalla: revisa la conexión e intenta de nuevo.';
        }

        this.enviando = false;
    },

    error(campo) {
        return this.errores[campo]?.[0] ?? '';
    },
});
