import 'package:shared_preferences/shared_preferences.dart';

/// Stores the Sanctum bearer token. shared_preferences works on web (localStorage) and on Android/iOS
/// (see docs/SECURITY.md §6 — swap for flutter_secure_storage on devices if wanted).
class TokenStorage {
  static const _key = 'astrogann.token';

  Future<String?> read() async => (await SharedPreferences.getInstance()).getString(_key);

  Future<void> write(String token) async => (await SharedPreferences.getInstance()).setString(_key, token);

  Future<void> clear() async => (await SharedPreferences.getInstance()).remove(_key);
}
