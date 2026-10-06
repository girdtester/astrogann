# ASTRO GANN — Mobile app (Flutter)

Status: **Phases 10–11 done, Phase 12 done without Firebase files** (2026-10-06). Users / traders only.
App id `com.astrogann.app` (Android `applicationId`, iOS bundle id), display name **ASTRO GANN**, icons from the logo.

## 1. Run it (Chrome — no phone or emulator needed)
Start the backend first (`backend/`: `php artisan serve`, and `php artisan schedule:work` for queued mail/push), then:
```
cd mobile
flutter run -d chrome --web-port 5555
```
Open http://localhost:5555 and log in with `user.active@astrogann.test` / `password` (or `user.expired@…`).
Local CORS allows any `http://localhost:<port>` while `APP_ENV=local` (`backend/config/cors.php`).

## 2. The ONE place to change the server (going live)
`lib/config/app_config.dart` → `apiBaseUrl` (default `http://localhost:8000/api`). Override without editing code:
```
flutter run  -d chrome --web-port 5555 --dart-define=API_BASE_URL=http://localhost:8000/api
flutter build apk --release --dart-define=API_BASE_URL=https://app.your-domain.com/api
```
Everything users see comes from the server (D-76/D-78/3.9): app name, category tabs (names, order, active), 7/3 limits,
Show More text, both phone/WhatsApp numbers, e-mail, notice text, date/time strings. Changing them in the Super Admin
screens changes the app with no update.

## 3. Structure
```
lib/
  main.dart, app.dart          app start, routing by login state, lifecycle refresh
  app_scope.dart               shares the controllers with every screen
  config/app_config.dart       API URL, device_type, poll interval, timeout
  core/api_client.dart         HTTP + {success,message,data,errors} envelope + session-ended hook
  core/api_exception.dart      error codes (session_replaced, account_inactive, …)
  core/token_storage.dart      bearer token (shared_preferences — web + devices)
  models/models.dart           types matching docs/API.md
  state/                       AuthController, SettingsController, NotificationsController
  services/push_service.dart   push behind one interface (NoPushService until Firebase is added)
  screens/                     splash, login, home shell, entries (tabs), subscription, notifications
  widgets/                     entry card + image viewer, Show More, loading/empty/error views, brand
  theme/app_theme.dart         same colours as the website: olive, light blue, yellow — never red
test/widget_test.dart          7 widget tests with a fake API (login, 7 vs 3 + Show More, staff refused, session replaced)
```
Packages: `http`, `shared_preferences`, `intl`, `url_launcher` (all Dart/Flutter-team packages).

## 4. Behaviour (spec 3.2 user / 3.8 / 3.9)
- Login sends `device_type=mobile` and the FCM token when push is available. Staff accounts are refused with
  "This app is for users / traders. Admins, please use the website." (their token is logged out at once).
- Tabs = active categories from the API; each tab: pull to refresh, entries (image, date, time — tap to zoom),
  7 when active / 3 when expired, then **Show More** with Call / WhatsApp buttons for each number and the e-mail.
- Subscription tab: status, start, expiry, days left; Show More when not active.
- Notifications tab: unread highlight + badge (refreshed every 60 s and when the app comes back), mark one / all
  as read, tap opens the category tab; paging.
- Logged in elsewhere / deactivated → the app logs out and the login screen shows the server's reason.
- Every screen has loading, empty and error (with retry) states.

### 4b. Sign up & forgot password (D-84, D-85)
- Login screen: **Forgot password?** (sends the reset e-mail; the link opens the website; then log in with the new
  password) and **Create an account** (hidden when the Super Admin switches sign-up off). After sign-up the user is
  logged in and sees the latest 3 entries + Show More until the admin activates a subscription.

## 5. Push notifications — add Firebase later (Phase 12)
The app works without push (users still get the in-app list and badge). When you have a Firebase project:
1. Firebase console → add an **Android app** with package `com.astrogann.app` → download `google-services.json`
   → put it in `mobile/android/app/`. Add an **iOS app** with bundle id `com.astrogann.app` → download
   `GoogleService-Info.plist` → add it to `mobile/ios/Runner/` in Xcode.
