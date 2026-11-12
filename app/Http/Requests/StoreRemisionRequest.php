<?php

namespace App\Http\Requests;

use App\Models\Actuacion;
use App\Models\RemisionExterna;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRemisionRequest extends FormRequest
{
    /**
     * Los errores se muestran en el formulario de remisión del expediente.
     */
    protected $errorBag = 'remision';

    /**
     * Solo el profesional asignado al caso puede remitirlo.
     */
    public function authorize(): bool
    {
        return $this->route('caso')?->id_profesional === $this->user()->id_usuario;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => trim((string) $this->input('motivo')) ?: null,
            'autorizacion_traslado' => trim((string) $this->input('autorizacion_traslado')) ?: null,
            'entidad_otra' => trim((string) $this->input('entidad_otra')),
            ...collect(self::CAMPOS_ACOMPANANTE)->mapWithKeys(fn (string $campo) => [$campo => trim((string) $this->input($campo))])->all(),
        ]);
    }

    /**
     * Datos del acompañante del formato GBU-GPGR-F-016: solo en remisiones URGENTES (RN-10).
     */
    public const CAMPOS_ACOMPANANTE = ['acompanante_nombre', 'acompanante_parentesco', 'acompanante_tipo_documento', 'acompanante_numero_documento', 'acompanante_telefono'];

    public const AUTORIZACION_MAX = 300;

    public function esExterna(): bool
    {
        return $this->input('destino_remision') === Actuacion::DESTINO_EXTERNA;
    }

    /**
     * Traslado a un psicólogo de otra facultad: es una excepción que alguien debe autorizar.
     */
    public function trasladoEsExcepcion(): bool
    {
        $idDestino = $this->input('id_profesional_destino');

        return $this->input('destino_remision') === Actuacion::DESTINO_INTERNA
            && is_string($idDestino) && $idDestino !== ''
            && (bool) $this->route('caso')?->trasladoEsExcepcion($idDestino);
    }

    public function esUrgente(): bool
    {
        return $this->esExterna() && $this->input('categoria') === RemisionExterna::URGENTE;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destino_remision' => ['required', Rule::in([Actuacion::DESTINO_INTERNA, Actuacion::DESTINO_EXTERNA])],
            'id_profesional_destino' => ['exclude_unless:destino_remision,'.Actuacion::DESTINO_INTERNA, 'required', 'uuid'],
            'entidad_destino' => ['exclude_unless:destino_remision,'.Actuacion::DESTINO_EXTERNA, 'required', Rule::in(Actuacion::ENTIDADES)],
            'entidad_otra' => [
                Rule::excludeIf(fn () => $this->input('destino_remision') !== Actuacion::DESTINO_EXTERNA || $this->input('entidad_destino') !== Actuacion::ENTIDAD_OTRA),
                'required', 'string', 'max:'.Actuacion::ENTIDAD_MAX,
            ],
            // El traslado a otro psicólogo no exige justificación; la remisión externa sí (formato F-016).
            'motivo' => [$this->esExterna() ? 'required' : 'nullable', 'string', 'max:'.Actuacion::MOTIVO_REMISION_MAX],
            'autorizacion_traslado' => [
                Rule::excludeIf(fn () => ! $this->trasladoEsExcepcion()),
                'required', 'string', 'max:'.self::AUTORIZACION_MAX,
            ],
            'confirmar_citas' => ['nullable', 'boolean'],
            'categoria' => ['exclude_unless:destino_remision,'.Actuacion::DESTINO_EXTERNA, 'required', Rule::in(RemisionExterna::CATEGORIAS)],
            'acompanante_nombre' => [Rule::excludeIf(fn () => ! $this->esUrgente()), 'required', 'string', 'max:150'],
            'acompanante_parentesco' => [Rule::excludeIf(fn () => ! $this->esUrgente()), 'required', 'string', 'max:50'],
            'acompanante_tipo_documento' => [Rule::excludeIf(fn () => ! $this->esUrgente()), 'required', Rule::in(RemisionExterna::TIPOS_DOCUMENTO_ACOMPANANTE)],
            'acompanante_numero_documento' => [Rule::excludeIf(fn () => ! $this->esUrgente()), 'required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'acompanante_telefono' => [Rule::excludeIf(fn () => ! $this->esUrgente()), 'required', 'string', 'max:20', 'regex:/^[0-9 +()\-]{7,20}$/'],
        ];
    }

    /**
     * El profesional destino debe ser otra cuenta activa con rol Profesional.
     */
    public function after(): array
    {
        return [
            // Remitir es parte de la atención: exige consentimiento firmado (RN-01).
            function (Validator $validator) {
                $caso = $this->route('caso');

                if ($caso?->estaActivo() && $motivo = $caso->motivoSinAtencion()) {
                    $validator->errors()->add('consentimiento', $motivo);
                }
            },
            // El formato de remisión externa toma del registro el tipo de documento, el sexo y la fecha de nacimiento.
            function (Validator $validator) {
                $persona = $this->route('caso')?->solicitud?->persona;

                if ($this->esExterna() && $persona && ! $persona->datosRemisionCompletos()) {
                    $validator->errors()->add('datos_estudiante', 'Completa el tipo de documento, el sexo y la fecha de nacimiento del estudiante antes de remitirlo a una entidad externa.');
                }
            },
            // Fiscalía, ICBF y Comisaría no se tramitan aquí, tampoco escritas en "Otra" (RN-12).
            function (Validator $validator) {
                $otra = mb_strtolower((string) $this->input('entidad_otra'));

                if ($this->esExterna() && $this->input('entidad_destino') === Actuacion::ENTIDAD_OTRA
                    && collect(Actuacion::ENTIDADES_FUERA_DE_ALCANCE)->contains(fn (string $entidad) => str_contains($otra, $entidad))) {
                    $validator->errors()->add('entidad_otra', 'Las remisiones a Comisaría, Fiscalía o ICBF las tramita la abogada con los formatos de esas entidades; no se registran aquí.');
                }
            },
            function (Validator $validator) {
                if ($this->input('destino_remision') !== Actuacion::DESTINO_INTERNA || $validator->errors()->has('id_profesional_destino')) {
                    return;
                }

                $idDestino = $this->input('id_profesional_destino');

                if ($idDestino === $this->user()->id_usuario) {
                    $validator->errors()->add('id_profesional_destino', 'No puedes remitirte el caso a ti mismo. Elige otro profesional.');
                } elseif (! User::profesionalesActivos()->whereKey($idDestino)->exists()) {
                    $validator->errors()->add('id_profesional_destino', 'El profesional seleccionado no existe o no está activo. Elige uno de la lista.');
                } elseif (! User::queAtienden($this->route('caso')?->solicitud?->tipo_servicio)->whereKey($idDestino)->exists()) {
                    $validator->errors()->add('id_profesional_destino', 'Ese profesional no atiende el área de este caso. Elige uno de la lista.');
                }
            },
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('casos.show', $this->route('caso')).'#remisiones';
    }

    /**
     * Entidad tal como se guarda: el nombre escrito cuando se eligió "Otra".
     */
    public function entidadDestino(): ?string
    {
        if ($this->input('destino_remision') !== Actuacion::DESTINO_EXTERNA) {
            return null;
        }

        return $this->input('entidad_destino') === Actuacion::ENTIDAD_OTRA
            ? $this->input('entidad_otra')
            : $this->input('entidad_destino');
    }

    public function messages(): array
    {
        return [
            'destino_remision.required' => 'Indica si remites el caso a otro profesional o a una entidad externa.',
            'destino_remision.in' => 'Indica si remites el caso a otro profesional o a una entidad externa.',
            'id_profesional_destino.required' => 'Selecciona el profesional que recibirá el caso.',
            'id_profesional_destino.uuid' => 'El profesional seleccionado no existe o no está activo. Elige uno de la lista.',
            'entidad_destino.required' => 'Selecciona la entidad a la que remites el caso.',
            'entidad_destino.in' => 'Selecciona una de las entidades de la lista.',
            'entidad_otra.required' => 'Escribe el nombre de la entidad a la que remites el caso.',
            'entidad_otra.max' => 'El nombre de la entidad no puede superar los '.Actuacion::ENTIDAD_MAX.' caracteres.',
            'motivo.required' => 'La justificación de la remisión externa es obligatoria.',
            'autorizacion_traslado.required' => 'Ese profesional no atiende la facultad del estudiante: indica quién autorizó el traslado a otra facultad.',
            'autorizacion_traslado.max' => 'La autorización no puede superar los '.self::AUTORIZACION_MAX.' caracteres.',
            'motivo.max' => 'El motivo no puede superar los '.Actuacion::MOTIVO_REMISION_MAX.' caracteres.',
            'categoria.required' => 'Indica si la remisión es URGENTE (va a urgencias) o PRIORITARIA (pide cita en su EPS).',
            'categoria.in' => 'Indica si la remisión es URGENTE o PRIORITARIA.',
            'acompanante_nombre.required' => 'En una remisión urgente escribe el nombre de la persona responsable del estudiante.',
            'acompanante_parentesco.required' => 'Escribe el parentesco del acompañante con el estudiante.',
            'acompanante_tipo_documento.required' => 'Selecciona el tipo de identificación del acompañante.',
            'acompanante_tipo_documento.in' => 'Selecciona el tipo de identificación del acompañante (CC, CE, TI o pasaporte).',
            'acompanante_numero_documento.required' => 'Escribe el número de identificación del acompañante.',
            'acompanante_numero_documento.regex' => 'El número de identificación solo puede tener números, letras, puntos y guiones.',
            'acompanante_telefono.required' => 'Escribe un número de contacto del acompañante.',
            'acompanante_telefono.regex' => 'Escribe un número de contacto válido (entre 7 y 20 dígitos).',
        ];
    }
}
