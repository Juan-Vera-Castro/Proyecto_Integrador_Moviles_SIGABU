<?php

namespace App\Http\Requests\Inclusion;

use App\Models\Inclusion\Evidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Un archivo o un link de evidencia. Del archivo se valida la extensión y también su tipo
 * real, para que no pase, por ejemplo, un programa renombrado como PDF (RN-28).
 */
class SubirEvidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inclusion.gestionar', $this->route('caso'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['url' => trim((string) $this->input('url')) ?: null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'archivo' => ['nullable', 'required_without:url', 'file', 'max:'.Evidencia::ARCHIVO_MAX_KB, 'extensions:'.implode(',', array_keys(Evidencia::TIPOS_PERMITIDOS))],
            'url' => ['nullable', 'required_without:archivo', 'url:https', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required_without' => 'Sube un archivo o pega un link.',
            'archivo.uploaded' => 'No se pudo subir el archivo: es más grande de lo que permite el servidor.',
            'archivo.max' => 'El archivo no puede superar los '.(Evidencia::ARCHIVO_MAX_KB / 1024).' MB.',
            'archivo.extensions' => 'Formatos permitidos: PDF, JPG, PNG, DOCX y XLSX.',
            'url.required_without' => 'Sube un archivo o pega un link.',
            'url.url' => 'El link debe ser una dirección https:// válida.',
            'url.max' => 'El link no puede superar los 500 caracteres.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $archivo = $this->file('archivo');

            if (! $archivo || $validator->errors()->has('archivo')) {
                return;
            }

            if (! Evidencia::tipoRealPermitido($archivo)) {
                $validator->errors()->add('archivo', 'El contenido del archivo no corresponde a su formato.');
            }
        }];
    }
}
