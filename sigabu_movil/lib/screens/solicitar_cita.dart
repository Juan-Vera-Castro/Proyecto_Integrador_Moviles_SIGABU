import 'package:flutter/material.dart';

import '../api/api.dart';
import '../theme.dart';
import '../widgets/comunes.dart';

/// Solicitud de cita por pasos (citas/solicitar.blade.php): servicio, datos y franja horaria,
/// consentimiento informado del servicio (HU-009) y confirmación. La facultad y la jornada se
/// toman del registro del estudiante; solo se preguntan si faltan.
class PantallaSolicitarCita extends StatelessWidget {
  const PantallaSolicitarCita({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Solicitar cita'),
          Text('Vicerrectoría de Bienestar Universitario · UCEVA', style: TextStyle(fontSize: 12, color: Colores.gris, fontWeight: FontWeight.w500)),
        ]),
      ),
      body: Cargador<Map<String, dynamic>>(
        cargar: () => api.get('/citas/formulario'),
        construir: (context, datos, _) => _Formulario(datos),
      ),
    );
  }
}

class _Formulario extends StatefulWidget {
  const _Formulario(this.datos);

  final Map<String, dynamic> datos;

  @override
  State<_Formulario> createState() => _FormularioState();
}

class _FormularioState extends State<_Formulario> {
  static const _pasos = ['Servicio', 'Tus datos y horario', 'Consentimiento', 'Confirmación'];
  static const _campoPaso = {'tipo_servicio': 0, 'id_facultad': 1, 'jornada': 1, 'franja_horaria': 1, 'es_emergencia': 1, 'acepta_consentimiento': 2};
  static const _franjas = {
    'Mañana': ('🌅', 'Antes del mediodía'),
    'Tarde': ('☀️', 'Después del mediodía'),
    'Noche': ('🌙', 'Al final de la jornada'),
    'Todas': ('🕒', 'Cualquier franja me sirve'),
  };
  static const _servicios = {
    'Salud Mental': 'Orientación emocional, ansiedad, estrés académico, duelo, relaciones.',
    'Inclusión': 'Caracterización de discapacidad, ajustes razonables, apoyos académicos.',
  };

  int _paso = 0;
  String? _servicio;
  int? _facultad;
  String? _jornada;
  String? _franja;
  bool _emergencia = false;
  bool _acepta = false;
  bool _enviando = false;
  ApiException? _error;

  Map<String, dynamic> get d => widget.datos;
  Map<String, dynamic>? get _facultadRegistrada => d['facultad_registrada'] as Map<String, dynamic>?;
  String? get _jornadaRegistrada => d['jornada_registrada'] as String?;
  List<Map<String, dynamic>> get _serviciosLista => (d['servicios'] as List).cast<Map<String, dynamic>>();
  Map<String, dynamic>? get _servicioActual => _serviciosLista.where((s) => s['tipo'] == _servicio).firstOrNull;
  Map<String, dynamic>? get _consentimiento => _servicioActual?['consentimiento'] as Map<String, dynamic>?;

  @override
  void initState() {
    super.initState();
    _facultad = _facultadRegistrada?['id'] as int?;
    _jornada = _jornadaRegistrada;
  }

  bool get _puedeAvanzar => switch (_paso) {
        0 => _servicio != null,
        1 => _facultad != null && _jornada != null && _franja != null,
        2 => _consentimiento == null || _acepta,
        _ => true,
      };

