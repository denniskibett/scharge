import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'api.dart';
import 'device.dart';

/// Push notifications (bills, reminders, bill links, receipts) through Firebase Cloud Messaging.
/// Needs android/app/google-services.json from the scharge Firebase project; without it [available] stays false and
/// everything else works (the server falls back to SMS).
class Push {
  static bool available = false;
  static bool _listening = false;

  /// Called with an invoice id when the person taps a notification about a bill.
  static void Function(int invoiceId)? onOpenBill;

  /// Called for a notification that arrives while the app is open (title, body, invoice id or null).
  static void Function(String title, String body, int? invoiceId)? onForeground;

  static Future<void> init() async {
    try {
      await Firebase.initializeApp();
      available = true;
    } catch (e) {
      debugPrint('Push off (no Firebase config): $e');
      return;
    }
    FirebaseMessaging.onMessage.listen((m) =>
        onForeground?.call(m.notification?.title ?? 'scharge', m.notification?.body ?? '', _invoice(m)));
    FirebaseMessaging.onMessageOpenedApp.listen((m) => _open(m));
    final first = await FirebaseMessaging.instance.getInitialMessage();
    if (first != null) Future.delayed(const Duration(seconds: 2), () => _open(first));
  }

  static int? _invoice(RemoteMessage m) => int.tryParse('${m.data['invoice_id'] ?? ''}');

  static void _open(RemoteMessage m) {
    final id = _invoice(m);
    if (id != null) onOpenBill?.call(id);
  }

  /// After sign-in (first time asks for permission) and whenever Firebase rotates the token.
  static Future<void> register(Api api) async {
    if (!available) return;
    try {
      await FirebaseMessaging.instance.requestPermission();
      // The server sends through FCM HTTP v1 to this FCM token. The newer register()/onRegistered gives a Firebase
      // Installation ID instead, which our sender cannot use, so the token calls stay until the server moves too.
      // ignore: deprecated_member_use
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _send(api, token);
      if (!_listening) {
        _listening = true; // once per app run, not once per sign-in
        // ignore: deprecated_member_use
        FirebaseMessaging.instance.onTokenRefresh.listen((t) => _send(api, t));
      }
    } catch (e) {
      debugPrint('Push registration failed: $e');
    }
  }

  static Future<void> _send(Api api, String token) => api.post('/device', {
        'push_token': token,
        'app_version': Device.appVersion,
        'os_version': Device.osVersion,
        'device_model': Device.model,
      });
}
