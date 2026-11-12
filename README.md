# SIGABU
Plataforma web del Ecosistema Digital de la UCEVA para la gestión integral, trazabilidad y analítica del Bienestar Universitario. Integra los módulos de Violencias Basadas en Género, Salud Mental e Inclusión y banco de alimentos. Incorpora IA para transcripción, análisis de riesgo y reportes, optimizando la toma de decisiones con datos seguros.
# EQUIPOS:
- Violencias Basadas en Género: Alxis Gonzales Cardenas, Johan Eliu Salgado Castro, Brahian Andres Tenorio Reina
- Salud Mental e Inclusión: Juan Esteban Vera Castro, Víctor Manuelle Sánchez Lozano, John Stiven Gonzáles Rivera, Nicolas Lozano Salazar
- Banco de alimentos: Andres Felipe Rios Vásquez, Nicolás Millan Rodríguez

# Salud Mental y Apoyo Psicosocial / Inclusión Universitaria

Salud Mental y Apoyo Psicosocial: Registro de historias clínicas psicológicas, agendamiento de citas, asignación de tareas terapéuticas, seguimiento a la adherencia del tratamiento y medición del impacto de las intervenciones.  Inclusión Universitaria: Registro de estudiantes, gestión de certificaciones de discapacidad, identificación de barreras de aprendizaje, seguimiento a procesos de adaptación y evaluación del acompañamiento. 

# Integrantes:
- Juan Esteban Vera Castro
- Víctor Manuelle Sánchez Lozano
- John Stiven Gonzáles Rivera
- Nicolas Lozano Salazar

# Cómo correr el proyecto en tu computador

Hay dos formas de ejecutarlo. Elige según dónde estés:

| | Opción 1 · Datos ficticios | Opción 2 · Base real del servidor |
|---|---|---|
| **Dónde funciona** | En cualquier lugar, también desde casa | Solo conectado a la red de la universidad |
| **Qué necesitas** | Nada más que los programas del paso 0 | Usuario del servidor y claves que entrega quien lo administra |
| **Datos** | Ficticios; se reinician cada vez que preparas el entorno | Los reales: lo que hagas queda guardado para todos |
| **Para qué sirve** | Programar y probar el día a día | Revisar algo con los datos reales |

Si no sabes cuál usar, usa la **opción 1**.

## Paso 0 · Programas (una sola vez)

Instala estos programas y comprueba cada uno en una terminal (PowerShell o CMD):

