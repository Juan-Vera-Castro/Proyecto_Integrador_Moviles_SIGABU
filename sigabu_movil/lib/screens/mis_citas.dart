import 'package:flutter/material.dart';

import '../api/api.dart';
import '../theme.dart';
import '../utils/formato.dart';
import '../widgets/comunes.dart';
import 'consentimiento_general.dart';
import 'consentimiento_inclusion.dart';
import 'principal.dart';
import 'solicitar_cita.dart';

/// "Mis citas" (citas/miscitas.blade.php): próximas citas, solicitudes y casos, horarios que
/// propone el profesional, citas anteriores y el consentimiento informado.
class PantallaMisCitas extends StatefulWidget {
  const PantallaMisCitas({super.key});

  @override
  State<PantallaMisCitas> createState() => _PantallaMisCitasState();
}

class _PantallaMisCitasState extends State<PantallaMisCitas> {
  final _cargador = GlobalKey<CargadorState<Map<String, dynamic>>>();
  final Set<String> _enviando = {};

  Future<void> _accion(String clave, Future<Map<String, dynamic>> Function() peticion) async {
    setState(() => _enviando.add(clave));
    try {
      final respuesta = await peticion();
      if (!mounted) return;
      mostrarMensaje(context, respuesta['mensaje']?.toString() ?? 'Listo.');
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    } finally {
      if (mounted) setState(() => _enviando.remove(clave));
      await _cargador.currentState?.recargar();
    }
  }

  Future<void> _elegir(Map<String, dynamic> propuesta) async {
    if (!await confirmar(context, '¿Aceptas tu cita el ${propuesta['etiqueta']}?', si: 'Aceptar cita')) return;
    await _accion('p${propuesta['id']}', () => api.post('/propuestas/${propuesta['id']}/elegir'));
  }

  Future<void> _rechazar(Map<String, dynamic> caso, bool unica) async {
    final pregunta = unica ? '¿Rechazas esta solicitud de cita?' : '¿Ninguno de estos horarios te sirve? Se rechazará la solicitud.';
    if (!await confirmar(context, pregunta, si: 'Rechazar', peligro: true)) return;
    await _accion('r${caso['id']}', () => api.post('/casos/${caso['id']}/propuestas/rechazar'));
  }

