<?php

namespace App\Support;

use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\RecordatorioEnviado;
use App\Models\TareaTerapeutica;
use App\Models\User;
use App\Notifications\AvisoSigabu;
use App\Notifications\ConsentimientoPorContrafirmar;
use App\Notifications\ConsentimientoPorFirmar;
use App\Notifications\RecordatorioCita;
use App\Notifications\TareaVencida;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Recordatorios que revisa el programador de tareas cada pocos minutos (sigabu:recordatorios).
 *
 * Cada recordatorio sale una sola vez: se reserva en recordatorios_enviados en la misma
 * transacción en que se guarda el aviso. Si algo falla, se deshace la reserva y se vuelve a
 * intentar en la siguiente ejecución. Un error con un pendiente no detiene a los demás.
 */
class Recordatorios
{
    /**
     * Máximo de pendientes de cada tipo por ejecución; el resto sale en la siguiente.
     */
    public const LOTE = 200;

    /**
     * @return array{citas: int, tareas_vencidas: int, consentimientos_por_firmar: int, contrafirmas_pendientes: int}
     */
    public function enviarPendientes(): array
    {
        return [
            'citas' => $this->citas(),
            'tareas_vencidas' => $this->tareasVencidas(),
            'consentimientos_por_firmar' => $this->consentimientosPorFirmar(),
            'contrafirmas_pendientes' => $this->contrafirmasPendientes(),
        ];
    }

    /**
     * Recordatorio de cita al estudiante y al profesional, según las horas de anticipación
     * configuradas (p. ej. 24 y 2). Cada anticipación cubre desde la siguiente más corta: una
     * cita agendada con 10 horas de margen recibe el de 24 h ahora y el de 2 h después.
     */
    public function citas(): int
    {
        $ahora = now(Actuacion::ZONA_HORARIA);
        $anticipaciones = config('sigabu.notificaciones.recordatorios_cita', []);
        rsort($anticipaciones);
        $enviados = 0;

        foreach ($anticipaciones as $i => $horas) {
            $tipo = RecordatorioEnviado::tipoCita($horas);
            $desde = $ahora->copy()->addHours($anticipaciones[$i + 1] ?? 0);

            $citas = Actuacion::citas()
                ->where('estado_asistencia', Actuacion::ASISTENCIA_PENDIENTE)
                ->where('fecha_hora', '>', $desde->toDateTimeString())
                ->where('fecha_hora', '<=', $ahora->copy()->addHours($horas)->toDateTimeString())
                ->whereHas('caso', fn (Builder $caso) => $caso->activos())
                ->whereNotExists($this->yaEnviado($tipo, 'actuaciones.id_actuacion'))
                ->with(['profesional.persona', 'caso.solicitud.persona.usuario.persona'])
                ->orderBy('fecha_hora')
                ->limit(self::LOTE)
                ->get();

            foreach ($citas as $cita) {
                $enviados += (int) $this->enviarUnaVez(
                    $tipo,
                    $cita->id_actuacion,
                    [$this->estudianteDe($cita->caso), $cita->profesional],
                    new RecordatorioCita($cita, $horas),
                );
            }
        }

        return $enviados;
    }

    /**
     * Tareas cuya fecha límite ya pasó y siguen sin evaluar: aviso al estudiante y al profesional.
     */
    public function tareasVencidas(): int
    {
        $hoy = now(Actuacion::ZONA_HORARIA)->startOfDay();

        $tareas = TareaTerapeutica::query()
            ->where('estado', TareaTerapeutica::ESTADO_PENDIENTE)
            ->where('fecha_limite', '<', $hoy->toDateString())
            ->where('fecha_limite', '>=', $hoy->copy()->subDays($this->ventanaDias())->toDateString())
            ->whereHas('caso', fn (Builder $caso) => $caso->activos())
            ->whereNotExists($this->yaEnviado(RecordatorioEnviado::TAREA_VENCIDA, 'tareas_terapeuticas.id_tarea'))
            ->with(['profesional.persona', 'caso.solicitud.persona.usuario.persona'])
            ->orderBy('fecha_limite')
            ->limit(self::LOTE)
            ->get();

        $enviados = 0;

        foreach ($tareas as $tarea) {
            $enviados += (int) $this->enviarUnaVez(
                RecordatorioEnviado::TAREA_VENCIDA,
                $tarea->id_tarea,
                [$this->estudianteDe($tarea->caso), $tarea->profesional],
                new TareaVencida($tarea),
            );
        }

        return $enviados;
    }

