<?php

namespace App\Http\Requests\Inclusion;

use App\Http\Requests\RegistrarConsentimientoRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Envío en línea del consentimiento de Inclusión: los datos del formato y la firma.
 */
class EnviarConsentimientoRequest extends FormRequest
{
    use ValidaDatosConsentimiento;

    private ?string $firmaPng = null;

    /**
     * La firma no vuelve a la sesión si hay errores: es pesada y es un dato sensible.
     */
    protected $dontFlash = ['firma'];

    public function authorize(): bool
    {
        return Gate::allows('inclusion.diligenciarConsentimiento', $this->caso());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reglasDatos() + [
            'id_version' => ['required', 'integer'],
            'firma' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensajesDatos() + [
            'id_version.required' => 'Vuelve a cargar la página: falta la versión del consentimiento.',
            'firma.required' => 'Falta la firma. Firma dentro del recuadro.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $this->validarDependencia($validator);

            if (! $validator->errors()->has('id_version') && (int) $this->input('id_version') !== $this->versionVigente()?->id_version) {
                $validator->errors()->add('id_version', 'El consentimiento se actualizó mientras lo leías. Vuelve a cargar la página para ver el texto vigente.');
            }

            if (! $validator->errors()->has('firma')) {
                [$this->firmaPng, $error] = RegistrarConsentimientoRequest::leerFirma((string) $this->input('firma'));

                if ($error) {
                    $validator->errors()->add('firma', $error);
                }
            }
        }];
    }

    /**
     * Bytes del PNG ya validado.
     */
    public function firmaPng(): string
    {
        return (string) $this->firmaPng;
    }
}