  Future<void> _enviar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final respuesta = await api.post('/citas', {
        'tipo_servicio': _servicio,
        if (_facultadRegistrada == null) 'id_facultad': _facultad,
        if (_jornadaRegistrada == null) 'jornada': _jornada,
        'franja_horaria': _franja,
        'es_emergencia': _emergencia,
        if (_consentimiento != null) 'id_version': _consentimiento!['id_version'],
        if (_consentimiento != null) 'acepta_consentimiento': _acepta,
      });
      if (!mounted) return;
      await showDialog(
        context: context,
        builder: (context) => AlertDialog(
          icon: const Icon(Icons.check_circle, color: Colores.verde, size: 48),
          title: const Text('Solicitud enviada'),
          content: Text(respuesta['mensaje']?.toString() ?? 'Recibimos tu solicitud.'),
          actions: [FilledButton(onPressed: () => Navigator.pop(context), child: const Text('Ver mis citas'))],
        ),
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      // Con errores, vuelve al primer paso que tiene uno.
      final pasoError = e.errores.keys.map((c) => _campoPaso[c]).whereType<int>().fold<int?>(null, (a, b) => a == null || b < a ? b : a);
      setState(() {
        _error = e;
        if (pasoError != null) _paso = pasoError;
      });
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          color: Colors.white,
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
          child: IndicadorPasos(pasos: _pasos, actual: _paso),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              ResumenErrores(_error),
              ...switch (_paso) {
                0 => _pasoServicio(),
                1 => _pasoDatos(),
                2 => _pasoConsentimiento(),
                _ => _pasoConfirmacion(),
              },
            ],
          ),
        ),
        SafeArea(
          top: false,
          child: Container(
            color: Colors.white,
            padding: const EdgeInsets.all(12),
            child: Row(children: [
              if (_paso > 0) ...[
                Expanded(child: OutlinedButton(onPressed: _enviando ? null : () => setState(() => _paso--), child: const Text('← Atrás'))),
                const SizedBox(width: 10),
              ],
              Expanded(
                flex: 2,
                child: _paso < 3
                    ? FilledButton(onPressed: _puedeAvanzar ? () => setState(() => _paso++) : null, child: const Text('Continuar →'))
                    : BotonEnviar(texto: 'Enviar solicitud', enviando: _enviando, alPresionar: _enviar),
              ),
            ]),
          ),
        ),
      ],
    );
  }

  Widget _titulo(String titulo, String texto) => Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(titulo, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text(texto, style: const TextStyle(color: Colores.gris, height: 1.35)),
        ]),
      );

  List<Widget> _pasoServicio() => [
        _titulo('¿Qué servicio necesitas?', 'Selecciona el tipo de acompañamiento que estás buscando.'),
        for (final servicio in _serviciosLista)
          OpcionTarjeta(
            icono: servicio['icono'] as String,
            titulo: servicio['titulo'] as String,
            texto: _servicios[servicio['tipo']],
            seleccionada: _servicio == servicio['tipo'],
            alTocar: () => setState(() {
              _servicio = servicio['tipo'] as String;
              _acepta = false;
            }),
          ),
      ];

  List<Widget> _pasoDatos() {
    final facultades = (d['facultades'] as List).cast<Map<String, dynamic>>();
    final jornadas = (d['jornadas'] as List).cast<String>();
    final franjas = (d['franjas'] as List).cast<String>();
    final registrados = {
      'Programa académico': d['programa'] as String?,
      'Facultad': _facultadRegistrada?['nombre'] as String?,
      'Jornada': _jornadaRegistrada,
      'Semestre': d['semestre']?.toString(),
    }..removeWhere((_, v) => v == null);

    return [
      _titulo('Tus datos y tu horario', 'Con tu facultad y tu jornada te asignamos el profesional que te atenderá, quien acordará contigo la hora exacta de la cita.'),
      if (registrados.isNotEmpty) ...[
        Seccion(children: [
          for (final e in registrados.entries) Dato(e.key, e.value),
          const Text('Según tu registro en la universidad. Si algún dato no es correcto, comunícate con Bienestar Universitario.', style: TextStyle(color: Colores.gris, fontSize: 12)),
        ]),
        const SizedBox(height: 14),
      ],
      if (_facultadRegistrada == null) ...[
        DropdownButtonFormField<int>(
          initialValue: _facultad,
          isExpanded: true,
          decoration: InputDecoration(labelText: 'Facultad *', helperText: 'Tu facultad no está en tu registro; indícala esta vez.', errorText: _error?.error('id_facultad')),
          items: facultades.map((f) => DropdownMenuItem(value: f['id'] as int, child: Text(f['nombre'] as String, overflow: TextOverflow.ellipsis))).toList(),
          onChanged: (v) => setState(() => _facultad = v),
        ),
        const SizedBox(height: 16),
      ],
      if (_jornadaRegistrada == null) ...[
        const Text('Jornada *', style: TextStyle(fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        SegmentedButton<String>(
          emptySelectionAllowed: true,
          segments: jornadas.map((j) => ButtonSegment(value: j, label: Text(j))).toList(),
          selected: {?_jornada},
          onSelectionChanged: (s) => setState(() => _jornada = s.firstOrNull),
        ),
        if (_error?.error('jornada') != null) Text(_error!.error('jornada')!, style: const TextStyle(color: Colores.rojo, fontSize: 12)),
        const SizedBox(height: 16),
      ],
      const Text('Franja horaria en la que puedes asistir *', style: TextStyle(fontWeight: FontWeight.w700)),
      const SizedBox(height: 8),
      GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 1.6,
        children: [
          for (final franja in franjas)
            _Franja(
              icono: _franjas[franja]?.$1 ?? '🕒',
              titulo: franja,
              texto: _franjas[franja]?.$2 ?? '',
              seleccionada: _franja == franja,
              alTocar: () => setState(() => _franja = franja),
            ),
        ],
      ),
      const SizedBox(height: 16),
      Material(
        color: _emergencia ? const Color(0xFFFFF1F2) : Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: _emergencia ? Colores.rojo : const Color(0xFFE5E7EB))),
        child: CheckboxListTile(
          value: _emergencia,
          activeColor: Colores.rojo,
          onChanged: (v) => setState(() => _emergencia = v ?? false),
          title: const Text('🚨 Caso de emergencia (opcional)', style: TextStyle(fontWeight: FontWeight.w800)),
          subtitle: const Text('Márcalo si necesitas atención urgente. Tu solicitud le aparecerá al psicólogo como prioritaria.'),
        ),
      ),
      if (_emergencia) ...[
        const SizedBox(height: 10),
        const Aviso(
          'Si tu vida o la de otra persona está en peligro en este momento, no esperes la cita: llama a la línea de emergencias 123 o acude al servicio de urgencias más cercano.',
          tipo: TipoAviso.error,
        ),
      ],
      const SizedBox(height: 12),
      const Text('La atención es presencial. Si necesitas atención virtual, coméntalo con tu profesional, que la autoriza en casos especiales.',
          style: TextStyle(color: Colores.gris, fontSize: 12)),
    ];
  }

  List<Widget> _pasoConsentimiento() {
    final consentimiento = _consentimiento;
    if (consentimiento == null) {
      return [
        _titulo('Consentimiento informado', 'Léelo con atención. Lo que hables en la atención es confidencial.'),
        const Aviso('Este servicio aún no tiene un consentimiento publicado. Tu profesional te lo presentará en la primera cita.'),
      ];
    }
    return [
      _titulo('Consentimiento informado', 'Léelo con atención. Lo que hables en la atención es confidencial.'),
      Seccion(
        titulo: 'Consentimiento informado de $_servicio',
        subtitulo: '${consentimiento['nombre']} · versión ${consentimiento['numero_version']}',
        children: [
          Container(
            constraints: const BoxConstraints(maxHeight: 340),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE5E7EB))),
            child: SingleChildScrollView(child: Text(consentimiento['texto'] as String, style: const TextStyle(fontSize: 13.5, height: 1.5))),
          ),
          const SizedBox(height: 8),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            value: _acepta,
            onChanged: (v) => setState(() => _acepta = v ?? false),
            title: const Text('Leí y acepto el consentimiento informado. *', style: TextStyle(fontWeight: FontWeight.w700)),
            subtitle: _error?.error('acepta_consentimiento') != null ? Text(_error!.error('acepta_consentimiento')!, style: const TextStyle(color: Colores.rojo)) : null,
          ),
        ],
      ),
    ];
  }

  List<Widget> _pasoConfirmacion() {
    final facultades = (d['facultades'] as List).cast<Map<String, dynamic>>();
    final facultad = _facultadRegistrada?['nombre'] ?? facultades.where((f) => f['id'] == _facultad).firstOrNull?['nombre'];
    return [
      _titulo('Confirma tu solicitud', 'Revisa los datos antes de enviar.'),
      Seccion(children: [
        Dato('Servicio', '${_servicioActual?['icono']} ${_servicioActual?['titulo']}'),
        Dato('Facultad', facultad as String?),
        Dato('Jornada', _jornada),
        Dato('Franja horaria', _franja),
        Dato('Emergencia', _emergencia ? 'Sí, atención prioritaria' : 'No'),
        Dato('Consentimiento', _consentimiento == null ? 'Te lo presentará tu profesional' : (_acepta ? 'Aceptado' : 'Sin aceptar')),
      ]),
    ];
  }
}

class _Franja extends StatelessWidget {
  const _Franja({required this.icono, required this.titulo, required this.texto, required this.seleccionada, required this.alTocar});

  final String icono;
  final String titulo;
  final String texto;
  final bool seleccionada;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: seleccionada ? Colores.verde.withValues(alpha: 0.07) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: seleccionada ? Colores.verde : const Color(0xFFE5E7EB), width: seleccionada ? 2 : 1),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: alTocar,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(icono, style: const TextStyle(fontSize: 22)),
            Text(titulo, style: const TextStyle(fontWeight: FontWeight.w800)),
            Text(texto, textAlign: TextAlign.center, style: const TextStyle(color: Colores.gris, fontSize: 11)),
          ]),
        ),
      ),
    );
  }
}
