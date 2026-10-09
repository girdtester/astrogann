/// The ONE place for environment settings (docs/MOBILE.md).
///
/// Default = the LIVE server, so a plain `flutter build apk --release` talks to astrogann25.com.
/// Local testing: override at run time, e.g.
///   flutter run -d chrome --web-port 5555 --dart-define=API_BASE_URL=http://localhost:8000/api
/// When the site gets a valid SSL certificate, change the default to https:// (and remove the http exception in
/// android/app/src/main/res/xml/network_security_config.xml and ios/Runner/Info.plist).
class AppConfig {
  AppConfig._();

  /// Laravel API base URL (no trailing slash).
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://astrogann25.com/api',
  );

  /// Sent with login (spec 3.6: login accepts device_type).
  static const String deviceType = 'mobile';

  /// How often the unread-notification badge is refreshed while the app is open.
  /// Also detects "logged in on another device" while the app is just open (same as the website, D-92).
  static const Duration notificationPollInterval = Duration(seconds: 20); // fallback until /settings loads (D-96)

  /// Network timeout for API calls.
  static const Duration requestTimeout = Duration(seconds: 20);
}