    /**
     * Casos activos con profesional que exigen consentimiento y todavía no tienen ninguna
     * decisión. Solo si hoy se puede firmar (texto publicado y almacenamiento listo) y el caso
     * no está eximido por fecha: no se le pide al estudiante algo que no puede hacer.
     */
    public function consentimientosPorFirmar(): int
    {
        $casos = Caso::activos()
            ->whereNotNull('id_profesional')
            ->where('fecha_apertura', '>=', $this->inicioVentana()->toDateTimeString())
            ->whereHas('solicitud', fn (Builder $solicitud) => $solicitud->whereIn('tipo_servicio', Caso::SERVICIOS_CON_CONSENTIMIENTO))
            ->whereDoesntHave('aceptacionesConsentimiento')
            ->whereNotExists($this->yaEnviado(RecordatorioEnviado::CONSENTIMIENTO_ESTUDIANTE, 'casos.id_caso'))
            ->with(['solicitud.persona.usuario.persona', 'profesional.persona'])
            ->orderBy('fecha_apertura')
            ->limit(self::LOTE)
            ->get();

        $enviados = 0;

        foreach ($casos as $caso) {
            if ($caso->eximidoPorFecha() || $caso->faltantesParaFirmar() !== []) {
                continue;
            }

            $enviados += (int) $this->enviarUnaVez(
                RecordatorioEnviado::CONSENTIMIENTO_ESTUDIANTE,
                $caso->id_caso,
                [$this->estudianteDe($caso)],
                new ConsentimientoPorFirmar($caso),
            );
        }

        return $enviados;
    }

    /**
     * Consentimientos vigentes que el estudiante ya firmó y esperan la contrafirma del profesional.
     */
    public function contrafirmasPendientes(): int
    {
        $aceptaciones = ConsentimientoAceptacion::query()
            ->where('decision', ConsentimientoAceptacion::ACEPTADO)
            ->where('medio', '<>', ConsentimientoAceptacion::MEDIO_FIRMA_PAPEL)
            ->where('fecha_hora', '>=', $this->inicioVentana()->toDateTimeString())
            ->whereDoesntHave('firmaProfesional')
            ->whereHas('caso', fn (Builder $caso) => $caso->activos())
            ->whereNotExists($this->yaEnviado(RecordatorioEnviado::CONSENTIMIENTO_PROFESIONAL, 'consentimiento_aceptaciones.id_aceptacion'))
            ->with(['caso.aceptacionesConsentimiento', 'firmaProfesional', 'profesional.persona'])
            ->orderBy('fecha_hora')
            ->limit(self::LOTE)
            ->get();

        $enviados = 0;

        foreach ($aceptaciones as $aceptacion) {
            // Solo la decisión vigente: si después hubo un rechazo u otra aceptación, esta ya no cuenta.
            if (! $aceptacion->caso->consentimientoVigente()?->is($aceptacion)) {
                continue;
            }

            $enviados += (int) $this->enviarUnaVez(
                RecordatorioEnviado::CONSENTIMIENTO_PROFESIONAL,
                $aceptacion->id_aceptacion,
                [$aceptacion->profesional],
                new ConsentimientoPorContrafirmar($aceptacion),
            );
        }

        return $enviados;
    }

    /**
     * Reserva el recordatorio y envía el aviso en una sola transacción.
     *
     * @param  array<int, ?User>  $destinatarios
     */
    private function enviarUnaVez(string $tipo, string $referencia, array $destinatarios, AvisoSigabu $aviso): bool
    {
        $destinatarios = array_values(array_filter($destinatarios));

        if ($destinatarios === []) {
            return false;
        }

        try {
            return DB::transaction(function () use ($tipo, $referencia, $destinatarios, $aviso) {
                if (! RecordatorioEnviado::reservar($tipo, $referencia)) {
                    return false;
                }

                Notification::send($destinatarios, $aviso);

                return true;
            });
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Subconsulta "ya se envió este recordatorio". La referencia es texto y los id son UUID en
     * PostgreSQL: se compara como texto para que funcione en los dos motores.
     */
    private function yaEnviado(string $tipo, string $columnaId): \Closure
    {
        return fn (QueryBuilder $consulta) => $consulta->selectRaw('1')
            ->from('recordatorios_enviados')
            ->where('recordatorios_enviados.tipo', $tipo)
            ->whereRaw("recordatorios_enviados.referencia = CAST({$columnaId} AS VARCHAR(64))");
    }

    private function estudianteDe(?Caso $caso): ?User
    {
        return $caso?->solicitud?->persona?->usuario;
    }

    private function ventanaDias(): int
    {
        return max(1, (int) config('sigabu.notificaciones.ventana_dias', 30));
    }

    private function inicioVentana(): Carbon
    {
        return now(Actuacion::ZONA_HORARIA)->subDays($this->ventanaDias())->startOfDay();
    }
}
