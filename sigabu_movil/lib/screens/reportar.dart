import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api.dart';
import '../theme.dart';
import '../widgets/comunes.dart';

/// Reporte de una situación de Violencias Basadas en Género (reportes/crear.blade.php): propio,
/// en nombre de otra persona o anónimo. Confidencial.
class PantallaReportar extends StatelessWidget {
  const PantallaReportar({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Reportar una situación'),
          Text('Violencias de Género · Confidencial', style: TextStyle(fontSize: 13, color: Colores.vbg, fontWeight: FontWeight.w600)),
        ]),
      ),
      body: Cargador<Map<String, dynamic>>(
        cargar: () => api.get('/reportes'),
        construir: (context, datos, recargar) => _Formulario(datos: datos, recargar: recargar),
      ),
    );
  }
}

class _Formulario extends StatefulWidget {
  const _Formulario({required this.datos, required this.recargar});

  final Map<String, dynamic> datos;
  final Future<void> Function() recargar;

  @override
  State<_Formulario> createState() => _FormularioState();
}

class _FormularioState extends State<_Formulario> {
  static const _tercero = 'Tercero';
  static const _anonimo = 'Anónimo';

  int _paso = 0;
  String? _quien;
  final _afectado = TextEditingController();
  final _vinculo = TextEditingController();
  final _relato = TextEditingController();
  final _lugar = TextEditingController();
  final _medio = TextEditingController();
  String? _tipoViolencia;
  DateTime? _fecha;
  bool _contacto = false;
  bool _acepta = false;
  bool _enviando = false;
  ApiException? _error;
  Map<String, dynamic>? _enviado;

  int get _minimo => widget.datos['relato_min'] as int;

  @override
  void initState() {
    super.initState();
    for (final c in [_afectado, _relato, _medio]) {
      c.addListener(() => setState(() {}));
    }
  }

  bool get _puedeAvanzar => switch (_paso) {
        0 => _quien != null,
        1 => _relato.text.trim().length >= _minimo &&
            _acepta &&
            (_quien != _tercero || _afectado.text.trim().isNotEmpty) &&
            (_quien == _anonimo || !_contacto || _medio.text.trim().isNotEmpty),
        _ => true,
      };

