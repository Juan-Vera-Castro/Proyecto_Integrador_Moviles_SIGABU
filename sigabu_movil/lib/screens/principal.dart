import 'package:flutter/material.dart';

import '../api/api.dart';
import '../theme.dart';
import '../widgets/comunes.dart';
import 'alimentario.dart';
import 'consentimiento_general.dart';
import 'consentimiento_inclusion.dart';
import 'inicio.dart';
import 'login.dart';
import 'mis_citas.dart';
import 'notificaciones.dart';
import 'perfil.dart';
import 'recursos.dart';
import 'reportar.dart';
import 'solicitar_cita.dart';

final llavePrincipal = GlobalKey<PantallaPrincipalState>();

/// Navegación del estudiante: las mismas secciones del menú lateral de la web. Las de uso
/// diario van en la barra inferior; el resto se abre desde el inicio o el menú.
class PantallaPrincipal extends StatefulWidget {
  PantallaPrincipal() : super(key: llavePrincipal);

  @override
  State<PantallaPrincipal> createState() => PantallaPrincipalState();
}

class PantallaPrincipalState extends State<PantallaPrincipal> {
  int _pestana = 0;
  int _avisosSinLeer = 0;

  // Cada vez que se vuelve a una pestaña se recarga, para ver lo último.
  final _versiones = [0, 0, 0, 0];

  void irA(int pestana) {
    setState(() {
      _pestana = pestana;
      _versiones[pestana]++;
    });
  }

  void actualizarAvisos(int sinLeer) {
    if (mounted && sinLeer != _avisosSinLeer) setState(() => _avisosSinLeer = sinLeer);
  }

  Future<void> cerrarSesion() async {
    if (!await confirmar(context, '¿Quieres cerrar sesión en este teléfono?', si: 'Cerrar sesión')) return;
    try {
      await api.post('/logout');
    } on ApiException {
      // Aunque falle la red, el token se borra del teléfono.
    }
    await api.borrarToken();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const PantallaLogin()), (_) => false);
  }

  /// Abre la pantalla de una ruta de la web (p. ej. la url de un aviso).
  Future<void> abrirRuta(String url) async {
    final ruta = Uri.parse(url).path;
    final consentimiento = RegExp(r'^/estudiante/casos/([0-9a-f-]{36})/(inclusion/)?consentimiento').firstMatch(ruta);
    final verInclusion = RegExp(r'^/inclusion/casos/([0-9a-f-]{36})/consentimiento').firstMatch(ruta);

    if (consentimiento != null) {
      final idCaso = consentimiento.group(1)!;
      await abrir(consentimiento.group(2) != null ? PantallaConsentimientoInclusion(idCaso: idCaso) : PantallaConsentimiento(idCaso: idCaso));
    } else if (verInclusion != null) {
      await abrir(PantallaVerConsentimientoInclusion(idCaso: verInclusion.group(1)!));
    } else if (ruta.startsWith('/estudiante/mis-citas')) {
      irA(1);
    } else if (ruta.startsWith('/estudiante/solicitar-cita')) {
      await abrir(const PantallaSolicitarCita());
    } else if (ruta.startsWith('/estudiante/reportar-situacion')) {
      await abrir(const PantallaReportar());
    } else if (ruta.startsWith('/estudiante/apoyo-alimentario')) {
      await abrir(const PantallaAlimentario());
    } else if (ruta.startsWith('/estudiante/recursos') || ruta.startsWith('/repositorio')) {
      await abrir(const PantallaRecursos());
    } else if (ruta.startsWith('/notificaciones')) {
      irA(2);
    } else {
      irA(0);
    }
  }

  Future<T?> abrir<T>(Widget pantalla) async {
    final resultado = await Navigator.of(context).push<T>(MaterialPageRoute(builder: (_) => pantalla));
    // Al volver se recarga la pestaña actual (una cita nueva, un consentimiento firmado...).
    if (mounted) irA(_pestana);
    return resultado;
  }

  @override
  Widget build(BuildContext context) {
    final pantallas = [
      PantallaInicio(key: ValueKey('inicio${_versiones[0]}')),
      PantallaMisCitas(key: ValueKey('citas${_versiones[1]}')),
      PantallaNotificaciones(key: ValueKey('avisos${_versiones[2]}')),
      PantallaPerfil(key: ValueKey('perfil${_versiones[3]}')),
    ];

    return Scaffold(
      body: IndexedStack(index: _pestana, children: pantallas),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _pestana,
        onDestinationSelected: irA,
        indicatorColor: Colores.verde.withValues(alpha: 0.15),
        destinations: [
          const NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home, color: Colores.verde), label: 'Inicio'),
          const NavigationDestination(icon: Icon(Icons.event_note_outlined), selectedIcon: Icon(Icons.event_note, color: Colores.verde), label: 'Mis citas'),
          NavigationDestination(
            icon: Badge(isLabelVisible: _avisosSinLeer > 0, label: Text('$_avisosSinLeer'), child: const Icon(Icons.notifications_outlined)),
            selectedIcon: const Icon(Icons.notifications, color: Colores.verde),
            label: 'Avisos',
          ),
          const NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person, color: Colores.verde), label: 'Perfil'),
        ],
      ),
    );
  }
}

/// Menú lateral con todas las secciones del estudiante, como en la web.
class MenuEstudiante extends StatelessWidget {
  const MenuEstudiante({super.key, this.nombre});

  final String? nombre;

  @override
  Widget build(BuildContext context) {
    final principal = llavePrincipal.currentState!;
    void ir(VoidCallback accion) {
      Navigator.pop(context);
      accion();
    }

    Widget item(IconData icono, String texto, VoidCallback accion, {Color? color}) => ListTile(
          leading: Icon(icono, color: color ?? Colors.white70),
          title: Text(texto, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
          onTap: () => ir(accion),
        );

    return Drawer(
      backgroundColor: Colores.verdeOscuro,
      child: SafeArea(
        child: ListView(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('SIGABU', style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900, letterSpacing: 1.5)),
                  const Text('Panel del estudiante', style: TextStyle(color: Colores.verdeNeon, fontWeight: FontWeight.w600)),
                  if (nombre != null) ...[
                    const SizedBox(height: 10),
                    Text(nombre!, style: const TextStyle(color: Colors.white70)),
                  ],
                ],
              ),
            ),
            item(Icons.home_outlined, 'Inicio', () => principal.irA(0)),
            item(Icons.add_circle_outline, 'Solicitar cita', () => principal.abrir(const PantallaSolicitarCita())),
            item(Icons.event_note_outlined, 'Mis citas', () => principal.irA(1)),
            item(Icons.shield_outlined, 'Reportar situación', () => principal.abrir(const PantallaReportar())),
            item(Icons.shopping_basket_outlined, 'Apoyo alimentario', () => principal.abrir(const PantallaAlimentario())),
            item(Icons.menu_book_outlined, 'Recursos', () => principal.abrir(const PantallaRecursos())),
            item(Icons.notifications_outlined, 'Avisos', () => principal.irA(2)),
            item(Icons.person_outline, 'Mi perfil', () => principal.irA(3)),
            const Divider(color: Colors.white24),
            item(Icons.logout, 'Cerrar sesión', principal.cerrarSesion, color: Colores.verdeNeon),
          ],
        ),
      ),
    );
  }
}
