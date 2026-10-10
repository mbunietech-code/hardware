import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../l10n/l10n.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.code, this.errors});

  final String message;
  final int? statusCode;
  final String? code;
  final Map<String, dynamic>? errors;

  bool get isUnauthorized => statusCode == 401;

  /// Network problems (no internet, server down) – the record must stay queued.
  bool get isNetwork => statusCode == null;

  @override
  String toString() => message;
}

/// Thin HTTP client for the Laravel API (/api/v1).
class ApiClient {
  ApiClient({required this.baseUrl, this.token, required this.deviceId, http.Client? client}) : _client = client ?? http.Client();

  String baseUrl;
  String? token;
  final String deviceId;
  final http.Client _client;

  static const timeout = Duration(seconds: 25);

  Uri _uri(String path, [Map<String, String>? query]) {
    final base = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    return Uri.parse('$base/api/v1/$path').replace(queryParameters: query);
  }

  Map<String, String> get _headers => {
    'Accept': 'application/json',
    'Accept-Language': L10n.lang,
    'Content-Type': 'application/json',
    'X-Device-Id': deviceId,
    if (token != null) 'Authorization': 'Bearer $token',
  };

  Future<Map<String, dynamic>> get(String path, {Map<String, String>? query}) => _send(() => _client.get(_uri(path, query), headers: _headers));

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) =>
      _send(() => _client.post(_uri(path), headers: _headers, body: jsonEncode(body ?? {})));

  Future<Map<String, dynamic>> _send(Future<http.Response> Function() request) async {
    http.Response res;
    try {
      res = await request().timeout(timeout);
    } on SocketException {
      throw ApiException(tr('No connection to the server.'));
    } on TimeoutException {
      throw ApiException(tr('The server took too long to respond.'));
    } on http.ClientException catch (e) {
      throw ApiException(tr('Connection problem: {error}', {'error': e.message}));
    } on HandshakeException {
      throw ApiException(tr('Secure connection to the server failed.'));
    }

    Map<String, dynamic> body = {};
    if (res.body.isNotEmpty) {
      try {
        final decoded = jsonDecode(res.body);
        body = decoded is Map<String, dynamic> ? decoded : {'data': decoded};
      } on FormatException {
        body = {};
      }
    }
    if (res.statusCode >= 200 && res.statusCode < 300) return body;

    final errors = body['errors'] is Map ? Map<String, dynamic>.from(body['errors'] as Map) : null;
    final firstError = errors?.values.expand((v) => v is List ? v : [v]).map((e) => e.toString()).firstOrNull;
    throw ApiException(
      firstError ?? body['message']?.toString() ?? 'Server error (${res.statusCode}).',
      statusCode: res.statusCode,
      code: body['code']?.toString(),
      errors: errors,
    );
  }

  Future<Map<String, dynamic>> login(String login, String password, {required String deviceName, required String platform}) => post('auth/login', {
    'login': login,
    'password': password,
    'device_id': deviceId,
    'device_name': deviceName,
    'platform': platform,
    'app_version': '1.0.0',
  });
}
