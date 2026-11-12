<?php

namespace Tests\Feature;

use App\Models\Actuacion;
use App\Models\Alimentos\Donante;
use App\Models\Alimentos\Entrega;
use App\Models\Alimentos\Lote;
use App\Models\Alimentos\Priorizado;
use App\Models\Alimentos\Solicitud;
use App\Models\User;
use App\Support\CodigoQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEsquemaSigabu;
use Tests\Concerns\GeneraFirmaPng;
use Tests\TestCase;

/**
 * Módulo de Donación Alimentaria "Ucevistas de Corazón" dentro de SIGABU: el estudiante
 * solicita un mercado y el equipo de Alimentos gestiona donantes, bodega y entregas.
 */
class DonacionAlimentariaTest extends TestCase
{
    use CreaEsquemaSigabu, GeneraFirmaPng, RefreshDatabase;

    private User $estudiante;

    private User $almacen;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', Actuacion::ZONA_HORARIA));

        Storage::fake('firmas');
        $this->crearEsquemaSigabu();
        $this->estudiante = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Mariana Cardona', 'numero_documento' => '1116300102']);
        $this->almacen = $this->crearUsuarioConRol('Alimentos', ['nombre_completo' => 'Mateo Suárez']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function solicitar(array $cambios = [])
    {
        return $this->actingAs($this->estudiante)->from(route('alimentario.crear'))->post(route('alimentario.store'), array_merge([
            'situacion' => 'Cabeza de familia',
            'estrato' => '2',
            'personas_a_cargo' => '2',
            'acepta_datos' => '1',
        ], $cambios));
    }

    private function firma(bool $trazo = true): string
    {
        return 'data:image/png;base64,'.base64_encode($this->firmaPng($trazo));
    }

    private function lote(int $cantidad = 5, ?string $vence = '2026-12-01', string $tipo = 'Mercado Tipo A'): Lote
    {
        $this->actingAs($this->almacen)->post(route('almacen.lotes.store'), [
            'tipo' => $tipo, 'descripcion' => 'Arroz, aceite, panela', 'cantidad' => $cantidad, 'fecha_ingreso' => '2026-10-15', 'fecha_vencimiento' => $vence,
        ])->assertSessionHasNoErrors();

        return Lote::orderByDesc('id_lote')->first();
    }

    public function test_el_estudiante_solicita_y_queda_con_prioridad_calculada(): void
    {
        $this->actingAs($this->estudiante)->get(route('alimentario.crear'))->assertOk()->assertSee('¿Cuál es tu situación prioritaria?');

        $this->solicitar()->assertRedirect(route('alimentario.crear'))->assertSessionHas('status');

        $solicitud = Solicitud::sole();
        $this->assertSame(['Pendiente', 'Alta'], [$solicitud->estado, $solicitud->prioridad]);

        // Una sola abierta a la vez.
        $this->solicitar(['situacion' => 'Emergencia económica'])->assertSessionHas('error');
        $this->assertSame(1, Solicitud::count());

        $this->actingAs($this->estudiante)->get(route('alimentario.crear'))
            ->assertOk()
            ->assertSee('Ya tienes una solicitud pendiente del')
            ->assertSee('Estado de tu Solicitud')
            ->assertSee('Historial de Solicitudes y Beneficios Recibidos');
    }

    public function test_calcula_la_prioridad_con_equidad(): void
    {
        $this->assertSame('Alta', Solicitud::calcularPrioridad('Emergencia económica', null, 0));
        $this->assertSame('Alta', Solicitud::calcularPrioridad('Cabeza de familia', 1, 0));
        $this->assertSame('Media', Solicitud::calcularPrioridad('Cabeza de familia', 4, 0));
        $this->assertSame('Media', Solicitud::calcularPrioridad('Desempleo o reducción de ingresos', null, 0));
        // Quien ya recibió dos mercados en 90 días baja un nivel.
        $this->assertSame('Media', Solicitud::calcularPrioridad('Emergencia económica', null, 2));
        $this->assertSame('Baja', Solicitud::calcularPrioridad('Desempleo o reducción de ingresos', 5, 3));
    }

    public function test_valida_la_solicitud(): void
    {
        $this->solicitar(['situacion' => 'Otra', 'estrato' => '9', 'acepta_datos' => null])->assertSessionHasErrors(['situacion', 'estrato', 'acepta_datos']);

        $this->assertSame(0, Solicitud::count());
    }

    /**
     * DADO una solicitud aprobada y un lote con mercados,
     * CUANDO se entrega verificando el documento de quien la hizo,
     * ENTONCES se descuenta del lote y la entrega queda registrada e inmutable.
     */
    public function test_aprueba_y_entrega_verificando_el_documento(): void
    {
        $this->solicitar();
        $solicitud = Solicitud::sole();
        $lote = $this->lote();
        $this->assertSame('LT-2026-001', $lote->codigo_lote);

        $this->actingAs($this->almacen)->post(route('almacen.solicitudes.aprobar', $solicitud))->assertSessionHasNoErrors();
        $this->assertSame('Aprobada', $solicitud->fresh()->estado);

        $entregar = fn (string $documento) => $this->actingAs($this->almacen)->post(route('almacen.solicitudes.entregar', $solicitud), ['id_lote' => $lote->id_lote, 'metodo_validacion' => 'Cédula', 'documento' => $documento, 'firma' => $this->firma()]);

        $entregar('999')->assertSessionHasErrorsIn('entrega-'.$solicitud->id_solicitud, ['documento']);
        $this->assertSame(5, $lote->fresh()->cantidad_disponible);

        $entregar('1.116.300.102')->assertSessionHasNoErrors();

        $this->assertSame('Entregada', $solicitud->fresh()->estado);
        $this->assertSame(4, $lote->fresh()->cantidad_disponible);
        $entrega = Entrega::sole();
        $this->assertSame(['1116300102', $this->almacen->id_usuario], [$entrega->documento_verificado, $entrega->entregado_por]);
        // La firma de recepción queda en el almacenamiento privado, con su huella.
        $this->assertSame(hash('sha256', Storage::disk('firmas')->get($entrega->firma_ruta)), $entrega->firma_sha256);

        // No se entrega dos veces, y el intento fallido no toca la firma de la entrega hecha.
        $entregar('1116300102')->assertSessionHasErrorsIn('entrega-'.$solicitud->id_solicitud, ['documento']);
        $this->assertSame(1, Entrega::count());
        $this->assertSame([$entrega->firma_ruta], Storage::disk('firmas')->allFiles());

        $this->actingAs($this->almacen)->get(route('almacen.entregas.firma', $entrega))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Firma-Integra', 'si');
        $this->actingAs($this->estudiante)->get(route('almacen.entregas.firma', $entrega))->assertRedirect();

        $this->actingAs($this->almacen)->get(route('almacen.home', ['tab' => 'entregas']))->assertSee('Mariana Cardona')->assertSee('LT-2026-001');
        $this->actingAs($this->estudiante)->get(route('alimentario.crear'))->assertSee('Mercado entregado el 20/10/2026 10:00');

        $this->expectException(\Throwable::class);
        DB::table('alim_entregas')->delete();
    }

    public function test_no_entrega_de_un_lote_vencido_o_agotado(): void
    {
        $this->solicitar();
        $solicitud = Solicitud::sole();
        $this->actingAs($this->almacen)->post(route('almacen.solicitudes.aprobar', $solicitud));

        $vencido = Lote::create(['codigo_lote' => 'LT-2026-900', 'tipo' => 'Mercado Tipo A', 'descripcion' => 'x', 'cantidad_inicial' => 3, 'cantidad_disponible' => 3, 'fecha_ingreso' => '2026-08-01', 'fecha_vencimiento' => '2026-10-01']);
        $agotado = Lote::create(['codigo_lote' => 'LT-2026-901', 'tipo' => 'Mercado Tipo A', 'descripcion' => 'x', 'cantidad_inicial' => 3, 'cantidad_disponible' => 0, 'fecha_ingreso' => '2026-08-01']);

        foreach ([$vencido, $agotado] as $lote) {
            $this->actingAs($this->almacen)->post(route('almacen.solicitudes.entregar', $solicitud), ['id_lote' => $lote->id_lote, 'metodo_validacion' => 'Cédula', 'documento' => '1116300102', 'firma' => $this->firma()])
                ->assertSessionHasErrorsIn('entrega-'.$solicitud->id_solicitud, ['id_lote']);
        }

        $this->assertSame(0, Entrega::count());
    }

    public function test_rechazar_exige_motivo_y_el_estudiante_lo_ve(): void
    {
        $this->solicitar();
        $solicitud = Solicitud::sole();

        $this->actingAs($this->almacen)->post(route('almacen.solicitudes.rechazar', $solicitud), ['motivo_rechazo' => ''])
            ->assertSessionHasErrorsIn('revision-'.$solicitud->id_solicitud, ['motivo_rechazo']);
        $this->actingAs($this->almacen)->post(route('almacen.solicitudes.rechazar', $solicitud), ['motivo_rechazo' => 'No hay mercados esta semana.'])->assertSessionHasNoErrors();

        $this->actingAs($this->estudiante)->get(route('alimentario.crear'))->assertSee('Motivo: No hay mercados esta semana.');
        // Rechazada ya no cuenta como abierta: puede volver a solicitar.
        $this->solicitar()->assertSessionHasNoErrors();
        $this->assertSame(2, Solicitud::count());
    }

    public function test_registra_donantes_y_el_inventario_marca_lo_proximo_a_vencer(): void
    {
        $this->actingAs($this->almacen)->post(route('almacen.donantes.store'), ['nombre' => 'Fundación Manos Solidarias', 'tipo' => 'Externo', 'categoria' => 'Fundación'])->assertSessionHasNoErrors();
        $this->actingAs($this->almacen)->post(route('almacen.donantes.store'), ['nombre' => 'X', 'tipo' => 'Interno', 'categoria' => 'Empresa'])->assertSessionHasErrorsIn('donante', ['categoria']);
        $donante = Donante::sole();

        $this->lote(10, '2026-11-01');
        $this->actingAs($this->almacen)->post(route('almacen.lotes.store'), ['tipo' => 'Mercado Tipo B', 'descripcion' => 'Mercado familiar', 'cantidad' => 3, 'fecha_ingreso' => '2026-10-18', 'id_donante' => $donante->id_donante])->assertSessionHasNoErrors();
        $this->actingAs($this->almacen)->post(route('almacen.lotes.store'), ['tipo' => 'Mercado Tipo A', 'descripcion' => 'x', 'cantidad' => 0, 'fecha_ingreso' => '2026-12-01'])->assertSessionHasErrorsIn('lote', ['cantidad', 'fecha_ingreso']);

        $this->actingAs($this->almacen)->get(route('almacen.home', ['tab' => 'inventario']))
            ->assertSee('LT-2026-001')
            ->assertSee('LT-2026-002')
            ->assertSee('Próximo a vencer')
            ->assertSee('Fundación Manos Solidarias');

        $this->actingAs($this->almacen)->get(route('almacen.home'))->assertSee('Mercados en Bodega');

        $this->actingAs($this->almacen)->patch(route('almacen.donantes.estado', $donante));
        $this->assertFalse($donante->fresh()->activo);
        $this->assertSame(1, Donante::count());
    }

    public function test_solo_el_equipo_de_alimentos_gestiona_la_bodega(): void
    {
        $this->actingAs($this->estudiante)->get(route('almacen.home'))->assertRedirect(route('estudiante.home'));
        $this->actingAs($this->crearUsuarioConRol('VBG'))->post(route('almacen.lotes.store'), [])->assertRedirect(route('vbg.home'));

        $this->assertSame(0, Lote::count());
    }

    public function test_estudiante_puede_consultar_su_ficha_socioeconomica_en_tiempo_real(): void
    {
        $respuesta = $this->actingAs($this->estudiante)
            ->getJson(route('alimentario.consultar', ['codigo' => $this->estudiante->codigo_unico]));

        $respuesta->assertOk()
            ->assertJson([
                'success' => true,
                'codigo' => $this->estudiante->codigo_unico,
                'nombre' => 'Mariana Cardona',
                'matriculaActiva' => null,
            ]);
    }

    public function test_almacen_genera_certificado_de_donacion_en_pdf(): void
    {
        $this->actingAs($this->almacen)->post(route('almacen.donantes.store'), [
            'nombre' => 'Empresa NutriValle S.A.S.',
            'tipo' => 'Externo',
            'categoria' => 'Empresa',
            'contacto' => 'contacto@nutrivalle.com',
        ])->assertSessionHasNoErrors();

        $donante = Donante::sole();

        $this->lote(20, '2026-12-15', 'Mercado Tipo A');

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('almacen.donantes.certificado', $donante));

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_almacen_descarga_informe_de_transparencia_en_pdf(): void
    {
        $this->solicitar();
        $this->lote();

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('almacen.reportes.transparencia'));

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    /**
     * La entrega exige la firma de recepción del estudiante, con trazo.
     */
    public function test_la_entrega_exige_la_firma_de_recepcion(): void
    {
        $this->solicitar();
        $solicitud = Solicitud::sole();
        $lote = $this->lote();
        $this->actingAs($this->almacen)->post(route('almacen.solicitudes.aprobar', $solicitud));
        $entregar = fn (array $cambios) => $this->actingAs($this->almacen)->post(route('almacen.solicitudes.entregar', $solicitud), array_merge(['id_lote' => $lote->id_lote, 'metodo_validacion' => 'Cédula', 'documento' => '1116300102'], $cambios));

        $entregar([])->assertSessionHasErrorsIn('entrega-'.$solicitud->id_solicitud, ['firma' => 'Falta la firma de recepción del estudiante.']);
        $entregar(['firma' => $this->firma(trazo: false)])->assertSessionHasErrorsIn('entrega-'.$solicitud->id_solicitud, ['firma']);

        $this->assertSame(0, Entrega::count());
        $this->assertSame(5, $lote->fresh()->cantidad_disponible);
        $this->assertSame([], Storage::disk('firmas')->allFiles());
    }

    /**
     * El pase del estudiante aprobado lleva un QR real con el código de su solicitud.
     */
    public function test_el_pase_aprobado_lleva_un_qr_real_de_la_solicitud(): void
    {
        $this->solicitar();
        $solicitud = Solicitud::sole();
        $this->actingAs($this->almacen)->post(route('almacen.solicitudes.aprobar', $solicitud));

        $this->assertSame('ALIM:'.$solicitud->id_solicitud, $solicitud->codigoPase());
        $this->assertSame($solicitud->id_solicitud, Solicitud::idDesdePase('alim:'.$solicitud->id_solicitud));

        $this->actingAs($this->estudiante)->get(route('alimentario.crear'))
            ->assertOk()
            ->assertSee(CodigoQr::svg($solicitud->codigoPase(), 160), false)
            ->assertDontSee('QR Pattern Simulado', false);
        $this->actingAs($this->almacen)->get(route('almacen.home', ['tab' => 'despachos']))->assertOk()->assertSee('Escanea el QR del pase');
    }

    /**
     * Trabajo Social carga sus priorizados: no se crean solicitudes, suben las pendientes y las
     * que se hagan después quedan con la prioridad indicada; las filas malas se informan.
     */
    public function test_el_cargue_de_trabajo_social_prioriza_de_verdad(): void
    {
        $this->solicitar(['situacion' => 'Desempleo o reducción de ingresos', 'estrato' => '4']);
        $pendiente = Solicitud::sole();
        $this->assertSame('Media', $pendiente->prioridad);
        $otro = $this->crearUsuarioConRol('Persona', ['nombre_completo' => 'Juan Pablo Valencia', 'numero_documento' => '1116300103']);

        $csv = "Código;Documento;Estrato;Prioridad\n"
            ."EST-1;1.116.300.102;2;alta\n"
            ."EST-2;1116300103;;\n"
            ."EST-3;;3;Alta\n"
            ."EST-4;1116300199;9;Alta\n"
            ."EST-5;1116300198;1;Urgente\n";
        $archivo = UploadedFile::fake()->createWithContent('priorizados.csv', $csv);

        $this->actingAs($this->almacen)->post(route('almacen.beneficiarios.cargue'), ['archivo_csv' => $archivo])
            ->assertRedirect(route('almacen.home', ['tab' => 'solicitudes']))
            ->assertSessionHas('status', 'Priorizados de Trabajo Social cargados: 2 (2 nuevos, 0 actualizados). 2 tienen cuenta en SIGABU; 1 solicitudes pendientes subieron de prioridad. Los demás quedarán priorizados cuando soliciten.')
            ->assertSessionHas('erroresCargue', [
                'Fila 4: falta el documento o no es válido.',
                'Fila 5: el estrato debe ser un número de 1 a 6.',
                'Fila 6: la prioridad debe ser Alta, Media o Baja.',
            ]);

        $this->assertSame(1, Solicitud::count(), 'No crea solicitudes en nombre de los estudiantes');
        $this->assertSame('Alta', $pendiente->fresh()->prioridad);
        $this->assertSame(['1116300102' => 'Alta', '1116300103' => 'Alta'], Priorizado::orderBy('documento')->pluck('prioridad', 'documento')->all());

        // El otro estudiante solicita después: queda en Alta aunque sus datos darían otra prioridad.
        $this->actingAs($otro)->from(route('alimentario.crear'))->post(route('alimentario.store'), [
            'situacion' => 'Desempleo o reducción de ingresos', 'estrato' => '5', 'acepta_datos' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Alta', Solicitud::where('id_persona', $otro->persona->id_persona)->sole()->prioridad);

        $this->actingAs($this->almacen)->get(route('almacen.home'))->assertSee('Priorizado por Trabajo Social');
    }

    public function test_el_cargue_exige_un_archivo_y_solo_lo_hace_el_equipo_de_alimentos(): void
    {
        $this->actingAs($this->almacen)->post(route('almacen.beneficiarios.cargue'))
            ->assertSessionHasErrorsIn('cargue', ['archivo_csv' => 'Selecciona el archivo CSV de Trabajo Social.']);

        $this->actingAs($this->almacen)->post(route('almacen.beneficiarios.cargue'), ['archivo_csv' => UploadedFile::fake()->createWithContent('vacio.csv', "documento\n")])
            ->assertSessionHas('error', 'No se cargó ningún estudiante.');

        $this->actingAs($this->estudiante)->post(route('almacen.beneficiarios.cargue'), ['archivo_csv' => UploadedFile::fake()->createWithContent('p.csv', "1;1116300102;1;Alta\n")])
            ->assertRedirect();
        $this->assertSame(0, Priorizado::count());
    }

    public function test_no_permite_consultar_codigos_estudiantiles_inexistentes(): void
    {
        $this->actingAs($this->estudiante)
            ->getJson(route('alimentario.consultar', [
                'codigo' => 'CODIGO-INEXISTENTE-999',
            ]))
            ->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);
    }
}