  Future<void> _abrir(Widget pantalla) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => pantalla));
    await _cargador.currentState?.recargar();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: const MenuEstudiante(),
      appBar: AppBar(
        title: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Mis citas'),
          Text('Historial y estado de tus solicitudes de atención', style: TextStyle(fontSize: 13, color: Colores.gris, fontWeight: FontWeight.w500)),
        ]),
      ),
      body: Cargador<Map<String, dynamic>>(
        key: _cargador,
        cargar: () => api.get('/citas'),
        construir: (context, datos, recargar) {
          final casos = (datos['casos'] as List).cast<Map<String, dynamic>>();
          final proximas = (datos['proximas'] as List).cast<Map<String, dynamic>>();

          if (casos.isEmpty) {
            return RefreshIndicator(
              onRefresh: recargar,
              child: ListView(padding: const EdgeInsets.all(32), children: [
                const SizedBox(height: 60),
                const Center(child: Text('🗓️', style: TextStyle(fontSize: 56))),
                const SizedBox(height: 12),
                const Text('Aún no tienes citas', textAlign: TextAlign.center, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                const Text('Solicita tu primera sesión de acompañamiento', textAlign: TextAlign.center, style: TextStyle(color: Colores.gris)),
                const SizedBox(height: 20),
                FilledButton(onPressed: () => _abrir(const PantallaSolicitarCita()), child: const Text('Solicitar nueva cita')),
              ]),
            );
          }

          return RefreshIndicator(
            onRefresh: recargar,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                FilledButton.icon(
                  onPressed: () => _abrir(const PantallaSolicitarCita()),
                  icon: const Icon(Icons.add),
                  label: const Text('Solicitar nueva cita'),
                ),
                const SizedBox(height: 20),
                const Text('Próximas citas', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 10),
                if (proximas.isEmpty)
                  const Text('No tienes citas confirmadas próximamente. Cuando tu profesional agende una, aparecerá aquí.', style: TextStyle(color: Colores.gris))
                else
                  for (final cita in proximas) _TarjetaCita(cita),
                const SizedBox(height: 24),
                const Text('Mis solicitudes', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 10),
                for (final caso in casos) ...[_caso(caso), const SizedBox(height: 14)],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _caso(Map<String, dynamic> caso) {
    final propuestas = (caso['propuestas'] as List).cast<Map<String, dynamic>>();
    final historial = (caso['historial'] as List).cast<Map<String, dynamic>>();
    final rechazo = caso['rechazo'] as Map<String, dynamic>?;
    final consentimiento = caso['consentimiento'] as Map<String, dynamic>?;
    final estado = caso['estado'] as String;
    final colorEstado = switch (estado) {
      'Cita pendiente' => Colores.ambar,
      'Elige tu horario' => Colores.azul,
      'Rechazada' => Colores.rojo,
      'En atención' => Colores.verde,
      _ => Colores.gris,
    };
    final esInclusion = caso['es_inclusion'] == true;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${caso['servicio_icono']} ${caso['servicio_titulo']}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 2),
                  Text('${caso['codigo']} · solicitada el ${caso['fecha_solicitud']}', style: const TextStyle(color: Colores.gris, fontSize: 12)),
                ]),
              ),
              Pildora(estado, color: colorEstado),
            ]),
            const SizedBox(height: 14),
            Dato('Facultad y horario', caso['atencion'] as String?),
            Dato('Preferencia', caso['preferencia'] as String?),
            Dato('Profesional', caso['profesional'] as String?),
            Dato('Día y hora de la cita', caso['dia_hora_cita'] as String?),
            Dato('Motivo', caso['motivo'] as String?),

            // Horarios que propone el profesional: el estudiante acepta uno o los rechaza.
            if (propuestas.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: const Color(0xFFF0F9FF), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFBAE6FD))),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  const Text('Solicitud de cita', style: TextStyle(fontWeight: FontWeight.w800, color: Colores.azul)),
                  const SizedBox(height: 4),
                  Text(
                    '${caso['profesional'] ?? 'Tu profesional'} ${propuestas.length == 1 ? 'te propone tu próxima cita. ¿La aceptas?' : 'te propone estos horarios. Acepta el que mejor te quede.'}',
                    style: const TextStyle(height: 1.35),
                  ),
                  const SizedBox(height: 10),
                  for (final propuesta in propuestas)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(backgroundColor: Colors.white, foregroundColor: Colores.azul, alignment: Alignment.centerLeft),
                        onPressed: _enviando.isEmpty ? () => _elegir(propuesta) : null,
                        child: Row(children: [
                          Expanded(child: Text('📅 ${propuesta['etiqueta']}')),
                          _enviando.contains('p${propuesta['id']}')
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Text('✓ Aceptar', style: TextStyle(color: Colores.verde)),
                        ]),
                      ),
                    ),
                  TextButton(
                    style: TextButton.styleFrom(foregroundColor: Colores.rojo),
                    onPressed: _enviando.isEmpty ? () => _rechazar(caso, propuestas.length == 1) : null,
                    child: Text('✕ ${propuestas.length == 1 ? 'Rechazar la cita' : 'Ninguno me sirve: rechazar'}'),
                  ),
                ]),
              ),
            ],

            if (caso['aviso'] != null) ...[const SizedBox(height: 6), Aviso(caso['aviso'] as String)],
            if (rechazo != null) ...[
              const SizedBox(height: 6),
              Aviso(
                'Solicitud rechazada${rechazo['fecha'] != null ? ' el ${rechazo['fecha']}' : ''}',
                tipo: TipoAviso.error,
                hijos: [
                  if (rechazo['motivo'] != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(rechazo['motivo'] as String)),
                  TextButton(
                    style: TextButton.styleFrom(padding: EdgeInsets.zero),
                    onPressed: () => _abrir(const PantallaSolicitarCita()),
                    child: const Text('Solicitar una nueva cita con otro profesional'),
                  ),
                ],
              ),
            ],

            if (historial.isNotEmpty) ...[
              const SizedBox(height: 10),
              const Text('CITAS ANTERIORES', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Colores.gris, letterSpacing: 0.5)),
              const SizedBox(height: 6),
              for (final cita in historial)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(children: [
                    Expanded(child: Text(_fechaCorta(cita['fecha_hora'] as String))),
                    Text(cita['estado_asistencia']?.toString() ?? '', style: TextStyle(fontWeight: FontWeight.w700, color: _colorAsistencia(cita['estado_asistencia'] as String?))),
                  ]),
                ),
            ],

            // El estudiante firma su consentimiento (RQ-1) desde aquí.
            if (caso['firma_pendiente'] != null) ...[
              const SizedBox(height: 10),
              Aviso(caso['firma_pendiente'] as String, tipo: TipoAviso.alerta, hijos: [
                const SizedBox(height: 10),
                FilledButton.icon(
                  style: FilledButton.styleFrom(backgroundColor: Colores.ambar),
                  onPressed: () => _abrir(esInclusion ? PantallaConsentimientoInclusion(idCaso: caso['id'] as String) : PantallaConsentimiento(idCaso: caso['id'] as String)),
                  icon: const Icon(Icons.draw_outlined),
                  label: const Text('Firmar consentimiento'),
                ),
              ]),
            ],
            if (caso['tiene_consentimiento_inclusion'] == true) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => _abrir(PantallaVerConsentimientoInclusion(idCaso: caso['id'] as String)),
                icon: const Icon(Icons.description_outlined),
                label: const Text('Mi consentimiento'),
              ),
            ],
            if (consentimiento != null) ...[
              const SizedBox(height: 8),
              _ConsentimientoFirmado(consentimiento),
            ],
          ],
        ),
      ),
    );
  }

  String _fechaCorta(String valor) {
    final fecha = Fechas.leer(valor)!;
    return '${Fechas.corta(fecha)} · ${Fechas.hora(fecha)}';
  }

  Color _colorAsistencia(String? estado) => switch (estado) {
        'Asistió' => Colores.verde,
        'No asistió' => Colores.rojo,
        _ => Colores.gris,
      };
}

