<?php

namespace App\Http\Controllers\Api\Movil;

use App\Models\Actuacion;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Formatos comunes de la API móvil. Las fechas y horas van en la hora de Colombia, sin zona
 * ("2026-11-12T15:00:00"), igual que se muestran en la web.
 */
final class Formato
{
    public const SERVICIOS = [
        'Salud Mental' => ['icono' => '🧠', 'titulo' => 'Atención Psicológica'],
        'Inclusión' => ['icono' => '♿', 'titulo' => 'Inclusión Universitaria'],
    ];

    public static function fechaHora(?CarbonInterface $fecha): ?string
    {
        return $fecha?->format('Y-m-d\TH:i:s');
    }

    /**
     * Casos del estudiante autenticado.
     *
     * @return Collection<int, string>
     */
    public static function idsCasos(Request $request): Collection
    {
        return $request->user()->persona?->casos()->pluck('casos.id_caso') ?? collect();
    }

    /**
     * @return array<string, mixed>
     */
    public static function cita(Actuacion $cita): array
    {
        $servicio = $cita->caso?->solicitud?->tipo_servicio;

        return [
            'id' => $cita->id_actuacion,
            'fecha_hora' => self::fechaHora($cita->fecha_hora),
            'servicio' => $servicio,
            'servicio_titulo' => self::SERVICIOS[$servicio]['titulo'] ?? 'Cita de acompañamiento',
            'servicio_icono' => self::SERVICIOS[$servicio]['icono'] ?? '📅',
            'profesional' => $cita->profesional?->persona?->nombre_completo,
            'descripcion' => $cita->descripcion,
            'estado_asistencia' => $cita->asistenciaPendiente() ? 'Asistencia por registrar' : $cita->estado_asistencia,
        ];
    }
}
