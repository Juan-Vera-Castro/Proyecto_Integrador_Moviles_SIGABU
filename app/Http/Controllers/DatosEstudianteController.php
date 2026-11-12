<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DatosEstudianteController extends Controller
{
    /**
     * Completa o corrige el tipo de documento, el sexo y la fecha de nacimiento del estudiante
     * del caso (D-01). Solo lo hace el psicólogo asignado al caso (D-20).
     */
    public function update(Request $request, Caso $caso): RedirectResponse
    {
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);

        $persona = $caso->solicitud?->persona;
        abort_unless($persona, 404);

        $volver = route('casos.show', $caso).'#estudiante';

        $hoy = now(Actuacion::ZONA_HORARIA)->toDateString();

        $datos = $request->validateWithBag('estudiante', [
            'tipo_documento' => ['required', Rule::in(Persona::TIPOS_DOCUMENTO)],
            'sexo' => ['required', Rule::in(Persona::SEXOS)],
            'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.$hoy],
        ], [
            'tipo_documento.required' => 'Selecciona el tipo de documento (TI, CC o CE).',
            'tipo_documento.in' => 'Selecciona el tipo de documento (TI, CC o CE).',
            'sexo.required' => 'Selecciona el sexo (F o M).',
            'sexo.in' => 'Selecciona el sexo (F o M).',
            'fecha_nacimiento.required' => 'Escribe la fecha de nacimiento.',
            'fecha_nacimiento.date_format' => 'Escribe una fecha válida, por ejemplo 2005-03-10.',
            'fecha_nacimiento.after_or_equal' => 'La fecha de nacimiento no es válida.',
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser posterior a hoy.',
        ]);

        $persona->fill($datos);
        $cambios = array_keys($persona->getDirty());

        if ($cambios) {
            $persona->save();
            AuditoriaExpediente::registrar(AuditoriaExpediente::DATOS_ESTUDIANTE_ACTUALIZADOS, $caso, ['campos' => $cambios]);
        }

        return redirect()->to($volver)->with('status', 'Datos del estudiante guardados.');
    }
}
