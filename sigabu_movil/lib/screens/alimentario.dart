import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../api/api.dart';
import '../theme.dart';
import '../widgets/comunes.dart';

/// Apoyo alimentario "Ucevistas de Corazón" (alimentario/crear.blade.php): ficha
/// socioeconómica, solicitud abierta con su pase QR, formulario de postulación e historial.
class PantallaAlimentario extends StatefulWidget {
  const PantallaAlimentario({super.key});

  @override
  State<PantallaAlimentario> createState() => _PantallaAlimentarioState();
}

class _PantallaAlimentarioState extends State<PantallaAlimentario> {
  final _cargador = GlobalKey<CargadorState<Map<String, dynamic>>>();
  String? _mensaje;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Apoyo Alimentario'),
          Text('"Ucevistas de Corazón" · Donaciones Alimentarias', style: TextStyle(fontSize: 12, color: Colores.alimentario, fontWeight: FontWeight.w600)),
        ]),
      ),
      body: Cargador<Map<String, dynamic>>(
        key: _cargador,
        cargar: () => api.get('/alimentario'),
        construir: (context, datos, recargar) {
          final abierta = datos['abierta'] as Map<String, dynamic>?;
          final solicitudes = (datos['solicitudes'] as List).cast<Map<String, dynamic>>();
          return RefreshIndicator(
            onRefresh: recargar,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_mensaje != null) ...[Aviso(_mensaje!, tipo: TipoAviso.exito), const SizedBox(height: 14)],
                _Ficha(datos['ficha'] as Map<String, dynamic>),
                const SizedBox(height: 14),
                if (abierta != null)
                  _EstadoSolicitud(abierta)
                else
                  _FormularioSolicitud(
                    datos: datos,
                    alEnviar: (mensaje) async {
                      setState(() => _mensaje = mensaje);
                      await _cargador.currentState?.recargar();
                    },
                  ),
                if (solicitudes.isNotEmpty) ...[const SizedBox(height: 14), _Historial(solicitudes)],
                const SizedBox(height: 20),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Ficha socioeconómica: datos de SIGABU; la verificación académica con SIGA está pendiente.
class _Ficha extends StatefulWidget {
  const _Ficha(this.inicial);

  final Map<String, dynamic> inicial;

  @override
  State<_Ficha> createState() => _FichaState();
}

class _FichaState extends State<_Ficha> {
  late Map<String, dynamic> _ficha = widget.inicial;
  late final _codigo = TextEditingController(text: widget.inicial['codigo']?.toString() ?? '');
  bool _buscando = false;
  String? _mensaje;

  Future<void> _consultar() async {
    if (_codigo.text.trim().isEmpty) return;
    setState(() {
      _buscando = true;
      _mensaje = null;
    });
    try {
      final datos = await api.get('/alimentario/consultar-ficha', query: {'codigo': _codigo.text.trim()});
      setState(() {
        _ficha = datos;
        _mensaje = 'Usuario encontrado en SIGABU. Verificación académica institucional pendiente.';
      });
    } on ApiException catch (e) {
      setState(() => _mensaje = e.mensaje == 'No tienes permiso para hacer esto.' ? 'El código no corresponde al estudiante autenticado.' : e.mensaje);
    } finally {
      if (mounted) setState(() => _buscando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final f = _ficha;
    String valor(Object? v) => v == null || v.toString().isEmpty ? 'Pendiente' : v.toString();
    Widget cuadro(String etiqueta, String texto) => Expanded(
          child: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFFFFF7ED), borderRadius: BorderRadius.circular(10)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(etiqueta.toUpperCase(), style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Colores.alimentario)),
              const SizedBox(height: 2),
              Text(texto, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800)),
            ]),
          ),
        );

    return Seccion(
      titulo: 'Ficha Socioeconómica del Estudiante',
      subtitulo: 'Verificación de matrícula activa, nivel de vulnerabilidad y condiciones socioeconómicas.',
      children: [
        Row(children: [
          Expanded(child: TextField(controller: _codigo, decoration: const InputDecoration(hintText: 'Código estudiantil...', isDense: true), onSubmitted: (_) => _consultar())),
          const SizedBox(width: 8),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colores.alimentario, minimumSize: const Size(80, 46)),
            onPressed: _buscando ? null : _consultar,
            child: Text(_buscando ? 'Buscando…' : 'Buscar'),
          ),
        ]),
        if (_mensaje != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_mensaje!, style: const TextStyle(fontSize: 12, color: Colores.gris))),
        const SizedBox(height: 12),
        Text(f['nombre']?.toString() ?? '', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        Text(
          'Código: ${f['codigo'] ?? ''} · Doc: ${f['documento'] ?? ''}${f['programa'] != null ? ' · ${f['programa']} (${f['semestre'] ?? '-'}° Semestre)' : ''}',
          style: const TextStyle(color: Colores.gris, fontSize: 12),
        ),
        const SizedBox(height: 6),
        const Pildora('Matrícula pendiente de verificación', color: Colores.ambar),
        const SizedBox(height: 10),
        Row(children: [cuadro('Estrato', f['estrato'] == null ? 'Pendiente' : 'Estrato ${f['estrato']}'), const SizedBox(width: 8), cuadro('Sisbén', valor(f['sisben']))]),
        const SizedBox(height: 8),
        Row(children: [cuadro('Vulnerabilidad', valor(f['situacionPrioritaria'])), const SizedBox(width: 8), cuadro('Factor equidad', '${f['mercadosRecibidos'] ?? 0} entregados')]),
      ],
    );
  }
}

