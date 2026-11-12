import 'package:flutter/material.dart';

import '../api/api.dart';
import '../theme.dart';
import '../widgets/comunes.dart';
import 'principal.dart';

class PantallaLogin extends StatefulWidget {
  const PantallaLogin({super.key, this.mensaje});

  final String? mensaje;

  @override
  State<PantallaLogin> createState() => _PantallaLoginState();
}

class _PantallaLoginState extends State<PantallaLogin> {
  final _correo = TextEditingController();
  final _contrasena = TextEditingController();
  bool _ocultar = true;
  bool _enviando = false;
  ApiException? _error;

  Future<void> _entrar() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final datos = await api.post('/login', {
        'email': _correo.text.trim(),
        'password': _contrasena.text,
        'dispositivo': 'App móvil Flutter',
      });
      await api.guardarToken(datos['token'] as String);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => PantallaPrincipal()));
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colores.verdeOscuro,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                const Text('SIGABU', style: TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w900, letterSpacing: 2)),
                const SizedBox(height: 4),
                const Text('Bienestar Universitario · UCEVA', style: TextStyle(color: Colores.verdeNeon, fontWeight: FontWeight.w600)),
                const SizedBox(height: 28),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: AutofillGroup(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text('Iniciar sesión', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
                          const SizedBox(height: 4),
                          const Text('Ingresa con tu correo institucional.', style: TextStyle(color: Colores.gris)),
                          const SizedBox(height: 18),
                          if (widget.mensaje != null && _error == null) ...[
                            Aviso(widget.mensaje!, tipo: TipoAviso.alerta),
                            const SizedBox(height: 14),
                          ],
                          if (_error != null) ...[
                            Aviso(_error!.mensaje, tipo: TipoAviso.error),
                            const SizedBox(height: 14),
                          ],
                          TextField(
                            controller: _correo,
                            keyboardType: TextInputType.emailAddress,
                            autofillHints: const [AutofillHints.email],
                            textInputAction: TextInputAction.next,
                            decoration: const InputDecoration(labelText: 'Correo institucional', hintText: 'nombre@uceva.edu.co', prefixIcon: Icon(Icons.mail_outline)),
                          ),
                          const SizedBox(height: 14),
                          TextField(
                            controller: _contrasena,
                            obscureText: _ocultar,
                            autofillHints: const [AutofillHints.password],
                            onSubmitted: (_) => _entrar(),
                            decoration: InputDecoration(
                              labelText: 'Contraseña',
                              prefixIcon: const Icon(Icons.lock_outline),
                              suffixIcon: IconButton(
                                tooltip: _ocultar ? 'Mostrar contraseña' : 'Ocultar contraseña',
                                icon: Icon(_ocultar ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                onPressed: () => setState(() => _ocultar = !_ocultar),
                              ),
                            ),
                          ),
                          const SizedBox(height: 20),
                          BotonEnviar(texto: 'Entrar', enviando: _enviando, alPresionar: _entrar),
                          const SizedBox(height: 12),
                          const Text(
                            '¿Olvidaste tu contraseña? Recupérala desde SIGABU web o comunícate con Bienestar Universitario.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: Colores.gris, fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
