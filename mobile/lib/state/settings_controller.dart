import 'package:flutter/foundation.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../models/models.dart';

/// App config from GET /api/settings (app name, limits, Show More text, contact numbers, notice).
class SettingsController extends ChangeNotifier {
  SettingsController(this.api);

  final ApiClient api;
  AppSettings? settings;
  ApiException? error;
  bool loading = false;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      settings = AppSettings.fromJson(await api.get('settings') as Map<String, dynamic>);
    } on ApiException catch (e) {
      error = e;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
