<?php

namespace App\Http\Requests\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Models\Inclusion\Bitacora;
use App\Models\Inclusion\Evidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Caracterización de la bitácora: sección II del formato, con sus soportes.
 */
class GuardarCaracterizacionRequest extends FormRequest
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
        $reglas = collect(Bitacora::CARACTERIZACION)
            ->map(fn (array $campo) => [$campo[1] ? 'required' : 'nullable', 'string', 'max:'.$campo[2]])
            ->all();

        $reglas['rol_institucional'][] = Rule::in(array_keys(ConsentimientoInclusion::VINCULACIONES));

        return $reglas + [
            'soportes' => ['nullable', 'array', 'max:'.Evidencia::MAX_POR_REGISTRO],
            'soportes.*' => ['uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(Bitacora::CARACTERIZACION)->map(fn (array $campo) => $campo[0])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Completa el campo :attribute.',
            'max' => 'El campo :attribute es demasiado largo.',
            'rol_institucional.in' => 'Selecciona un rol de la lista.',
            'soportes.max' => 'Puedes adjuntar hasta '.Evidencia::MAX_POR_REGISTRO.' soportes por registro.',
        ];
    }

    /**
     * Solo los campos de la caracterización, sin los soportes.
     *
     * @return array<string, string|null>
     */
    public function caracterizacion(): array
    {
        return collect(Bitacora::CARACTERIZACION)
            ->map(fn (array $campo, string $nombre) => $this->validated($nombre))
            ->all();
    }
}
