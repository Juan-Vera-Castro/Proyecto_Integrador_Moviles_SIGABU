/**
 * Lista de evidencias de un formulario de Inclusión: sube cada archivo por separado, para no
 * pasar del límite por petición del servidor, y permite quitarlo antes de guardar. El servidor
 * vuelve a validar todo: lo de aquí solo evita esperar una subida que va a fallar.
 */
const PESO_MAX = 10 * 1024 * 1024;

export default (config) => ({
    items: config.iniciales ?? [],
    subiendo: [],
    enlace: '',
    error: '',
    anuncio: '',

    get lleno() {
        return this.items.length + this.subiendo.length >= config.maximo;
    },

    cabecera() {
        return {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        };
    },

    mensajeDe(error) {
        const datos = error.response?.data;
        const primero = datos?.errors ? Object.values(datos.errors)[0]?.[0] : null;

        return primero ?? datos?.message ?? 'Sin conexión. Revisa la conexión e intenta de nuevo.';
    },

    async elegir(evento) {
        this.error = '';

        for (const archivo of Array.from(evento.target.files)) {
            await this.subir(archivo);
        }

        evento.target.value = '';
    },

    async subir(archivo) {
        if (this.lleno) {
            this.error = `Puedes adjuntar hasta ${config.maximo} evidencias.`;
            return;
        }

        if (archivo.size > PESO_MAX) {
            this.error = `${archivo.name} supera los 10 MB.`;
            return;
        }

        const clave = `${archivo.name}-${Date.now()}`;
        const vista = archivo.type.startsWith('image/') ? URL.createObjectURL(archivo) : null;
        this.subiendo.push({ clave, nombre: archivo.name, progreso: 0 });

        const datos = new FormData();
        datos.append('archivo', archivo);

        try {
            const { data } = await window.axios.post(config.subir, datos, {
                headers: this.cabecera(),
                onUploadProgress: (avance) => {
                    const carga = this.subiendo.find((item) => item.clave === clave);

                    if (carga && avance.total) {
                        carga.progreso = Math.round((avance.loaded / avance.total) * 100);
                    }
                },
            });

            this.items.push({ ...data, vista });
            this.anuncio = `${archivo.name} adjuntado.`;
        } catch (error) {
            this.error = `${archivo.name}: ${this.mensajeDe(error)}`;

            if (vista) {
                URL.revokeObjectURL(vista);
            }
        }

        this.subiendo = this.subiendo.filter((item) => item.clave !== clave);
    },

    async agregarEnlace() {
        this.error = '';

        if (!this.enlace.trim()) {
            this.error = 'Pega el link antes de agregarlo.';
            return;
        }

        if (this.lleno) {
            this.error = `Puedes adjuntar hasta ${config.maximo} evidencias.`;
            return;
        }

        try {
            const { data } = await window.axios.post(config.subir, { url: this.enlace }, { headers: this.cabecera() });

            this.items.push(data);
            this.anuncio = 'Link agregado.';
            this.enlace = '';
        } catch (error) {
            this.error = this.mensajeDe(error);
        }
    },

    async quitar(item) {
        this.error = '';

        try {
            await window.axios.delete(item.quitar, { headers: this.cabecera() });

            if (item.vista) {
                URL.revokeObjectURL(item.vista);
            }

            this.items = this.items.filter((otro) => otro.id !== item.id);
            this.anuncio = `${item.nombre} quitado.`;
        } catch (error) {
            this.error = this.mensajeDe(error);
        }
    },
});
