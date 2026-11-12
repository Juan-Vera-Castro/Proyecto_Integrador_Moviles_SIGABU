import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'api/api.dart';
import 'screens/login.dart';
import 'screens/principal.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('es');
  final conSesion = await api.cargarToken();
  runApp(SigabuApp(conSesion: conSesion));
}

final navegador = GlobalKey<NavigatorState>();

class SigabuApp extends StatefulWidget {
  const SigabuApp({super.key, required this.conSesion});

  final bool conSesion;

  @override
  State<SigabuApp> createState() => _SigabuAppState();
}

class _SigabuAppState extends State<SigabuApp> {
  @override
  void initState() {
    super.initState();
    // Si el token vence o se revoca, cualquier pantalla vuelve al login.
    api.alPerderSesion = () {
      navegador.currentState?.pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const PantallaLogin(mensaje: 'Tu sesión terminó. Vuelve a iniciar sesión.')),
        (_) => false,
      );
    };
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SIGABU Estudiante',
      debugShowCheckedModeBanner: false,
      navigatorKey: navegador,
      theme: temaSigabu(),
      locale: const Locale('es', 'CO'),
      supportedLocales: const [Locale('es', 'CO'), Locale('es')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: widget.conSesion ? PantallaPrincipal() : const PantallaLogin(),
    );
  }
}
