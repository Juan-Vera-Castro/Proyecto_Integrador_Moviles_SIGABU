import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sigabu_movil/utils/formato.dart';
import 'package:sigabu_movil/widgets/firma.dart';

void main() {
  group('TextoConsentimiento (igual que TextoConsentimiento::renderizar en Laravel)', () {
    final texto = TextoConsentimiento({
      'marcadores': ['nombre_estudiante', 'eps_ips', 'acudiente_notificacion', 'profesional'],
      'campos_de_firma': {'eps_ips': 'eps_ips', 'acudiente_notificacion': 'contacto_urgencia'},
      'datos_base': {'nombre_estudiante': 'Ana Pérez', 'profesional': 'Dr. Ruiz'},
      'vacio': '____',
    });

    test('llena los marcadores conocidos con los datos base y los del formulario', () {
      final resultado = texto.renderizar('Yo, {nombre_estudiante}, afiliada a {eps_ips}, atendida por {profesional}.', {'eps_ips': 'Nueva EPS'});
      expect(resultado, 'Yo, Ana Pérez, afiliada a Nueva EPS, atendida por Dr. Ruiz.');
    });

    test('los marcadores sin dato quedan como ____ y los desconocidos tal cual', () {
      final resultado = texto.renderizar('{eps_ips} / {acudiente_notificacion} / {desconocido}', {'eps_ips': '  '});
      expect(resultado, '____ / ____ / {desconocido}');
    });
  });

  test('la firma se exporta como PNG transparente del tamaño que exige el servidor', () async {
    TestWidgetsFlutterBinding.ensureInitialized();
    final firma = FirmaController();
    expect(firma.vacia, isTrue);
    expect(firma.suficiente, isFalse);

    firma.empezar(const Offset(0.1, 0.5));
    for (var x = 0.1; x <= 0.9; x += 0.05) {
      firma.mover(Offset(x, 0.4 + (x * 10 % 2) * 0.1));
    }
    expect(firma.suficiente, isTrue);

    final dataUrl = await firma.exportar();
    expect(dataUrl.startsWith('data:image/png;base64,'), isTrue);
    final png = base64Decode(dataUrl.substring('data:image/png;base64,'.length));
    final datos = ByteData.sublistView(Uint8List.fromList(png));
    // Cabecera IHDR: ancho y alto (big endian) y tipo de color 6 (RGBA, con transparencia).
    expect(datos.getUint32(16), FirmaController.anchoExportado);
    expect(datos.getUint32(20), FirmaController.altoExportado);
    expect(png[25], 6);
    expect(png.length, lessThan(262144));

    firma.borrar();
    expect(firma.vacia, isTrue);
  });
}