  Future<void> _enviar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final respuesta = await api.post('/reportes', {
        'tipo_reportante': _quien,
        if (_quien == _tercero) 'afectado_nombre': _afectado.text,
        if (_quien == _tercero) 'afectado_vinculo': _vinculo.text,
        'relato': _relato.text,
        'tipo_violencia': _tipoViolencia,
        'lugar': _lugar.text,
        'fecha_hechos': _fecha == null ? null : '${_fecha!.year}-${_fecha!.month.toString().padLeft(2, '0')}-${_fecha!.day.toString().padLeft(2, '0')}',
        if (_quien != _anonimo) 'desea_contacto': _contacto,
        if (_quien != _anonimo && _contacto) 'medio_contacto': _medio.text,
        'acepta_datos': _acepta,
      });
      setState(() => _enviado = (respuesta['datos'] as Map<String, dynamic>?) ?? {});
    } on ApiException catch (e) {
      setState(() {
        _error = e;
        if (e.errores.keys.any((k) => k != 'tipo_reportante')) _paso = 1;
        if (e.errores.containsKey('tipo_reportante')) _paso = 0;
      });
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_enviado != null) return _confirmacionEnvio();

    return Column(children: [
      Container(
        color: Colors.white,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
        child: IndicadorPasos(pasos: const ['Quién reporta', 'Relato y consentimiento', 'Confirmación'], actual: _paso, color: Colores.vbg),
      ),
      Expanded(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFFDF2F8), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFBCFE8))),
              child: const Text(
                '🔒 Este espacio es confidencial. Solo el equipo autorizado de la Vicerrectoría de Bienestar Universitario podrá acceder a la información que compartas.',
                style: TextStyle(color: Color(0xFF9D174D), fontSize: 13, height: 1.35),
              ),
            ),
            const SizedBox(height: 14),
            if (_error != null) ResumenErrores(_error),
            ...switch (_paso) {
              0 => _pasoQuien(),
              1 => _pasoRelato(),
              _ => _pasoConfirmar(),
            },
            if (_misReportes().isNotEmpty) ...[const SizedBox(height: 24), ..._misReportes()],
          ],
        ),
      ),
      SafeArea(
        top: false,
        child: Container(
          color: Colors.white,
          padding: const EdgeInsets.all(12),
          child: Row(children: [
            if (_paso > 0) ...[
              Expanded(child: OutlinedButton(onPressed: _enviando ? null : () => setState(() => _paso--), child: const Text('← Atrás'))),
              const SizedBox(width: 10),
            ],
            Expanded(
              flex: 2,
              child: _paso < 2
                  ? FilledButton(
                      style: FilledButton.styleFrom(backgroundColor: Colores.vbg),
                      onPressed: _puedeAvanzar ? () => setState(() => _paso++) : null,
                      child: const Text('Continuar →'),
                    )
                  : BotonEnviar(texto: 'Enviar reporte', enviando: _enviando, color: Colores.vbg, alPresionar: _enviar),
            ),
          ]),
        ),
      ),
    ]);
  }

  List<Widget> _pasoQuien() => [
        const Text('¿Quién está reportando?', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        const Text('Puedes reportar por ti mismo/a, en nombre de otra persona, o de forma anónima.', style: TextStyle(color: Colores.gris)),
        const SizedBox(height: 14),
        for (final opcion in (widget.datos['tipos_reportante'] as List).cast<Map<String, dynamic>>())
          OpcionTarjeta(
            icono: opcion['icono'] as String,
            titulo: opcion['titulo'] as String,
            texto: opcion['texto'] as String,
            color: Colores.vbg,
            seleccionada: _quien == opcion['valor'],
            alTocar: () => setState(() => _quien = opcion['valor'] as String),
          ),
      ];

  List<Widget> _pasoRelato() {
    final tipos = (widget.datos['tipos_violencia'] as List).cast<String>();
    final faltan = _minimo - _relato.text.trim().length;
    return [
      const Text('Cuéntanos qué ocurrió', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
      const SizedBox(height: 4),
      const Text('Escribe solo lo que te sientas cómodo/a compartiendo.', style: TextStyle(color: Colores.gris)),
      const SizedBox(height: 14),
      if (_quien == _tercero) ...[
        TextField(controller: _afectado, decoration: InputDecoration(labelText: 'Nombre de la persona afectada', errorText: _error?.error('afectado_nombre'))),
        const SizedBox(height: 12),
        TextField(controller: _vinculo, decoration: const InputDecoration(labelText: 'Su relación con la universidad (opcional)', hintText: 'Ej. estudiante, docente')),
        const SizedBox(height: 12),
      ],
      TextField(
        controller: _relato,
        minLines: 5,
        maxLines: 10,
        maxLength: widget.datos['relato_max'] as int,
        decoration: InputDecoration(
          labelText: '¿Qué pasó?',
          alignLabelWithHint: true,
          hintText: 'Describe la situación: qué ocurrió, quiénes estuvieron involucrados y si sigue ocurriendo.',
          helperText: faltan > 0 ? 'Escribe al menos $_minimo caracteres (faltan $faltan).' : null,
          errorText: _error?.error('relato'),
        ),
      ),
      const SizedBox(height: 8),
      DropdownButtonFormField<String>(
        initialValue: _tipoViolencia,
        decoration: const InputDecoration(labelText: 'Tipo de violencia (opcional)'),
        items: [const DropdownMenuItem<String>(value: null, child: Text('Prefiero no indicarlo')), ...tipos.map((t) => DropdownMenuItem(value: t, child: Text(t)))],
        onChanged: (v) => setState(() => _tipoViolencia = v),
      ),
      const SizedBox(height: 12),
      TextField(controller: _lugar, decoration: const InputDecoration(labelText: 'Lugar (opcional)', hintText: 'Ej. Bloque B, redes sociales')),
      const SizedBox(height: 12),
      InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () async {
          final hoy = DateTime.now();
          final fecha = await showDatePicker(context: context, initialDate: _fecha ?? hoy, firstDate: DateTime(hoy.year - 10), lastDate: hoy);
          if (fecha != null) setState(() => _fecha = fecha);
        },
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: 'Fecha (opcional)',
            errorText: _error?.error('fecha_hechos'),
            suffixIcon: _fecha == null ? const Icon(Icons.calendar_today_outlined) : IconButton(icon: const Icon(Icons.clear), onPressed: () => setState(() => _fecha = null)),
          ),
          child: Text(_fecha == null ? 'Sin fecha' : '${_fecha!.day}/${_fecha!.month}/${_fecha!.year}'),
        ),
      ),
      if (_quien != _anonimo) ...[
        const SizedBox(height: 8),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          activeColor: Colores.vbg,
          value: _contacto,
          onChanged: (v) => setState(() => _contacto = v ?? false),
          title: const Text('Quiero que el equipo me contacte para acompañamiento.'),
        ),
        if (_contacto)
          TextField(
            controller: _medio,
            decoration: InputDecoration(labelText: '¿Cómo podemos contactarte de forma segura?', hintText: 'Ej. correo institucional, celular en horario de la tarde', errorText: _error?.error('medio_contacto')),
          ),
      ],
      const SizedBox(height: 8),
      CheckboxListTile(
        contentPadding: EdgeInsets.zero,
        controlAffinity: ListTileControlAffinity.leading,
        activeColor: Colores.vbg,
        value: _acepta,
        onChanged: (v) => setState(() => _acepta = v ?? false),
        title: const Text(
          'Autorizo a la Vicerrectoría de Bienestar Universitario a tratar de forma confidencial la información de este reporte para brindar orientación y activar las rutas de atención que correspondan (Ley 1581 de 2012).',
          style: TextStyle(fontSize: 13, height: 1.35),
        ),
        subtitle: _error?.error('acepta_datos') != null ? Text(_error!.error('acepta_datos')!, style: const TextStyle(color: Colores.rojo)) : null,
      ),
    ];
  }

  List<Widget> _pasoConfirmar() => [
        const Text('Confirma tu reporte', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
        const SizedBox(height: 14),
        Seccion(children: [
          Dato('Quién reporta', _quien == _anonimo ? 'Anónimo' : (_quien == _tercero ? 'En nombre de ${_afectado.text.trim()}' : 'La persona afectada')),
          Dato('Relato', _relato.text.trim()),
          Dato('Tipo de violencia', _tipoViolencia),
          Dato('Lugar', _lugar.text.trim()),
        ]),
        const SizedBox(height: 12),
        const _LineasEmergencia(),
      ];

  List<Widget> _misReportes() {
    final reportes = (widget.datos['mis_reportes'] as List).cast<Map<String, dynamic>>();
    if (reportes.isEmpty) return [];
    return [
      Seccion(
        titulo: 'Mis reportes',
        subtitulo: 'Los reportes anónimos no aparecen aquí: no guardamos quién los envió.',
        children: [
          for (final r in reportes)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(children: [
                Expanded(child: Text('${r['codigo']} · ${r['fecha']}', style: const TextStyle(fontWeight: FontWeight.w700))),
                Pildora(r['estado'] as String, color: switch (r['estado']) {
                  'Recibido' => Colores.ambar,
                  'En atención' => Colores.azul,
                  _ => Colores.gris,
                }),
              ]),
            ),
        ],
      ),
    ];
  }

  Widget _confirmacionEnvio() {
    return ListView(padding: const EdgeInsets.all(24), children: [
      const SizedBox(height: 30),
      const Center(child: Text('💜', style: TextStyle(fontSize: 60))),
      const SizedBox(height: 12),
      const Text('Recibimos tu reporte', textAlign: TextAlign.center, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
      const SizedBox(height: 10),
      Text(
        'Código ${_enviado!['codigo'] ?? ''}. El equipo de Violencias de Género de la Vicerrectoría de Bienestar Universitario lo revisará de forma confidencial.',
        textAlign: TextAlign.center,
        style: const TextStyle(height: 1.4),
      ),
      if (_enviado!['anonimo'] == true) ...[
        const SizedBox(height: 10),
        const Text('Como es anónimo, no guardamos quién lo envió. Si quieres acompañamiento, puedes acercarte a Bienestar y mencionar este código.',
            textAlign: TextAlign.center, style: TextStyle(color: Colores.gris, height: 1.4)),
      ],
      const SizedBox(height: 16),
      const _LineasEmergencia(),
      const SizedBox(height: 20),
      FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Volver al inicio')),
    ]);
  }
}

class _LineasEmergencia extends StatelessWidget {
  const _LineasEmergencia();

  @override
  Widget build(BuildContext context) {
    return Aviso('Si estás en peligro inmediato, llama a la línea 155 (orientación a mujeres) o al 123.', tipo: TipoAviso.error, hijos: [
      const SizedBox(height: 8),
      Wrap(spacing: 8, children: [
        OutlinedButton.icon(onPressed: () => launchUrl(Uri.parse('tel:155')), icon: const Icon(Icons.call), label: const Text('155')),
        OutlinedButton.icon(onPressed: () => launchUrl(Uri.parse('tel:123')), icon: const Icon(Icons.call), label: const Text('123')),
      ]),
    ]);
  }
}
