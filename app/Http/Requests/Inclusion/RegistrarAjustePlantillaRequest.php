<?php

namespace App\Http\Requests\Inclusion;

use App\Models\AjusteRazonable;
use App\Models\Inclusion\Evidencia;
use App\Models\Inclusion\Recurso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Fila de la plantilla 5.8. Se guarda en ajustes_razonables: no hay dos registros paralelos (RN-24).
 */
class RegistrarAjustePlantillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inclusion.gestionar', $this->route('caso'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $texto = 'max:'.AjusteRazonable::TEXTO_MAX;

        return [
            'area' => ['required', Rule::in(AjusteRazonable::AREAS)],
            'barrera' => ['required', 'string', $texto],
            'descripcion' => ['required', 'string', $texto],
            'responsable' => ['required', 'string', 'max:150'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'observaciones' => ['nullable', 'string', $texto],
            'id_recurso' => ['nullable', Rule::in(Recurso::activos()->pluck('id_recurso')->all())],
            'evidencias' => ['nullable', 'array', 'max:'.Evidencia::MAX_POR_REGISTRO],
            'evidencias.*' => ['uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'area.required' => 'Selecciona el área o barrera.',
            'area.in' => 'Selecciona un área de la lista.',
            'barrera.required' => 'Describe la barrera.',
            'descripcion.required' => 'Describe el ajuste o la estrategia.',
            'responsable.required' => 'Indica quién es responsable del ajuste.',
            'fecha_inicio.required' => 'Indica la fecha.',
            'fecha_inicio.date_format' => 'Indica una fecha válida.',
            'id_recurso.in' => 'Selecciona un recurso del catálogo.',
            'evidencias.max' => 'Puedes adjuntar hasta '.Evidencia::MAX_POR_REGISTRO.' evidencias por fila.',
            'max' => 'El campo es demasiado largo.',
        ];
    }
}