class _TarjetaCita extends StatelessWidget {
  const _TarjetaCita(this.cita);

  final Map<String, dynamic> cita;

  @override
  Widget build(BuildContext context) {
    final fecha = Fechas.leer(cita['fecha_hora'] as String)!;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            Container(
              width: 58,
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: BoxDecoration(color: Colores.verde, borderRadius: BorderRadius.circular(12)),
              child: Column(children: [
                Text(Fechas.mesCorto(fecha), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                Text('${fecha.day}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 22)),
              ]),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(Fechas.citaCompleta(fecha), style: const TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(
                  '${cita['servicio_icono']} ${cita['servicio_titulo']}${cita['profesional'] != null ? ' · con ${cita['profesional']}' : ''}',
                  style: const TextStyle(color: Colores.gris, fontSize: 13),
                ),
                if (cita['descripcion'] != null) Text(cita['descripcion'] as String, style: const TextStyle(color: Colores.gris, fontSize: 12, fontStyle: FontStyle.italic)),
              ]),
            ),
          ]),
        ),
      ),
    );
  }
}

/// Consentimiento firmado (RQ-1): el texto que se le leyó, sin la imagen de la firma (D-13).
class _ConsentimientoFirmado extends StatelessWidget {
  const _ConsentimientoFirmado(this.consentimiento);

  final Map<String, dynamic> consentimiento;

  @override
  Widget build(BuildContext context) {
    final version = consentimiento['version'];
    return Container(
      decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFBBF7D0))),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          shape: const Border(),
          tilePadding: const EdgeInsets.symmetric(horizontal: 12),
          leading: const Icon(Icons.verified_outlined, color: Colores.verde),
          title: Text(
            'Consentimiento informado aceptado el ${consentimiento['fecha']}${version != null ? ' (versión $version)' : ''}',
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Colores.verde),
          ),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
          children: [
            Container(
              constraints: const BoxConstraints(maxHeight: 280),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(8)),
              child: SingleChildScrollView(child: Text(consentimiento['texto']?.toString() ?? '', style: const TextStyle(fontSize: 13, height: 1.45))),
            ),
            if (consentimiento['pdf'] == true) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => descargarYAbrir(context, '/consentimientos/${consentimiento['id_aceptacion']}/documento.pdf', 'Consentimiento-${consentimiento['fecha']}.pdf'),
                icon: const Icon(Icons.picture_as_pdf_outlined),
                label: const Text('Descargar PDF'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
