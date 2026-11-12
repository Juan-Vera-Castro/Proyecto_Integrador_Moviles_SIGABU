<?php

namespace App\Http\Requests;

use App\Documentos\FirmaPng;
use App\Models\Actuacion;
use App\Models\Caso;
use App\Models\ConsentimientoAceptacion;
use App\Models\ModeloConsentimiento;
use App\Models\VersionConsentimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Decisión sobre el consentimiento informado de un caso (HU-CI-01, HU-CI-02, HU-CI-05):
 * la aceptación exige la firma táctil y los datos que llenan el texto (D-25).
 */
class RegistrarConsentimientoRequest extends FormRequest
{
    public const FIRMA_PREFIJO = 'data:image/png;base64,';

    public const FIRMA_MAX_BYTES = 262144;

    /**
     * Píxeles con trazo (muestreados cada 2 px) por debajo de los cuales la firma se
     * considera vacía o un simple punto.
     */
    public const FIRMA_MIN_TRAZO = 50;

    private ?string $firmaPng = null;

    private ?string $firmaEstudiantePng = null;

    private ?VersionConsentimiento $version = null;

    protected $errorBag = 'consentimiento';

    /**
     * La firma no vuelve a la sesión si hay errores: es pesada y es un dato sensible.
     */
    protected $dontFlash = ['firma', 'firma_estudiante'];

    /**
     * Solo el estudiante dueño del caso registra su decisión, desde su cuenta.
     */
    public function authorize(): bool
    {
        return (bool) $this->caso()?->esDelEstudiante($this->user());
    }

