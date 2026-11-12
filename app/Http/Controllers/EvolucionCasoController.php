<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Support\EvolucionCaso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class EvolucionCasoController extends Controller
{
    /**
     * Vista de la evolución del caso (HU-015): resumen del progreso y línea de tiempo.
     * Con "parcial" devuelve solo la siguiente página de eventos, para "Cargar más".
     */
    public function show(Request $request, string $caso): View|Response
    {
        $caso = $this->casoDe($request, $caso);
        $evolucion = new EvolucionCaso($caso);

        // Un filtro inválido no rompe la vista: se muestra el error y no se aplican filtros.
        $validador = Validator::make($request->query(), $evolucion->reglas(), EvolucionCaso::MENSAJES);
        $filtros = $validador->fails() ? [] : $validador->validated();
        $evolucion->filtrar($filtros);

        $pagina = $evolucion->eventos($filtros['cursor'] ?? null, $filtros['por_pagina'] ?? null);
        $siguiente = $pagina['siguiente_cursor']
            ? route('casos.evolucion', ['caso' => $caso->id_caso] + array_filter($evolucion->filtros()) + ['cursor' => $pagina['siguiente_cursor']])
            : null;

        if ($request->boolean('parcial')) {
            return response()
                ->view('casos.partials.evolucion-eventos', [
                    'eventos' => $pagina['eventos'],
                    'mesPrevio' => $request->query('mes'),
                    'puedeRegistrar' => $evolucion->puedeRegistrar($request->user()),
                ])
                ->header('X-Siguiente', $siguiente ?? '')
                ->header('X-Cantidad', (string) count($pagina['eventos']))
                ->header('Cache-Control', 'no-store, private');
        }

        if (! isset($filtros['cursor'])) {
            AuditoriaExpediente::registrar(AuditoriaExpediente::EVOLUCION_CONSULTADA, $caso, ['medio' => 'vista']);
        }

        return view('casos.evolucion', [
            'caso' => $caso,
            'datos' => $evolucion->caso($request->user()),
            'resumen' => $evolucion->resumen(),
            'pagina' => $pagina,
            'siguiente' => $siguiente,
            'filtros' => $evolucion->filtros(),
            'hayFiltros' => $evolucion->hayFiltros(),
            'sinActividad' => $evolucion->sinActividad(),
            'profesionales' => $evolucion->profesionales(),
            'puedeRegistrar' => $evolucion->puedeRegistrar($request->user()),
            'erroresFiltro' => $validador->errors(),
        ]);
    }

    /**
     * GET /api/v1/casos/{caso}/evolucion: el mismo contenido de la vista, en JSON.
     */
    public function json(Request $request, string $caso): JsonResponse
    {
        abort_unless($request->user()->role === 'profesional', 403, 'No tienes permiso para consultar la evolución.');

        $caso = $this->casoDe($request, $caso);
        $evolucion = new EvolucionCaso($caso);

        $filtros = $request->validate($evolucion->reglas() + [
            'incluir' => ['nullable', 'array'],
            'incluir.*' => ['in:resumen,eventos'],
        ], EvolucionCaso::MENSAJES);
        $evolucion->filtrar($filtros);
        $incluir = $filtros['incluir'] ?? ['resumen', 'eventos'];

        $respuesta = [
            'caso' => $evolucion->caso($request->user()),
            'filtros_aplicados' => $evolucion->filtros(),
        ];

        if (in_array('resumen', $incluir, true)) {
            $respuesta['resumen'] = $evolucion->resumen();
        }

        if (in_array('eventos', $incluir, true)) {
            $pagina = $evolucion->eventos($filtros['cursor'] ?? null, $filtros['por_pagina'] ?? null);
            $respuesta['eventos'] = array_map(fn (array $evento) => $evolucion->aJson($evento, $request->user()), $pagina['eventos']);
            $respuesta['paginacion'] = [
                'por_pagina' => (int) ($filtros['por_pagina'] ?? EvolucionCaso::POR_PAGINA),
                'total' => $pagina['total'],
                'siguiente_cursor' => $pagina['siguiente_cursor'],
                'hay_mas' => $pagina['siguiente_cursor'] !== null,
            ];
        }

        if (! isset($filtros['cursor'])) {
            AuditoriaExpediente::registrar(AuditoriaExpediente::EVOLUCION_CONSULTADA, $caso, ['medio' => 'api']);
        }

        return response()->json($respuesta)->header('Cache-Control', 'no-store, private');
    }

    /**
     * Solo el profesional asignado lo consulta (quien remitió el caso ya no). A los demás se les
     * responde 404, para no revelar que el caso existe.
     */
    private function casoDe(Request $request, string $id): Caso
    {
        return Caso::deAreaDe($request->user())->where('id_profesional', $request->user()->id_usuario)->whereKey($id)->first()
            ?? abort(404, 'Caso no encontrado.');
    }
}
