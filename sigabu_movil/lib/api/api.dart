import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';

import '../config.dart';

/// Error de la API con el mensaje que da el servidor y, si es de validación (422), los
/// errores por campo.
class ApiException implements Exception {
  ApiException(this.mensaje, {this.estado = 0, this.errores = const {}});

  final String mensaje;
  final int estado;
  final Map<String, List<String>> errores;

  bool get sinSesion => estado == 401;

  /// Primer error de un campo (o de sus hijos, p. ej. "contactos.0.nombre").
  String? error(String campo) => errores[campo]?.first;

  /// Todos los mensajes, sin repetir: para el resumen de errores.
  List<String> get todos {
    final lista = errores.values.expand((e) => e).toSet().toList();
    return lista.isEmpty ? [mensaje] : lista;
  }

  @override
  String toString() => mensaje;
}

/// Cliente de la API móvil: guarda el token de forma segura y lo envía en cada petición.
class Api {
  Api._();

  static final Api instancia = Api._();

  static const _almacen = FlutterSecureStorage();
  static const _claveToken = 'sigabu_token';
  static const _espera = Duration(seconds: 30);

  String? _token;

  /// Se llama cuando el servidor dice que la sesión ya no vale (401): vuelve al login.
  void Function()? alPerderSesion;

  Future<bool> cargarToken() async {
    _token = await _almacen.read(key: _claveToken);
    return _token != null;
  }

  Future<void> guardarToken(String token) async {
    _token = token;
    await _almacen.write(key: _claveToken, value: token);
  }

  Future<void> borrarToken() async {
    _token = null;
    await _almacen.delete(key: _claveToken);
  }

  Map<String, String> get _cabeceras => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Uri _uri(String ruta, [Map<String, String>? query]) =>
      Uri.parse('${Config.api}$ruta').replace(queryParameters: query);

  Future<Map<String, dynamic>> get(String ruta, {Map<String, String>? query}) =>
      _enviar(() => http.get(_uri(ruta, query), headers: _cabeceras));

  Future<Map<String, dynamic>> post(String ruta, [Map<String, dynamic>? datos]) =>
      _enviar(() => http.post(_uri(ruta), headers: _cabeceras, body: jsonEncode(datos ?? {})));

  Future<Map<String, dynamic>> put(String ruta, [Map<String, dynamic>? datos]) =>
      _enviar(() => http.put(_uri(ruta), headers: _cabeceras, body: jsonEncode(datos ?? {})));

  Future<Map<String, dynamic>> patch(String ruta, [Map<String, dynamic>? datos]) =>
      _enviar(() => http.patch(_uri(ruta), headers: _cabeceras, body: jsonEncode(datos ?? {})));

  /// Descarga un archivo (PDF, recurso) a la carpeta temporal y devuelve su ruta.
  Future<String> descargar(String ruta, String nombre) async {
    final http.Response respuesta;
    try {
      respuesta = await http.get(_uri(ruta), headers: {..._cabeceras, 'Accept': '*/*'}).timeout(const Duration(seconds: 60));
    } on SocketException {
      throw ApiException('No hay conexión con SIGABU. Revisa tu internet.');
    } on TimeoutException {
      throw ApiException('SIGABU tardó demasiado en responder. Intenta de nuevo.');
    }
    if (respuesta.statusCode != 200) {
      throw _error(respuesta);
    }
    final carpeta = await getTemporaryDirectory();
    final archivo = File('${carpeta.path}/${_nombreSeguro(nombre)}');
    await archivo.writeAsBytes(respuesta.bodyBytes);
    return archivo.path;
  }

  Future<Map<String, dynamic>> _enviar(Future<http.Response> Function() peticion) async {
    final http.Response respuesta;
    try {
      respuesta = await peticion().timeout(_espera);
    } on SocketException {
      throw ApiException('No hay conexión con SIGABU. Revisa tu internet e intenta de nuevo.');
    } on TimeoutException {
      throw ApiException('SIGABU tardó demasiado en responder. Intenta de nuevo.');
    } on http.ClientException {
      throw ApiException('No hay conexión con SIGABU. Revisa tu internet e intenta de nuevo.');
    }

    if (respuesta.statusCode >= 200 && respuesta.statusCode < 300) {
      if (respuesta.body.isEmpty) return {};
      final datos = jsonDecode(utf8.decode(respuesta.bodyBytes));
      return datos is Map<String, dynamic> ? datos : {'datos': datos};
    }

    final error = _error(respuesta);
    if (error.sinSesion) {
      await borrarToken();
      alPerderSesion?.call();
    }
    throw error;
  }

  ApiException _error(http.Response respuesta) {
    Map<String, dynamic> datos = {};
    try {
      final decodificado = jsonDecode(utf8.decode(respuesta.bodyBytes));
      if (decodificado is Map<String, dynamic>) datos = decodificado;
    } catch (_) {}

    final errores = <String, List<String>>{};
    final crudos = datos['errors'];
    if (crudos is Map) {
      crudos.forEach((campo, mensajes) {
        errores[campo.toString()] = (mensajes as List).map((m) => m.toString()).toList();
      });
    }

    final mensaje = switch (respuesta.statusCode) {
      401 => 'Tu sesión terminó. Vuelve a iniciar sesión.',
      403 => datos['message']?.toString().isNotEmpty == true && datos['message'] != 'This action is unauthorized.'
          ? datos['message'].toString()
          : 'No tienes permiso para hacer esto.',
      404 => 'No encontramos lo que buscabas.',
      422 => errores.values.isNotEmpty ? errores.values.first.first : (datos['message']?.toString() ?? 'Revisa los datos.'),
      429 => 'Hiciste demasiados intentos. Espera un momento.',
      >= 500 => datos['message']?.toString().isNotEmpty == true && respuesta.statusCode == 503
          ? datos['message'].toString()
          : 'SIGABU tuvo un problema. Intenta de nuevo en unos minutos.',
      _ => datos['message']?.toString() ?? 'No fue posible completar la acción.',
    };

    return ApiException(mensaje, estado: respuesta.statusCode, errores: errores);
  }

  String _nombreSeguro(String nombre) => nombre.replaceAll(RegExp(r'[\\/:*?"<>|]'), '_');
}

final api = Api.instancia;
