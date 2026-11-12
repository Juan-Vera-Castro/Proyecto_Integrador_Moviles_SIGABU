{{-- Datos del formato: necesita x-data con "campos" y "error(campo)". Variables: $menor, $facultades. --}}
@php
    use App\Documentos\ConsentimientoInclusion;
    use App\Models\ConsentimientoAceptacion;

    $control = 'block w-full rounded-lg border-gray-300 shadow-sm text-base sm:text-sm text-gray-900 focus:border-[#007F2F] focus:ring-[#007F2F]';
    $etiqueta = 'block text-sm font-medium text-gray-700 mb-1';
    $estudiante = "(campos.vinculacion || '').startsWith('Estudiante')";
@endphp

<section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-4" aria-labelledby="datos-titulo">
    <div>
        <h2 id="datos-titulo" class="text-base font-bold text-gray-900">Datos de la persona</h2>
        <p class="text-xs text-gray-500 mt-0.5">Corrige lo que no esté bien. Todos los campos son obligatorios, salvo la dependencia.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="nombre_persona" class="{{ $etiqueta }}">Nombre de la persona</label>
            <input type="text" id="nombre_persona" name="nombre_persona" x-model="campos.nombre_persona" maxlength="150" autocomplete="off" aria-required="true" aria-describedby="nombre_persona-error" class="{{ $control }}">
            @include('inclusion.consentimiento.partials.error', ['campo' => 'nombre_persona'])
        </div>
        <div>
            <label for="documento_persona" class="{{ $etiqueta }}">Documento de identidad</label>
            <input type="text" id="documento_persona" name="documento_persona" x-model="campos.documento_persona" maxlength="20" autocomplete="off" aria-required="true" aria-describedby="documento_persona-error" class="{{ $control }}">
            @include('inclusion.consentimiento.partials.error', ['campo' => 'documento_persona'])
        </div>
        <div>
            <label for="numero_contacto" class="{{ $etiqueta }}">Número de contacto</label>
            <input type="tel" id="numero_contacto" name="numero_contacto" x-model="campos.numero_contacto" maxlength="20" inputmode="tel" autocomplete="off" aria-required="true" aria-describedby="numero_contacto-error" class="{{ $control }}">
            @include('inclusion.consentimiento.partials.error', ['campo' => 'numero_contacto'])
        </div>
        <div>
            <label for="vinculacion" class="{{ $etiqueta }}">Vinculación</label>
            <select id="vinculacion" name="vinculacion" x-model="campos.vinculacion" aria-required="true" aria-describedby="vinculacion-error" class="{{ $control }}">
                <option value="">Selecciona…</option>
                @foreach (array_keys(ConsentimientoInclusion::VINCULACIONES) as $opcion)
                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                @endforeach
            </select>
            @include('inclusion.consentimiento.partials.error', ['campo' => 'vinculacion'])
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-6 gap-4" x-show="{{ $estudiante }}" style="display: none;">
        <div class="sm:col-span-3">
            <label for="facultad" class="{{ $etiqueta }}">Facultad</label>
            <select id="facultad" name="facultad" x-model="campos.facultad" aria-required="true" aria-describedby="facultad-error" class="{{ $control }}">
                <option value="">Selecciona…</option>
                @foreach ($facultades as $facultad)
                    <option value="{{ $facultad }}">{{ $facultad }}</option>
                @endforeach
            </select>
            @include('inclusion.consentimiento.partials.error', ['campo' => 'facultad'])
        </div>
        <div class="sm:col-span-2">
            <label for="programa_academico" class="{{ $etiqueta }}">Programa</label>
            <input type="text" id="programa_academico" name="programa_academico" x-model="campos.programa_academico" maxlength="150" autocomplete="off" aria-required="true" aria-describedby="programa_academico-error" class="{{ $control }}">
            @include('inclusion.consentimiento.partials.error', ['campo' => 'programa_academico'])
        </div>
        <div>
            <label for="semestre" class="{{ $etiqueta }}">Semestre</label>
            <input type="number" id="semestre" name="semestre" x-model="campos.semestre" min="1" max="{{ ConsentimientoAceptacion::SEMESTRE_MAX }}" inputmode="numeric" aria-required="true" aria-describedby="semestre-error" class="{{ $control }}">
            @include('inclusion.consentimiento.partials.error', ['campo' => 'semestre'])
        </div>
    </div>

    <div>
        <label for="dependencia" class="{{ $etiqueta }}">Dependencia (opcional)</label>
        <input type="text" id="dependencia" name="dependencia" x-model="campos.dependencia" maxlength="150" placeholder="N/A" autocomplete="off" aria-describedby="dependencia-error" class="{{ $control }}">
        @include('inclusion.consentimiento.partials.error', ['campo' => 'dependencia'])
    </div>
</section>

@if ($menor)
    <section class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-gray-100 space-y-4" aria-labelledby="acudiente-titulo">
        <div>
            <h2 id="acudiente-titulo" class="text-base font-bold text-gray-900">Datos del acudiente</h2>
            <p class="text-xs text-gray-500 mt-0.5">La persona es menor de edad: el consentimiento lo otorga y firma su madre, padre o acudiente.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label for="acudiente_nombre" class="{{ $etiqueta }}">Nombre completo del acudiente</label>
                <input type="text" id="acudiente_nombre" name="acudiente_nombre" x-model="campos.acudiente_nombre" maxlength="150" autocomplete="off" aria-required="true" aria-describedby="acudiente_nombre-error" class="{{ $control }}">
                @include('inclusion.consentimiento.partials.error', ['campo' => 'acudiente_nombre'])
            </div>
            <div>
                <label for="acudiente_tipo_documento" class="{{ $etiqueta }}">Tipo de documento</label>
                <select id="acudiente_tipo_documento" name="acudiente_tipo_documento" x-model="campos.acudiente_tipo_documento" aria-required="true" aria-describedby="acudiente_tipo_documento-error" class="{{ $control }}">
                    <option value="">Selecciona…</option>
                    @foreach (ConsentimientoAceptacion::TIPOS_DOCUMENTO_ACUDIENTE as $tipo)
                        <option value="{{ $tipo }}">{{ $tipo === 'PA' ? 'Pasaporte' : $tipo }}</option>
                    @endforeach
                </select>
                @include('inclusion.consentimiento.partials.error', ['campo' => 'acudiente_tipo_documento'])
            </div>
            <div>
                <label for="acudiente_numero_documento" class="{{ $etiqueta }}">Número de documento</label>
                <input type="text" id="acudiente_numero_documento" name="acudiente_numero_documento" x-model="campos.acudiente_numero_documento" maxlength="20" autocomplete="off" aria-required="true" aria-describedby="acudiente_numero_documento-error" class="{{ $control }}">
                @include('inclusion.consentimiento.partials.error', ['campo' => 'acudiente_numero_documento'])
            </div>
            <div class="sm:col-span-2">
                <label for="acudiente_parentesco" class="{{ $etiqueta }}">Parentesco con la persona</label>
                <input type="text" id="acudiente_parentesco" name="acudiente_parentesco" x-model="campos.acudiente_parentesco" maxlength="50" placeholder="Ej. Madre, padre, tío" autocomplete="off" aria-required="true" aria-describedby="acudiente_parentesco-error" class="{{ $control }}">
                @include('inclusion.consentimiento.partials.error', ['campo' => 'acudiente_parentesco'])
            </div>
        </div>
    </section>
@endif
