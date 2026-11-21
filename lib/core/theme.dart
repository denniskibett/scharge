import 'package:flutter/material.dart';

/// scharge brand (canvas "scharge brand", 02 · Brand identity): Scharge Red, Deep Red, Blush, Ink.
/// Headings in Unbounded, everything else in DM Sans. A white label swaps only the red.
class Sc {
  static const red = Color(0xFFDC2626);
  static const deep = Color(0xFF991B1B);
  static const blush = Color(0xFFFEF2F2);
  static const blush2 = Color(0xFFFEE2E2);
  static const ink = Color(0xFF3A2E31); // calm dark, never pure black
  static const muted = Color(0xFF7A6A6D);
  static const alert = Color(0xFF7F1D1D);
  static const ok = Color(0xFF166534);

  /// The brand colour for this person (white label), set after sign-in. Main buttons and prices always use it.
  static Color brand = red;

  // Secondary colours (canvas "Secondary colours"): one per audience, never on main buttons, never two side by side.
  static const teal = Color(0xFF0F766E); // owners & landlord staff
  static const gold = Color(0xFFF2B33D); // field staff (ink text only)
  static const jacaranda = Color(0xFF7B5EA7); // BnB hosts
  static const sand = Color(0xFFF5EBDD);

  /// The accent for summary cards and the tab highlight. Tenants keep the brand red.
  static Color accent = red;
  static Color onAccent = Colors.white;
  static Color accentSoft = blush;

  static void accentFor(List<String> roles) {
    final r = roles.toSet();
    (Color, Color, Color) pick;
    if (r.contains('tenant')) {
      pick = (brand, Colors.white, blush); // tenants: the red layout, always, whatever other role the account has
    } else if (r.intersection({'sysadmin', 'account_manager', 'sales'}).isNotEmpty) {
      pick = (ink, Colors.white, const Color(0xFFEDEBEC));
    } else if (r.intersection({'admin', 'property_manager', 'accountant', 'agent'}).isNotEmpty) {
      pick = (teal, Colors.white, const Color(0xFFE3F1EF));
    } else if (r.contains('bnb_host')) {
      pick = (jacaranda, Colors.white, const Color(0xFFF0ECF6));
    } else if (r.intersection({'meter_reader', 'maintenance', 'security', 'cleaning_staff'}).isNotEmpty) {
      pick = (gold, ink, const Color(0xFFFDF3DF));
    } else {
      pick = (brand, Colors.white, blush); // tenants: red only
    }
    accent = pick.$1;
    onAccent = pick.$2;
    accentSoft = pick.$3;
  }

  static Color parse(String? hex) {
    if (hex == null || !RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(hex)) return red;
    return Color(int.parse('FF${hex.substring(1)}', radix: 16));
  }

  /// Unbounded: only for money amounts and the big numbers on cards.
  static TextStyle head(double size, {Color? color}) => TextStyle(
      fontFamily: 'Unbounded', fontSize: size, letterSpacing: -0.02 * size, height: 1.15, color: color ?? ink,
      fontVariations: const [FontVariation('wght', 650)], fontFeatures: const [FontFeature.tabularFigures()]);

  /// Screen and section titles: plain bold DM Sans, like a normal app.
  static TextStyle title(double size, {Color? color}) => TextStyle(
      fontFamily: 'Outfit', fontSize: size, height: 1.2, color: color ?? ink, fontVariations: const [FontVariation('wght', 600)]);

  /// Text and descriptions: Outfit (calm, as on the scharge dashboard). Weights a step lighter than asked.
  static TextStyle body(double size, {double weight = 400, Color? color}) => TextStyle(
      fontFamily: 'Outfit', fontSize: size, height: 1.3, color: color ?? ink, fontVariations: [FontVariation('wght', weight > 400 ? weight - 50 : weight)],
      fontFeatures: const [FontFeature.tabularFigures()]);

