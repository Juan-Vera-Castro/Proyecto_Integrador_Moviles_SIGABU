<?php

namespace App\Http\Requests;

use App\Models\Actuacion;
use App\Models\Caso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observaciones' => trim((string) $this->input('observaciones')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_caso' => ['required', 'uuid'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', Rule::in(Actuacion::HORARIOS)],
            'observaciones' => ['nullable', 'string', 'max:'.Actuacion::OBSERVACIONES_MAX],
        ];
    }

    /**
     * Reglas de HU-011: el caso existe, está activo y es del profesional,
     * y la franja es futura y no se cruza con otra cita de su agenda.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->has('id_caso')) {
                    $this->validarCaso($validator);
                }

                if (! $validator->errors()->hasAny(['fecha', 'hora'])) {
                    $this->validarHorario($validator);
                }
            },
        ];
    }

    private function validarCaso(Validator $validator): void
    {
        $caso = Caso::find($this->input('id_caso'));

        if (! $caso) {
            $validator->errors()->add('id_caso', 'El caso seleccionado no existe. Elige un caso de la lista.');
        } elseif (! $caso->estaActivo()) {
            $validator->errors()->add('id_caso', "Solo se pueden agendar citas para casos activos. El caso {$caso->codigo_expediente} está en estado \"{$caso->estado_caso}\".");
        } elseif ($caso->id_profesional !== $this->user()->id_usuario) {
            $validator->errors()->add('id_caso', "El caso {$caso->codigo_expediente} no está asignado a ti. Solo puedes agendar citas en tus casos.");
        }
    }

    private function validarHorario(Validator $validator): void
    {
        $inicio = $this->inicioCita();
        $ahora = now(Actuacion::ZONA_HORARIA);

        if ($inicio->toDateString() < $ahora->toDateString()) {
            $validator->errors()->add('fecha', 'La fecha de la cita no puede ser anterior a hoy.');
        } elseif ($inicio->lte($ahora)) {
            $validator->errors()->add('hora', 'Esa hora ya pasó. Elige una hora posterior o otra fecha.');
        } elseif (Actuacion::horarioOcupado($this->user()->id_usuario, $inicio)) {
            $validator->errors()->add('hora', self::mensajeConflicto($inicio));
        }
    }

    public function inicioCita(): Carbon
    {
        return Actuacion::inicioDeFranja($this->input('fecha'), $this->input('hora'));
    }

    public static function mensajeConflicto(Carbon $inicio): string
    {
        return 'El horario seleccionado no está disponible: ya tienes otra cita agendada el '
            .$inicio->format('Y-m-d').' a las '.$inicio->format('g:i a').'. Elige otra hora o fecha.';
    }

    public function attributes(): array
    {
        return [
            'id_caso' => 'caso',
            'fecha' => 'fecha de la cita',
            'hora' => 'hora de la cita',
            'observaciones' => 'observaciones',
        ];
    }

    public function messages(): array
    {
        return [
            'id_caso.required' => 'Selecciona el caso para el que agendas la cita.',
            'id_caso.uuid' => 'El caso seleccionado no existe. Elige un caso de la lista.',
            'fecha.required' => 'Selecciona la fecha de la cita.',
            'fecha.date_format' => 'Escribe una fecha válida, por ejemplo 2026-10-05.',
            'hora.required' => 'Selecciona la hora de la cita.',
            'hora.in' => 'Selecciona una de las horas de atención disponibles.',
            'observaciones.max' => 'Las observaciones no pueden superar los '.Actuacion::OBSERVACIONES_MAX.' caracteres.',
        ];
    }
}
