import 'package:flutter/material.dart';

/// ASTRO GANN brand (same as the website, DECISIONS D-69): white / light background, olive green primary,
/// light blue info, yellow attention. **No red anywhere** — even the Material "error" colour is a dark yellow.
class AppColors {
  AppColors._();
  static const olive = Color(0xFF6B7C2E);
  static const oliveDark = Color(0xFF556227);
  static const oliveLight = Color(0xFFEBF0D8);
  static const oliveTint = Color(0xFFF6F8EE);
  static const blue = Color(0xFF1B78B0);
  static const blueLight = Color(0xFFE0F0FA);
  static const yellow = Color(0xFFFACC15);
  static const yellowLight = Color(0xFFFEFBE8);
  static const yellowDark = Color(0xFF854D0E);
  static const page = Color(0xFFF9FAFB);
  static const text = Color(0xFF1F2937);
  static const muted = Color(0xFF6B7280);
  static const border = Color(0xFFE5E7EB);
}

class AppTheme {
  AppTheme._();

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(seedColor: AppColors.olive).copyWith(
      primary: AppColors.olive,
      onPrimary: Colors.white,
      secondary: AppColors.blue,
      tertiary: AppColors.yellow,
      error: AppColors.yellowDark,
      onError: Colors.white,
      errorContainer: AppColors.yellowLight,
      surface: Colors.white,
    );

    final input = OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.border));

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.page,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: AppColors.text,
        elevation: 0,
        scrolledUnderElevation: 1,
        surfaceTintColor: Colors.white,
        titleTextStyle: TextStyle(color: AppColors.oliveDark, fontSize: 18, fontWeight: FontWeight.w700, letterSpacing: 0.5),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: const BorderSide(color: AppColors.border)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: input,
        enabledBorder: input,
        focusedBorder: input.copyWith(borderSide: const BorderSide(color: AppColors.olive, width: 1.6)),
        errorBorder: input.copyWith(borderSide: const BorderSide(color: AppColors.yellowDark)),
        focusedErrorBorder: input.copyWith(borderSide: const BorderSide(color: AppColors.yellowDark, width: 1.6)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.olive,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.oliveDark,
          side: const BorderSide(color: AppColors.border),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      tabBarTheme: const TabBarThemeData(
        labelColor: AppColors.oliveDark,
        unselectedLabelColor: AppColors.muted,
        indicatorColor: AppColors.olive,
        labelStyle: TextStyle(fontWeight: FontWeight.w700),
        tabAlignment: TabAlignment.start,
        dividerColor: AppColors.border,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: AppColors.oliveLight,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (s) => TextStyle(fontSize: 12, fontWeight: s.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500),
        ),
      ),
      badgeTheme: const BadgeThemeData(backgroundColor: AppColors.yellow, textColor: AppColors.text),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: AppColors.olive),
    );
  }
}
