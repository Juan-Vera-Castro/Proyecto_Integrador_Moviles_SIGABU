/// Dirección del backend de SIGABU (Laravel).
///
/// Por defecto apunta al servidor de prueba en el computador visto desde el emulador de
/// Android (10.0.2.2 es el "localhost" del computador). Para otro servidor:
///   flutter run --dart-define=API_URL=https://sigabu.uceva.edu.co
class Config {
  static const String apiUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'http://10.0.2.2:8123',
  );

  static String get api => '$apiUrl/api/movil';
}
