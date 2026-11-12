<?php

namespace App\Http\Middleware;

use App\Models\AlertaTemprana;
use App\Models\Caso;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El profesional solo entra a lo de su área. Con servicios como parámetro protege un listado:
 * quien no los atiende vuelve a su panel, como en CheckRole. Sin parámetros protege cualquier
 * ruta que apunte a un caso, directamente o por algo que le pertenece.
 */
class CheckAreaProfesional
{
    /**
     * Parámetros de ruta que llevan a un caso.
     */
    private const PARAMETROS = ['caso', 'cita', 'ajuste', 'aceptacion', 'remision', 'desistimiento', 'entrada', 'evidencia', 'alerta'];

    public function handle(Request $request, Closure $next, string ...$servicios): Response
    {
        $usuario = $request->user();

        if ($servicios && ! array_intersect($servicios, $usuario->serviciosQueAtiende())) {
            return redirect($usuario->getDashboardUrl());
        }

        $caso = $this->casoDeLaRuta($request);

        abort_if($caso && ! $usuario->atiendeServicio($caso->solicitud?->tipo_servicio), 403, 'Este caso no pertenece a tu área.');

        return $next($request);
    }

    private function casoDeLaRuta(Request $request): ?Caso
    {
        foreach (self::PARAMETROS as $parametro) {
            $valor = $request->route($parametro);

            if ($valor !== null) {
                return match (true) {
                    $valor instanceof Caso => $valor,
                    $valor instanceof Model => $valor->caso,
                    $parametro === 'alerta' => AlertaTemprana::find($valor)?->caso,
                    default => Caso::find($valor),
                };
            }
        }

        return null;
    }
}
