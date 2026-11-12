import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api.dart';
import '../theme.dart';
import '../utils/formato.dart';
import '../widgets/comunes.dart';
import 'alimentario.dart';
import 'principal.dart';
import 'recursos.dart';
import 'reportar.dart';
import 'solicitar_cita.dart';

/// Inicio del estudiante (estudiante/home.blade.php): frases de Bienestar, su próxima cita,
/// las tareas terapéuticas pendientes (HU-013), consejos y la ayuda urgente.
class PantallaInicio extends StatefulWidget {
  const PantallaInicio({super.key});

  @override
  State<PantallaInicio> createState() => _PantallaInicioState();
}

class _PantallaInicioState extends State<PantallaInicio> {
  String _filtro = 'todas';
  final _cargador = GlobalKey<CargadorState<Map<String, dynamic>>>();

  Future<Map<String, dynamic>> _cargar() async {
    final datos = await api.get('/inicio', query: _filtro == 'todas' ? null : {'tareas': _filtro});
    llavePrincipal.currentState?.actualizarAvisos(datos['avisos_sin_leer'] as int? ?? 0);
    return datos;
  }

  String _saludo() {
    final hora = DateTime.now().hour;
    if (hora < 12) return 'Buen día';
    if (hora < 18) return 'Buenas tardes';
    return 'Buenas noches';
  }

  @override
  Widget build(BuildContext context) {
    return Cargador<Map<String, dynamic>>(
      key: _cargador,
      cargar: _cargar,
      construir: (context, datos, recargar) {
        final usuario = datos['usuario'] as Map<String, dynamic>;
        final nombre = (usuario['nombre'] as String? ?? '').split(' ').first;
        return Scaffold(
          drawer: MenuEstudiante(nombre: usuario['nombre'] as String?),
          appBar: AppBar(
            title: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${_saludo()}, $nombre 👋', style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800)),
                const Text('Estudiante UCEVA', style: TextStyle(fontSize: 13, color: Colores.gris, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
          body: RefreshIndicator(
            onRefresh: recargar,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                const _CarruselFrases(),
                const SizedBox(height: 16),
                _AccesosRapidos(),
                const SizedBox(height: 16),
                _tareas(datos),
                const SizedBox(height: 16),
                const _ConsejoBienestar(),
                const SizedBox(height: 16),
                const _AyudaUrgente(),
                const SizedBox(height: 8),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _tareas(Map<String, dynamic> datos) {
    final filtros = (datos['filtros'] as Map).map((k, v) => MapEntry(k.toString(), v.toString()));
    final tareas = (datos['tareas'] as List).cast<Map<String, dynamic>>();
    final cita = datos['proxima_cita'] as Map<String, dynamic>?;
    final hoy = datos['hoy'] as String;

    return Seccion(
      children: [
        Row(
          children: [
            const Expanded(child: Text('Recordatorio de tareas pendientes', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800))),
            PopupMenuButton<String>(
              initialValue: _filtro,
              tooltip: 'Filtrar tareas',
              onSelected: (valor) {
                setState(() => _filtro = valor);
                _cargador.currentState?.recargar();
              },
              itemBuilder: (_) => filtros.entries.map((e) => PopupMenuItem(value: e.key, child: Text(e.value))).toList(),
              child: Chip(
                avatar: const Icon(Icons.filter_list, size: 18),
                label: Text(_filtro == 'todas' ? 'Filtrar' : filtros[_filtro]!),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (cita != null) _ProximaCita(cita),
        if (tareas.isEmpty && cita == null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 20),
            child: Column(children: [
              const Text('✅', style: TextStyle(fontSize: 36)),
              const SizedBox(height: 8),
              Text(
                _filtro == 'todas' ? 'No tienes tareas pendientes' : 'No tienes tareas que venzan en los ${filtros[_filtro]!.toLowerCase()}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 4),
              const Text('Aquí aparecerán las actividades que te asigne tu profesional y tus próximas citas.',
                  textAlign: TextAlign.center, style: TextStyle(color: Colores.gris, fontSize: 13)),
            ]),
          )
        else if (tareas.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              _filtro == 'todas' ? 'No tienes tareas pendientes.' : 'No tienes tareas que venzan en los ${filtros[_filtro]!.toLowerCase()}.',
              style: const TextStyle(color: Colores.gris),
            ),
          ),
        for (final tarea in tareas) _Tarea(tarea, hoy),
      ],
    );
  }
}

class _ProximaCita extends StatelessWidget {
  const _ProximaCita(this.cita);

  final Map<String, dynamic> cita;

  @override
  Widget build(BuildContext context) {
    final fecha = Fechas.leer(cita['fecha_hora'] as String)!;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colores.verde.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => llavePrincipal.currentState?.irA(1),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              const Text('📅', style: TextStyle(fontSize: 28)),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('TU PRÓXIMA CITA', style: TextStyle(color: Colores.verde, fontWeight: FontWeight.w800, fontSize: 11, letterSpacing: 0.5)),
                  const SizedBox(height: 2),
                  Text(Fechas.citaCompleta(fecha), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                  if (cita['profesional'] != null) Text('Con ${cita['profesional']}', style: const TextStyle(color: Colores.gris, fontSize: 13)),
                ]),
              ),
              const Icon(Icons.chevron_right, color: Colores.verde),
            ]),
          ),
        ),
      ),
    );
  }
}

class _Tarea extends StatelessWidget {
  const _Tarea(this.tarea, this.hoy);

  final Map<String, dynamic> tarea;
  final String hoy;

  @override
  Widget build(BuildContext context) {
    final limite = tarea['fecha_limite'] as String?;
    final vencida = limite != null && limite.compareTo(hoy) < 0;
    final venceHoy = limite == hoy;
    final (texto, color) = switch ((limite, vencida, venceHoy)) {
      (null, _, _) => ('Sin fecha límite', Colores.gris),
      (_, true, _) => ('Venció el $limite', Colores.rojo),
      (_, _, true) => ('Vence hoy', Colores.ambar),
      _ => ('Hasta el $limite', Colores.morado),
    };
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFAF5FF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE9D5FF)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: Text(tarea['titulo'] as String, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
          const SizedBox(width: 8),
          Pildora(texto, color: color),
        ]),
        const SizedBox(height: 6),
        Text(tarea['descripcion'] as String? ?? '', style: const TextStyle(height: 1.35)),
        const SizedBox(height: 6),
        Text('Asignada el ${tarea['fecha_asignacion']} · ${tarea['codigo_expediente'] ?? ''}', style: const TextStyle(color: Colores.gris, fontSize: 12)),
      ]),
    );
  }
}

