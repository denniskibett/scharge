import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

import 'api.dart';
import 'theme.dart';

/// One screen the server lets this person open, with the buttons they may use there.
class AppScreen {
  AppScreen(this.key, this.title, this.actions);
  final String key;
  final String title;
  final List<String> actions;
  bool can(String action) => actions.contains(action);
}

class Brand {
  Brand(this.json);
  final Map<String, dynamic> json;
  String get name => json['name'] as String? ?? 'scharge';
  bool get whiteLabel => json['white_label'] == true;
  String? get logoUrl => json['logo_url'] as String?;
  String? get supportPhone => json['support_phone'] as String?;
  bool get poweredBy => json['powered_by'] == true;
}

/// The signed-in person on this phone. The token sits in the phone's keystore (encrypted storage); the PIN and
/// fingerprint only unlock it. The server decides the screens: the app never works out access on its own.
class Session extends ChangeNotifier {
  static const _store = FlutterSecureStorage();
  final api = Api(null);
  final _auth = LocalAuthentication();

  Map<String, dynamic>? profile;
  bool unlocked = false;
  bool biometrics = false;
  int pinLength = 4;
  bool ready = false;

  bool get signedIn => api.token != null && profile != null;
  Map<String, dynamic> get user => _map(profile?['user']);
  static Map<String, dynamic> _map(dynamic v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
  String get name => user['name'] as String? ?? '';
  String get firstName => name.split(' ').first;
  List<String> get roles => ((user['roles'] as List?) ?? []).cast<String>();
  Brand get brand => Brand(_map(profile?['brand']));
  bool get hasPin => _map(profile?['session'])['has_pin'] == true;
  List<AppScreen> get screens => ((profile?['screens'] as List?) ?? [])
      .map((s) => AppScreen(s['key'] as String, s['title'] as String, ((s['actions'] as List?) ?? []).cast<String>()))
      .toList();
  AppScreen? screen(String key) => screens.where((s) => s.key == key).firstOrNull;

  String get roleLabel {
    const labels = {
      'tenant': 'Tenant', 'admin': 'Admin', 'property_manager': 'Property manager', 'accountant': 'Accountant',
      'meter_reader': 'Meter reader', 'maintenance': 'Maintenance', 'cleaning_staff': 'Cleaning', 'security': 'Security',
      'bnb_host': 'BnB host', 'sysadmin': 'scharge admin', 'account_manager': 'Account manager', 'sales': 'Sales', 'agent': 'Agent',
    };
    return roles.map((r) => labels[r] ?? r).firstOrNull ?? '';
  }

  Future<void> load() async {
    api.token = await _store.read(key: 'token');
    final cached = await _store.read(key: 'profile');
    if (cached != null) profile = jsonDecode(cached) as Map<String, dynamic>;
    biometrics = await _store.read(key: 'biometrics') == 'on';
    pinLength = int.tryParse(await _store.read(key: 'pin_length') ?? '') ?? 4;
    if (profile != null) {
      Sc.brand = Sc.parse(brand.json['primary_color'] as String?);
      Sc.accentFor(roles);
    }
    ready = true;
    notifyListeners();
  }

  /// After the SMS code or email sign-in: keep the token and who this is.
  Future<void> signedInWith(Map<String, dynamic> res) async {
    api.token = res['token'] as String;
    await _store.write(key: 'token', value: api.token);
    await _keep(res);
    unlocked = true;
    notifyListeners();
  }

  Future<void> _keep(Map<String, dynamic> res) async {
    profile = {for (final k in ['user', 'brand', 'screens', 'permissions', 'session']) k: res[k]};
    Sc.brand = Sc.parse(brand.json['primary_color'] as String?);
    Sc.accentFor(roles);
    await _store.write(key: 'profile', value: jsonEncode(profile));
  }

  /// Fresh screens and brand from the server (roles may have changed on the Roles page).
  Future<void> refresh() async {
    await _keep(await Api.fresh(() => api.get('/me'))); // never from the cache: roles may have just changed
    notifyListeners();
  }

  Future<bool> canUseBiometrics() async {
    try {
      return await _auth.isDeviceSupported();
    } catch (_) {
      return false;
    }
  }

  Future<void> setBiometrics(bool on) async {
    biometrics = on;
    await _store.write(key: 'biometrics', value: on ? 'on' : 'off');
    notifyListeners();
  }

  /// Fingerprint, face or the phone's own lock (PIN / pattern), then a fresh profile from the server.
  Future<bool> unlockWithPhone() async {
    try {
      final ok = await _auth.authenticate(localizedReason: 'Open ${brand.name}', biometricOnly: false);
      if (!ok) return false;
    } catch (_) {
      return false;
    }
    await refresh();
    unlocked = true;
    notifyListeners();
    return true;
  }

  Future<void> unlockWithPin(String pin) async {
    await _keep(await api.post('/auth/unlock', {'pin': pin}));
    unlocked = true;
    notifyListeners();
  }

  Future<void> createPin(String pin) async {
    await api.post('/auth/pin', {'pin': pin});
    pinLength = pin.length;
    await _store.write(key: 'pin_length', value: '$pinLength');
    (profile!['session'] as Map)['has_pin'] = true;
    await _store.write(key: 'profile', value: jsonEncode(profile));
    notifyListeners();
  }

  /// Fingerprint, face or the phone's lock to confirm a payment (no server call). False if not possible or cancelled.
  Future<bool> confirmWithPhone(String reason) async {
    if (!biometrics) return false;
    try {
      return await _auth.authenticate(localizedReason: reason, biometricOnly: false);
    } catch (_) {
      return false;
    }
  }

  void lock() {
    unlocked = false;
    notifyListeners();
  }

  Future<void> signOut({bool tellServer = true}) async {
    if (tellServer && api.token != null) {
      try {
        await api.post('/auth/logout');
      } catch (_) {}
    }
    api.token = null;
    api.clearCache();
    profile = null;
    unlocked = false;
    Sc.brand = Sc.red;
    Sc.accentFor(const []);
    await _store.deleteAll();
    notifyListeners();
  }
}