class _EstadoSolicitud extends StatelessWidget {
  const _EstadoSolicitud(this.solicitud);

  final Map<String, dynamic> solicitud;

  @override
  Widget build(BuildContext context) {
    final pase = solicitud['pase'] as Map<String, dynamic>?;
    return Seccion(
      titulo: '📋 Estado de tu Solicitud',
      children: [
        Align(alignment: Alignment.centerLeft, child: Pildora(solicitud['estado'] as String, color: pase != null ? Colores.azul : Colores.ambar)),
        const SizedBox(height: 12),
        if (pase != null) ...[
          const Text('🎉 ¡Solicitud Aprobada por Bienestar Universitario!', style: TextStyle(fontWeight: FontWeight.w800, color: Colores.verde)),
          const SizedBox(height: 6),
          const Text('Pase Oficial de Retiro de Mercado Solidario', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          const Text(
            'Acércate a la Bodega de Bienestar Universitario (Bloque C) y presenta este código QR con tu carné estudiantil o cédula. En la ventanilla firmarás al recibir el mercado.',
            style: TextStyle(height: 1.4),
          ),
          const SizedBox(height: 14),
          Center(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFE5E7EB))),
              child: Column(children: [
                QrImageView(data: pase['qr'] as String, size: 200, semanticsLabel: 'Código QR del pase de retiro'),
                Text(pase['orden'] as String, style: const TextStyle(fontWeight: FontWeight.w900, letterSpacing: 1)),
              ]),
            ),
          ),
          const SizedBox(height: 12),
          const Text('Horario de atención: Lunes a Viernes de 8:00 a.m. a 5:00 p.m.', style: TextStyle(fontSize: 13)),
          const Text('Lugar: Ventanilla de Bodega · Bienestar Universitario', style: TextStyle(fontSize: 13)),
        ] else ...[
          const Text('⏳ Tu solicitud está en revisión y priorización', style: TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          Text('Fecha de solicitud: ${solicitud['fecha']} · Nivel de prioridad calculado: ${solicitud['prioridad']}.'),
          const SizedBox(height: 6),
          const Text(
            'El equipo de Trabajo Social y Almacén están asignando los lotes de alimentos disponibles según la fecha de postulación y situación de vulnerabilidad. Te notificaremos en cuanto tu mercado esté listo para retirar.',
            style: TextStyle(color: Colores.gris, height: 1.4),
          ),
        ],
      ],
    );
  }
}

class _FormularioSolicitud extends StatefulWidget {
  const _FormularioSolicitud({required this.datos, required this.alEnviar});

  final Map<String, dynamic> datos;
  final Future<void> Function(String mensaje) alEnviar;

  @override
  State<_FormularioSolicitud> createState() => _FormularioSolicitudState();
}

class _FormularioSolicitudState extends State<_FormularioSolicitud> {
  late String _situacion = 'Emergencia económica';
  late int _estrato = (widget.datos['ficha'] as Map)['estrato'] as int? ?? 1;
  final _personas = TextEditingController(text: '0');
  final _detalle = TextEditingController();
  bool _acepta = true;
  bool _enviando = false;
  ApiException? _error;