| Programa | Para qué | Cómo comprobarlo |
|---|---|---|
| [Git](https://git-scm.com/downloads) | Descargar el código | `git --version` |
| [Laravel Herd](https://herd.laravel.com/windows) (trae PHP y Composer) | Ejecutar Laravel | `php -v` debe decir 8.2 o más; `composer -V` |
| [Node.js](https://nodejs.org) versión LTS | Compilar los estilos | `node -v` debe decir 20 o más |

Usa **Herd completo**, no *Herd Lite*: a Herd Lite le faltan extensiones, así que `composer install` falla pidiendo `ext-gd` y la opción 2 falla con `could not find driver`. `php --ini` muestra cuál PHP estás usando: si la ruta dice `herd-lite`, instala Herd completo. Si usas XAMPP o el PHP de php.net, abre su `php.ini` y quita el `;` del inicio de estas líneas: `extension=pdo_pgsql`, `extension=pgsql`, `extension=pdo_sqlite`, `extension=fileinfo`, `extension=gd`, `extension=zip`.

## Paso 1 · Descargar e instalar el proyecto (una sola vez)

En una terminal, dentro de la carpeta donde guardas tus proyectos:

```
git clone https://github.com/Vmanu-29/SIGABU.git
cd SIGABU
git checkout SALUD-MENTAL-E-INCLUSION-SOCIAL
composer install
npm install
copy .env.example .env
php artisan key:generate
```

`php artisan key:generate` es obligatorio: sin él la aplicación muestra *No application encryption key has been specified*.

## Opción 1 · Datos ficticios (desde cualquier lugar)

No usa PostgreSQL, ni el túnel, ni MinIO, ni contraseñas: crea una base SQLite con datos de prueba en `storage/framework/testing/e2e.sqlite` y su propio archivo `.env.e2e`. Nunca toca la base real.

1. Prepara los datos de prueba. Vuelve a ejecutarlo cuando quieras empezar de cero (borra lo que hayas hecho):

   ```
   php tests/e2e/preparar.php
   ```

   Debe terminar con `Entorno E2E listo.`

2. Compila los estilos:

   ```
   npm run build
   ```

   Si vas a cambiar vistas o estilos, en lugar de eso deja abierta otra terminal con `npm run dev`.

3. Arranca la aplicación y deja esa terminal abierta:

   ```
   php artisan serve --env=e2e --port=8123
   ```

4. Abre `http://127.0.0.1:8123/login` y entra con alguno de estos usuarios. La contraseña de todos es `Prueba123`:

   | Rol | Correo |
   |---|---|
   | Administrador | `admin.e2e@uceva.edu.co` |
   | Profesional de Inclusión | `inclusion.e2e@uceva.edu.co` |
   | Profesional de Psicología | `psicologia.e2e@uceva.edu.co` |
   | Estudiante | `persona.e2e@uceva.edu.co` |
   | Profesional VBG (con un caso en atención) | `vbg.e2e@uceva.edu.co` |
   | Personal de Almacén (Donaciones) | `almacen.e2e@uceva.edu.co` |

5. Para detenerla, pulsa `Ctrl + C` en la terminal.

Límites: los usuarios de la sección *Usuarios de prueba* de abajo no existen aquí; las firmas y los archivos se guardan en una carpeta local; y como la estructura sale de una copia en SQLite, lo que solo existe en PostgreSQL (migraciones y reglas de la base) se prueba con la opción 2.

## Opción 2 · Base real del servidor (solo desde la universidad)

La base de datos (PostgreSQL) y los archivos (MinIO) están en el servidor `192.168.10.67`, que solo responde desde la red de la universidad.

1. Pide a quien administra el servidor: tu usuario SSH del servidor, `DB_PASSWORD`, `SIGABU_S3_KEY` y `SIGABU_S3_SECRET`.

2. Comprueba que tu PHP tenga PostgreSQL. Este comando debe mostrar `pdo_pgsql`:

   ```
   php -m
   ```

3. Abre `.env` y pon esas tres claves. No cambies `DB_HOST=127.0.0.1` ni `DB_PORT=5434`: apuntan al túnel del paso siguiente.

4. Abre el túnel en una terminal aparte y déjala abierta mientras uses la aplicación (pide la contraseña de tu usuario del servidor y luego no muestra nada, es normal):

   ```
   ssh -N -L 5434:127.0.0.1:5432 -L 9000:127.0.0.1:9000 <usuario>@192.168.10.67
   ```

5. En otra terminal, dentro de la carpeta del proyecto:

   ```
   composer run dev
   ```

   Levanta el servidor, la cola de trabajos y Vite juntos.

6. Abre `http://127.0.0.1:8000/login` y entra con los usuarios de la sección *Usuarios de prueba*.

Cuidado: todo lo que hagas aquí queda en la base real que usa todo el equipo.

Las migraciones nuevas se ejecutan con el usuario dueño de la base (`sigabu_owner`), no con `sigabu_app`, que no puede cambiar la estructura. Los scripts y pasos de la base están en [database/postgres/README.md](database/postgres/README.md).

## Después de cada `git pull`

```
composer install
npm install
```

Con la opción 1, vuelve a ejecutar `php tests/e2e/preparar.php` para que la base ficticia tenga las tablas nuevas.

## Si algo falla

| Mensaje o síntoma | Causa | Qué hacer |
|---|---|---|
| `could not find driver` | Tu PHP no tiene la extensión de la base | Opción 1: activa `pdo_sqlite`. Opción 2: activa `pdo_pgsql` (paso 0), o usa la opción 1 |
| `connection to server at "127.0.0.1", port 5434 failed` | El túnel no está abierto, o no estás en la red de la universidad | Abre el túnel (opción 2, paso 4) o usa la opción 1 |
| `No application encryption key has been specified` | Falta generar la clave | `php artisan key:generate` |
| La página carga sin colores ni estilos | Los estilos no están compilados, o quedó un archivo de Vite viejo | Ejecuta `npm run build`; si sigue igual, borra el archivo `public/hot` |
| El usuario y la contraseña no entran | Estás usando los usuarios de la otra opción | Opción 1: usuarios `.e2e`. Opción 2: *Usuarios de prueba* |
| La firma o los archivos no se guardan (opción 2) | El túnel no incluye el puerto 9000 de MinIO o faltan las claves S3 | Usa el comando completo del túnel y revisa `SIGABU_S3_KEY` y `SIGABU_S3_SECRET` |
| `npm warn install-scripts ... esbuild` al instalar | Aviso de npm | Se puede ignorar: los estilos compilan igual |

## Consentimiento informado firmado y remisiones

Después de cada `git pull` corre `composer install`: el módulo usa librerías para generar el documento de remisión (PDF y Word) y para guardar las firmas en el almacenamiento S3.

1. **Firmas (almacenamiento S3).** Las firmas táctiles del consentimiento se guardan en el bucket privado `consentimientos-firmas` de un almacenamiento compatible con S3. En el servidor se instala MinIO con `sh instalar.sh` desde [docker/minio](docker/minio): compila la última versión, crea los buckets y muestra las claves. MinIO está archivado y ya no recibe arreglos de seguridad, así que sus puertos no se exponen fuera del servidor. Llena en `.env`:

   ```
   SIGABU_S3_KEY=...
   SIGABU_S3_SECRET=...
   SIGABU_S3_REGION=us-east-1
   SIGABU_S3_ENDPOINT=https://<servidor-de-almacenamiento>
   SIGABU_S3_BUCKET=consentimientos-firmas
   ```

   Sin estas claves la pantalla de firma lo avisa y solo permite registrar que el estudiante no acepta.

2. **Textos de consentimiento.** `php artisan migrate` publica como versión 1 los formatos oficiales de Salud Mental e Inclusión: GBU-GPGR-F-032 (general, lo firma el estudiante) y GBU-GPGR-F-019 (menores de edad, lo firma el acudiente). No reemplaza modelos que ya existan. Para cambiar un texto, el administrador crea y publica una versión nueva en *Consentimientos*: los consentimientos nuevos usan esa versión y los ya firmados conservan la suya. El texto puede usar marcadores como `{nombre_estudiante}` o `{profesional}`, que el sistema llena al firmar; la lista completa aparece en la pantalla de cada modelo.

   Como en el formato en papel, al aceptar se llena el encabezado (vinculación, programa académico, semestre y dependencia o facultad) y después el profesional tratante contrafirma el consentimiento desde el expediente. Mientras no lo firme, el expediente muestra "Falta la firma del profesional tratante".

3. **Bloqueo de la atención (apagado por defecto).** Cuando se active, en los casos de Salud Mental e Inclusión no se podrá registrar "Asistió", asignar tareas ni remitir hasta que el estudiante (o su acudiente, si es menor) firme el consentimiento en el expediente. Agendar citas y aceptar solicitudes sí se puede. Se activa en el `.env`, **solo cuando estén las claves y los textos publicados** (el panel de *Consentimientos* indica qué falta):

   ```
   SIGABU_EXIGIR_CONSENTIMIENTO=true
   SIGABU_CONSENTIMIENTO_DESDE=AAAA-MM-DD
   ```

   Los casos abiertos antes de `SIGABU_CONSENTIMIENTO_DESDE` no exigen firma. Aunque esté activo, si falta el texto publicado o las claves, el sistema no bloquea y avisa qué falta. Si un estudiante rechaza el consentimiento, la atención se bloquea siempre.

4. **Formato de remisión.** Las remisiones externas generan el documento GBU-GPGR-F-016 en PDF y Word desde el historial del expediente. Si Calidad cambia el código, la versión o la fecha del formato, se actualiza en *Formatos* (menú del administrador).


## Formatos de la Unidad de Inclusión

El módulo digitaliza tres formatos dentro del expediente de los casos de Inclusión: el consentimiento informado (lo llena y firma la persona, o el profesional lo carga firmado en papel), la bitácora de orientación y acompañamiento con su constancia de cierre, y el recurso pedagógico TEA N1 con la plantilla 5.8 de ajustes. Cada uno se descarga en PDF. Solo los usa el profesional asignado con área Inclusión o Ambas; el administrador asigna el área en *Usuarios*.

1. **Migraciones.** Son seis: de `2026_11_07_000000` a `2026_11_07_000005`. Corren solo en PostgreSQL, con `sigabu_owner`, y se pueden repetir sin riesgo. Antes de aplicarlas en producción, pruébalas en una copia de la base: las pruebas automáticas usan SQLite y no ejecutan este SQL.

   ```
   DB_USERNAME=sigabu_owner DB_PASSWORD=<clave_owner> php artisan migrate --force
   ```

2. **Almacenamiento.** Los soportes y evidencias van en un bucket privado propio, `inclusion-evidencias`, con las mismas claves S3 de las firmas. `sh instalar.sh` en [docker/minio](docker/minio) ya lo crea; en una instalación existente se crea con `mc mb --ignore-existing sigabu/inclusion-evidencias`. Variables en `.env`:

   ```
   SIGABU_S3_BUCKET_INCLUSION=inclusion-evidencias
   SIGABU_S3_BUCKET_REPOSITORIO=repositorio-documentos
   ```

   Las firmas y los consentimientos escaneados en papel siguen en `consentimientos-firmas`.

3. **Archivos sin guardar.** Un archivo que se sube y nunca se guarda con su formulario queda como temporal. Se descartan con el comando siguiente, que se puede programar una vez al día:

   ```
   php artisan inclusion:limpiar-temporales
   ```

4. **Código de los formatos.** Los tres formatos quedan con código `POR-ASIGNAR` en *Formatos*, hasta que Calidad entregue el código, la versión y la fecha.

5. **Pruebas en el navegador.** Solo en desarrollo: `npm run build` y luego `npm run test:e2e`. Usan el Chrome instalado, una base SQLite con datos ficticios y una carpeta local para las firmas; nunca tocan la base real. Revisan los flujos en 1280, 768 y 360 px, la firma con mouse y con el dedo, y la accesibilidad con axe.

## Notificaciones y recordatorios

Cada usuario tiene una campana (**Notificaciones** en el menú, con el número de avisos sin leer). Los avisos también llegan al correo institucional.

| Aviso | Quién lo recibe | Cuándo |
|---|---|---|
| Nueva solicitud de cita | El profesional asignado; si no hay, los profesionales activos del área; si tampoco hay, los administradores | Al enviar la solicitud |
| Solicitud de emergencia | Los mismos, marcada como **urgente** | Al enviar la solicitud |
| Recordatorio de cita | Estudiante y profesional | 24 horas y 2 horas antes (`SIGABU_RECORDATORIOS_CITA`) |
| Consentimiento por firmar | Estudiante | Cuando su caso tiene profesional y le falta firmar (solo si hoy se puede firmar) |
| Consentimiento por contrafirmar | Profesional tratante | Cuando el estudiante ya firmó |
| Tarea vencida | Estudiante y profesional | Al día siguiente de la fecha límite, si sigue sin evaluar |

- **Cada recordatorio sale una sola vez.** La tabla `recordatorios_enviados` lo garantiza, aunque el comando se ejecute varias veces.
- **Confidencialidad.** Los textos no llevan información clínica, solo el código del expediente. Los detalles se ven dentro de SIGABU.
- **Correo al estudiante.** Lo envía la cuenta del sistema (`MAIL_FROM_ADDRESS`), pero muestra el nombre de su profesional. Si el estudiante responde, la respuesta le llega al profesional.
- **Correo desactivado.** Mientras `MAIL_MAILER=log`, los correos no salen: quedan en `storage/logs/laravel.log` y la campana funciona igual. Para enviarlos de verdad se cambian las variables `MAIL_*` del `.env` (hay un ejemplo de Office 365 en `.env.example`).
- **Procesos que lo hacen funcionar.**
  - `php artisan schedule:work` revisa los pendientes cada 5 minutos con `php artisan sigabu:recordatorios`.
  - `php artisan queue:work` envía los correos.
  - En tu computador, `composer run dev` ya levanta los dos. En el servidor son los contenedores `sigabu-scheduler` y `sigabu-worker`.


## Violencias de Género: citas, consentimiento, audios e informes

Integrado desde la rama `VIOLENCIA_GENERO` dentro del módulo VBG de SIGABU, con el inicio de sesión y los roles de SIGABU. En la ficha de cada caso, el profesional VBG a cargo:

- agenda **citas** (fecha, horario, presencial o virtual, consultorio o enlace, motivo y notas);
- registra el **consentimiento informado de la cita con firma táctil**; si la persona es menor de edad, firman ella y su acudiente (nombre, teléfono, cédula y parentesco). La cita solo se inicia con el consentimiento aceptado;
- sube el **audio de la sesión** (MP3, WAV o M4A, hasta 2 horas) y escribe su **transcripción**;
- elabora el **informe de análisis** (resumen, nivel de riesgo, tipos de violencia, marco legal, documentos del Repositorio y ruta de atención) y lo descarga en **PDF**. Cada versión se conserva.

Audios, firmas, transcripciones e informes solo los ve el profesional a cargo, y cada consulta queda en la auditoría.

1. **Base propia, separada de la principal.** Estos datos viven en la base `sigabu_vbg`, con sus propios usuarios (`sigabu_vbg_owner` y `sigabu_vbg_app`). Se relaciona con la base principal solo por identificadores; ninguna de las dos aplicaciones puede entrar a la base de la otra. Se crea una vez en el servidor con [06_vbg_roles_y_base.sql](database/postgres/06_vbg_roles_y_base.sql) y se configura en `.env`:

   ```
   DB_VBG_DATABASE=sigabu_vbg
   DB_VBG_USERNAME=sigabu_vbg_app
   DB_VBG_PASSWORD=...
   SIGABU_S3_BUCKET_VBG=vbg-evidencias
   ```

   Si esa base no responde, la ficha del caso se sigue viendo y avisa que las citas no están disponibles.

2. **Migración.** `2026_11_10_000000_esquema_vbg_citas_e_informes` crea las tablas en `sigabu_vbg`. Se ejecuta con los dos dueños, el de la base principal (registro de migraciones) y el de VBG (tablas):

   ```
   DB_USERNAME=sigabu_owner DB_PASSWORD=<clave_owner> DB_VBG_USERNAME=sigabu_vbg_owner DB_VBG_PASSWORD=<clave_owner_vbg> php artisan migrate --force
   ```

3. **Almacenamiento.** Firmas y audios van al bucket privado `vbg-evidencias` de MinIO; `sh instalar.sh` en [docker/minio](docker/minio) ya lo crea. En una instalación existente: `mc mb --ignore-existing sigabu/vbg-evidencias`. Los audios grandes necesitan el límite de subida de 120 MB de [docker/php.ini](docker/php.ini), que se aplica al reconstruir el contenedor.

4. **Texto del consentimiento.** Es el que redactó el equipo VBG en su rama y está en `config/sigabu.php` (`vbg.consentimiento_cita`). **Debe revisarlo el equipo VBG antes de usarlo en atenciones reales:** hoy habla de atención psicológica. Al cambiarlo, sube la versión; los ya firmados conservan su texto.

5. **IA.** La transcripción automática (faster-whisper) y el informe con IA estaban contemplados en esa rama, pero sin conectar. Aquí quedan contemplados y apagados: ver [docs/vbg-ia](docs/vbg-ia/README.md).

6. **La rama `VIOLENCIA_GENERO` no se publica por separado.** Su backend Java deja la consola de su base abierta a la red y su Laravel no tiene inicio de sesión. Todo lo que hacía y funcionaba ya está aquí, detrás del login de SIGABU.


# Publicar cambios en el servidor

La aplicación está en `https://192.168.10.67`. Cuando los cambios ya estén en `main`, publícalos con:

```
ssh -t sigabu1@192.168.10.67 "sh sigabu/docker/servidor/actualizar.sh"
```

Si el script avisa de migraciones pendientes, ejecútalas con `sigabu_owner` (ver [database/postgres](database/postgres/README.md)).

Desde las notificaciones, el servidor corre tres contenedores con la misma imagen: `sigabu` (la web), `sigabu-worker` (envía los correos) y `sigabu-scheduler` (recordatorios cada 5 minutos). `actualizar.sh` los levanta y actualiza a los tres. Para revisarlos:

```
sudo docker compose -f docker-compose.servidor.yml ps
sudo docker logs --tail 50 sigabu-scheduler
```

# USUARIOS DE PRUEBA DE LA BASE DE DATOS

- Administrador rcastro@uceva.edu.co / Prueba123

- Estudiante: 
1. srios@uceva.edu.co / Prueba123 (usuario de prueba original)
2. mcardona@uceva.edu.co	Mariana Cardona Ospina	Ciencias de la Salud · Enfermería
3. jvalencia@uceva.edu.co	Juan Pablo Valencia Ríos	Derecho · Derecho
4. lmontoya@uceva.edu.co	Laura Montoya Giraldo	Ciencias de la Educación · Lic. en Educación Física
5. sarango@uceva.edu.co	Santiago Arango Pérez	Ciencias Administrativas · Contaduría Pública
6. vquintero@uceva.edu.co	Valeria Quintero Marín	Ciencias de la Salud · Medicina
7. agomez@uceva.edu.co	Alejandro Gómez Castaño	Ingeniería · Ingeniería Electrónica
8. crojas@uceva.edu.co	Camila Rojas Betancourt	Ciencias Ambientales · Ingeniería Ambiental
9. flopez@uceva.edu.co	Felipe López Zuluaga	Ciencias Administrativas · Administración de Empresas
10. imejia@uceva.edu.co	Isabella Mejía Duque	Ciencias de la Salud · Fisioterapia (menor de edad)
11. dhenao@uceva.edu.co	Daniel Henao Restrepo	Ingeniería · Ingeniería de Sistemas


- Profesional: cmunoz@uceva.edu.co / Prueba123
- Alimentos: msuarez@uceva.edu.co / Prueba123
- VBG: vlopez@uceva.edu.co / Prueba123
1. pvargas@uceva.edu.co	Paula Andrea Vargas Torres	CC 29400101
2. rcastillo@uceva.edu.co	Ricardo Castillo Montes	CC 29400102
3. nsalazar@uceva.edu.co	Natalia Salazar Echeverri	CC 29400103
4. jmorales@uceva.edu.co	Jorge Iván Morales Patiño	CC 29400104
5. dramirez@uceva.edu.co	Diana Marcela Ramírez Ortiz	CC 29400105
