<?php

namespace App\Http\Requests\Inclusion;

use App\Documentos\ConsentimientoInclusion;
use App\Documentos\TextoConsentimiento;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\Facultad;
use App\Models\VersionConsentimiento;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Datos del formato de consentimiento de Inclusión, comunes al envío en línea y al registro en
 * papel. Las casillas son independientes: solo se guarda lo que se marcó (RN-08).
 */
trait ValidaDatosConsentimiento
{
    private ?VersionConsentimiento $version = null;

    protected function prepareForValidation(): void
    {
        $campos = ['nombre_persona', 'documento_persona', 'vinculacion', 'facultad', 'programa_academico', 'semestre', 'dependencia',
            'acudiente_nombre', 'acudiente_numero_documento', 'acudiente_parentesco', 'motivo_papel'];

        $this->merge(collect($campos)->mapWithKeys(fn (string $campo) => [$campo => trim((string) $this->input($campo)) ?: null])->all() + [
            'numero_contacto' => preg_replace('/[\s-]/', '', (string) $this->input('numero_contacto')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasDatos(): array
    {
        $soloEstudiante = Rule::excludeIf(! ConsentimientoInclusion::esEstudiante($this->input('vinculacion')));
        $soloMenor = Rule::excludeIf(! $this->esMenor());

        return [
            'nombre_persona' => ['required', 'string', 'max:150'],
            'documento_persona' => ['required', 'string', 'max:20'],
            'numero_contacto' => ['required', 'regex:/^\d{7,15}$/'],
            'vinculacion' => ['required', Rule::in(array_keys(ConsentimientoInclusion::VINCULACIONES))],
            'facultad' => [$soloEstudiante, 'required', Rule::in(Facultad::activas()->pluck('nombre')->all())],
            'programa_academico' => [$soloEstudiante, 'required', 'string', 'max:150'],
            'semestre' => [$soloEstudiante, 'required', 'integer', 'between:1,'.ConsentimientoAceptacion::SEMESTRE_MAX],
            'dependencia' => ['nullable', 'string', 'max:150'],
            'acudiente_nombre' => [$soloMenor, 'required', 'string', 'max:150'],
            'acudiente_tipo_documento' => [$soloMenor, 'required', Rule::in(ConsentimientoAceptacion::TIPOS_DOCUMENTO_ACUDIENTE)],
            'acudiente_numero_documento' => [$soloMenor, 'required', 'string', 'max:20'],
            'acudiente_parentesco' => [$soloMenor, 'required', 'string', 'max:50'],
        ] + array_fill_keys(array_keys(ConsentimientoInclusion::DECISIONES), ['nullable', 'boolean']);
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDatos(): array
    {
        return [
            'nombre_persona.required' => 'Escribe el nombre de la persona.',
            'documento_persona.required' => 'Escribe el número de documento.',
            'numero_contacto.required' => 'Escribe un número de contacto.',
            'numero_contacto.regex' => 'El número de contacto debe tener entre 7 y 15 dígitos.',
            'vinculacion.required' => 'Selecciona la vinculación con la universidad.',
            'vinculacion.in' => 'Selecciona una vinculación de la lista.',
            'facultad.required' => 'Selecciona la facultad.',
            'facultad.in' => 'Selecciona una facultad de la lista.',
            'programa_academico.required' => 'Escribe el programa académico.',
            'semestre.required' => 'Indica el semestre.',
            'semestre.integer' => 'El semestre debe ser un número entre 1 y '.ConsentimientoAceptacion::SEMESTRE_MAX.'.',
            'semestre.between' => 'El semestre debe ser un número entre 1 y '.ConsentimientoAceptacion::SEMESTRE_MAX.'.',
            'acudiente_nombre.required' => 'Escribe el nombre del acudiente.',
            'acudiente_tipo_documento.required' => 'Selecciona el tipo de documento del acudiente.',
            'acudiente_tipo_documento.in' => 'Selecciona el tipo de documento del acudiente.',
            'acudiente_numero_documento.required' => 'Escribe el número de documento del acudiente.',
            'acudiente_parentesco.required' => 'Escribe el parentesco del acudiente.',
            'max' => 'El campo es demasiado largo.',
        ];
    }

    /**
     * Facultad y dependencia comparten una columna de 150 caracteres.
     */
    protected function validarDependencia(Validator $validator): void
    {
        if (! $validator->errors()->hasAny(['facultad', 'dependencia']) && mb_strlen($this->dependenciaFacultad()) > 150) {
            $validator->errors()->add('dependencia', 'La dependencia es demasiado larga para guardarla junto con la facultad.');
        }
    }

    public function caso(): Caso
    {
        return $this->route('caso');
    }

    public function esMenor(): bool
    {
        return $this->caso()->solicitud?->persona?->esMenorEn(now(Actuacion::ZONA_HORARIA)) === true;
    }

    public function versionVigente(): ?VersionConsentimiento
    {
        return $this->version ??= ConsentimientoInclusion::versionDe($this->caso());
    }

    /**
     * Columnas de consentimiento_aceptaciones que salen del formulario, con el texto aceptado.
     *
     * @return array<string, mixed>
     */
    public function columnasAceptacion(): array
    {
        $datos = $this->validated();
        $texto = $this->textoAceptado();

        return [
            'id_version' => $this->versionVigente()->id_version,
            'otorgante' => $this->esMenor() ? ConsentimientoAceptacion::OTORGANTE_ACUDIENTE : ConsentimientoAceptacion::OTORGANTE_ESTUDIANTE,
            'acudiente_nombre' => $datos['acudiente_nombre'] ?? null,
            'acudiente_tipo_documento' => $datos['acudiente_tipo_documento'] ?? null,
            'acudiente_numero_documento' => $datos['acudiente_numero_documento'] ?? null,
            'acudiente_parentesco' => $datos['acudiente_parentesco'] ?? null,
            'vinculacion' => ConsentimientoInclusion::VINCULACIONES[$datos['vinculacion']],
            'programa_academico' => $datos['programa_academico'] ?? null,
            'semestre' => $datos['semestre'] ?? null,
            'dependencia_facultad' => $this->dependenciaFacultad(),
            'texto_presentado' => $texto,
            'texto_sha256' => hash('sha256', $texto),
        ];
    }

    /**
     * Columnas de inclusion_consentimientos que salen del formulario.
     *
     * @return array<string, mixed>
     */
    public function columnasDetalle(): array
    {
        return $this->decisiones() + [
            'nombre_persona' => $this->validated('nombre_persona'),
            'documento_persona' => $this->validated('documento_persona'),
            'numero_contacto' => $this->validated('numero_contacto'),
            'vinculacion_detalle' => $this->validated('vinculacion'),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function decisiones(): array
    {
        return collect(ConsentimientoInclusion::DECISIONES)->map(fn (string $texto, string $campo) => $this->boolean($campo))->all();
    }

    private function textoAceptado(): string
    {
        $caso = $this->caso();
        $datos = $this->validated();

        return TextoConsentimiento::renderizar($this->versionVigente()->texto, [
            'nombre_estudiante' => $datos['nombre_persona'],
            'documento_estudiante' => $datos['documento_persona'],
            'nombre_acudiente' => $datos['acudiente_nombre'] ?? null,
            'documento_acudiente' => $datos['acudiente_numero_documento'] ?? null,
            'parentesco_acudiente' => $datos['acudiente_parentesco'] ?? null,
            'decisiones' => ConsentimientoInclusion::textoDecisiones($this->decisiones()),
        ] + TextoConsentimiento::datosBase($caso, $caso->profesional, now(Actuacion::ZONA_HORARIA)));
    }

    private function dependenciaFacultad(): string
    {
        $estudiante = ConsentimientoInclusion::esEstudiante($this->input('vinculacion'));
        $dependencia = strcasecmp((string) $this->input('dependencia'), 'N/A') === 0 ? null : $this->input('dependencia');

        return collect([$estudiante ? $this->input('facultad') : null, $dependencia])->filter()->implode(' · ') ?: 'N/A';
    }
}
