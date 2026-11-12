import 'package:flutter/material.dart';

import '../api/api.dart';
import '../theme.dart';
import '../utils/formato.dart';
import '../widgets/comunes.dart';
import '../widgets/firma.dart';
import 'consentimiento_inclusion.dart';

/// Consentimiento informado con firma táctil (consentimientos/firmar.blade.php, RQ-1). Lo firma
/// el estudiante o, si es menor de edad, su acudiente y también el estudiante. Si el caso es
/// de Inclusión, se abre su propio formato.
class PantallaConsentimiento extends StatelessWidget {
  const PantallaConsentimiento({super.key, required this.idCaso});

  final String idCaso;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Cargador<Map<String, dynamic>>(
        cargar: () => api.get('/casos/$idCaso/consentimiento'),
        construir: (context, datos, _) {
          if (datos['formato'] == 'inclusion') {
            return PantallaConsentimientoInclusion(idCaso: idCaso);
          }
          return _Formulario(idCaso: idCaso, datos: datos);
        },
      ),
    );
  }
}

class _Contacto {
  final nombre = TextEditingController();
  final telefono = TextEditingController();
  String? parentesco;

  Map<String, String?> get json => {'nombre': nombre.text, 'telefono': telefono.text, 'parentesco': parentesco};

  String? get resumen {
    final n = nombre.text.trim();
    final t = telefono.text.replaceAll(RegExp(r'[\s().-]'), '');
    final p = (parentesco ?? '').trim();
    if (n.isEmpty && t.isEmpty && p.isEmpty) return null;
    return '$n${p.isNotEmpty ? ' ($p)' : ''}${t.isNotEmpty ? ' - Tel. $t' : ''}'.trim();
  }
}

class _Formulario extends StatefulWidget {
  const _Formulario({required this.idCaso, required this.datos});

  final String idCaso;
  final Map<String, dynamic> datos;

  @override
  State<_Formulario> createState() => _FormularioState();
}

class _FormularioState extends State<_Formulario> {
  static const _etiquetas = {
    'lugar_expedicion': ('Lugar de expedición del documento del estudiante', 'Ej. Tuluá'),
    'eps_ips': ('EPS o IPS del estudiante', 'Ej. Nueva EPS'),
    'acudiente_nombre': ('Nombre completo del acudiente que firma', ''),
    'acudiente_telefono': ('Teléfono del acudiente', 'Ej. 3001234567'),
    'acudiente_numero_documento': ('Número de cédula del acudiente', ''),
    'acudiente_parentesco': ('Parentesco con el estudiante', 'Ej. Madre, padre, tío'),
    'acudiente_lugar_expedicion': ('Lugar de expedición del documento del acudiente', 'Ej. Tuluá'),
  };

  late final bool _menor = widget.datos['menor'] == true;
  late final Map<String, dynamic> _opciones = widget.datos['opciones'] as Map<String, dynamic>;
  late final TextoConsentimiento _texto = TextoConsentimiento(widget.datos['texto'] as Map<String, dynamic>);
  late final List<String> _nombresCampos = _menor
      ? ['acudiente_nombre', 'acudiente_telefono', 'acudiente_tipo_documento', 'acudiente_numero_documento', 'acudiente_lugar_expedicion', 'acudiente_parentesco', 'eps_ips']
      : ['lugar_expedicion', 'eps_ips'];

  final Map<String, TextEditingController> _campos = {};
  String _tipoDocumento = 'CC';
  final List<_Contacto> _contactos = [_Contacto()];
  late String _vinculacion = widget.datos['vinculacion_sugerida'] as String? ?? '';
  late final _programa = TextEditingController(text: (widget.datos['vinculacion'] as Map?)?['programa']?.toString() ?? '');
  late final _semestre = TextEditingController(text: (widget.datos['vinculacion'] as Map?)?['semestre']?.toString() ?? '');
  late final _dependencia = TextEditingController(text: (widget.datos['vinculacion'] as Map?)?['facultad']?.toString() ?? '');
  final _firma = FirmaController();
  final _firmaEstudiante = FirmaController();
  bool _enviando = false;
  ApiException? _error;

