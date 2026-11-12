import 'dart:convert';
import 'dart:ui' as ui;

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';

import '../theme.dart';

/// Trazos de una firma táctil. Los puntos se guardan relativos al recuadro (0 a 1), así la
/// imagen exportada no depende del tamaño de la pantalla.
class FirmaController extends ChangeNotifier {
  final List<List<Offset>> _trazos = [];

  /// Tamaño de la imagen que se envía: PNG transparente de 8 bits, como el lienzo de la web
  /// (el servidor exige entre 200x80 y 2000x1000 px y menos de 256 KB).
  static const anchoExportado = 900;
  static const altoExportado = 338;

  List<List<Offset>> get trazos => _trazos;

  bool get vacia => _trazos.every((t) => t.length < 2);

  /// Largo total del trazo (relativo): sirve para no aceptar un simple punto.
  double get largo {
    var total = 0.0;
    for (final trazo in _trazos) {
      for (var i = 1; i < trazo.length; i++) {
        total += (trazo[i] - trazo[i - 1]).distance;
      }
    }
    return total;
  }

  bool get suficiente => !vacia && largo > 0.35;

  void empezar(Offset punto) {
    _trazos.add([punto]);
    notifyListeners();
  }

  void mover(Offset punto) {
    if (_trazos.isEmpty) return;
    _trazos.last.add(punto);
    notifyListeners();
  }

  void borrar() {
    _trazos.clear();
    notifyListeners();
  }

  /// La firma como data URL de un PNG ("data:image/png;base64,..."), como canvas.toDataURL.
  Future<String> exportar() async {
    final grabador = ui.PictureRecorder();
    final lienzo = Canvas(grabador);
    pintarTrazos(lienzo, const Size(anchoExportado + 0.0, altoExportado + 0.0), _trazos, grosor: 5);
    final imagen = await grabador.endRecording().toImage(anchoExportado, altoExportado);
    final bytes = await imagen.toByteData(format: ui.ImageByteFormat.png);
    return 'data:image/png;base64,${base64Encode(bytes!.buffer.asUint8List())}';
  }
}

void pintarTrazos(Canvas lienzo, Size tamano, List<List<Offset>> trazos, {double grosor = 3}) {
  final pincel = Paint()
    ..color = const Color(0xFF111827)
    ..strokeWidth = grosor
    ..strokeCap = StrokeCap.round
    ..strokeJoin = StrokeJoin.round
    ..style = PaintingStyle.stroke;

  for (final trazo in trazos) {
    if (trazo.length == 1) {
      lienzo.drawCircle(Offset(trazo.first.dx * tamano.width, trazo.first.dy * tamano.height), grosor / 2, pincel..style = PaintingStyle.fill);
      pincel.style = PaintingStyle.stroke;
      continue;
    }
    final camino = Path()..moveTo(trazo.first.dx * tamano.width, trazo.first.dy * tamano.height);
    for (final punto in trazo.skip(1)) {
      camino.lineTo(punto.dx * tamano.width, punto.dy * tamano.height);
    }
    lienzo.drawPath(camino, pincel);
  }
}

/// Recuadro de firma con el dedo, con su botón "Borrar".
class LienzoFirma extends StatelessWidget {
  const LienzoFirma({super.key, required this.controller, required this.titulo, required this.ayuda, this.error, this.habilitado = true});

  final FirmaController controller;
  final String titulo;
  final String ayuda;
  final String? error;
  final bool habilitado;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Text(titulo, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800))),
            TextButton.icon(
              onPressed: habilitado ? controller.borrar : null,
              icon: const Icon(Icons.delete_outline, size: 18),
              label: const Text('Borrar'),
            ),
          ],
        ),
        Text(ayuda, style: const TextStyle(color: Colores.gris, fontSize: 13)),
        const SizedBox(height: 8),
        AspectRatio(
          aspectRatio: FirmaController.anchoExportado / FirmaController.altoExportado,
          child: LayoutBuilder(builder: (context, restricciones) {
            final tamano = Size(restricciones.maxWidth, restricciones.maxHeight);
            Offset relativo(Offset p) => Offset((p.dx / tamano.width).clamp(0, 1), (p.dy / tamano.height).clamp(0, 1));
            return Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: error != null ? Colores.rojo : const Color(0xFFD1D5DB), width: 2),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: GestureDetector(
                  // El trazo empieza donde se apoyó el dedo, no después del umbral de arrastre.
                  dragStartBehavior: DragStartBehavior.down,
                  // Con arrastres verticales y horizontales propios, la página no se desplaza mientras se firma.
                  onVerticalDragStart: habilitado ? (d) => controller.empezar(relativo(d.localPosition)) : null,
                  onVerticalDragUpdate: habilitado ? (d) => controller.mover(relativo(d.localPosition)) : null,
                  onHorizontalDragStart: habilitado ? (d) => controller.empezar(relativo(d.localPosition)) : null,
                  onHorizontalDragUpdate: habilitado ? (d) => controller.mover(relativo(d.localPosition)) : null,
                  child: ListenableBuilder(
                    listenable: controller,
                    builder: (context, _) => CustomPaint(
                      painter: _PintorFirma(controller.trazos),
                      child: controller.vacia
                          ? const Center(child: Text('Firma aquí', style: TextStyle(color: Color(0xFFD1D5DB), fontSize: 22, fontWeight: FontWeight.w700)))
                          : const SizedBox.expand(),
                    ),
                  ),
                ),
              ),
            );
          }),
        ),
        if (error != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text(error!, style: const TextStyle(color: Colores.rojo, fontSize: 13))),
      ],
    );
  }
}

class _PintorFirma extends CustomPainter {
  _PintorFirma(this.trazos);

  final List<List<Offset>> trazos;

  @override
  void paint(Canvas canvas, Size size) {
    // Línea de base, como en el formato en papel.
    canvas.drawLine(
      Offset(size.width * 0.08, size.height * 0.78),
      Offset(size.width * 0.92, size.height * 0.78),
      Paint()
        ..color = const Color(0xFFE5E7EB)
        ..strokeWidth = 1.5,
    );
    pintarTrazos(canvas, size, trazos, grosor: 3);
  }

  @override
  bool shouldRepaint(covariant _PintorFirma oldDelegate) => true;
}
