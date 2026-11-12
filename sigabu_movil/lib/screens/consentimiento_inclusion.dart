import 'package:flutter/material.dart';

import '../api/api.dart';
import '../theme.dart';
import '../utils/formato.dart';
import '../widgets/comunes.dart';
import '../widgets/firma.dart';
import 'consentimiento_general.dart';

/// Consentimiento del Proceso de Inclusión (inclusion/consentimiento/formulario.blade.php): la
/// persona corrige sus datos, marca sus decisiones, firma y lo envía. Puede guardar borrador.
class PantallaConsentimientoInclusion extends StatelessWidget {
  const PantallaConsentimientoInclusion({super.key, required this.idCaso});

  final String idCaso;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Cargador<Map<String, dynamic>>(
        cargar: () => api.get('/casos/$idCaso/inclusion/consentimiento/formulario'),
        construir: (context, datos, _) {
          if (datos['formato'] == 'general') return PantallaConsentimiento(idCaso: idCaso);
          if (datos['vigente'] == true) return PantallaVerConsentimientoInclusion(idCaso: idCaso, mensaje: datos['mensaje'] as String?);
          return _Formulario(idCaso: idCaso, datos: datos);
        },
      ),
    );
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
  static const _textos = ['nombre_persona', 'documento_persona', 'numero_contacto', 'programa_academico', 'semestre', 'dependencia', 'acudiente_nombre', 'acudiente_numero_documento', 'acudiente_parentesco'];

  late final Map<String, dynamic> _opciones = widget.datos['opciones'] as Map<String, dynamic>;
  late final bool _menor = widget.datos['menor'] == true;
  late final TextoConsentimiento _texto = TextoConsentimiento(widget.datos['texto'] as Map<String, dynamic>);
  late final Map<String, String> _decisionesTexto = (_opciones['decisiones'] as Map).map((k, v) => MapEntry(k.toString(), v.toString()));

  final Map<String, TextEditingController> _campos = {};
  String? _vinculacion;
  String? _facultad;
  String? _tipoDocumento;
  final Map<String, bool> _decisiones = {};
  final _firma = FirmaController();
  bool _enviando = false;
  bool _guardando = false;
  String? _borrador;
  ApiException? _error;

  @override
  void initState() {
    super.initState();
    final iniciales = (widget.datos['iniciales'] as Map).map((k, v) => MapEntry(k.toString(), v));
    for (final campo in _textos) {
      _campos[campo] = TextEditingController(text: iniciales[campo]?.toString() ?? '')..addListener(() => setState(() {}));
    }
    final vinculaciones = (_opciones['vinculaciones'] as List).cast<String>();
    final facultades = (_opciones['facultades'] as List).cast<String>();
    _vinculacion = vinculaciones.contains(iniciales['vinculacion']) ? iniciales['vinculacion'] as String : null;
    _facultad = facultades.contains(iniciales['facultad']) ? iniciales['facultad'] as String : null;
    _tipoDocumento = iniciales['acudiente_tipo_documento'] as String?;
    for (final campo in _decisionesTexto.keys) {
      final valor = iniciales[campo];
      _decisiones[campo] = valor == true || valor == 1 || valor == '1' || valor == 'true';
    }
    _borrador = widget.datos['borrador_guardado'] as String?;
    _firma.addListener(() => setState(() {}));
  }

  bool get _esEstudiante => (_opciones['vinculaciones_estudiante'] as List).contains(_vinculacion);

  Map<String, dynamic> get _valores => {
        for (final e in _campos.entries) e.key: e.value.text,
        'vinculacion': _vinculacion,
        'facultad': _facultad,
        if (_menor) 'acudiente_tipo_documento': _tipoDocumento,
        ..._decisiones,
      };

  Future<void> _guardarBorrador() async {
    setState(() => _guardando = true);
    try {
      final respuesta = await api.put('/casos/${widget.idCaso}/inclusion/borradores/consentimiento', {'datos': _valores});
      setState(() => _borrador = respuesta['guardado']?.toString());
      if (mounted) mostrarMensaje(context, 'Borrador guardado a las $_borrador. La firma no se guarda en el borrador.');
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    } finally {
      if (mounted) setState(() => _guardando = false);
    }
  }

  Future<void> _enviar() async {
    if (!_firma.suficiente) {
      setState(() => _error = ApiException('Falta la firma. Firma dentro del recuadro.', errores: {'firma': ['Falta la firma. Firma dentro del recuadro.']}));
      return;
    }
    final aConfirmar = _opciones['decision_a_confirmar'] as String?;
    if (aConfirmar != null && _decisiones[aConfirmar] != true) {
      final ok = await confirmar(context,
          'No marcaste la decisión de recibir acompañamiento y seguimiento del Área de Inclusión. ¿Confirmas que quieres enviar el consentimiento así?',
          si: 'Enviar así');
      if (!ok) return;
    }
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final cuerpo = {
        ..._valores,
        'semestre': int.tryParse(_campos['semestre']!.text),
        'id_version': (widget.datos['version'] as Map)['id'],
        'firma': await _firma.exportar(),
      };
      final respuesta = await api.post('/casos/${widget.idCaso}/inclusion/consentimiento', cuerpo);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(
        builder: (_) => PantallaVerConsentimientoInclusion(idCaso: widget.idCaso, mensaje: respuesta['mensaje']?.toString()),
      ));
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
    final version = d['version'] as Map<String, dynamic>;
    final listo = d['almacenamiento_listo'] == true;
    final textoValores = _valores.map((k, v) => MapEntry(k, v?.toString()));

    return Scaffold(
      appBar: AppBar(
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Consentimiento informado'),
          Text('Caso ${caso['codigo']} · Proceso de Inclusión Universitaria', style: const TextStyle(fontSize: 12, color: Colores.gris, fontWeight: FontWeight.w500)),
        ]),
        actions: [
          if (_borrador != null) const Padding(padding: EdgeInsets.only(right: 12), child: Center(child: Pildora('Borrador', color: Colores.ambar))),
        ],
      ),
      body: d['faltan_datos'] == true
          ? ListView(padding: const EdgeInsets.all(16), children: [
              Aviso('Aún no puedes firmar', tipo: TipoAviso.alerta, hijos: [
                const SizedBox(height: 6),
                Text('Antes de firmar, tu profesional (${d['profesional']}) debe registrar tu tipo de documento, sexo y fecha de nacimiento: con la fecha se sabe si firmas tú o tu acudiente.'),
                const SizedBox(height: 6),
                const Text('Pídeselo en tu próxima cita o comunícate con Bienestar Universitario.'),
              ]),
            ])
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (!listo) ...[
                  const Aviso('Por ahora no es posible guardar firmas. Puedes guardar el borrador y avisar a Bienestar Universitario.', tipo: TipoAviso.alerta),
                  const SizedBox(height: 14),
                ],
                ResumenErrores(_error),
                _datosPersona(),
                if (_menor) ...[const SizedBox(height: 14), _datosAcudiente()],
                const SizedBox(height: 14),
                Seccion(
                  titulo: version['nombre'] as String?,
                  subtitulo: 'Versión ${version['numero']} · ${_menor ? 'el acudiente debe leerla' : 'léela'} con atención antes de decidir.',
                  children: [
                    Container(
                      constraints: const BoxConstraints(maxHeight: 380),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE5E7EB))),
                      child: SingleChildScrollView(child: Text(_texto.renderizar(version['texto'] as String, textoValores), style: const TextStyle(fontSize: 13.5, height: 1.5))),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Seccion(
                  titulo: 'Decisiones',
                  subtitulo: 'Son independientes: marca solo lo que autorizas.',
                  children: [
                    for (final e in _decisionesTexto.entries)
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        controlAffinity: ListTileControlAffinity.leading,
                        value: _decisiones[e.key] ?? false,
                        onChanged: _enviando ? null : (v) => setState(() => _decisiones[e.key] = v ?? false),
                        title: Text(e.value, style: const TextStyle(fontSize: 14, height: 1.35)),
                      ),
                  ],
                ),
                const SizedBox(height: 14),
                Seccion(children: [
                  LienzoFirma(
                    controller: _firma,
                    titulo: _menor ? 'Firma del acudiente' : 'Tu firma',
                    ayuda: 'Firma con el dedo dentro del recuadro. La firma no se guarda en el borrador. Si no puedes firmar en pantalla, avisa a tu profesional: puedes firmar en papel.',
                    error: _error?.error('firma'),
                    habilitado: !_enviando,
                  ),
                ]),
                if (_error?.error('id_version') != null) ...[const SizedBox(height: 10), Aviso(_error!.error('id_version')!, tipo: TipoAviso.error)],
                const SizedBox(height: 18),
                Row(children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _guardando || _enviando ? null : _guardarBorrador,
                      child: _guardando ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Guardar borrador'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(child: BotonEnviar(texto: 'Firmar y enviar', enviando: _enviando, alPresionar: listo ? _enviar : null)),
                ]),
                if (_borrador != null)
                  Padding(padding: const EdgeInsets.only(top: 8), child: Text('Borrador guardado a las $_borrador', textAlign: TextAlign.center, style: const TextStyle(color: Colores.gris, fontSize: 12))),
                const SizedBox(height: 24),
              ],
            ),
    );
  }

  Widget _campo(String nombre, String etiqueta, {TextInputType? teclado, String? ayuda}) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextField(
          controller: _campos[nombre],
          keyboardType: teclado,
          decoration: InputDecoration(labelText: etiqueta, hintText: ayuda, errorText: _error?.error(nombre)),
        ),
      );

  Widget _datosPersona() {
    final vinculaciones = (_opciones['vinculaciones'] as List).cast<String>();
    final facultades = (_opciones['facultades'] as List).cast<String>();
    return Seccion(
      titulo: 'Datos de la persona',
      subtitulo: 'Corrige lo que no esté bien. Todos los campos son obligatorios, salvo la dependencia.',
      children: [
        _campo('nombre_persona', 'Nombre de la persona'),
        _campo('documento_persona', 'Documento de identidad'),
        _campo('numero_contacto', 'Número de contacto', teclado: TextInputType.phone),
        DropdownButtonFormField<String>(
          initialValue: _vinculacion,
          isExpanded: true,
          decoration: InputDecoration(labelText: 'Vinculación', errorText: _error?.error('vinculacion')),
          items: vinculaciones.map((v) => DropdownMenuItem(value: v, child: Text(v))).toList(),
          onChanged: (v) => setState(() => _vinculacion = v),
        ),
        const SizedBox(height: 12),
        if (_esEstudiante) ...[
          DropdownButtonFormField<String>(
            initialValue: _facultad,
            isExpanded: true,
            decoration: InputDecoration(labelText: 'Facultad', errorText: _error?.error('facultad')),
            items: facultades.map((f) => DropdownMenuItem(value: f, child: Text(f, overflow: TextOverflow.ellipsis))).toList(),
            onChanged: (v) => setState(() => _facultad = v),
          ),
          const SizedBox(height: 12),
          _campo('programa_academico', 'Programa'),
          _campo('semestre', 'Semestre (1 a ${_opciones['semestre_max']})', teclado: TextInputType.number),
        ],
        _campo('dependencia', 'Dependencia (opcional)', ayuda: 'N/A'),
      ],
    );
  }

  Widget _datosAcudiente() {
    final tipos = (_opciones['tipos_documento'] as Map).map((k, v) => MapEntry(k.toString(), v.toString()));
    return Seccion(
      titulo: 'Datos del acudiente',
      subtitulo: 'La persona es menor de edad: el consentimiento lo otorga y firma su madre, padre o acudiente.',
      children: [
        _campo('acudiente_nombre', 'Nombre completo del acudiente'),
        DropdownButtonFormField<String>(
          initialValue: tipos.containsKey(_tipoDocumento) ? _tipoDocumento : null,
          decoration: InputDecoration(labelText: 'Tipo de documento', errorText: _error?.error('acudiente_tipo_documento')),
          items: tipos.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
          onChanged: (v) => setState(() => _tipoDocumento = v),
        ),
        const SizedBox(height: 12),
        _campo('acudiente_numero_documento', 'Número de documento'),
        _campo('acudiente_parentesco', 'Parentesco con la persona', ayuda: 'Ej. Madre, padre, tío'),
      ],
    );
  }
}

