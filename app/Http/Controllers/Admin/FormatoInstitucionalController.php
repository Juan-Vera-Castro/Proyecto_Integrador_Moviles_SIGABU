<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\FormatoInstitucional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Código, versión y fecha de los formatos de Calidad (D-17, RN-13). Cambiarlos no altera
 * los documentos ya registrados: cada remisión guarda los valores con que se generó.
 */
class FormatoInstitucionalController extends Controller
{
    public function index(): View
    {
        return view('admin.formatos.index', [
            'formatos' => FormatoInstitucional::with('autor.persona')->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, FormatoInstitucional $formato): RedirectResponse
    {
        $request->merge([
            'codigo' => mb_strtoupper(trim((string) $request->input('codigo'))),
            'version' => trim((string) $request->input('version')),
        ]);

        $datos = $request->validateWithBag('formato-'.$formato->id_formato, [
            'proceso' => ['required', 'string', 'max:100'],
            'nombre' => ['required', 'string', 'max:100'],
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'version' => ['required', 'string', 'max:10', 'regex:/^[0-9A-Za-z.]+$/'],
            'fecha_formato' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'proceso.required' => 'Escribe el proceso del formato.',
            'nombre.required' => 'Escribe el nombre del formato.',
            'codigo.required' => 'Escribe el código del formato, por ejemplo GBU-GPGR-F-016.',
            'codigo.regex' => 'El código solo puede tener letras, números y guiones.',
            'version.required' => 'Escribe la versión del formato.',
            'version.regex' => 'La versión solo puede tener números, letras y puntos.',
            'fecha_formato.required' => 'Escribe la fecha del formato.',
            'fecha_formato.date_format' => 'Escribe una fecha válida, por ejemplo 2024-04-18.',
            'fecha_formato.before_or_equal' => 'La fecha del formato no puede ser posterior a hoy.',
            'max' => 'El campo es demasiado largo.',
        ]);

        $anterior = $formato->only(['codigo', 'version']) + ['fecha_formato' => $formato->fecha_formato?->toDateString()];

        $formato->update([
            ...$datos,
            'actualizado_por' => $request->user()->id_usuario,
            'fecha_actualizacion' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
        ]);

        AuditoriaExpediente::registrar(AuditoriaExpediente::FORMATO_ACTUALIZADO, null, [
            'clave' => $formato->clave,
            'anterior' => $anterior,
            'nuevo' => $formato->only(['codigo', 'version']) + ['fecha_formato' => $formato->fecha_formato->toDateString()],
        ]);

        return redirect()->route('admin.formatos.index')
            ->with('status', "Formato {$formato->nombre} actualizado: {$formato->codigo} versión {$formato->version}. Las remisiones ya registradas conservan el formato con que se generaron.");
    }
}
