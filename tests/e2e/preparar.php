<?php

/**
 * Prepara el entorno de las pruebas E2E: una base SQLite local, con datos ficticios y discos
 * locales. No usa la base real, ni MinIO, ni el .env del proyecto. Lo ejecuta Playwright antes
 * de levantar la aplicación: php tests/e2e/preparar.php
 */

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\RecursosInclusion;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\Persona;
use App\Models\SolicitudAtencion;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEsquemaSigabu;

$raiz = dirname(__DIR__, 2);
$base = $raiz.'/storage/framework/testing/e2e.sqlite';

if (! is_dir(dirname($base))) {
    mkdir(dirname($base), 0777, true);
}

file_put_contents($base, '');

// Base propia de VBG, separada de la principal como en el servidor (sigabu_vbg).
$baseVbg = $raiz.'/storage/framework/testing/e2e-vbg.sqlite';
file_put_contents($baseVbg, '');

file_put_contents($raiz.'/.env.e2e', implode("\n", [
    'APP_NAME=SIGABU',
    'APP_ENV=e2e',
    'APP_KEY=base64:'.base64_encode(random_bytes(32)),
    'APP_DEBUG=true',
    'APP_URL=http://127.0.0.1:8123',
    'APP_LOCALE=es',
    'DB_CONNECTION=sqlite',
    'DB_DATABASE="'.str_replace('\\', '/', $base).'"',
    'DB_VBG_DRIVER=sqlite',
    'DB_VBG_DATABASE="'.str_replace('\\', '/', $baseVbg).'"',
    'SESSION_DRIVER=file',
    'CACHE_STORE=file',
    'QUEUE_CONNECTION=sync',
    'MAIL_MAILER=array',
    'SIGABU_FIRMAS_DRIVER=local',
    'SIGABU_S3_KEY=e2e',
    'SIGABU_S3_SECRET=e2e',
    'SIGABU_S3_ENDPOINT=http://e2e.invalid',
    '',
]));

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.e2e');
$app->make(Kernel::class)->bootstrap();

$datos = new class
{
    use CreaEsquemaSigabu {
        crearEsquemaSigabu as public;
    }

    public function usuario(string $rol, string $correo, string $nombre, string $documento, array $persona = []): User
    {
        $usuario = User::create([
            'codigo_unico' => $documento,
            'contrasena_hash' => 'Prueba123',
            'id_rol' => DB::table('roles')->where('nombre_rol', $rol)->value('id_rol'),
            'estado_activo' => true,
        ]);

        Persona::create($persona + [
            'id_usuario' => $usuario->id_usuario,
            'numero_documento' => $documento,
            'nombre_completo' => $nombre,
            'correo_inst' => $correo,
            'tipo_documento' => 'CC',
            'sexo' => 'F',
            'fecha_nacimiento' => '2000-01-15',
        ]);

        return $usuario;
    }
};

$datos->crearEsquemaSigabu();
ConsentimientoInclusion::cargar();
RecursosInclusion::cargar();

$persona = $datos->usuario('Persona', 'persona.e2e@uceva.edu.co', 'Persona Ficticia E2E', '900000001');
$profesional = $datos->usuario('Profesional', 'inclusion.e2e@uceva.edu.co', 'Profesional Inclusión E2E', '900000002');
$profesional->forceFill(['area_profesional' => 'Inclusión'])->save();
$psicologo = $datos->usuario('Profesional', 'psicologia.e2e@uceva.edu.co', 'Profesional Psicología E2E', '900000003');
$psicologo->forceFill(['area_profesional' => 'Salud Mental'])->save();
$datos->usuario('Admin', 'admin.e2e@uceva.edu.co', 'Administrador E2E', '900000004');

$solicitud = SolicitudAtencion::create([
    'id_persona' => $persona->persona->id_persona,
    'tipo_servicio' => 'Inclusión',
    'descripcion' => 'Solicitud ficticia para pruebas E2E',
]);
Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail()
    ->forceFill(['id_profesional' => $profesional->id_usuario, 'estado_caso' => Caso::ESTADO_ABIERTO])
    ->save();

// Una persona por tamaño de pantalla: el consentimiento firmado no se puede repetir en el mismo caso.
Facultad::firstOrCreate(['nombre' => 'Facultad Ficticia E2E'], ['activa' => true]);

foreach (['escritorio' => '900000011', 'tableta' => '900000012', 'celular' => '900000013'] as $tamano => $documento) {
    $otra = $datos->usuario('Persona', "persona.{$tamano}.e2e@uceva.edu.co", "Persona Ficticia {$tamano}", $documento);
    $solicitudOtra = SolicitudAtencion::create([
        'id_persona' => $otra->persona->id_persona,
        'tipo_servicio' => 'Inclusión',
        'descripcion' => 'Solicitud ficticia para pruebas E2E',
    ]);
    Caso::where('id_solicitud_origen', $solicitudOtra->id_solicitud)->firstOrFail()
        ->forceFill(['id_profesional' => $profesional->id_usuario, 'estado_caso' => Caso::ESTADO_ABIERTO])
        ->save();
}