2. `cd mobile && flutter pub add firebase_core firebase_messaging`
3. Android: in `android/settings.gradle.kts` add `id("com.google.gms.google-services") version "4.4.2" apply false`
   to `plugins {}`, and in `android/app/build.gradle.kts` add `id("com.google.gms.google-services")` to `plugins {}`.
4. iOS (on a Mac): Xcode → Runner → Signing & Capabilities → add **Push Notifications** and **Background Modes →
   Remote notifications**; upload your APNs key in Firebase → Cloud Messaging.
5. Create `lib/services/firebase_push_service.dart`:
```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'push_service.dart';

class FirebasePushService implements PushService {
  bool _ready = false;
  @override
  Future<void> init({required void Function(String? categoryId) onOpenFromPush}) async {
    if (kIsWeb || _ready) return;
    await Firebase.initializeApp();
    await FirebaseMessaging.instance.requestPermission();
    FirebaseMessaging.onMessageOpenedApp.listen((m) => onOpenFromPush(m.data['category_id'] as String?));
    _ready = true;
  }
  @override
  Future<String?> token() async => _ready ? FirebaseMessaging.instance.getToken() : null;
  @override
  void onTokenRefresh(void Function(String token) callback) {
    if (_ready) FirebaseMessaging.instance.onTokenRefresh.listen(callback);
  }
}
```
6. In `lib/main.dart` use `runApp(AstroGannApp(push: kIsWeb ? NoPushService() : FirebasePushService()));`
7. ~~Backend `.env`: `PUSH_DRIVER=fcm`, …~~ (superseded 2026-10-06, D-89): in the website go to **Super Admin →
   Settings → Push notifications (Firebase)**, upload the service-account key (Firebase console → Project settings →
   Service accounts → Generate new private key), press **Check connection**, then **Send test push** to a user who
   is logged in on the app. Push is skipped in Chrome by design.
8. Foreground notifications: Android/iOS show FCM notifications automatically when the app is in the background;
   to also show a banner while the app is open, add `flutter_local_notifications` (optional).

## 6. Build — Android
- Fix the Java warning from `flutter doctor` first: Android Studio → Settings → Build Tools → Gradle → Gradle JDK =
  bundled JBR, then `flutter config --jdk-dir "C:\Program Files\Android\Android Studio\jbr"`.
- Debug APK: `flutter build apk --debug --dart-define=API_BASE_URL=https://app.your-domain.com/api`
- Release (Play Store):
  1. `keytool -genkey -v -keystore %USERPROFILE%\astrogann-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload`
  2. Create `mobile/android/key.properties` (never commit it): `storePassword=… keyPassword=… keyAlias=upload storeFile=C:/Users/<you>/astrogann-upload.jks`
  3. In `android/app/build.gradle.kts` load `key.properties` into a `signingConfigs { create("release") {…} }` and use it in
     `buildTypes.release` (Flutter docs: "Build and release an Android app").
  4. `flutter build appbundle --release --dart-define=API_BASE_URL=https://app.your-domain.com/api` → upload
     `build/app/outputs/bundle/release/app-release.aab`.
- The API must be **https** on real phones (Android blocks plain http by default).

## 7. Build — iOS (needs a Mac with Xcode)
1. Copy the `mobile/` folder to the Mac, `flutter pub get`, `cd ios && pod install`.
2. Open `ios/Runner.xcworkspace`, set your Team under Signing, bundle id `com.astrogann.app`.
3. `flutter build ipa --release --dart-define=API_BASE_URL=https://app.your-domain.com/api`, upload with Transporter
   or Xcode Organizer to App Store Connect.

## 8. Tests
`cd mobile && flutter analyze && flutter test` (5 widget tests, fake API — no server needed).
