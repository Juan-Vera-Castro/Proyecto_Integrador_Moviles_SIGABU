import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';

import '../api/api.dart';
import '../theme.dart';

/// Carga datos de la API con estados de carga, error y "jalar para actualizar".
class Cargador<T> extends StatefulWidget {
  const Cargador({super.key, required this.cargar, required this.construir});

  final Future<T> Function() cargar;
  final Widget Function(BuildContext context, T datos, Future<void> Function() recargar) construir;

  @override
  State<Cargador<T>> createState() => CargadorState<T>();
}

class CargadorState<T> extends State<Cargador<T>> {
  T? _datos;
  ApiException? _error;
  bool _cargando = true;

  @override
  void initState() {
    super.initState();
    recargar();
  }

  Future<void> recargar() async {
    setState(() {
      _cargando = _datos == null;
      _error = null;
    });
    try {
      final datos = await widget.cargar();
      if (!mounted) return;
      setState(() {
        _datos = datos;
        _cargando = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _cargando = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_cargando) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _datos == null) {
      return VistaError(mensaje: _error!.mensaje, reintentar: recargar);
    }
    return widget.construir(context, _datos as T, recargar);
  }
}

class VistaError extends StatelessWidget {
  const VistaError({super.key, required this.mensaje, required this.reintentar});

  final String mensaje;
  final VoidCallback reintentar;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('⚠️', style: TextStyle(fontSize: 44)),
            const SizedBox(height: 12),
            Text(mensaje, textAlign: TextAlign.center, style: const TextStyle(fontSize: 15)),
            const SizedBox(height: 16),
            FilledButton.icon(onPressed: reintentar, icon: const Icon(Icons.refresh), label: const Text('Reintentar')),
          ],
        ),
      ),
    );
  }
}

enum TipoAviso { exito, error, info, alerta }

/// Franja de mensaje, como los avisos verdes, rojos y ámbar de la web.
class Aviso extends StatelessWidget {
  const Aviso(this.texto, {super.key, this.tipo = TipoAviso.info, this.hijos = const []});

  final String texto;
  final TipoAviso tipo;
  final List<Widget> hijos;

  @override
  Widget build(BuildContext context) {
    final (fondo, borde, color, icono) = switch (tipo) {
      TipoAviso.exito => (const Color(0xFFF0FDF4), const Color(0xFFBBF7D0), Colores.verde, '✅'),
      TipoAviso.error => (const Color(0xFFFFF1F2), const Color(0xFFFECDD3), Colores.rojo, '⚠️'),
      TipoAviso.alerta => (const Color(0xFFFFFBEB), const Color(0xFFFDE68A), Colores.ambar, '⚠️'),
      TipoAviso.info => (const Color(0xFFF0F9FF), const Color(0xFFBAE6FD), Colores.azul, 'ℹ️'),
    };
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: fondo, border: Border.all(color: borde), borderRadius: BorderRadius.circular(12)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(icono, style: const TextStyle(fontSize: 18)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(texto, style: TextStyle(color: color, fontWeight: FontWeight.w600, height: 1.35)),
                ...hijos,
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Resumen de errores de un formulario (como el recuadro rojo de la web).
class ResumenErrores extends StatelessWidget {
  const ResumenErrores(this.error, {super.key});

  final ApiException? error;

  @override
  Widget build(BuildContext context) {
    if (error == null) return const SizedBox.shrink();
    final mensajes = error!.todos;
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Aviso(
        mensajes.length == 1 ? mensajes.first : 'No se pudo enviar:',
        tipo: TipoAviso.error,
        hijos: mensajes.length == 1
            ? []
            : mensajes.map((m) => Padding(padding: const EdgeInsets.only(top: 4), child: Text('• $m', style: const TextStyle(color: Colores.rojo)))).toList(),
      ),
    );
  }
}

/// Tarjeta blanca con título, como las secciones de la web.
class Seccion extends StatelessWidget {
  const Seccion({super.key, this.titulo, this.subtitulo, required this.children, this.color});

  final String? titulo;
  final String? subtitulo;
  final List<Widget> children;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: color,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (titulo != null) Text(titulo!, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
            if (subtitulo != null) ...[
              const SizedBox(height: 4),
              Text(subtitulo!, style: const TextStyle(color: Colores.gris, fontSize: 13, height: 1.35)),
            ],
            if (titulo != null || subtitulo != null) const SizedBox(height: 12),
            ...children,
          ],
        ),
      ),
    );
  }
}

/// Etiqueta de estado en forma de píldora.
class Pildora extends StatelessWidget {
  const Pildora(this.texto, {super.key, required this.color});

  final String texto;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        border: Border.all(color: color.withValues(alpha: 0.35)),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(texto, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 12)),
    );
  }
}

