import 'package:flutter/material.dart';

/// Colores de SIGABU web: verde institucional UCEVA y los acentos de cada módulo.
class Colores {
  static const verde = Color(0xFF007F2F);
  static const verdeOscuro = Color(0xFF0A4220);
  static const verdeNeon = Color(0xFF39FF14);
  static const vbg = Color(0xFFE30052);
  static const alimentario = Color(0xFFC2410C);
  static const morado = Color(0xFF7E22CE);
  static const fondo = Color(0xFFF5F7F6);
  static const ambar = Color(0xFFB45309);
  static const rojo = Color(0xFFBE123C);
  static const azul = Color(0xFF0369A1);
  static const gris = Color(0xFF6B7280);
}

ThemeData temaSigabu() {
  final esquema = ColorScheme.fromSeed(seedColor: Colores.verde, primary: Colores.verde);
  return ThemeData(
    colorScheme: esquema,
    useMaterial3: true,
    scaffoldBackgroundColor: Colores.fondo,
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: Color(0xFF111827),
      elevation: 0,
      scrolledUnderElevation: 1,
      centerTitle: false,
      titleTextStyle: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF111827)),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFFE5E7EB)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(48, 50),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(48, 50),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
  );
}