class _AccesosRapidos extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final principal = llavePrincipal.currentState;
    Widget acceso(String icono, String texto, Color color, VoidCallback alTocar) => Expanded(
          child: Material(
            color: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: const BorderSide(color: Color(0xFFE5E7EB))),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: alTocar,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 6),
                child: Column(children: [
                  CircleAvatar(radius: 22, backgroundColor: color.withValues(alpha: 0.12), child: Text(icono, style: const TextStyle(fontSize: 20))),
                  const SizedBox(height: 6),
                  Text(texto, textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                ]),
              ),
            ),
          ),
        );
    return IntrinsicHeight(
        child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      acceso('🗓️', 'Solicitar cita', Colores.verde, () => principal?.abrir(const PantallaSolicitarCita())),
      const SizedBox(width: 8),
      acceso('💜', 'Reportar situación', Colores.vbg, () => principal?.abrir(const PantallaReportar())),
      const SizedBox(width: 8),
      acceso('🛒', 'Apoyo alimentario', Colores.alimentario, () => principal?.abrir(const PantallaAlimentario())),
      const SizedBox(width: 8),
      acceso('📚', 'Recursos', Colores.azul, () => principal?.abrir(const PantallaRecursos())),
    ]));
  }
}

/// Carrusel de frases de Bienestar (cambia cada 6 segundos, como en la web).
class _CarruselFrases extends StatefulWidget {
  const _CarruselFrases();

  @override
  State<_CarruselFrases> createState() => _CarruselFrasesState();
}

class _CarruselFrasesState extends State<_CarruselFrases> {
  static const _frases = [
    ('Bienestar', '"Pedir ayuda es un acto de valentía. Estamos aquí para acompañarte en cada paso."'),
    ('Salud Mental', '"Tu salud mental importa tanto como tus notas. No tienes que cargarlo todo solo."'),
    ('Inclusión', '"Cada persona aprende diferente. La inclusión es nuestro compromiso contigo."'),
    ('Autocuidado', '"Un momento de pausa puede cambiar todo. Date permiso de descansar."'),
  ];

  final _paginas = PageController();
  Timer? _temporizador;
  int _actual = 0;

