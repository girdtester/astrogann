import 'package:flutter/widgets.dart';

import 'core/api_client.dart';
import 'state/auth_controller.dart';
import 'state/notifications_controller.dart';
import 'state/settings_controller.dart';

/// Gives every screen access to the shared controllers (no extra state package needed).
class AppScope extends InheritedWidget {
  const AppScope({
    super.key,
    required this.api,
    required this.auth,
    required this.settings,
    required this.notifications,
    required super.child,
  });

  final ApiClient api;
  final AuthController auth;
  final SettingsController settings;
  final NotificationsController notifications;

  static AppScope of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AppScope>();
    assert(scope != null, 'AppScope missing');
    return scope!;
  }

  @override
  bool updateShouldNotify(AppScope oldWidget) => false;
}