  Future<void> _enviar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final respuesta = await api.post('/alimentario', {
        'situacion': _situacion,
        'estrato': _estrato,
        'personas_a_cargo': int.tryParse(_personas.text),
        'detalle': _detalle.text,
        'acepta_datos': _acepta,
      });
      await widget.alEnviar(respuesta['mensaje']?.toString() ?? 'Solicitud registrada.');
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final situaciones = (widget.datos['situaciones'] as List).cast<Map<String, dynamic>>();
    return Seccion(
      titulo: '🛒 Pedir Donación de Apoyo Alimentario',
      subtitulo: 'Selecciona tu situación actual para que el sistema calcule tu nivel de prioridad de entrega.',
      children: [
        ResumenErrores(_error),
        const Text('¿Cuál es tu situación prioritaria? *', style: TextStyle(fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        for (final s in situaciones)
          OpcionTarjeta(
            icono: s['icono'] as String,
            titulo: s['titulo'] as String,
            texto: s['texto'] as String,
            color: Colores.alimentario,
            seleccionada: _situacion == s['valor'],
            alTocar: () => setState(() => _situacion = s['valor'] as String),
          ),
        const SizedBox(height: 6),
        Row(children: [
          Expanded(
            child: DropdownButtonFormField<int>(
              initialValue: _estrato,
              decoration: InputDecoration(labelText: 'Estrato', errorText: _error?.error('estrato')),
              items: List.generate(6, (i) => DropdownMenuItem(value: i + 1, child: Text('Estrato ${i + 1}'))),
              onChanged: (v) => setState(() => _estrato = v ?? 1),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: _personas,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: 'Personas a tu cargo', errorText: _error?.error('personas_a_cargo')),
            ),
          ),
        ]),
        const SizedBox(height: 12),
        TextField(
          controller: _detalle,
          minLines: 3,
          maxLines: 5,
          maxLength: widget.datos['detalle_max'] as int,
          decoration: InputDecoration(labelText: 'Detalle adicional o situación de emergencia (opcional)', alignLabelWithHint: true, errorText: _error?.error('detalle')),
        ),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          activeColor: Colores.alimentario,
          value: _acepta,
          onChanged: (v) => setState(() => _acepta = v ?? false),
          title: const Text(
            'Autorizo a la Vicerrectoría de Bienestar Universitario de la UCEVA al tratamiento confidencial de mis datos con fines asistenciales y de priorización de apoyos alimentarios, en cumplimiento de la Ley 1581 de 2012 (Habeas Data).',
            style: TextStyle(fontSize: 12.5, height: 1.35),
          ),
        ),
        const SizedBox(height: 8),
        BotonEnviar(texto: 'Pedir Donación de Apoyo Alimentario →', enviando: _enviando, color: Colores.alimentario, alPresionar: _acepta ? _enviar : null),
      ],
    );
  }
}

class _Historial extends StatelessWidget {
  const _Historial(this.solicitudes);

  final List<Map<String, dynamic>> solicitudes;

  @override
  Widget build(BuildContext context) {
    return Seccion(
      titulo: '📜 Historial de Solicitudes y Beneficios Recibidos',
      subtitulo: 'Trazabilidad institucional',
      children: [
        for (final s in solicitudes)
          Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(10)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('${s['fecha']} · ${s['situacion']}', style: const TextStyle(fontWeight: FontWeight.w700))),
                Pildora(s['estado'] as String, color: switch (s['estado']) {
                  'Pendiente' => Colores.ambar,
                  'Aprobada' => Colores.azul,
                  'Entregada' => Colores.verde,
                  _ => Colores.rojo,
                }),
              ]),
              if (s['entrega'] != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text('✓ Mercado entregado el ${s['entrega']['fecha']} (Lote: ${s['entrega']['lote'] ?? '-'} · ${s['entrega']['tipo'] ?? '-'}).',
                      style: const TextStyle(color: Colores.verde, fontSize: 13)),
                ),
              if (s['motivo_rechazo'] != null)
                Padding(padding: const EdgeInsets.only(top: 6), child: Text('Motivo: ${s['motivo_rechazo']}', style: const TextStyle(color: Colores.rojo, fontSize: 13))),
            ]),
          ),
      ],
    );
  }
}