    protected function prepareForValidation(): void
    {
        $campos = ['lugar_expedicion', 'eps_ips', 'acudiente_nombre', 'acudiente_numero_documento', 'acudiente_parentesco', 'acudiente_lugar_expedicion',
            'vinculacion', 'programa_academico', 'semestre', 'dependencia_facultad'];

        $this->merge(collect($campos)->mapWithKeys(fn (string $campo) => [$campo => trim((string) $this->input($campo)) ?: null])->all());

        $this->merge(['acudiente_telefono' => preg_replace('/[\s().-]/', '', (string) $this->input('acudiente_telefono')) ?: null]);

        // Contactos de urgencia: se descartan las filas vacías y el teléfono queda solo con dígitos.
        $contactos = collect(is_array($this->input('contactos')) ? $this->input('contactos') : [])
            ->filter(fn ($fila) => is_array($fila))
            ->map(fn (array $fila) => [
                'nombre' => trim((string) ($fila['nombre'] ?? '')) ?: null,
                'telefono' => preg_replace('/[\s().-]/', '', (string) ($fila['telefono'] ?? '')) ?: null,
                'parentesco' => trim((string) ($fila['parentesco'] ?? '')) ?: null,
            ])
            ->reject(fn (array $fila) => $fila['nombre'] === null && $fila['telefono'] === null && $fila['parentesco'] === null)
            ->values()
            ->all();

        // El resumen que llena el texto lo arma el servidor, no el navegador.
        $this->merge([
            'contactos' => $contactos,
            'contacto_urgencia' => ConsentimientoAceptacion::resumirContactosUrgencia($contactos),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $acepta = $this->input('decision') === ConsentimientoAceptacion::ACEPTADO;
        $acudiente = $this->esMenor();
        $soloSiAcepta = fn (bool $ademas = true) => Rule::excludeIf(! $acepta || ! $ademas);
        $acudienteRequerido = $acepta && $acudiente ? 'required' : 'nullable';
        // Encabezado del formato: programa y semestre solo se piden a los estudiantes.
        $esEstudiante = $this->input('vinculacion') === ConsentimientoAceptacion::VINCULACION_ESTUDIANTE;

        return [
            'vinculacion' => [$acepta ? 'required' : 'nullable', Rule::in(ConsentimientoAceptacion::VINCULACIONES)],
            'programa_academico' => [$acepta && $esEstudiante ? 'required' : 'nullable', 'string', 'max:150'],
            'semestre' => [$acepta && $esEstudiante ? 'required' : 'nullable', 'integer', 'between:1,'.ConsentimientoAceptacion::SEMESTRE_MAX],
            'dependencia_facultad' => [$acepta ? 'required' : 'nullable', 'string', 'max:150'],
            'decision' => ['required', Rule::in([ConsentimientoAceptacion::ACEPTADO, ConsentimientoAceptacion::RECHAZADO])],
            'id_version' => ['required', 'integer'],
            'firma' => [$soloSiAcepta(), 'required', 'string', 'starts_with:'.self::FIRMA_PREFIJO],
            'lugar_expedicion' => [$soloSiAcepta(! $acudiente), 'required', 'string', 'max:100'],
            'eps_ips' => [$soloSiAcepta(), 'required', 'string', 'max:150'],
            // Al menos una persona a quien avisar en una urgencia, con nombre, teléfono y parentesco.
            'contactos' => [$soloSiAcepta(! $acudiente), 'required', 'array', 'min:1', 'max:'.ConsentimientoAceptacion::MAX_CONTACTOS_URGENCIA],
            'contactos.*.nombre' => [$soloSiAcepta(! $acudiente), 'required', 'string', 'max:100'],
            'contactos.*.telefono' => [$soloSiAcepta(! $acudiente), 'required', 'regex:/^\+?\d{7,15}$/'],
            'contactos.*.parentesco' => [$soloSiAcepta(! $acudiente), 'required', Rule::in(ConsentimientoAceptacion::PARENTESCOS_URGENCIA)],
            // Resumen armado en prepareForValidation; existe siempre que "contactos" sea válido.
            'contacto_urgencia' => [$soloSiAcepta(! $acudiente), 'nullable', 'string', 'max:500'],
            'acudiente_nombre' => [Rule::excludeIf(! $acudiente), $acudienteRequerido, 'string', 'max:150'],
            'acudiente_tipo_documento' => [Rule::excludeIf(! $acudiente), $acudienteRequerido, Rule::in(ConsentimientoAceptacion::TIPOS_DOCUMENTO_ACUDIENTE)],
            'acudiente_numero_documento' => [Rule::excludeIf(! $acudiente), $acudienteRequerido, 'string', 'max:20'],
            'acudiente_parentesco' => [Rule::excludeIf(! $acudiente), $acudienteRequerido, 'string', 'max:50'],
            'acudiente_lugar_expedicion' => [Rule::excludeIf(! $acudiente), $acudienteRequerido, 'string', 'max:100'],
            'acudiente_telefono' => [Rule::excludeIf(! $acudiente), $acudienteRequerido, 'regex:/^\+?\d{7,15}$/'],
            // Menor de edad: firma el acudiente (firma) y también el propio estudiante.
            'firma_estudiante' => [$soloSiAcepta($acudiente), 'required', 'string', 'starts_with:'.self::FIRMA_PREFIJO],
        ];
    }

    /**
     * La versión debe ser la vigente que se mostró, y la firma un PNG razonable y con trazo.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->has('id_version')) {
                    $this->validarVersion($validator);
                }

                if ($this->input('decision') === ConsentimientoAceptacion::ACEPTADO && ! $validator->errors()->has('firma')) {
                    $this->firmaPng = $this->validarFirma($validator, 'firma');
                }

                if ($this->input('decision') === ConsentimientoAceptacion::ACEPTADO && $this->esMenor() && ! $validator->errors()->has('firma_estudiante')) {
                    $this->firmaEstudiantePng = $this->validarFirma($validator, 'firma_estudiante');
                }
            },
        ];
    }

    private function validarVersion(Validator $validator): void
    {
        $vigente = $this->versionVigente();

        if (! $vigente) {
            $validator->errors()->add('id_version', 'No hay un consentimiento publicado para este caso. Pide al administrador que lo publique.');
        } elseif ((int) $this->input('id_version') !== $vigente->id_version) {
            $validator->errors()->add('id_version', "El consentimiento se actualizó mientras lo leías. Vuelve a cargar la página y lee la versión {$vigente->numero_version}.");
        }
    }

    private function validarFirma(Validator $validator, string $campo): ?string
    {
        [$png, $error] = self::leerFirma((string) $this->input($campo));

        if ($error) {
            $validator->errors()->add($campo, $error);
        }

        return $png;
    }

    /**
     * Lee la firma que exporta el lienzo (data URL de un PNG): devuelve sus bytes, o el
     * error que se le muestra a quien firma. También la usa la firma del profesional.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function leerFirma(string $dataUrl): array
    {
        $png = base64_decode(substr($dataUrl, strlen(self::FIRMA_PREFIJO)), true);
        $info = $png === false ? false : @getimagesizefromstring($png);

        $error = match (true) {
            ! str_starts_with($dataUrl, self::FIRMA_PREFIJO), $png === false, $info === false, $info[2] !== IMAGETYPE_PNG => 'La firma no se pudo leer. Bórrala y vuelve a firmar.',
            strlen($png) > self::FIRMA_MAX_BYTES => 'La imagen de la firma es demasiado grande. Bórrala y vuelve a firmar.',
            $info[0] < 200 || $info[1] < 80 || $info[0] > 2000 || $info[1] > 1000 => 'La imagen de la firma no tiene un tamaño válido. Bórrala y vuelve a firmar.',
            FirmaPng::pixelesConTrazo($png) < self::FIRMA_MIN_TRAZO => 'La firma está vacía o es muy corta. Bórrala y vuelve a firmar.',
            default => null,
        };

        return $error ? [null, $error] : [$png, null];
    }

    public function caso(): ?Caso
    {
        return $this->route('caso');
    }

    /**
     * Si el estudiante es menor de edad hoy: entonces otorga el consentimiento su acudiente.
     */
    public function esMenor(): bool
    {
        return (bool) $this->caso()?->solicitud?->persona?->esMenorEn(now(Actuacion::ZONA_HORARIA));
    }

    public function poblacion(): string
    {
        return $this->esMenor() ? ModeloConsentimiento::POBLACION_MENORES : ModeloConsentimiento::POBLACION_GENERAL;
    }

    public function versionVigente(): ?VersionConsentimiento
    {
        return $this->version ??= ModeloConsentimiento::versionVigenteDe((string) $this->caso()?->solicitud?->tipo_servicio, $this->poblacion());
    }

    /**
     * Bytes del PNG ya validado (solo en aceptaciones).
     */
    public function firmaPng(): ?string
    {
        return $this->firmaPng;
    }

    /**
     * Bytes del PNG de la firma del estudiante menor de edad (solo en aceptaciones de menores).
     */
    public function firmaEstudiantePng(): ?string
    {
        return $this->firmaEstudiantePng;
    }

    /**
     * Si los datos del acudiente vienen completos: así un rechazo de un menor queda a su nombre.
     */
    public function otorgaAcudiente(): bool
    {
        return $this->esMenor() && collect(['acudiente_nombre', 'acudiente_tipo_documento', 'acudiente_numero_documento', 'acudiente_parentesco'])
            ->every(fn (string $campo) => filled($this->validated($campo)));
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Indica si aceptas o no el consentimiento.',
            'decision.in' => 'Indica si aceptas o no el consentimiento.',
            'id_version.required' => 'Vuelve a cargar la página: falta la versión del consentimiento.',
            'firma.required' => 'Falta la firma. Firma dentro del recuadro.',
            'firma.starts_with' => 'La firma no se pudo leer. Bórrala y vuelve a firmar.',
            'lugar_expedicion.required' => 'Escribe el lugar de expedición del documento.',
            'eps_ips.required' => 'Escribe la EPS o IPS del estudiante.',
            'contactos.required' => 'Agrega al menos una persona a quien avisar en caso de urgencia.',
            'contactos.min' => 'Agrega al menos una persona a quien avisar en caso de urgencia.',
            'contactos.max' => 'Puedes agregar hasta '.ConsentimientoAceptacion::MAX_CONTACTOS_URGENCIA.' personas para avisar en caso de urgencia.',
            'contactos.*.nombre.required' => 'Escribe el nombre de la persona.',
            'contactos.*.nombre.max' => 'El nombre es muy largo (máximo 100 caracteres).',
            'contactos.*.telefono.required' => 'Escribe el teléfono de la persona.',
            'contactos.*.telefono.regex' => 'Escribe un teléfono válido: solo números, entre 7 y 15 dígitos.',
            'contactos.*.parentesco.required' => 'Selecciona el parentesco.',
            'contactos.*.parentesco.in' => 'Selecciona un parentesco de la lista.',
            'acudiente_nombre.required' => 'Escribe el nombre del acudiente que firma.',
            'acudiente_tipo_documento.required' => 'Selecciona el tipo de documento del acudiente.',
            'acudiente_tipo_documento.in' => 'Selecciona el tipo de documento del acudiente (CC, CE, TI o pasaporte).',
            'acudiente_numero_documento.required' => 'Escribe el número de documento del acudiente.',
            'acudiente_parentesco.required' => 'Escribe el parentesco del acudiente con el estudiante.',
            'acudiente_telefono.required' => 'Escribe el teléfono del acudiente.',
            'acudiente_telefono.regex' => 'Escribe un teléfono válido para el acudiente: solo números, entre 7 y 15 dígitos.',
            'firma_estudiante.required' => 'Falta la firma del estudiante. Firma dentro de su recuadro.',
            'firma_estudiante.starts_with' => 'La firma del estudiante no se pudo leer. Bórrala y vuelve a firmar.',
            'acudiente_lugar_expedicion.required' => 'Escribe el lugar de expedición del documento del acudiente.',
            'vinculacion.required' => 'Selecciona la vinculación del estudiante con la universidad.',
            'vinculacion.in' => 'Selecciona una vinculación de la lista.',
            'programa_academico.required' => 'Escribe el programa académico.',
            'semestre.required' => 'Indica el semestre que cursa.',
            'semestre.integer' => 'El semestre debe ser un número entre 1 y '.ConsentimientoAceptacion::SEMESTRE_MAX.'.',
            'semestre.between' => 'El semestre debe ser un número entre 1 y '.ConsentimientoAceptacion::SEMESTRE_MAX.'.',
            'dependencia_facultad.required' => 'Escribe la dependencia o facultad.',
            'max' => 'El campo es demasiado largo.',
        ];
    }
}
