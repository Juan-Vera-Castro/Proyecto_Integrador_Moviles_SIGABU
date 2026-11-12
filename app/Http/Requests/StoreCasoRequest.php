<?php

namespace App\Http\Requests;

use App\Models\Caso;
use App\Models\ModeloConsentimiento;
use App\Models\Persona;
use App\Models\SolicitudAtencion;
use App\Models\User;
use App\Models\VersionConsentimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCasoRequest extends FormRequest
{
    public const MOTIVO_MAX = 300;

    private ?VersionConsentimiento $versionConsentimiento = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza la cédula (se permite escribirla con puntos o espacios) y el correo.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre_completo' => trim((string) $this->input('nombre_completo')),
            'numero_documento' => preg_replace('/[\s.]/', '', (string) $this->input('numero_documento')),
            'correo_inst' => mb_strtolower(trim((string) $this->input('correo_inst'))),
            'motivo' => trim((string) $this->input('motivo')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre_completo' => ['required', 'string', 'min:3', 'max:150'],
            'numero_documento' => ['required', 'regex:/^\d{6,11}$/'],
            'correo_inst' => ['required', 'email', 'max:120', 'ends_with:@uceva.edu.co'],
            'tipo_servicio' => ['required', Rule::in($this->user()->serviciosQueAtiende())],
            'nivel_riesgo' => ['nullable', Rule::in(Caso::NIVELES_RIESGO)],
            'motivo' => ['required', 'string', 'min:10', 'max:'.self::MOTIVO_MAX],
            'id_version' => ['nullable', 'integer'],
            'acepta_consentimiento' => ['nullable'],
        ];
    }

    /**
     * Versión vigente del consentimiento del servicio elegido (HU-009), o null si el
     * servicio aún no tiene uno publicado.
     */
    public function versionConsentimiento(): ?VersionConsentimiento
    {
        if (! in_array($this->input('tipo_servicio'), SolicitudAtencion::SERVICIOS, true)) {
            return null;
        }

        return $this->versionConsentimiento ??= ModeloConsentimiento::versionVigenteDe($this->input('tipo_servicio'));
    }

    /**
     * Si el servicio tiene consentimiento vigente, el profesional debe confirmar que el
     * solicitante lo aceptó, y debe ser la misma versión que se le mostró en el formulario.
     */
    private function validarConsentimiento(Validator $validator): void
    {
        $vigente = $this->versionConsentimiento();

        if (! $vigente) {
            return;
        }

        if ((int) $this->input('id_version') !== $vigente->id_version) {
            $validator->errors()->add('acepta_consentimiento', "El consentimiento de {$this->input('tipo_servicio')} se actualizó mientras llenabas el formulario. Lee la versión {$vigente->numero_version} y confirma de nuevo que el solicitante la aceptó.");
        } elseif (! $this->boolean('acepta_consentimiento')) {
            $validator->errors()->add('acepta_consentimiento', 'Confirma que el solicitante leyó y aceptó el consentimiento informado.');
        }
    }

    /**
     * El solicitante se identifica por su cédula. Si ya existe, el correo debe
     * ser el suyo; si no existe, el correo y el código de usuario deben estar libres
     * (personas.correo_inst y usuarios.codigo_unico son únicos en la base).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->has('tipo_servicio')) {
                    $this->validarConsentimiento($validator);
                }
            },
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['numero_documento', 'correo_inst'])) {
                    return;
                }

                $documento = $this->input('numero_documento');
                $correo = $this->input('correo_inst');
                $persona = Persona::where('numero_documento', $documento)->first();

                if ($persona) {
                    if (mb_strtolower($persona->correo_inst) !== $correo) {
                        $validator->errors()->add('correo_inst', 'Este documento ya está registrado con otro correo institucional. Verifica el correo del solicitante.');
                    }

                    return;
                }

                if (Persona::whereRaw('lower(correo_inst) = ?', [$correo])->exists()) {
                    $validator->errors()->add('correo_inst', 'Este correo institucional ya pertenece a otra persona. Verifica el documento o el correo.');
                } elseif (User::where('codigo_unico', $documento)->exists()) {
                    $validator->errors()->add('numero_documento', 'Ya existe una cuenta con este número de documento. Comunícate con el administrador del sistema.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_completo' => 'nombre completo',
            'numero_documento' => 'documento de identidad',
            'correo_inst' => 'correo institucional',
            'tipo_servicio' => 'servicio',
            'nivel_riesgo' => 'nivel de riesgo inicial',
            'motivo' => 'motivo de consulta',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'nombre_completo.min' => 'Escribe el nombre completo del solicitante (mínimo 3 caracteres).',
            'nombre_completo.max' => 'El nombre completo no puede superar los 150 caracteres.',
            'numero_documento.regex' => 'El documento de identidad debe tener entre 6 y 11 dígitos, sin letras.',
            'correo_inst.email' => 'Escribe un correo válido, por ejemplo usuario@uceva.edu.co.',
            'correo_inst.max' => 'El correo institucional no puede superar los 120 caracteres.',
            'correo_inst.ends_with' => 'Usa el correo institucional del solicitante (termina en @uceva.edu.co).',
            'tipo_servicio.in' => 'Selecciona un servicio de la lista.',
            'nivel_riesgo.in' => 'Selecciona un nivel de riesgo de la lista.',
            'motivo.min' => 'Describe el motivo de consulta con al menos 10 caracteres.',
            'motivo.max' => 'El motivo de consulta no puede superar los '.self::MOTIVO_MAX.' caracteres.',
        ];
    }
}