  static ThemeData theme(Color brandColor) {
    brand = brandColor;
    // Fixed colours, not a generated palette: a seeded scheme tints icons and surfaces off-brand.
    final scheme = ColorScheme.light(
        primary: brandColor, onPrimary: Colors.white, secondary: accent, onSecondary: onAccent,
        surface: Colors.white, onSurface: ink, surfaceTint: Colors.transparent, error: alert, outline: blush2,
        primaryContainer: blush, onPrimaryContainer: ink, secondaryContainer: accentSoft, onSecondaryContainer: ink,
        tertiary: brandColor, tertiaryContainer: blush, surfaceContainerHighest: blush, surfaceContainerHigh: blush,
        surfaceContainer: Colors.white, surfaceContainerLow: Colors.white, inversePrimary: blush2);
    return ThemeData(
      useMaterial3: true,
      pageTransitionsTheme: const PageTransitionsTheme(builders: {
        TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
      }),
      colorScheme: scheme,
      fontFamily: 'Outfit',
      scaffoldBackgroundColor: Colors.white,
      visualDensity: VisualDensity.compact,
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
      appBarTheme: AppBarTheme(
          backgroundColor: Colors.white, foregroundColor: ink, elevation: 0, scrolledUnderElevation: 0, toolbarHeight: 48,
          titleTextStyle: body(17, weight: 600)),
      dividerTheme: const DividerThemeData(color: blush2, thickness: 1, space: 1),
      listTileTheme: const ListTileThemeData(dense: true, contentPadding: EdgeInsets.zero),
      filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
              backgroundColor: brandColor, foregroundColor: Colors.white, minimumSize: const Size.fromHeight(48),
              elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), textStyle: body(15, weight: 600))),
      outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
              foregroundColor: brandColor, minimumSize: const Size.fromHeight(46), side: BorderSide(color: brandColor, width: 1.5),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), textStyle: body(14, weight: 600))),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: blush2)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: blush2)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: brandColor, width: 1.5)),
        labelStyle: body(14, color: muted),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        indicatorColor: accentSoft,
        iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(color: s.contains(WidgetState.selected) ? (accent == gold ? ink : accent) : muted)),
        labelTextStyle: WidgetStateProperty.resolveWith((s) =>
            body(12, weight: s.contains(WidgetState.selected) ? 700 : 500, color: s.contains(WidgetState.selected) ? (accent == gold ? ink : accent) : muted)),
        elevation: 0,
        height: 62,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: Colors.white,
        selectedColor: brandColor,
        checkmarkColor: Colors.white,
        side: const BorderSide(color: blush2),
        labelStyle: WidgetStateTextStyle.resolveWith((s) => body(14, weight: 600, color: s.contains(WidgetState.selected) ? Colors.white : ink)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? Colors.white : muted),
        trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? brandColor : blush2),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: brandColor, refreshBackgroundColor: Colors.white),
      textButtonTheme: TextButtonThemeData(style: TextButton.styleFrom(foregroundColor: brandColor)),
      iconButtonTheme: IconButtonThemeData(style: IconButton.styleFrom(foregroundColor: ink)),
      radioTheme: RadioThemeData(fillColor: WidgetStatePropertyAll(brandColor)),
      checkboxTheme: CheckboxThemeData(fillColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? brandColor : null)),
      badgeTheme: BadgeThemeData(backgroundColor: brandColor),
      dropdownMenuTheme: const DropdownMenuThemeData(menuStyle: MenuStyle(backgroundColor: WidgetStatePropertyAll(Colors.white), surfaceTintColor: WidgetStatePropertyAll(Colors.transparent))),
      popupMenuTheme: const PopupMenuThemeData(color: Colors.white, surfaceTintColor: Colors.transparent),
      floatingActionButtonTheme: FloatingActionButtonThemeData(backgroundColor: brandColor, foregroundColor: Colors.white),
      dialogTheme: const DialogThemeData(backgroundColor: Colors.white, surfaceTintColor: Colors.transparent),
      bottomSheetTheme: const BottomSheetThemeData(backgroundColor: Colors.white, surfaceTintColor: Colors.transparent),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    );
  }
}