// Casos propios de la bitácora: la prueba termina cerrándolos.
foreach (['escritorio' => '900000021', 'tableta' => '900000022', 'celular' => '900000023'] as $tamano => $documento) {
    $otra = $datos->usuario('Persona', "bitacora.{$tamano}.e2e@uceva.edu.co", "Persona Bitácora {$tamano}", $documento);
    $solicitudOtra = SolicitudAtencion::create([
        'id_persona' => $otra->persona->id_persona,
        'tipo_servicio' => 'Inclusión',
        'descripcion' => 'Solicitud ficticia para pruebas E2E',
    ]);
    Caso::where('id_solicitud_origen', $solicitudOtra->id_solicitud)->firstOrFail()
        ->forceFill(['id_profesional' => $profesional->id_usuario, 'estado_caso' => Caso::ESTADO_ABIERTO])
        ->save();
}

// Violencias de Género: tablas de su base propia y un caso en atención a cargo del profesional VBG.
(require $raiz.'/database/migrations/2026_11_10_000000_esquema_vbg_citas_e_informes.php')->up();
$vbg = $datos->usuario('VBG', 'vbg.e2e@uceva.edu.co', 'Profesional VBG E2E', '900000005');
App\Models\Vbg\Reporte::registrar([
    'tipo_reportante' => App\Models\Vbg\Reporte::AFECTADA,
    'id_persona_reporta' => $persona->persona->id_persona,
    'relato' => 'Relato ficticio para pruebas E2E.',
    'tipo_violencia' => 'Psicológica',
    'acepta_datos' => true,
    'estado' => App\Models\Vbg\Reporte::EN_ATENCION,
    'nivel_riesgo' => 'Medio',
    'id_profesional' => $vbg->id_usuario,
]);

// Banco de Alimentos "Ucevistas de Corazón": usuario de almacén, donante y lotes para pruebas
$almacenUser = $datos->usuario('Alimentos', 'almacen.e2e@uceva.edu.co', 'Personal Almacén E2E', '900000006');
$donanteE2E = App\Models\Alimentos\Donante::create([
    'nombre' => 'Fundación Nutrir Valle',
    'tipo' => 'Externo',
    'categoria' => 'Fundación',
    'anonimo' => false,
    'contacto' => 'contacto@nutrirvalle.org',
    'activo' => true,
    'fecha_registro' => date('Y-m-d H:i:s'),
]);
App\Models\Alimentos\Lote::create([
    'codigo_lote' => 'LT-2026-001',
    'tipo' => 'Mercado Tipo A',
    'descripcion' => 'Arroz, aceite, panela, lentejas, pastas',
    'cantidad_inicial' => 30,
    'cantidad_disponible' => 28,
    'fecha_ingreso' => '2026-10-15',
    'fecha_vencimiento' => '2026-12-15',
    'id_donante' => $donanteE2E->id_donante,
    'registrado_por' => $almacenUser->id_usuario,
]);
App\Models\Alimentos\Lote::create([
    'codigo_lote' => 'LT-2026-002',
    'tipo' => 'Mercado Tipo B',
    'descripcion' => 'Mercado familiar reforzado con enlatados',
    'cantidad_inicial' => 20,
    'cantidad_disponible' => 20,
    'fecha_ingreso' => '2026-10-18',
    'fecha_vencimiento' => '2026-12-20',
    'id_donante' => $donanteE2E->id_donante,
    'registrado_por' => $almacenUser->id_usuario,
]);

// App móvil del estudiante: tabla de tokens de Sanctum y algo que ver en su inicio y sus recursos.
(require $raiz.'/database/migrations/2026_11_12_000000_tokens_app_movil.php')->up();
$casoPersona = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->firstOrFail();
App\Models\TareaTerapeutica::create([
    'id_caso' => $casoPersona->id_caso,
    'id_profesional' => $profesional->id_usuario,
    'titulo' => 'Registrar las barreras que encuentres en clase',
    'descripcion' => 'Anota durante la semana las situaciones de clase en las que necesitaste un apoyo o un ajuste.',
    'fecha_asignacion' => date('Y-m-d'),
    'fecha_limite' => date('Y-m-d', strtotime('+5 days')),
    'estado' => App\Models\TareaTerapeutica::ESTADO_PENDIENTE,
]);
App\Models\RepositorioDocumento::create([
    'titulo' => 'Línea 106: apoyo emocional',
    'categoria' => 'Material psicoeducativo',
    'descripcion' => 'Información de la línea gratuita de atención en crisis emocional, 24 horas.',
    'url' => 'https://www.minsalud.gov.co',
    'audiencia' => App\Models\RepositorioDocumento::TODOS,
    'activo' => true,
    'fecha_publicacion' => date('Y-m-d H:i:s'),
    'publicado_por' => $profesional->id_usuario,
]);

echo "Entorno E2E listo.\n";
