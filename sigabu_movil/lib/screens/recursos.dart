import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api.dart';
import '../theme.dart';
import '../widgets/comunes.dart';

/// Recursos de bienestar (recursos/index.blade.php): lo que Bienestar publicó para todos, con
/// búsqueda y filtro por categoría. Los archivos se descargan y los enlaces se abren afuera.
class PantallaRecursos extends StatefulWidget {
  const PantallaRecursos({super.key});

  @override
  State<PantallaRecursos> createState() => _PantallaRecursosState();
}

class _PantallaRecursosState extends State<PantallaRecursos> {
  static const _colores = [Color(0xFF047857), Color(0xFFDB2777), Color(0xFF0F766E), Color(0xFFB45309)];

  String _categoria = 'Todos';
  String _busqueda = '';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Recursos de bienestar'),
          Text('Material psicoeducativo gratuito para tu salud mental', style: TextStyle(fontSize: 12, color: Colores.gris, fontWeight: FontWeight.w500)),
        ]),
      ),
      body: Cargador<Map<String, dynamic>>(
        cargar: () => api.get('/recursos'),
        construir: (context, datos, recargar) {
          final recursos = (datos['recursos'] as List).cast<Map<String, dynamic>>();
          final categorias = (datos['categorias'] as List).cast<String>();

          if (recursos.isEmpty) {
            return RefreshIndicator(
              onRefresh: recargar,
              child: ListView(padding: const EdgeInsets.all(32), children: const [
                SizedBox(height: 60),
                Center(child: Text('📚', style: TextStyle(fontSize: 56))),
                SizedBox(height: 12),
                Text('Aún no hay recursos publicados.', textAlign: TextAlign.center, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                SizedBox(height: 4),
                Text('Cuando Bienestar Universitario publique material, lo encontrarás aquí.', textAlign: TextAlign.center, style: TextStyle(color: Colores.gris)),
              ]),
            );
          }

          final visibles = recursos.where((r) {
            final texto = '${r['titulo']} ${r['descripcion'] ?? ''} ${r['categoria'] ?? ''}'.toLowerCase();
            return (_categoria == 'Todos' || r['categoria'] == _categoria) && texto.contains(_busqueda.toLowerCase());
          }).toList();

          return RefreshIndicator(
            onRefresh: recargar,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  decoration: const InputDecoration(hintText: 'Buscar recursos...', prefixIcon: Icon(Icons.search)),
                  onChanged: (v) => setState(() => _busqueda = v),
                ),
                if (categorias.length > 1) ...[
                  const SizedBox(height: 10),
                  SizedBox(
                    height: 40,
                    child: ListView(scrollDirection: Axis.horizontal, children: [
                      for (final c in ['Todos', ...categorias])
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(label: Text(c), selected: _categoria == c, onSelected: (_) => setState(() => _categoria = c)),
                        ),
                    ]),
                  ),
                ],
                const SizedBox(height: 14),
                if (visibles.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No hay recursos que coincidan con tu búsqueda.', textAlign: TextAlign.center)),
                for (final r in visibles) ...[_tarjeta(context, r, categorias), const SizedBox(height: 12)],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _tarjeta(BuildContext context, Map<String, dynamic> r, List<String> categorias) {
    final indice = categorias.indexOf(r['categoria'] as String? ?? '');
    final color = _colores[(indice < 0 ? 0 : indice) % _colores.length];
    final archivo = r['tiene_archivo'] == true;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Container(
          color: color.withValues(alpha: 0.08),
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            Text(r['icono'] as String, style: const TextStyle(fontSize: 26)),
            const SizedBox(width: 10),
            Expanded(child: Text(r['tipo'] as String, style: TextStyle(color: color, fontWeight: FontWeight.w800))),
            Text(r['tamano']?.toString() ?? '', style: TextStyle(color: color, fontSize: 12)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (r['categoria'] != null) Text((r['categoria'] as String).toUpperCase(), style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.4)),
            const SizedBox(height: 4),
            Text(r['titulo'] as String, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            if (r['descripcion'] != null) ...[const SizedBox(height: 4), Text(r['descripcion'] as String, style: const TextStyle(color: Colores.gris, height: 1.35))],
            const SizedBox(height: 12),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(foregroundColor: color, side: BorderSide(color: color.withValues(alpha: 0.4))),
              onPressed: () {
                if (archivo) {
                  descargarYAbrir(context, '/recursos/${r['id']}/archivo', r['archivo_nombre'] as String? ?? 'recurso');
                } else if (r['url'] != null) {
                  launchUrl(Uri.parse(r['url'] as String), mode: LaunchMode.externalApplication);
                }
              },
              icon: Icon(archivo ? Icons.download : Icons.open_in_new, size: 18),
              label: Text(archivo ? 'Descargar' : 'Abrir enlace'),
            ),
          ]),
        ),
      ]),
    );
  }
}
