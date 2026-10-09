/// Push notifications behind one class (Phase 12, docs/MOBILE.md §Push).
///
/// Until the Firebase files (google-services.json / GoogleService-Info.plist) are added, and always when running
/// in Chrome, the app uses [NoPushService]: everything works, there is just no push — users still see new entries
/// and the in-app notification list/badge.
/// Where a tapped notification should take the user: a category tab, or (null) the Notifications tab.
/// A new object each time, so tapping the same notification twice still reacts.
class PushTarget {
  PushTarget(this.categoryId);
  final int? categoryId;
}

abstract class PushService {
  /// Prepare push (permissions, listeners). Safe to call more than once.
  Future<void> init({required void Function(String? categoryId) onOpenFromPush});

  /// Device token to send with login and to POST /api/update-fcm-token; null when push is unavailable.
  Future<String?> token();

  /// Called when the device token changes so the app can tell the server.
  void onTokenRefresh(void Function(String token) callback);
}

class NoPushService implements PushService {
  @override
  Future<void> init({required void Function(String? categoryId) onOpenFromPush}) async {}

  @override
  Future<String?> token() async => null;

  @override
  void onTokenRefresh(void Function(String token) callback) {}
}
