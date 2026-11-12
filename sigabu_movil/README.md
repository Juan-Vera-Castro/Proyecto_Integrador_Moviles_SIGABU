# SIGABU Estudiante (Flutter)

App móvil del panel del estudiante de SIGABU. Cómo correrla: sección *App móvil del estudiante (Flutter)* del [README principal](../README.md).

## Estructura

| Carpeta | Qué hay |
|---|---|
| `lib/config.dart` | Dirección del backend (`--dart-define=API_URL=...`) |
| `lib/api/api.dart` | Cliente HTTP con el token de Sanctum guardado de forma segura |
| `lib/screens/` | Una pantalla por sección del panel web del estudiante |
| `lib/widgets/firma.dart` | Firma táctil: exporta un PNG transparente como el lienzo de la web |
| `lib/utils/formato.dart` | Fechas y la vista previa del texto del consentimiento |

## Pruebas

```
flutter analyze
flutter test
```
