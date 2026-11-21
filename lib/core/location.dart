import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

import 'theme.dart';

/// The phone's position at one moment (a meter reading, arriving at a repair), with the reason said on screen first.
/// Never tracked in the background. Returns null when the person says no or location is off; the action still saves.
Future<Position?> positionNow(BuildContext context, String why) async {
  try {
    if (!await Geolocator.isLocationServiceEnabled()) return null;
    var perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) {
      if (!context.mounted) return null;
      final ok = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Use your location?'),
          content: Text('$why\n\nOnly at this moment, never in the background.', style: Sc.body(15)),
          actions: [
            TextButton(onPressed: () => Navigator.of(c).pop(false), child: const Text('Not now')),
            TextButton(onPressed: () => Navigator.of(c).pop(true), child: const Text('Allow')),
          ],
        ),
      );
      if (ok != true) return null;
      perm = await Geolocator.requestPermission();
    }
    if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) return null;
    return await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 15)));
  } catch (_) {
    return null;
  }
}

Map<String, String> positionFields(Position? p) => p == null
    ? {}
    : {'lat': p.latitude.toStringAsFixed(7), 'lng': p.longitude.toStringAsFixed(7), 'accuracy_m': p.accuracy.toStringAsFixed(1)};