/// Fila "etiqueta: valor" de los datos de una tarjeta.
class Dato extends StatelessWidget {
  const Dato(this.etiqueta, this.valor, {super.key});

  final String etiqueta;
  final String? valor;

  @override
  Widget build(BuildContext context) {
    if (valor == null || valor!.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(etiqueta.toUpperCase(), style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colores.gris, letterSpacing: 0.4)),
          const SizedBox(height: 2),
          Text(valor!, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

void mostrarMensaje(BuildContext context, String texto, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(texto),
      backgroundColor: error ? Colores.rojo : Colores.verdeOscuro,
      behavior: SnackBarBehavior.floating,
      duration: Duration(seconds: texto.length > 80 ? 6 : 4),
    ));
}

Future<bool> confirmar(BuildContext context, String pregunta, {String si = 'Sí, continuar', bool peligro = false}) async {
  final respuesta = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      content: Text(pregunta, style: const TextStyle(fontSize: 15, height: 1.4)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
        FilledButton(
          style: peligro ? FilledButton.styleFrom(backgroundColor: Colores.rojo) : null,
          onPressed: () => Navigator.pop(context, true),
          child: Text(si),
        ),
      ],
    ),
  );
  return respuesta ?? false;
}

/// Descarga un archivo de la API y lo abre con la app del teléfono (visor de PDF, etc.).
Future<void> descargarYAbrir(BuildContext context, String ruta, String nombre) async {
  mostrarMensaje(context, 'Descargando…');
  try {
    final archivo = await api.descargar(ruta, nombre);
    final resultado = await OpenFilex.open(archivo);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    if (resultado.type != ResultType.done) {
      mostrarMensaje(context, 'No hay una app para abrir este archivo en el teléfono.', error: true);
    }
  } on ApiException catch (e) {
    if (context.mounted) mostrarMensaje(context, e.mensaje, error: true);
  }
}

/// Botón principal con indicador de "enviando".
class BotonEnviar extends StatelessWidget {
  const BotonEnviar({super.key, required this.texto, required this.enviando, this.alPresionar, this.color, this.icono});

  final String texto;
  final bool enviando;
  final VoidCallback? alPresionar;
  final Color? color;
  final IconData? icono;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      style: color != null ? FilledButton.styleFrom(backgroundColor: color) : null,
      onPressed: enviando ? null : alPresionar,
      child: enviando
          ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
          : Row(mainAxisSize: MainAxisSize.min, children: [
              if (icono != null) ...[Icon(icono, size: 20), const SizedBox(width: 8)],
              Flexible(child: Text(texto, textAlign: TextAlign.center)),
            ]),
    );
  }
}

/// Indicador de pasos de los formularios (como el stepper de la web).
class IndicadorPasos extends StatelessWidget {
  const IndicadorPasos({super.key, required this.pasos, required this.actual, this.color = Colores.verde});

  final List<String> pasos;
  final int actual;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < pasos.length; i++) ...[
          Expanded(
            child: Column(
              children: [
                CircleAvatar(
                  radius: 15,
                  backgroundColor: i <= actual ? color : const Color(0xFFE5E7EB),
                  child: i < actual
                      ? const Icon(Icons.check, size: 16, color: Colors.white)
                      : Text('${i + 1}', style: TextStyle(color: i <= actual ? Colors.white : Colores.gris, fontWeight: FontWeight.w700, fontSize: 13)),
                ),
                const SizedBox(height: 4),
                Text(pasos[i],
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    style: TextStyle(fontSize: 11, fontWeight: i == actual ? FontWeight.w800 : FontWeight.w500, color: i == actual ? color : Colores.gris)),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// Tarjeta seleccionable (opción de radio grande), como las de servicio o situación.
class OpcionTarjeta extends StatelessWidget {
  const OpcionTarjeta({
    super.key,
    required this.icono,
    required this.titulo,
    this.texto,
    required this.seleccionada,
    required this.alTocar,
    this.color = Colores.verde,
  });

  final String icono;
  final String titulo;
  final String? texto;
  final bool seleccionada;
  final VoidCallback alTocar;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: seleccionada ? color.withValues(alpha: 0.07) : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: seleccionada ? color : const Color(0xFFE5E7EB), width: seleccionada ? 2 : 1),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: alTocar,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Text(icono, style: const TextStyle(fontSize: 28)),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(titulo, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                      if (texto != null) ...[
                        const SizedBox(height: 2),
                        Text(texto!, style: const TextStyle(color: Colores.gris, fontSize: 13, height: 1.3)),
                      ],
                    ],
                  ),
                ),
                Icon(seleccionada ? Icons.radio_button_checked : Icons.radio_button_off, color: seleccionada ? color : Colores.gris),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
