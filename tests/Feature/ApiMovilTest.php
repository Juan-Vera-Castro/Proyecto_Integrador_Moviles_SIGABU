<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\Facultad;
use App\Models\PropuestaCita;
use App\Models\SolicitudAtencion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\TestCase;

/**
 * API de la app móvil del estudiante (routes/api.php): login con token de Sanctum, solo para
 * estudiantes, y acciones de la web respondidas en JSON.
 */
class ApiMovilTest extends TestCase
{
    use CreaEsquemaSigabu, RefreshDatabase;

    private User $estudiante;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', Actuacion::ZONA_HORARIA));
        $this->crearEsquemaSigabu();
        $this->estudiante = $this->crearUsuarioConRol('Persona', ['correo_inst' => 'ana@uceva.edu.co', 'nombre_completo' => 'Ana Pérez']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function token(?User $usuario = null): string
    {
        return ($usuario ?? $this->estudiante)->createToken('prueba')->plainTextToken;
    }

    /**
     * DADO un estudiante activo,
     * CUANDO inicia sesión con su correo y contraseña,
     * ENTONCES recibe un token que le da acceso a su inicio.
     */
    public function test_el_estudiante_inicia_sesion_y_usa_el_token(): void
    {
        $respuesta = $this->postJson('/api/movil/login', ['email' => 'ana@uceva.edu.co', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('usuario.nombre', 'Ana Pérez')
            ->assertJsonPath('usuario.rol', 'estudiante');

        $this->withToken($respuesta->json('token'))
            ->getJson('/api/movil/inicio')
            ->assertOk()
            ->assertJsonPath('usuario.correo', 'ana@uceva.edu.co')
            ->assertJsonStructure(['tareas', 'proxima_cita', 'filtros', 'avisos_sin_leer']);
    }

    public function test_credenciales_invalidas_dan_el_mismo_mensaje(): void
    {
        $this->postJson('/api/movil/login', ['email' => 'ana@uceva.edu.co', 'password' => 'mala'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El correo o la contraseña no son correctos.');

        $this->postJson('/api/movil/login', ['email' => 'nadie@uceva.edu.co', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El correo o la contraseña no son correctos.');
    }

    /**
     * DADO un profesional,
     * CUANDO intenta entrar a la app o usar la API con un token,
     * ENTONCES se le niega: la app es solo para estudiantes.
     */
    public function test_solo_entran_estudiantes(): void
    {
        $profesional = $this->crearUsuarioConRol('Profesional', ['correo_inst' => 'pro@uceva.edu.co']);

        $this->postJson('/api/movil/login', ['email' => 'pro@uceva.edu.co', 'password' => 'password'])->assertForbidden();
        $this->withToken($this->token($profesional))->getJson('/api/movil/citas')->assertForbidden();
    }

    public function test_sin_token_responde_401_en_json(): void
    {
        $this->get('/api/movil/inicio')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_cerrar_sesion_revoca_el_token(): void
    {
        $token = $this->token();

        $this->withToken($token)->postJson('/api/movil/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * DADO un estudiante sin facultad registrada,
     * CUANDO solicita una cita desde la app,
     * ENTONCES se crea el caso con las reglas de la web y la respuesta trae el mensaje en JSON.
     */
    public function test_solicitar_cita_reutiliza_las_reglas_de_la_web(): void
    {
        $facultad = Facultad::where('nombre', 'Facultad de Ingeniería')->sole();
        $token = $this->token();

        $this->withToken($token)->postJson('/api/movil/citas', ['tipo_servicio' => 'Salud Mental'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_facultad', 'jornada', 'franja_horaria']);

        $this->withToken($token)->postJson('/api/movil/citas', [
            'tipo_servicio' => 'Salud Mental',
            'id_facultad' => $facultad->id_facultad,
            'jornada' => 'Diurna',
            'franja_horaria' => 'Tarde',
        ])->assertOk()->assertJsonPath('mensaje', fn (string $mensaje) => str_starts_with($mensaje, 'Recibimos tu solicitud'));

        $this->withToken($token)->getJson('/api/movil/citas')
            ->assertOk()
            ->assertJsonCount(1, 'casos')
            ->assertJsonPath('casos.0.servicio', 'Salud Mental')
            ->assertJsonPath('casos.0.estado', Caso::CITA_PENDIENTE);
    }

    /**
     * DADO un caso con dos horarios propuestos por el profesional,
     * CUANDO el estudiante elige uno desde la app,
     * ENTONCES queda la cita, y elegir otra vez responde el error de la web en JSON.
     */
    public function test_elegir_un_horario_propuesto(): void
    {
        $profesional = $this->crearUsuarioConRol('Profesional');
        $solicitud = SolicitudAtencion::create(['id_persona' => $this->estudiante->persona->id_persona, 'tipo_servicio' => 'Inclusión']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->sole();
        $caso->forceFill(['id_profesional' => $profesional->id_usuario, 'estado_caso' => Caso::ESTADO_ABIERTO])->save();
        $propuesta = PropuestaCita::create([
            'id_caso' => $caso->id_caso,
            'id_profesional' => $profesional->id_usuario,
            'fecha_hora' => '2026-10-08 09:00:00',
            'fecha_propuesta' => now()->toDateTimeString(),
        ]);
        $token = $this->token();

        $this->withToken($token)->getJson('/api/movil/citas')->assertJsonPath('casos.0.propuestas.0.id', $propuesta->id_propuesta);

        $this->withToken($token)->postJson("/api/movil/propuestas/{$propuesta->id_propuesta}/elegir")
            ->assertOk()
            ->assertJsonPath('mensaje', 'Tu cita quedó agendada el 2026-10-08 a las 9:00 am.');

        $this->withToken($token)->postJson("/api/movil/propuestas/{$propuesta->id_propuesta}/elegir")
            ->assertStatus(422)
            ->assertJsonPath('errors.propuesta.0', 'Ese horario ya no está disponible. Revisa los horarios vigentes.');

        $this->withToken($token)->getJson('/api/movil/inicio')->assertJsonPath('proxima_cita.fecha_hora', '2026-10-08T09:00:00');
    }

    public function test_no_ve_ni_responde_casos_de_otro_estudiante(): void
    {
        $otro = $this->crearUsuarioConRol('Persona');
        $solicitud = SolicitudAtencion::create(['id_persona' => $otro->persona->id_persona, 'tipo_servicio' => 'Salud Mental']);
        $caso = Caso::where('id_solicitud_origen', $solicitud->id_solicitud)->sole();
        $token = $this->token();

        $this->withToken($token)->getJson('/api/movil/citas')->assertJsonCount(0, 'casos');
        $this->withToken($token)->postJson("/api/movil/casos/{$caso->id_caso}/propuestas/rechazar")->assertForbidden();
        $this->withToken($token)->getJson("/api/movil/casos/{$caso->id_caso}/consentimiento")->assertForbidden();
    }

    /**
     * Las acciones de la web que terminan con un mensaje de error en la sesión responden 409.
     */
    public function test_errores_de_la_web_salen_como_409(): void
    {
        $token = $this->token();
        $datos = ['situacion' => 'Cabeza de familia', 'estrato' => 2, 'acepta_datos' => true];

        $this->withToken($token)->postJson('/api/movil/alimentario', $datos)->assertOk();
        $this->withToken($token)->postJson('/api/movil/alimentario', $datos)
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $mensaje) => str_starts_with($mensaje, 'Ya tienes una solicitud pendiente'));
    }

    public function test_reporte_anonimo_devuelve_su_codigo(): void
    {
        $this->withToken($this->token())->postJson('/api/movil/reportes', [
            'tipo_reportante' => 'Anónimo',
            'relato' => 'Relato de prueba con suficientes caracteres.',
            'acepta_datos' => true,
        ])->assertOk()->assertJsonPath('datos.anonimo', true)->assertJsonStructure(['datos' => ['codigo']]);
    }
}
