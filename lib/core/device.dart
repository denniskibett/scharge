import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';

/// App version, Android version and phone model: sent on every API call (X-App-Version, X-OS-Version,
/// X-Device-Model) and with crash reports, so App health shows which phones and versions are in use.
class Device {
  static String appVersion = '0';
  static String osVersion = Platform.operatingSystemVersion;
  static String model = 'unknown';

  static Future<void> load() async {
    try {
      final pkg = await PackageInfo.fromPlatform();
      appVersion = '${pkg.version}+${pkg.buildNumber}';
    } catch (_) {}
    try {
      if (Platform.isAndroid) {
        final a = await DeviceInfoPlugin().androidInfo;
        osVersion = 'Android ${a.version.release} (SDK ${a.version.sdkInt})';
        model = '${a.manufacturer} ${a.model}';
      }
    } catch (_) {}
  }

  static Map<String, String> get headers => {'X-App-Version': appVersion, 'X-OS-Version': osVersion, 'X-Device-Model': model};
}
