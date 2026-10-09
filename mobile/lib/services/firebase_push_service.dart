import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'push_service.dart';

/// Real phone push via Firebase Cloud Messaging (backlog B-01, D-104). Used on Android (google-services.json);
/// iPhone joins once GoogleService-Info.plist + the APNs key are added. Chrome / tests keep [NoPushService].
///
/// The server sends "notification" messages, so Android shows them by itself when the app is in the background or
/// closed. While the app is open, the in-app live check updates the badge and list (no duplicate banner).
class FirebasePushService implements PushService {
  bool _ready = false;
  final List<void Function(String token)> _refreshListeners = [];

  @override
  Future<void> init({required void Function(String? categoryId) onOpenFromPush}) async {
    if (_ready) return;
    try {
      await Firebase.initializeApp();
      final messaging = FirebaseMessaging.instance;
      // Android 13+ / iOS: ask once; the user can say no — the app still works without push.
      await messaging.requestPermission(alert: true, badge: true, sound: true);

      String? target(RemoteMessage m) => m.data['category_id']?.toString();
      // App opened by tapping a notification: from the background …
      FirebaseMessaging.onMessageOpenedApp.listen((m) => onOpenFromPush(target(m)));
      // … or from fully closed.
      final initial = await messaging.getInitialMessage();
      if (initial != null) onOpenFromPush(target(initial));

      messaging.onTokenRefresh.listen((t) {
        for (final l in _refreshListeners) {
          l(t);
        }
      });
      _ready = true;
    } catch (e) {
      // Firebase not configured on this platform or Play services missing: carry on without push.
      debugPrint('Push disabled: $e');
    }
  }

  @override
  Future<String?> token() async {
    if (!_ready) return null;
    try {
      return await FirebaseMessaging.instance.getToken().timeout(const Duration(seconds: 10));
    } catch (_) {
      return null; // offline at login: the token is sent later via onTokenRefresh / next login
    }
  }

  @override
  void onTokenRefresh(void Function(String token) callback) => _refreshListeners.add(callback);
}
