import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/token_storage.dart';
import '../models/models.dart';
import '../services/push_service.dart';

enum AuthStatus { unknown, loggedOut, loggedIn }

/// Login state for the user/trader (spec 3.2 "Login (web / mobile)", 3.8 single session).
class AuthController extends ChangeNotifier {
  AuthController({required this.api, required this.tokens, required this.push}) {
    // Server says the session ended (logged in elsewhere / deactivated / revoked) → log out with its message.
    api.onSessionEnded = (e) => _endLocally(e.message);
    // Firebase gave the phone a new token → tell the server (once, for the whole app life).
    push.onTokenRefresh((t) {
      if (status == AuthStatus.loggedIn) _sendPushToken(t);
    });
  }

  DateTime? _pushSyncedAt;

  /// Re-register this phone for push (D-105): on app start, after login and when the app comes back (at most every
  /// 30 min). If the server ever dropped the token, simply opening the app fixes it — no new login needed.
  Future<void> syncPushToken({bool force = false}) async {
    if (status != AuthStatus.loggedIn) return;
    final now = DateTime.now();
    if (!force && _pushSyncedAt != null && now.difference(_pushSyncedAt!) < const Duration(minutes: 30)) return;
    final t = await push.token();
    if (t != null) await _sendPushToken(t);
  }

  Future<void> _sendPushToken(String token) async {
    try {
      await api.post('update-fcm-token', body: {'fcm_token': token});
      _pushSyncedAt = DateTime.now();
    } catch (_) {
      // offline: next app start / resume tries again
    }
  }

  final ApiClient api;
  final TokenStorage tokens;
  final PushService push;

  AuthStatus status = AuthStatus.unknown;
  UserProfile? user;
  SubscriptionInfo? subscription;

  /// Shown on the login screen after an automatic logout (e.g. "signed in on another device").
  String? logoutReason;

  /// Restore a saved login on app start.
  Future<void> restore() async {
    if (await tokens.read() == null) {
      _set(AuthStatus.loggedOut);
      return;
    }
    try {
      final data = await api.get('auth/me') as Map<String, dynamic>;
      _apply(data);
      if (user!.roleKind != 'user') {
        await _endLocally(null);
        return;
      }
      _set(AuthStatus.loggedIn);
      syncPushToken(force: true);
    } on ApiException catch (e) {
      if (e.isNetwork) {
        // Offline at start-up: keep the token, screens show retry states.
        _set(AuthStatus.loggedIn);
      } else {
        await _endLocally(e.endsSession ? e.message : null);
      }
    }
  }

  /// Throws [ApiException] with a user-friendly message on failure.
  Future<void> login(String email, String password) async {
    final data = await api.post('auth/login', body: {
      'email': email.trim(),
      'password': password,
      'device_type': AppConfig.deviceType,
      'fcm_token': await push.token(),
    }) as Map<String, dynamic>;

    final profile = UserProfile.fromJson(data['user'] as Map<String, dynamic>);
    if (profile.roleKind != 'user') {
      // Staff use the website; end the token we just got.
      await tokens.write(data['token'] as String);
      try {
        await api.post('auth/logout');
      } catch (_) {}
      await tokens.clear();
      throw ApiException('This app is for users / traders. Admins, please use the website.', code: 'forbidden');
    }

    await tokens.write(data['token'] as String);
    _apply(data);
    logoutReason = null;
    _set(AuthStatus.loggedIn);
    _pushSyncedAt = DateTime.now();
  }

  /// Self sign-up (D-85): creates a user/trader without subscription and logs straight in.
  Future<void> register({required String name, required String email, String? phone, required String password}) async {
    final data = await api.post('auth/register', body: {
      'name': name.trim(),
      'email': email.trim(),
      if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
      'password': password,
      'password_confirmation': password,
      'device_type': AppConfig.deviceType,
      'fcm_token': await push.token(),
    }) as Map<String, dynamic>;
    await tokens.write(data['token'] as String);
    _apply(data);
    logoutReason = null;
    _set(AuthStatus.loggedIn);
  }

  /// "Forgot password": the server e-mails a reset link (opens the website). Returns the server's message.
  Future<String> forgotPassword(String email) async {
    await api.post('auth/forgot-password', body: {'email': email.trim()});
    return api.lastMessage ?? 'Please check your e-mail.';
  }

  Future<void> logout() async {
    try {
      await api.post('auth/logout');
    } catch (_) {
      // Already logged out on the server, or offline — we clear locally anyway.
    }
    await _endLocally(null);
  }

  Future<void> refreshSubscription() async {
    final data = await api.get('subscription') as Map<String, dynamic>;
    subscription = SubscriptionInfo.fromJson(data);
    notifyListeners();
  }

  void _apply(Map<String, dynamic> data) {
    user = UserProfile.fromJson(data['user'] as Map<String, dynamic>);
    subscription = data['subscription'] is Map ? SubscriptionInfo.fromJson(data['subscription'] as Map<String, dynamic>) : null;
  }

  Future<void> _endLocally(String? reason) async {
    await tokens.clear();
    user = null;
    subscription = null;
    logoutReason = reason;
    _set(AuthStatus.loggedOut);
  }

  void _set(AuthStatus s) {
    status = s;
    notifyListeners();
  }
}
