import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'app.dart';
import 'config/app_config.dart';
import 'services/firebase_push_service.dart';
import 'theme/app_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  // A phone build must point at the live server (docs/MOBILE.md §2). Catch a forgotten --dart-define early.
  final local = AppConfig.apiBaseUrl.contains('localhost') || AppConfig.apiBaseUrl.contains('127.0.0.1');
  if (kReleaseMode && !kIsWeb && local) {
    runApp(const _MisconfiguredApp());
    return;
  }

  // Phone push on Android (google-services.json, D-104). Chrome and iPhone (until its Firebase file is added) run
  // without push — everything else works the same.
  final push = !kIsWeb && defaultTargetPlatform == TargetPlatform.android ? FirebasePushService() : null;
  runApp(AstroGannApp(push: push));
}

class _MisconfiguredApp extends StatelessWidget {
  const _MisconfiguredApp();

  @override
  Widget build(BuildContext context) => MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        home: const Scaffold(
          body: Center(
            child: Padding(
              padding: EdgeInsets.all(32),
              child: Text(
                'This build has no server address.\n\nRebuild with:\n'
                '--dart-define=API_BASE_URL=https://your-domain/api',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.yellowDark, fontSize: 16),
              ),
            ),
          ),
        ),
      );
}