  @override
  void initState() {
    super.initState();
    _temporizador = Timer.periodic(const Duration(seconds: 6), (_) {
      if (!_paginas.hasClients) return;
      _paginas.animateToPage((_actual + 1) % _frases.length, duration: const Duration(milliseconds: 500), curve: Curves.easeInOut);
    });
  }

  @override
  void dispose() {
    _temporizador?.cancel();
    _paginas.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 170,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(colors: [Colores.verdeOscuro, Colores.verde], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Stack(children: [
        PageView.builder(
          controller: _paginas,
          itemCount: _frases.length,
          onPageChanged: (i) => setState(() => _actual = i),
          itemBuilder: (_, i) => Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 26),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(999)),
                child: Text(_frases[i].$1, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
              ),
              const SizedBox(height: 10),
              Expanded(child: Text(_frases[i].$2, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700, height: 1.3))),
              const Text('— Bienestar Universitario UCEVA', style: TextStyle(color: Colores.verdeNeon, fontSize: 12)),
            ]),
          ),
        ),
        Positioned(
          bottom: 10,
          left: 0,
          right: 0,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(
              _frases.length,
              (i) => AnimatedContainer(
                duration: const Duration(milliseconds: 250),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: i == _actual ? 18 : 7,
                height: 7,
                decoration: BoxDecoration(color: i == _actual ? Colors.white : Colors.white38, borderRadius: BorderRadius.circular(9)),
              ),
            ),
          ),
        ),
      ]),
    );
  }
}

class _ConsejoBienestar extends StatefulWidget {
  const _ConsejoBienestar();

  @override
  State<_ConsejoBienestar> createState() => _ConsejoBienestarState();
}

class _ConsejoBienestarState extends State<_ConsejoBienestar> {
  static const _consejos = [
    ('🚶', 'Una caminata de 10 minutos al aire libre reduce el cortisol significativamente.'),
    ('😮‍💨', 'Respira profundo: inhala 4 segundos, retén 4, exhala 4. Repítelo 3 veces.'),
    ('💤', 'Dormir 7-8 horas mejora la concentración y la memoria a largo plazo.'),
    ('📵', 'Desconéctate de pantallas 30 min antes de dormir para un sueño más profundo.'),
    ('🤝', 'Hablar con alguien de confianza sobre lo que sientes alivia la carga emocional.'),
  ];

  int _actual = 0;
  Timer? _temporizador;

  @override
  void initState() {
    super.initState();
    _temporizador = Timer.periodic(const Duration(seconds: 15), (_) => setState(() => _actual = (_actual + 1) % _consejos.length));
  }

  @override
  void dispose() {
    _temporizador?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final (icono, texto) = _consejos[_actual];
    return Seccion(
      color: const Color(0xFFFFFBEB),
      children: [
        const Text('💡 CONSEJO DE BIENESTAR', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: Colores.ambar, letterSpacing: 0.5)),
        const SizedBox(height: 10),
        GestureDetector(
          onTap: () => setState(() => _actual = (_actual + 1) % _consejos.length),
          child: AnimatedSwitcher(
            duration: const Duration(milliseconds: 400),
            child: Row(key: ValueKey(_actual), children: [
              Text(icono, style: const TextStyle(fontSize: 30)),
              const SizedBox(width: 12),
              Expanded(child: Text(texto, style: const TextStyle(fontWeight: FontWeight.w600, height: 1.35))),
            ]),
          ),
        ),
      ],
    );
  }
}

class _AyudaUrgente extends StatelessWidget {
  const _AyudaUrgente();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(colors: [Color(0xFFBE123C), Color(0xFFE11D48)]),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('● ¿NECESITAS AYUDA URGENTE?', style: TextStyle(color: Colors.white70, fontWeight: FontWeight.w800, fontSize: 12, letterSpacing: 0.5)),
        const SizedBox(height: 6),
        const Text('Si estás en una situación de crisis, no estás solo.', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        const Text('Línea 106 — atención gratuita las 24 horas, los 7 días de la semana.', style: TextStyle(color: Colors.white)),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(
            child: FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: Colores.rojo),
              onPressed: () => launchUrl(Uri.parse('tel:106')),
              icon: const Icon(Icons.call),
              label: const Text('Llamar al 106'),
            ),
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Bienestar UCEVA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
              Text('Lun-Vie 8am-5pm', style: TextStyle(color: Colors.white70, fontSize: 12)),
            ]),
          ),
        ]),
      ]),
    );
  }
}
