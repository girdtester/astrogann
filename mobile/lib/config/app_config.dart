/// The ONE place for environment settings (docs/MOBILE.md).
///
/// Override at build/run time, e.g.
///   flutter run -d chrome --web-port 5555 --dart-define=API_BASE_URL=http://localhost:8000/api
///   flutter build apk --release --dart-define=API_BASE_URL=https://app.your-domain.com/api
class AppConfig {
  AppConfig._();

  /// Laravel API base URL (no trailing slash).
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8000/api',
  );

  /// Sent with login (spec 3.6: login accepts device_type).
  static const String deviceType = 'mobile';

  /// How often the unread-notification badge is refreshed while the app is open.
  static const Duration notificationPollInterval = Duration(seconds: 60);

  /// Network timeout for API calls.
  static const Duration requestTimeout = Duration(seconds: 20);
}
