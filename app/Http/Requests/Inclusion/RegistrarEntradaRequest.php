<?php

namespace App\Http\Requests\Inclusion;

use App\Models\Actuacion;
use App\Models\Inclusion\EntradaBitacora;
use App\Models\Inclusion\Evidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Entrada de la matriz de seguimiento. El número y las reglas de tipo las pone el controlador,
 * dentro de la transacción, porque dependen de las entradas que ya existen.
 */
class RegistrarEntradaRequest extends FormRequest
{
    protected $errorBag = 'entrada';

    public function authorize(): bool
    {
        return Gate::allows('inclusion.gestionar', $this->route('caso'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_hora' => ['required', 'date', 'before_or_equal:'.now(Actuacion::ZONA_HORARIA)->format('Y-m-d H:i:s')],
            'tipo' => ['required', Rule::in(EntradaBitacora::TIPOS)],
            'descripcion' => ['required', 'string', 'max:'.EntradaBitacora::TEXTO_MAX],
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
            'fecha_hora.required' => 'Indica la fecha y la hora.',
            'fecha_hora.date' => 'Indica una fecha válida.',
            'fecha_hora.before_or_equal' => 'La fecha no puede ser futura.',
            'tipo.required' => 'Selecciona el tipo de acción.',
            'tipo.in' => 'Selecciona un tipo de la lista.',
            'descripcion.required' => 'Escribe la descripción de la orientación.',
            'descripcion.max' => 'La descripción no puede superar los '.EntradaBitacora::TEXTO_MAX.' caracteres.',
            'evidencias.max' => 'Puedes adjuntar hasta '.Evidencia::MAX_POR_REGISTRO.' evidencias por entrada.',
        ];
    }
}
