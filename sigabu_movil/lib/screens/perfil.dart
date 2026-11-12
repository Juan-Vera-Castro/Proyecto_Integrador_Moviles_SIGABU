import 'package:flutter/material.dart';

import '../api/api.dart';
import '../config.dart';
import '../theme.dart';
import '../widgets/comunes.dart';
import 'principal.dart';

/// Perfil (profile/edit.blade.php): datos personales, datos académicos registrados, cambio de
/// contraseña y cierre de sesión.
class PantallaPerfil extends StatelessWidget {
  const PantallaPerfil({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: const MenuEstudiante(),
      appBar: AppBar(title: const Text('Mi perfil')),
      body: Cargador<Map<String, dynamic>>(
        cargar: () => api.get('/perfil'),
        construir: (context, datos, recargar) {
          final usuario = datos['usuario'] as Map<String, dynamic>;
          final academicos = datos['academicos'] as Map<String, dynamic>?;
          return RefreshIndicator(
            onRefresh: recargar,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Center(
                  child: Column(children: [
                    CircleAvatar(
                      radius: 38,
                      backgroundColor: Colores.verde,
                      child: Text(_iniciales(usuario['nombre'] as String? ?? ''), style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800)),
                    ),
                    const SizedBox(height: 10),
                    Text(usuario['nombre'] as String? ?? '', style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800)),
                    Text('Estudiante · código ${usuario['codigo'] ?? ''}', style: const TextStyle(color: Colores.gris)),
                  ]),
                ),
                const SizedBox(height: 18),
                _DatosPersonales(usuario: usuario),
                const SizedBox(height: 14),
                Seccion(titulo: 'Datos académicos', subtitulo: 'Según tu registro en la universidad. Si algo no es correcto, comunícate con Bienestar Universitario.', children: [
                  Dato('Documento', '${usuario['tipo_documento'] ?? ''} ${usuario['documento'] ?? ''}'.trim()),
                  Dato('Vinculación', academicos?['vinculacion'] as String?),
                  Dato('Facultad', academicos?['facultad'] as String?),
                  Dato('Programa', academicos?['programa'] as String?),
                  Dato('Jornada', academicos?['jornada'] as String?),
                  Dato('Semestre', academicos?['semestre']?.toString()),
                ]),
                const SizedBox(height: 14),
                const _CambiarContrasena(),
                const SizedBox(height: 14),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(foregroundColor: Colores.rojo),
                  onPressed: () => llavePrincipal.currentState?.cerrarSesion(),
                  icon: const Icon(Icons.logout),
                  label: const Text('Cerrar sesión'),
                ),
                const SizedBox(height: 12),
                Text('Servidor: ${Config.apiUrl}', textAlign: TextAlign.center, style: const TextStyle(color: Colores.gris, fontSize: 11)),
                const SizedBox(height: 12),
              ],
            ),
          );
        },
      ),
    );
  }

  static String _iniciales(String nombre) {
    final partes = nombre.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (partes.isEmpty) return '?';
    return (partes.first[0] + (partes.length > 1 ? partes[1][0] : '')).toUpperCase();
  }
}

class _DatosPersonales extends StatefulWidget {
  const _DatosPersonales({required this.usuario});

  final Map<String, dynamic> usuario;

  @override
  State<_DatosPersonales> createState() => _DatosPersonalesState();
}

class _DatosPersonalesState extends State<_DatosPersonales> {
  late final _nombre = TextEditingController(text: widget.usuario['nombre'] as String? ?? '');
  late final _correo = TextEditingController(text: widget.usuario['correo'] as String? ?? '');
  bool _enviando = false;
  ApiException? _error;

  Future<void> _guardar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final respuesta = await api.patch('/perfil', {'name': _nombre.text.trim(), 'email': _correo.text.trim()});
      if (mounted) mostrarMensaje(context, respuesta['mensaje']?.toString() ?? 'Datos actualizados.');
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Seccion(
      titulo: 'Información del perfil',
      subtitulo: 'Actualiza tu nombre y tu correo institucional.',
      children: [
        TextField(controller: _nombre, decoration: InputDecoration(labelText: 'Nombre', errorText: _error?.error('name'))),
        const SizedBox(height: 12),
        TextField(
          controller: _correo,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(labelText: 'Correo institucional', errorText: _error?.error('email')),
        ),
        const SizedBox(height: 14),
        BotonEnviar(texto: 'Guardar', enviando: _enviando, alPresionar: _guardar),
      ],
    );
  }
}

class _CambiarContrasena extends StatefulWidget {
  const _CambiarContrasena();

  @override
  State<_CambiarContrasena> createState() => _CambiarContrasenaState();
}

class _CambiarContrasenaState extends State<_CambiarContrasena> {
  final _actual = TextEditingController();
  final _nueva = TextEditingController();
  final _confirmacion = TextEditingController();
  bool _enviando = false;
  ApiException? _error;

  Future<void> _guardar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final respuesta = await api.put('/perfil/contrasena', {
        'current_password': _actual.text,
        'password': _nueva.text,
        'password_confirmation': _confirmacion.text,
      });
      _actual.clear();
      _nueva.clear();
      _confirmacion.clear();
      if (mounted) mostrarMensaje(context, respuesta['mensaje']?.toString() ?? 'Contraseña actualizada.');
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Seccion(
      titulo: 'Actualizar contraseña',
      subtitulo: 'Usa una contraseña larga y difícil de adivinar.',
      children: [
        TextField(controller: _actual, obscureText: true, decoration: InputDecoration(labelText: 'Contraseña actual', errorText: _error?.error('current_password'))),
        const SizedBox(height: 12),
        TextField(controller: _nueva, obscureText: true, decoration: InputDecoration(labelText: 'Nueva contraseña', errorText: _error?.error('password'))),
        const SizedBox(height: 12),
        TextField(controller: _confirmacion, obscureText: true, decoration: const InputDecoration(labelText: 'Confirmar contraseña')),
        const SizedBox(height: 14),
        BotonEnviar(texto: 'Cambiar contraseña', enviando: _enviando, alPresionar: _guardar),
      ],
    );
  }
}
