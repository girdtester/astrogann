# ASTRO GANN — Backlog

Open work that is waiting for something from the owner. When the owner says an item is ready, do the "When ready"
steps, test, then move it to **Done** with the date. Never delete items.

---

## B-01 Push notifications on phones — IN PROGRESS (Android files received 2026-10-12, D-104; server verified with Google; phone test pending)

**Status:** backend ready and tested (D-89). App code prepared behind `PushService` (`mobile/lib/services/push_service.dart`,
currently `NoPushService`). Users already get in-app notifications + badge; only the phone banner/sound is missing.

### What the owner needs to provide / do
| # | Item | Where to get it | Give it to us as |
|---|---|---|---|
| 1 | **Firebase project** (free) | https://console.firebase.google.com → Add project → name e.g. "ASTRO GANN" | — |
| 2 | **Android app** in that project, package name **`com.astrogann.app`** | Project settings → General → Add app → Android | download **`google-services.json`** |
| 3 | **iOS app** in that project, bundle ID **`com.astrogann.app`** (only if publishing on iPhone) | Project settings → General → Add app → Apple | download **`GoogleService-Info.plist`** |
| 4 | **Apple push key (APNs .p8)** — iPhone only, needs an Apple Developer account ($99/year) | developer.apple.com → Certificates, IDs & Profiles → Keys → "+" → Apple Push Notifications service → download `.p8`, note Key ID + Team ID | upload it yourself in Firebase → Project settings → **Cloud Messaging** → Apple app configuration |
| 5 | **Service account key** (for the server) | Firebase → Project settings → **Service accounts** → "Generate new private key" | upload it yourself in the website: **Super Admin → Settings → Push notifications (Firebase) → Upload key**, then press **Check connection** (or send the file to the developer) |
| 6 | A **test phone** (Android is enough to start) with the app installed and a user logged in | — | tell us the user's e-mail |

Keep these files private (they are keys). Do not post them in chat or e-mail to people outside the project.

### When ready — developer steps (docs/MOBILE.md §5 has the code)
**Backend**
- [ ] Super Admin → Settings → Push notifications: key uploaded, status **ON**, **Check connection** = OK.
- [ ] Production `.env`/cron already running (cron link or cPanel cron, docs/DEPLOYMENT.md §6).

**App (Flutter)**
- [ ] Put `google-services.json` in `mobile/android/app/`; `GoogleService-Info.plist` in `mobile/ios/Runner/` (Xcode).
- [ ] `flutter pub add firebase_core firebase_messaging` (+ optional `flutter_local_notifications` for banners while the app is open).
- [ ] Android Gradle: add the `com.google.gms.google-services` plugin (settings.gradle.kts + app/build.gradle.kts).
- [ ] Android 13+: request the notification permission at first start (`FirebaseMessaging.requestPermission()`).
- [ ] iOS (on a Mac): Signing & Capabilities → Push Notifications + Background Modes → Remote notifications.
- [ ] Create `lib/services/firebase_push_service.dart` (code in MOBILE.md §5) and use it in `main.dart` (keep `NoPushService` on web).
- [ ] Tapping a notification opens the app on the right category tab (`data.category_id`).
- [ ] Build a test APK: `flutter build apk --debug --dart-define=API_BASE_URL=https://<live-domain>/api`.

### Test plan (tick all before closing)
- [ ] Log in on the phone → in Super Admin → Users the account has a device token (Settings → Send test push works).
- [ ] Admin adds an entry → **active** user's phone shows "New entry added in <category>…" with the image, within ~1 minute.
- [ ] **Expired** user's phone shows the "Renew your subscription…" push (or nothing if that switch is off).
- [ ] Tap the notification → app opens on that category tab.
- [ ] App closed / phone locked → notification still arrives.
- [ ] Log in on a second phone → only the new phone gets pushes (single session).
- [ ] Uninstall the app → next push removes the dead token (Reports / Logs shows "old token(s) removed").
- [ ] Reports / Logs shows `push.sent` rows with device counts.

---

## Done
_(nothing yet)_
