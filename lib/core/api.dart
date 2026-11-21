import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'device.dart';

/// The scharge app API (server: routes/api.php, prefix v1/app; docs/MOBILE_APP.md).
/// Build with --dart-define=API_BASE=https://host/api/v1/app to point at another server.
const apiBase = String.fromEnvironment('API_BASE', defaultValue: 'https://scharge.sharet.africa/api/v1/app');

class ApiException implements Exception {
  ApiException(this.status, this.message, [this.body]);
  final int status;
  final String message;
  final Map<String, dynamic>? body;
  bool get signedOut => status == 401;
  @override
  String toString() => message;
}

/// HTTP 426: this app version is too old (the server's "oldest app version allowed").
class UpdateRequired extends ApiException {
  UpdateRequired(this.downloadUrl) : super(426, 'Please update the app to keep using it.');
  final String? downloadUrl;
}

class Api {
  Api(this.token);

  /// Set by the app: shows the "Update the app" screen.
  static void Function(String? downloadUrl)? onUpdateRequired;
  String? token;
  final _client = http.Client();

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        ...Device.headers,
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final q = query?.map((k, v) => MapEntry(k, '$v'))?..removeWhere((k, v) => v == 'null' || v.isEmpty);
    return Uri.parse('$apiBase$path').replace(queryParameters: (q == null || q.isEmpty) ? null : q);
  }

  /// Answers to GET calls, kept for [cacheFor] so going back to a tab is instant and costs no data. Any POST clears
  /// it (what you changed shows at once); pull-to-refresh skips it ([fresh]). Memory only, gone when the app closes.
  static const cacheFor = Duration(seconds: 45);
  final _cache = <String, (DateTime, Map<String, dynamic>)>{};
  final _inFlight = <String, Future<Map<String, dynamic>>>{};

  /// Runs [load] with the cache skipped (pull-to-refresh, "Try again").
  static Future<T> fresh<T>(Future<T> Function() load) => runZoned(load, zoneValues: {#apiFresh: true});

  void clearCache() => _cache.clear();

  Future<Map<String, dynamic>> get(String path, [Map<String, dynamic>? query]) {
    final uri = _uri(path, query);
    final key = '$token|$uri';
    final hit = _cache[key];
    if (Zone.current[#apiFresh] != true && hit != null && DateTime.now().difference(hit.$1) < cacheFor) return Future.value(hit.$2);
    // Two screens asking for the same thing at once share one request.
    return _inFlight[key] ??= () async {
      try {
        final body = _decode(await _send(() => _client.get(uri, headers: _headers)));
        _cache[key] = (DateTime.now(), body);
        if (_cache.length > 60) _cache.remove(_cache.keys.first);
        return body;
      } finally {
        _inFlight.remove(key);
      }
    }();
  }

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) async {
    _cache.clear();
    return _decode(await _send(() => _client.post(_uri(path), headers: {..._headers, 'Content-Type': 'application/json'}, body: jsonEncode(body ?? {}))));
  }

  /// Multipart POST (a meter reading with its photo).
  Future<Map<String, dynamic>> upload(String path, Map<String, String> fields,
      {File? file, String field = 'photo', List<File> files = const [], String filesField = 'photos[]'}) async {
    _cache.clear();
    final req = http.MultipartRequest('POST', _uri(path))
      ..headers.addAll(_headers)
      ..fields.addAll(fields);
    if (file != null) req.files.add(await http.MultipartFile.fromPath(field, file.path));
    for (final f in files) {
      req.files.add(await http.MultipartFile.fromPath(filesField, f.path));
    }
    return _decode(await _send(() async => http.Response.fromStream(await _client.send(req))));
  }

  Future<http.Response> _send(Future<http.Response> Function() call) async {
    try {
      return await call().timeout(const Duration(seconds: 40));
    } on SocketException {
      throw ApiException(0, 'No internet. Check your connection and try again.');
    } on HttpException {
      throw ApiException(0, 'Could not reach scharge. Try again.');
    } on Exception catch (e) {
      if (e.toString().contains('TimeoutException')) throw ApiException(0, 'scharge is taking too long. Try again.');
      rethrow;
    }
  }

  Map<String, dynamic> _decode(http.Response r) {
    Map<String, dynamic> body = {};
    try {
      final d = jsonDecode(r.body);
      if (d is Map<String, dynamic>) body = d;
    } catch (_) {}
    if (r.statusCode >= 200 && r.statusCode < 300) return body;
    if (r.statusCode == 426) {
      final url = body['download_url'] as String?;
      onUpdateRequired?.call(url);
      throw UpdateRequired(url);
    }
    var message = body['message'] as String? ?? 'Something went wrong (${r.statusCode}).';
    final errors = body['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) message = '${first.first}';
    }
    throw ApiException(r.statusCode, message, body);
  }
}

/// Sends an uncaught error to the server (App health). Works signed out; never throws.
Future<void> reportError(Object error, StackTrace? stack, {String? token, String? screen}) async {
  try {
    await http
        .post(Uri.parse('$apiBase/errors'),
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', ...Device.headers, if (token != null) 'Authorization': 'Bearer $token'},
            body: jsonEncode({
              'message': '$error'.length > 1000 ? '$error'.substring(0, 1000) : '$error',
              'stack': '${stack ?? ''}'.length > 8000 ? '$stack'.substring(0, 8000) : '${stack ?? ''}',
              'screen': screen,
              'app_version': Device.appVersion,
              'os_version': Device.osVersion,
              'device_model': Device.model,
            }))
        .timeout(const Duration(seconds: 10));
  } catch (_) {}
}
