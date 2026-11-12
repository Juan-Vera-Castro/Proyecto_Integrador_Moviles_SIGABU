<?php

namespace App\Http\Controllers;

use App\Models\Actuacion;
use App\Models\AuditoriaExpediente;
use App\Models\Caso;
use App\Models\HistoriaClinica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HistoriaClinicaController extends Controller
{
    /**
     * Registra la historia clínica psicológica del caso: queda asociada al expediente.
     * Solo el psicólogo del caso, en un caso activo de Salud Mental con consentimiento
     * aceptado. Se registra una sola vez; después solo admite anotaciones.
     */
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);

        $request->merge(collect(HistoriaClinica::APARTADOS)
            ->map(fn ($apartado, string $campo) => ($texto = trim((string) $request->input($campo))) === '' ? null : $texto)
            ->all());

        $reglas = [];
        $mensajes = [];
        foreach (HistoriaClinica::APARTADOS as $campo => [$etiqueta, $obligatorio]) {
            $reglas[$campo] = [$obligatorio ? 'required' : 'nullable', 'string', 'max:'.HistoriaClinica::TEXTO_MAX];
            $mensajes["{$campo}.required"] = "El apartado \"{$etiqueta}\" es obligatorio.";
            $mensajes["{$campo}.max"] = "El apartado \"{$etiqueta}\" no puede superar los ".HistoriaClinica::TEXTO_MAX.' caracteres.';
        }

        $datos = $request->validateWithBag('historia', $reglas, $mensajes);

        $volver = redirect()->to(route('casos.show', $caso).'#historia-clinica');

        if ($motivo = $caso->motivoSinHistoriaClinica()) {
            return $volver->withInput()->with('error', $motivo);
        }

        if ($caso->historiaClinica) {
            return $volver->withInput()->with('error', 'El expediente ya tiene historia clínica: no se modifica. Agrega una anotación para corregirla o complementarla.');
        }

        DB::transaction(function () use ($caso, $datos, $request) {
            $caso->historiaClinica()->create($datos + [
                'id_profesional' => $request->user()->id_usuario,
                'fecha_registro' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);
            AuditoriaExpediente::registrar(AuditoriaExpediente::HISTORIA_CLINICA_REGISTRADA, $caso);
        });

        return $volver->with('status', "Historia clínica registrada en el expediente {$caso->codigo_expediente}.");
    }

    /**
     * Agrega una corrección o un complemento a la historia, con la fecha y el autor.
     * Las mismas condiciones que para registrarla.
     */
    public function anotar(Request $request, Caso $caso): RedirectResponse
    {
        abort_unless($caso->id_profesional === $request->user()->id_usuario, 403);

        $historia = $caso->historiaClinica;
        abort_unless($historia, 404);

        $request->merge([
            'apartado' => $request->input('apartado') ?: null,
            'texto' => trim((string) $request->input('texto')),
        ]);

        $datos = $request->validateWithBag('anotacion', [
            'apartado' => ['nullable', Rule::in(array_keys(HistoriaClinica::APARTADOS))],
            'texto' => ['required', 'string', 'max:'.HistoriaClinica::TEXTO_MAX],
        ], [
            'apartado.in' => 'Selecciona un apartado de la historia clínica.',
            'texto.required' => 'Escribe la anotación.',
            'texto.max' => 'La anotación no puede superar los '.HistoriaClinica::TEXTO_MAX.' caracteres.',
        ]);

        $volver = redirect()->to(route('casos.show', $caso).'#historia-clinica');

        if ($motivo = $caso->motivoSinHistoriaClinica()) {
            return $volver->withInput()->with('error', $motivo);
        }

        DB::transaction(function () use ($caso, $historia, $datos, $request) {
            $historia->anotaciones()->create($datos + [
                'id_profesional' => $request->user()->id_usuario,
                'fecha_hora' => now(Actuacion::ZONA_HORARIA)->toDateTimeString(),
            ]);
            AuditoriaExpediente::registrar(AuditoriaExpediente::HISTORIA_CLINICA_ANOTADA, $caso, ['apartado' => $datos['apartado']]);
        });

        return $volver->with('status', 'Anotación agregada a la historia clínica.');
    }
}
