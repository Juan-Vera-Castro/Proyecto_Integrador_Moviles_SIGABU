<?php

namespace App\Http\Requests\Inclusion;

use App\Models\Actuacion;
use App\Models\Inclusion\ConsentimientoDetalle;
use App\Models\Inclusion\Evidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Consentimiento firmado en papel: archivo, datos del formato, decisiones, fecha y motivo (RN-12).
 */
class RegistrarConsentimientoPapelRequest extends FormRequest
{
    use ValidaDatosConsentimiento;

    public function authorize(): bool
    {
        return Gate::allows('inclusion.gestionar', $this->caso());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reglasDatos() + [
            'archivo' => ['required', 'file', 'max:'.Evidencia::ARCHIVO_MAX_KB, 'extensions:'.implode(',', array_keys(Evidencia::TIPOS_PERMITIDOS))],
            'fecha_firma_papel' => ['required', 'date', 'before_or_equal:'.now(Actuacion::ZONA_HORARIA)->toDateString()],
            'motivo_papel' => ['required', 'string', 'max:'.ConsentimientoDetalle::MOTIVO_PAPEL_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensajesDatos() + [
            'archivo.required' => 'Sube el formato firmado.',
            'archivo.uploaded' => 'No se pudo subir el archivo: es más grande de lo que permite el servidor.',
            'archivo.max' => 'El archivo no puede superar los '.(Evidencia::ARCHIVO_MAX_KB / 1024).' MB.',
            'archivo.extensions' => 'Formatos permitidos: PDF, JPG, PNG, DOCX y XLSX.',
            'fecha_firma_papel.required' => 'Indica la fecha de la firma.',
            'fecha_firma_papel.date' => 'Indica una fecha válida.',
            'fecha_firma_papel.before_or_equal' => 'La fecha de la firma no puede ser futura.',
            'motivo_papel.required' => 'Escribe por qué la persona no firmó en la plataforma.',
            'motivo_papel.max' => 'El motivo no puede superar los '.ConsentimientoDetalle::MOTIVO_PAPEL_MAX.' caracteres.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $this->validarDependencia($validator);

            $archivo = $this->file('archivo');

            if ($archivo && ! $validator->errors()->has('archivo') && ! Evidencia::tipoRealPermitido($archivo)) {
                $validator->errors()->add('archivo', 'El contenido del archivo no corresponde a su formato.');
            }
        }];
    }
}
