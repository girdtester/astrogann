import 'package:flutter/material.dart';

import 'app_scope.dart';
import 'config/app_config.dart';
import 'core/api_client.dart';
import 'core/token_storage.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'screens/splash_screen.dart';
import 'services/push_service.dart';
import 'state/auth_controller.dart';
import 'state/notifications_controller.dart';
import 'state/settings_controller.dart';
import 'theme/app_theme.dart';

class AstroGannApp extends StatefulWidget {
  const AstroGannApp({super.key, this.api, this.push});

  /// Injectable for widget tests.
  final ApiClient? api;
  final PushService? push;

  @override
  State<AstroGannApp> createState() => _AstroGannAppState();
}

class _AstroGannAppState extends State<AstroGannApp> with WidgetsBindingObserver {
  late final TokenStorage _tokens = TokenStorage();
  late final ApiClient _api = widget.api ?? ApiClient(tokens: _tokens);
  late final PushService _push = widget.push ?? NoPushService();
  late final AuthController _auth = AuthController(api: _api, tokens: _api.tokens, push: _push);
  late final SettingsController _settings = SettingsController(_api);
  late final NotificationsController _notifications = NotificationsController(
    _api,
    baseInterval: () => _settings.settings == null
        ? AppConfig.notificationPollInterval
        : Duration(seconds: _settings.settings!.liveCheckSeconds),
  );

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _auth.addListener(_onAuthChanged);
    _settings.load();
    _auth.restore();
    _push.init(onOpenFromPush: (_) {});
  }

  void _onAuthChanged() {
    if (_auth.status == AuthStatus.loggedIn) {
      _notifications.startPolling();
    } else if (_auth.status == AuthStatus.loggedOut) {
      _notifications.stopPolling();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Back in the app: check immediately (badge + "logged in elsewhere"); in the background: no checks (D-96).
    if (_auth.status != AuthStatus.loggedIn) return;
    if (state == AppLifecycleState.resumed) {
      _notifications.resume();
    } else if (state == AppLifecycleState.paused || state == AppLifecycleState.hidden) {
      _notifications.pause();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _auth.removeListener(_onAuthChanged);
    _notifications.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AppScope(
    api: _api,
    auth: _auth,
    settings: _settings,
    notifications: _notifications,
    child: ListenableBuilder(
      listenable: Listenable.merge([_auth, _settings]),
      builder: (context, _) => MaterialApp(
        title: _settings.settings?.appName ?? 'ASTRO GANN',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        home: switch (_auth.status) {
          AuthStatus.unknown => const SplashScreen(),
          AuthStatus.loggedOut => const LoginScreen(),
          AuthStatus.loggedIn => const HomeShell(),
        },
      ),
    ),
  );
}
