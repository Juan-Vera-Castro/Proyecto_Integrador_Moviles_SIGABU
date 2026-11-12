import 'package:flutter/material.dart';

import '../api/api.dart';
import '../theme.dart';
import '../utils/formato.dart';
import '../widgets/comunes.dart';
import 'principal.dart';

/// Avisos de la campana (notificaciones/index.blade.php). Tocar un aviso lo marca como leído
/// y abre la pantalla donde se atiende.
class PantallaNotificaciones extends StatefulWidget {
  const PantallaNotificaciones({super.key});

  @override
  State<PantallaNotificaciones> createState() => _PantallaNotificacionesState();
}

class _PantallaNotificacionesState extends State<PantallaNotificaciones> {
  final _cargador = GlobalKey<CargadorState<Map<String, dynamic>>>();
  final List<Map<String, dynamic>> _avisos = [];
  int _pagina = 1;
  int _ultima = 1;
  bool _cargandoMas = false;

  Future<Map<String, dynamic>> _cargar() async {
    final datos = await api.get('/notificaciones');
    _avisos
      ..clear()
      ..addAll((datos['avisos'] as List).cast<Map<String, dynamic>>());
    _pagina = 1;
    _ultima = datos['ultima_pagina'] as int? ?? 1;
    llavePrincipal.currentState?.actualizarAvisos(datos['sin_leer'] as int? ?? 0);
    return datos;
  }

  Future<void> _mas() async {
    setState(() => _cargandoMas = true);
    try {
      final datos = await api.get('/notificaciones', query: {'page': '${_pagina + 1}'});
      setState(() {
        _avisos.addAll((datos['avisos'] as List).cast<Map<String, dynamic>>());
        _pagina++;
      });
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    } finally {
      if (mounted) setState(() => _cargandoMas = false);
    }
  }

  Future<void> _abrir(Map<String, dynamic> aviso) async {
    try {
      final respuesta = await api.post('/notificaciones/${aviso['id']}/leer');
      await llavePrincipal.currentState?.abrirRuta(respuesta['url']?.toString() ?? '/');
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    }
  }

  Future<void> _leerTodas() async {
    try {
      final respuesta = await api.post('/notificaciones/leer-todas');
      if (mounted) mostrarMensaje(context, respuesta['mensaje']?.toString() ?? 'Listo.');
      await _cargador.currentState?.recargar();
    } on ApiException catch (e) {
      if (mounted) mostrarMensaje(context, e.mensaje, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: const MenuEstudiante(),
      appBar: AppBar(
        title: const Text('Avisos'),
        actions: [TextButton(onPressed: _leerTodas, child: const Text('Marcar todos como leídos'))],
      ),
      body: Cargador<Map<String, dynamic>>(
        key: _cargador,
        cargar: _cargar,
        construir: (context, datos, recargar) {
          if (_avisos.isEmpty) {
            return RefreshIndicator(
              onRefresh: recargar,
              child: ListView(padding: const EdgeInsets.all(32), children: const [
                SizedBox(height: 60),
                Center(child: Text('🔔', style: TextStyle(fontSize: 56))),
                SizedBox(height: 12),
                Text('No tienes avisos', textAlign: TextAlign.center, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                SizedBox(height: 4),
                Text('Aquí te avisaremos de tus citas, consentimientos y solicitudes.', textAlign: TextAlign.center, style: TextStyle(color: Colores.gris)),
              ]),
            );
          }
          return RefreshIndicator(
            onRefresh: recargar,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: _avisos.length + (_pagina < _ultima ? 1 : 0),
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, i) {
                if (i == _avisos.length) {
                  return OutlinedButton(onPressed: _cargandoMas ? null : _mas, child: Text(_cargandoMas ? 'Cargando…' : 'Ver avisos anteriores'));
                }
                final aviso = _avisos[i];
                final leido = aviso['leido'] == true;
                final prioritario = aviso['prioridad'] == 'alta';
                final fecha = Fechas.leer(aviso['fecha'] as String?);
                return Material(
                  color: leido ? Colors.white : Colores.verde.withValues(alpha: 0.06),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                    side: BorderSide(color: prioritario && !leido ? Colores.rojo : const Color(0xFFE5E7EB)),
                  ),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () => _abrir(aviso),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Icon(prioritario ? Icons.priority_high : Icons.notifications_outlined, color: leido ? Colores.gris : (prioritario ? Colores.rojo : Colores.verde)),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(aviso['titulo'] as String, style: TextStyle(fontWeight: leido ? FontWeight.w600 : FontWeight.w800)),
                            const SizedBox(height: 2),
                            Text(aviso['mensaje'] as String, style: const TextStyle(color: Colores.gris, height: 1.35)),
                            if (fecha != null) ...[const SizedBox(height: 4), Text(Fechas.relativa(fecha), style: const TextStyle(fontSize: 12, color: Colores.gris))],
                          ]),
                        ),
                        if (!leido) const Padding(padding: EdgeInsets.only(top: 4), child: CircleAvatar(radius: 5, backgroundColor: Colores.verde)),
                      ]),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
