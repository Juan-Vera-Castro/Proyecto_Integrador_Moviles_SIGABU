<?php

namespace App\Http\Requests;

use App\Models\Caso;
use App\Models\Facultad;
use App\Models\ModeloConsentimiento;
use App\Models\SolicitudAtencion;
use App\Models\VersionConsentimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Solicitud de cita que hace el estudiante desde su panel: servicio, franja horaria y
 * consentimiento informado. La facultad y la jornada se toman de su registro (solo se piden si
 * faltan). No elige profesional ni escribe el motivo (revisión del prototipo con Bienestar,
 * 06/10/2026): el caso llega al psicólogo de su facultad y jornada, que concierta la hora exacta.
 */
class StoreSolicitudCitaRequest extends FormRequest
{
    /**
     * Paso del formulario en el que se captura cada campo, para volver al primero con error.
     */
    public const PASOS = [
        'tipo_servicio' => 1,
        'id_facultad' => 2,
        'jornada' => 2,
        'franja_horaria' => 2,
        'es_emergencia' => 2,
        'acepta_consentimiento' => 3,
    ];

    private ?VersionConsentimiento $versionConsentimiento = null;

    private ?array $datosAcademicos = null;

    /**
     * Facultad, programa y jornada registrados del estudiante (vacíos si no tiene persona).
     *
     * @return array{vinculacion: ?string, facultad: ?Facultad, programa: ?string, jornada: ?string, semestre: ?int}
     */
    public function datosAcademicos(): array
    {
        return $this->datosAcademicos ??= $this->user()?->persona?->datosAcademicos()
            ?? ['vinculacion' => null, 'facultad' => null, 'programa' => null, 'jornada' => null, 'semestre' => null];
    }

    /**
     * Facultad con la que se asigna el profesional: la registrada o, si no hay, la elegida.
     */
    public function idFacultad(): int
    {
        return $this->datosAcademicos()['facultad']?->id_facultad ?? (int) $this->validated('id_facultad');
    }

    public function jornada(): string
    {
        return $this->datosAcademicos()['jornada'] ?? (string) $this->validated('jornada');
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $registrados = $this->datosAcademicos();

        // La facultad y la jornada salen del registro del estudiante; solo se piden si faltan.
        return [
            'tipo_servicio' => ['required', Rule::in(SolicitudAtencion::SERVICIOS)],
            'id_facultad' => [Rule::excludeIf($registrados['facultad'] !== null), 'required', 'integer', Rule::in(Facultad::activas()->pluck('id_facultad')->all())],
            'jornada' => [Rule::excludeIf($registrados['jornada'] !== null), 'required', Rule::in(SolicitudAtencion::JORNADAS)],
            'franja_horaria' => ['required', Rule::in(SolicitudAtencion::FRANJAS)],
            'id_version' => ['nullable', 'integer'],
            'acepta_consentimiento' => ['nullable'],
            // Opcional: caso de emergencia (atención urgente).
            'es_emergencia' => ['nullable', 'boolean'],
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

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->user()->persona) {
                    $validator->errors()->add('tipo_servicio', 'Tu cuenta no tiene datos personales registrados, por eso no puedes solicitar citas. Comunícate con Bienestar Universitario.');

                    return;
                }

                if (! $validator->errors()->has('tipo_servicio')) {
                    $this->validarCasoEnCurso($validator);
                    $this->validarConsentimiento($validator);
                }
            },
        ];
    }

    /**
     * Un estudiante tiene a lo sumo un caso sin cerrar por servicio: si ya tiene uno,
     * sus citas se gestionan dentro de ese caso.
     */
    private function validarCasoEnCurso(Validator $validator): void
    {
        $caso = $this->user()->persona->casos()
            ->where('solicitudes_atencion.tipo_servicio', $this->input('tipo_servicio'))
            ->where('casos.estado_caso', '!=', Caso::ESTADO_CERRADO)
            ->first();

        if (! $caso) {
            return;
        }

        $validator->errors()->add('tipo_servicio', $caso->estado_caso === Caso::ESTADO_PENDIENTE
            ? "Ya tienes una solicitud de {$this->input('tipo_servicio')} en espera ({$caso->codigo_expediente}). Bienestar te contactará para confirmar tu cita."
            : "Ya tienes un caso de {$this->input('tipo_servicio')} en atención ({$caso->codigo_expediente}). Tu profesional agenda las próximas citas contigo.");
    }

    /**
     * Si el servicio tiene consentimiento vigente, el estudiante debe aceptarlo, y debe
     * ser la misma versión que se le mostró en el formulario.
     */
    private function validarConsentimiento(Validator $validator): void
    {
        $vigente = $this->versionConsentimiento();

        if (! $vigente) {
            return;
        }

        if ((int) $this->input('id_version') !== $vigente->id_version) {
            $validator->errors()->add('acepta_consentimiento', "El consentimiento de {$this->input('tipo_servicio')} se actualizó mientras llenabas la solicitud. Lee la versión {$vigente->numero_version} y acéptala de nuevo.");
        } elseif (! $this->boolean('acepta_consentimiento')) {
            $validator->errors()->add('acepta_consentimiento', 'Para enviar la solicitud debes leer y aceptar el consentimiento informado.');
        }
    }

    public function attributes(): array
    {
        return [
            'tipo_servicio' => 'servicio',
            'id_facultad' => 'facultad',
            'jornada' => 'jornada',
            'franja_horaria' => 'franja horaria',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_servicio.required' => 'Selecciona el servicio que necesitas.',
            'tipo_servicio.in' => 'Selecciona uno de los servicios de la lista.',
            'id_facultad.required' => 'Selecciona tu facultad.',
            'id_facultad.integer' => 'Selecciona una facultad de la lista.',
            'id_facultad.in' => 'Selecciona una facultad de la lista.',
            'jornada.required' => 'Selecciona tu jornada.',
            'jornada.in' => 'Selecciona la jornada diurna o nocturna.',
            'franja_horaria.required' => 'Selecciona la franja horaria en la que puedes asistir.',
            'franja_horaria.in' => 'Selecciona mañana, tarde, noche o todas.',
        ];
    }
}
