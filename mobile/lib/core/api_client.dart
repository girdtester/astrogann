import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import 'api_exception.dart';
import 'token_storage.dart';

/// Thin HTTP client for the Laravel API. Unwraps the `{success, message, data, errors}` envelope and turns every
/// failure into an [ApiException]. When the server says the session is over (another login, deactivated, token
/// revoked) [onSessionEnded] is called once so the app can log out with the server's message.
class ApiClient {
  ApiClient({required this.tokens, http.Client? client, String? baseUrl})
      : _http = client ?? http.Client(),
        baseUrl = baseUrl ?? AppConfig.apiBaseUrl;

  final TokenStorage tokens;
  final String baseUrl;
  final http.Client _http;

  void Function(ApiException error)? onSessionEnded;

  /// The `message` of the last successful response (e.g. the neutral forgot-password text).
  String? lastMessage;

  Future<dynamic> get(String path, {Map<String, String>? query}) => _send('GET', path, query: query);

  Future<dynamic> post(String path, {Map<String, dynamic>? body}) => _send('POST', path, body: body);

  Future<dynamic> _send(String method, String path, {Map<String, String>? query, Map<String, dynamic>? body}) async {
    final token = await tokens.read();
    final uri = Uri.parse('$baseUrl/$path').replace(queryParameters: query);
    final headers = {
      'Accept': 'application/json',
      if (body != null) 'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };

    http.Response res;
    try {
      final request = method == 'GET'
          ? _http.get(uri, headers: headers)
          : _http.post(uri, headers: headers, body: body == null ? null : jsonEncode(body));
      res = await request.timeout(AppConfig.requestTimeout);
    } on TimeoutException {
      throw ApiException('The server is taking too long. Please try again.', code: 'network');
    } catch (_) {
      throw ApiException('Cannot reach the server. Check your internet connection and try again.', code: 'network');
    }

    Map<String, dynamic> json;
    try {
      json = jsonDecode(utf8.decode(res.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException('Unexpected server response (${res.statusCode}).', code: 'bad_response', status: res.statusCode);
    }

    if (json['success'] == true) {
      lastMessage = json['message'] as String?;
      return json['data'];
    }

    final errors = json['errors'];
    final error = ApiException(
      (json['message'] as String?) ?? 'Something went wrong.',
      status: res.statusCode,
      code: errors is Map && errors['code'] is String ? errors['code'] as String : null,
      fieldErrors: errors is Map
          ? {
              for (final e in errors.entries)
                if (e.value is List) e.key.toString(): (e.value as List).map((v) => v.toString()).toList(),
            }
          : const {},
    );

    if (token != null && error.endsSession) onSessionEnded?.call(error);
    throw error;
  }
}
