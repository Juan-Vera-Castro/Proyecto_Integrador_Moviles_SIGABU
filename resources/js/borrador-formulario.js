/**
 * Guarda el formulario que contiene este componente como borrador en el servidor: cada 30 s si
 * cambió algo y con el botón. Nunca envía la firma ni archivos: el servidor también la descarta.
 */
const INTERVALO = 30000;
const EXCLUIDOS = ['_token', '_method', 'firma'];

export default (config) => ({
    estado: config.guardadoEn ? `Borrador guardado a las ${config.guardadoEn}.` : '',
    guardando: false,
    ultimo: '',

    init() {
        this.formulario = this.$el.closest('form');
        this.ultimo = this.serializar();
        this._temporizador = setInterval(() => this.guardarSiCambio(), INTERVALO);
    },

    destroy() {
        clearInterval(this._temporizador);
    },

    serializar() {
        const datos = {};

        new FormData(this.formulario).forEach((valor, campo) => {
            if (EXCLUIDOS.includes(campo) || valor instanceof File) {
                return;
            }

            if (campo.endsWith('[]')) {
                const nombre = campo.slice(0, -2);
                datos[nombre] = [...(datos[nombre] ?? []), valor];
            } else {
                datos[campo] = valor;
            }
        });

        return JSON.stringify(datos);
    },

    guardarSiCambio() {
        if (this.serializar() !== this.ultimo) {
            this.guardar();
        }
    },

    async guardar() {
        if (this.guardando) {
            return;
        }

        this.guardando = true;
        this.estado = 'Guardando borrador…';
        const actual = this.serializar();

        try {
            const { data } = await window.axios.put(config.url, { datos: JSON.parse(actual) }, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            this.ultimo = actual;
            this.estado = `Borrador guardado a las ${data.guardado}.`;
        } catch (error) {
            this.estado = error.response?.data?.message ?? 'No se pudo guardar el borrador. Lo escrito sigue en pantalla.';
        }

        this.guardando = false;
    },
});
