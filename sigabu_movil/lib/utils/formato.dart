import 'package:intl/intl.dart';

/// Fechas como en la web: "jueves 13 de noviembre · 9:00 am".
class Fechas {
  static DateTime? leer(String? valor) => valor == null ? null : DateTime.tryParse(valor);

  static String diaLargo(DateTime fecha) => DateFormat("EEEE d 'de' MMMM", 'es').format(fecha);

  /// "9:00 am", como format('g:i a') en Laravel.
  static String hora(DateTime fecha) => '${DateFormat('h:mm').format(fecha)} ${fecha.hour < 12 ? 'am' : 'pm'}';

  static String mesCorto(DateTime fecha) => DateFormat('MMM', 'es').format(fecha).replaceAll('.', '').toUpperCase();

  static String citaCompleta(DateTime fecha) {
    final dia = diaLargo(fecha);
    return '${dia[0].toUpperCase()}${dia.substring(1)} · ${hora(fecha)}';
  }

  static String corta(DateTime fecha) => DateFormat('yyyy-MM-dd').format(fecha);

  static String relativa(DateTime fecha) {
    final diferencia = DateTime.now().difference(fecha);
    if (diferencia.inMinutes < 1) return 'hace un momento';
    if (diferencia.inMinutes < 60) return 'hace ${diferencia.inMinutes} min';
    if (diferencia.inHours < 24) return 'hace ${diferencia.inHours} h';
    if (diferencia.inDays < 7) return 'hace ${diferencia.inDays} ${diferencia.inDays == 1 ? 'día' : 'días'}';
    return DateFormat('d MMM yyyy', 'es').format(fecha);
  }
}

/// Texto del consentimiento con sus marcadores llenos, igual que TextoConsentimiento::renderizar
/// en el servidor (y que firma-tactil.js en la web): los marcadores conocidos sin dato quedan
/// como "____" y los desconocidos se dejan tal cual. El servidor vuelve a armar el texto al
/// guardar; esto es solo la vista previa mientras se llena el formulario.
class TextoConsentimiento {
  TextoConsentimiento(Map<String, dynamic> datos)
      : marcadores = (datos['marcadores'] as List).cast<String>().toSet(),
        camposDeFirma = (datos['campos_de_firma'] as Map).map((k, v) => MapEntry(k.toString(), v.toString())),
        datosBase = (datos['datos_base'] as Map).map((k, v) => MapEntry(k.toString(), v?.toString())),
        vacio = datos['vacio']?.toString() ?? '____';

  final Set<String> marcadores;
  final Map<String, String> camposDeFirma;
  final Map<String, String?> datosBase;
  final String vacio;

  static final _patron = RegExp(r'\{([a-z_]+)\}');

  String renderizar(String plantilla, Map<String, String?> campos) {
    final datos = <String, String?>{...datosBase};
    camposDeFirma.forEach((marcador, campo) => datos[marcador] = campos[campo]);
    return plantilla.replaceAllMapped(_patron, (m) {
      final clave = m.group(1)!;
      if (!marcadores.contains(clave)) return m.group(0)!;
      final valor = (datos[clave] ?? '').trim();
      return valor.isEmpty ? vacio : valor;
    });
  }
}