  @override
  void initState() {
    super.initState();
    for (final nombre in _nombresCampos.where((n) => n != 'acudiente_tipo_documento')) {
      _campos[nombre] = TextEditingController()..addListener(_refrescar);
    }
    _contactos.first.nombre.addListener(_refrescar);
    _contactos.first.telefono.addListener(_refrescar);
    _firma.addListener(_refrescar);
    _firmaEstudiante.addListener(_refrescar);
  }

  void _refrescar() => setState(() {});

  bool get _esEstudiante => _vinculacion == _opciones['vinculacion_estudiante'];

  Map<String, String?> get _valores => {
        for (final e in _campos.entries) e.key: e.value.text,
        if (_menor) 'acudiente_tipo_documento': _tipoDocumento,
        if (!_menor) 'contacto_urgencia': _contactos.map((c) => c.resumen).whereType<String>().join('; '),
      };

  bool get _firmasCompletas => _firma.suficiente && (!_menor || _firmaEstudiante.suficiente);

  Future<void> _enviar(String decision) async {
    final acepta = decision == 'Aceptado';
    if (!acepta) {
      final pregunta = _menor
          ? '¿Confirmas que el acudiente NO acepta el consentimiento informado? Quedará registrado y no se podrá prestar la atención.'
          : '¿Confirmas que NO aceptas el consentimiento informado? Quedará registrado y no se podrá prestar la atención.';
      if (!await confirmar(context, pregunta, si: 'No acepto', peligro: true)) return;
    }

    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final cuerpo = <String, dynamic>{
        'decision': decision,
        'id_version': (widget.datos['version'] as Map)['id'],
        'vinculacion': _vinculacion,
        'programa_academico': _esEstudiante ? _programa.text : null,
        'semestre': _esEstudiante ? int.tryParse(_semestre.text) : null,
        'dependencia_facultad': _dependencia.text,
        ..._valores..remove('contacto_urgencia'),
        if (!_menor) 'contactos': _contactos.map((c) => c.json).toList(),
        if (acepta) 'firma': await _firma.exportar(),
        if (acepta && _menor) 'firma_estudiante': await _firmaEstudiante.exportar(),
      };
      final respuesta = await api.post('/casos/${widget.idCaso}/consentimiento', cuerpo);
      if (!mounted) return;
      mostrarMensaje(context, respuesta['mensaje']?.toString() ?? 'Consentimiento registrado.');
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.datos;
    final caso = d['caso'] as Map<String, dynamic>;
    final version = d['version'] as Map<String, dynamic>?;
    final listo = d['almacenamiento_listo'] == true;

    return Scaffold(
      appBar: AppBar(
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Consentimiento informado'),
          Text('Caso ${caso['codigo']} · ${caso['servicio']}', style: const TextStyle(fontSize: 13, color: Colores.gris, fontWeight: FontWeight.w500)),
        ]),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (d['faltan_datos'] == true)
            Aviso('Aún no puedes firmar', tipo: TipoAviso.alerta, hijos: [
              const SizedBox(height: 6),
              Text('Antes de firmar, tu profesional (${d['profesional']}) debe registrar tu tipo de documento, sexo y fecha de nacimiento: con la fecha se sabe si el consentimiento lo firmas tú o tu acudiente.'),
              const SizedBox(height: 6),
              const Text('Pídeselo en tu próxima cita o comunícate con Bienestar Universitario.'),
            ])
          else if (version == null)
            Aviso('No hay consentimiento ${_menor ? 'para menores de edad ' : ''}publicado para ${caso['servicio']}.', tipo: TipoAviso.alerta, hijos: const [
              SizedBox(height: 6),
              Text('Bienestar Universitario debe publicarlo antes de que puedas firmar. Vuelve a intentarlo más tarde.'),
            ])
          else ...[
            _datosAtencion(),
            const SizedBox(height: 14),
            if (!listo) ...[
              const Aviso('Por ahora no es posible guardar firmas. Avisa a Bienestar Universitario; mientras tanto solo se puede registrar que no aceptas.', tipo: TipoAviso.alerta),
              const SizedBox(height: 14),
            ],
            ResumenErrores(_error),
            _encabezado(),
            const SizedBox(height: 14),
            _datosFirma(),
            const SizedBox(height: 14),
            Seccion(
              titulo: version['nombre'] as String?,
              subtitulo: 'Versión ${version['numero']} · ${_menor ? 'tu acudiente debe leerla' : 'léela'} con atención antes de firmar.',
              children: [
                Container(
                  constraints: const BoxConstraints(maxHeight: 380),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE5E7EB))),
                  child: SingleChildScrollView(
                    child: Text(_texto.renderizar(version['texto'] as String, _valores), style: const TextStyle(fontSize: 13.5, height: 1.5)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (_menor) ...[
              Seccion(children: [
                LienzoFirma(
                  controller: _firmaEstudiante,
                  titulo: 'Firma del estudiante',
                  ayuda: 'Tú (el estudiante) firmas con el dedo dentro del recuadro.',
                  error: _error?.error('firma_estudiante'),
                  habilitado: !_enviando,
                ),
              ]),
              const SizedBox(height: 14),
            ],
            Seccion(children: [
              LienzoFirma(
                controller: _firma,
                titulo: _menor ? 'Firma del acudiente' : 'Tu firma',
                ayuda: _menor ? 'El acudiente firma con el dedo dentro del recuadro.' : 'Firma con el dedo dentro del recuadro.',
                error: _error?.error('firma'),
                habilitado: !_enviando,
              ),
            ]),
            const SizedBox(height: 18),
            Row(children: [
              Expanded(
                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(foregroundColor: Colores.rojo),
                  onPressed: _enviando ? null : () => _enviar('Rechazado'),
                  child: const Text('No acepto'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: BotonEnviar(
                  texto: 'Acepto y firmo',
                  enviando: _enviando,
                  icono: Icons.draw_outlined,
                  alPresionar: _firmasCompletas && listo ? () => _enviar('Aceptado') : null,
                ),
              ),
            ]),
            if (!_firmasCompletas)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Text('Para aceptar, firma dentro del recuadro.', textAlign: TextAlign.center, style: TextStyle(color: Colores.gris, fontSize: 12)),
              ),
            const SizedBox(height: 24),
          ],
        ],
      ),
    );
  }

  Widget _datosAtencion() {
    final d = widget.datos;
    final persona = d['persona'] as Map<String, dynamic>;
    final vinculacion = d['vinculacion'] as Map<String, dynamic>?;
    final datosBase = (d['texto'] as Map)['datos_base'] as Map;
    return Seccion(titulo: 'Datos de la atención', children: [
      Dato('Estudiante', persona['nombre'] as String?),
      Dato('Documento', '${persona['tipo_documento'] ?? ''} ${persona['numero_documento'] ?? ''}'.trim()),
      Dato('Edad', d['edad'] != null ? '${d['edad']} años${_menor ? ' · menor de edad' : ''}' : null),
      Dato('Vínculo', vinculacion?['tipo'] as String?),
      Dato('Facultad', vinculacion?['facultad'] as String?),
      Dato('Profesional', datosBase['profesional']?.toString()),
    ]);
  }

  Widget _encabezado() {
    final vinculaciones = (_opciones['vinculaciones'] as List).cast<String>();
    return Seccion(
      titulo: _menor ? 'Datos del estudiante para el formato' : 'Tu vinculación con la universidad',
      children: [
        DropdownButtonFormField<String>(
          initialValue: vinculaciones.contains(_vinculacion) ? _vinculacion : null,
          decoration: InputDecoration(labelText: 'Vinculación', errorText: _error?.error('vinculacion')),
          items: vinculaciones.map((v) => DropdownMenuItem(value: v, child: Text(v))).toList(),
          onChanged: (v) => setState(() => _vinculacion = v ?? ''),
        ),
        if (_esEstudiante) ...[
          const SizedBox(height: 12),
          TextField(controller: _programa, maxLength: 150, decoration: InputDecoration(labelText: 'Programa académico', hintText: 'Ej. Psicología', errorText: _error?.error('programa_academico'), counterText: '')),
          const SizedBox(height: 12),
          TextField(
            controller: _semestre,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: 'Semestre (1 a ${_opciones['semestre_max']})', errorText: _error?.error('semestre')),
          ),
        ],
        const SizedBox(height: 12),
        TextField(
          controller: _dependencia,
          maxLength: 150,
          decoration: InputDecoration(labelText: 'Dependencia y/o facultad', hintText: 'Ej. Facultad de Ciencias de la Salud', errorText: _error?.error('dependencia_facultad'), counterText: ''),
        ),
      ],
    );
  }

  Widget _datosFirma() {
    final tipos = (_opciones['tipos_documento'] as Map).map((k, v) => MapEntry(k.toString(), v.toString()));
    final parentescos = (_opciones['parentescos'] as List).cast<String>();
    final maxContactos = _opciones['max_contactos'] as int;

    return Seccion(
      titulo: _menor ? 'Datos del acudiente que otorga el consentimiento' : 'Tus datos para el consentimiento',
      subtitulo: _menor
          ? 'Eres menor de edad: tu madre, padre o acudiente llena sus datos y firma el consentimiento, y tú también lo firmas (formato GBU-GPGR-F-019).'
          : null,
      children: [
        for (final nombre in _nombresCampos) ...[
          if (nombre == 'acudiente_tipo_documento')
            DropdownButtonFormField<String>(
              initialValue: _tipoDocumento,
              decoration: InputDecoration(labelText: 'Tipo de documento del acudiente', errorText: _error?.error(nombre)),
              items: tipos.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
              onChanged: (v) => setState(() => _tipoDocumento = v ?? 'CC'),
            )
          else
            TextField(
              controller: _campos[nombre],
              keyboardType: nombre == 'acudiente_telefono' ? TextInputType.phone : TextInputType.text,
              decoration: InputDecoration(
                labelText: _etiquetas[nombre]!.$1,
                hintText: _etiquetas[nombre]!.$2.isEmpty ? null : _etiquetas[nombre]!.$2,
                errorText: _error?.error(nombre),
              ),
            ),
          const SizedBox(height: 12),
        ],
        if (!_menor) ...[
          const Text('Personas a quienes avisar en caso de urgencia', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          const SizedBox(height: 2),
          Text('Agrega al menos una con su nombre, teléfono y parentesco. Puedes agregar hasta $maxContactos.', style: const TextStyle(color: Colores.gris, fontSize: 13)),
          if (_error?.error('contactos') != null) Text(_error!.error('contactos')!, style: const TextStyle(color: Colores.rojo, fontSize: 13)),
          const SizedBox(height: 10),
          for (var i = 0; i < _contactos.length; i++) _contacto(i, parentescos),
          if (_contactos.length < maxContactos)
            TextButton.icon(
              onPressed: _enviando
                  ? null
                  : () => setState(() {
                        final nuevo = _Contacto();
                        nuevo.nombre.addListener(_refrescar);
                        nuevo.telefono.addListener(_refrescar);
                        _contactos.add(nuevo);
                      }),
              icon: const Icon(Icons.add),
              label: const Text('Agregar otra persona'),
            ),
        ],
      ],
    );
  }

  Widget _contacto(int i, List<String> parentescos) {
    final contacto = _contactos[i];
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE5E7EB))),
      child: Column(children: [
        Row(children: [
          Expanded(child: Text('Persona ${i + 1}', style: const TextStyle(fontWeight: FontWeight.w800))),
          if (_contactos.length > 1)
            TextButton(onPressed: _enviando ? null : () => setState(() => _contactos.removeAt(i)), child: const Text('Quitar')),
        ]),
        TextField(controller: contacto.nombre, decoration: InputDecoration(labelText: 'Nombre completo', hintText: 'Ej. María Pérez', errorText: _error?.error('contactos.$i.nombre'))),
        const SizedBox(height: 10),
        TextField(
          controller: contacto.telefono,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(labelText: 'Teléfono', hintText: 'Ej. 3001234567', errorText: _error?.error('contactos.$i.telefono')),
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          initialValue: contacto.parentesco,
          decoration: InputDecoration(labelText: 'Parentesco', errorText: _error?.error('contactos.$i.parentesco')),
          items: parentescos.map((p) => DropdownMenuItem(value: p, child: Text(p))).toList(),
          onChanged: (v) => setState(() => contacto.parentesco = v),
        ),
      ]),
    );
  }
}