/// "Mi consentimiento" de Inclusión (inclusion/consentimiento/ver.blade.php): texto aceptado,
/// decisiones, historial, PDF y revocación.
class PantallaVerConsentimientoInclusion extends StatefulWidget {
  const PantallaVerConsentimientoInclusion({super.key, required this.idCaso, this.mensaje});

  final String idCaso;
  final String? mensaje;

  @override
  State<PantallaVerConsentimientoInclusion> createState() => _PantallaVerConsentimientoInclusionState();
}

class _PantallaVerConsentimientoInclusionState extends State<PantallaVerConsentimientoInclusion> {
  final _cargador = GlobalKey<CargadorState<Map<String, dynamic>>>();
  late String? _mensaje = widget.mensaje;
  bool _revocando = false;

  Future<void> _revocar() async {
    final ok = await confirmar(context,
        '¿Confirmas que quieres revocar tu consentimiento? Se suspenderá la atención hasta que firmes uno nuevo. El historial se conserva.',
        si: 'Revocar', peligro: true);
    if (!ok) return;
    setState(() => _revocando = true);
    try {
      final respuesta = await api.post('/casos/${widget.idCaso}/inclusion/consentimiento/revocar');
      setState(() => _mensaje = respuesta['mensaje']?.toString());
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    } finally {
      if (mounted) setState(() => _revocando = false);
      await _cargador.currentState?.recargar();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mi consentimiento')),
      body: Cargador<Map<String, dynamic>>(
        key: _cargador,
        cargar: () => api.get('/casos/${widget.idCaso}/inclusion/consentimiento'),
        construir: (context, datos, recargar) {
          final caso = datos['caso'] as Map<String, dynamic>;
          final aceptacion = datos['aceptacion'] as Map<String, dynamic>?;
          final historial = (datos['historial'] as List).cast<Map<String, dynamic>>();
          final estado = datos['estado'] as String?;

          return RefreshIndicator(
            onRefresh: recargar,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Row(children: [
                  Expanded(child: Text('Caso ${caso['codigo']} · Proceso de Inclusión Universitaria', style: const TextStyle(color: Colores.gris))),
                  if (estado != null)
                    Pildora(estado, color: switch (estado) {
                      'Firmado' => Colores.verde,
                      'Completado' => Colores.azul,
                      'Revocado' || 'Rechazado' => Colores.rojo,
                      _ => Colores.gris,
                    }),
                ]),
                const SizedBox(height: 12),
                if (_mensaje != null) ...[Aviso(_mensaje!, tipo: TipoAviso.exito), const SizedBox(height: 12)],
                if (datos['revocado_el'] != null) ...[
                  Aviso('Revocado el ${datos['revocado_el']}. La atención queda suspendida hasta que se firme un consentimiento nuevo.', tipo: TipoAviso.alerta),
                  const SizedBox(height: 12),
                ],
                if (aceptacion == null)
                  const Seccion(children: [Text('Aún no hay un consentimiento firmado en este caso.')])
                else ...[
                  Seccion(
                    titulo: 'Texto aceptado',
                    subtitulo: '${aceptacion['nombre'] ?? ''} · versión ${aceptacion['version']} · ${aceptacion['fecha']}',
                    children: [
                      Container(
                        constraints: const BoxConstraints(maxHeight: 360),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(10)),
                        child: SingleChildScrollView(child: Text(aceptacion['texto']?.toString() ?? '', style: const TextStyle(fontSize: 13.5, height: 1.5))),
                      ),
                    ],
                  ),
                  if ((aceptacion['decisiones'] as List).isNotEmpty) ...[
                    const SizedBox(height: 14),
                    Seccion(titulo: 'Decisiones', children: [
                      for (final decision in (aceptacion['decisiones'] as List).cast<Map<String, dynamic>>())
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Icon(decision['marcada'] == true ? Icons.check_box : Icons.check_box_outline_blank,
                                color: decision['marcada'] == true ? Colores.verde : Colores.gris),
                            const SizedBox(width: 8),
                            Expanded(child: Text('${decision['marcada'] == true ? 'Marcada: ' : 'Sin marcar: '}${decision['texto']}', style: const TextStyle(height: 1.35))),
                          ]),
                        ),
                    ]),
                  ],
                  if (aceptacion['en_papel'] == true) ...[
                    const SizedBox(height: 14),
                    Seccion(titulo: aceptacion['leyenda_papel'] as String?, children: [
                      Dato('Fecha de la firma', aceptacion['fecha_firma_papel'] as String?),
                      Dato('Motivo', aceptacion['motivo_papel'] as String?),
                    ]),
                  ],
                  const SizedBox(height: 14),
                  Seccion(titulo: 'Historial', children: [
                    for (final fila in historial)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(children: [
                          Expanded(child: Text(fila['etiqueta'] as String, style: const TextStyle(fontWeight: FontWeight.w700))),
                          Text(fila['fecha'] as String, style: const TextStyle(color: Colores.gris)),
                        ]),
                      ),
                  ]),
                  const SizedBox(height: 14),
                  OutlinedButton.icon(
                    onPressed: () => descargarYAbrir(context, '/casos/${widget.idCaso}/inclusion/consentimiento.pdf', 'Consentimiento-inclusion-${caso['codigo']}.pdf'),
                    icon: const Icon(Icons.picture_as_pdf_outlined),
                    label: const Text('Descargar PDF'),
                  ),
                ],
                if (datos['puede_firmar'] == true) ...[
                  const SizedBox(height: 10),
                  FilledButton.icon(
                    onPressed: () async {
                      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaConsentimientoInclusion(idCaso: widget.idCaso)));
                      await _cargador.currentState?.recargar();
                    },
                    icon: const Icon(Icons.draw_outlined),
                    label: const Text('Firmar un consentimiento nuevo'),
                  ),
                ],
                if (datos['puede_revocar'] == true) ...[
                  const SizedBox(height: 10),
                  BotonEnviar(texto: 'Revocar consentimiento', enviando: _revocando, color: Colores.rojo, alPresionar: _revocar),
                ],
                const SizedBox(height: 24),
              ],
            ),
          );
        },
      ),
    );
  }
}
