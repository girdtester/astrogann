import 'dart:async';

import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../models/models.dart';

/// In-app notifications + unread badge (spec 3.7). Polls while the app is open and logged in.
class NotificationsController extends ChangeNotifier {
  NotificationsController(this.api);

  final ApiClient api;

  final List<AppNotification> items = [];
  int unread = 0;
  int _page = 0;
  int _lastPage = 1;
  bool loading = false;
  bool loadingMore = false;
  ApiException? error;
  Timer? _timer;

  bool get hasMore => _page < _lastPage;

  void startPolling() {
    _timer?.cancel();
    refresh(silent: true);
    _timer = Timer.periodic(AppConfig.notificationPollInterval, (_) => refresh(silent: true));
  }

  void stopPolling() {
    _timer?.cancel();
    _timer = null;
    items.clear();
    unread = 0;
    _page = 0;
    notifyListeners();
  }

  /// Reload page 1. [silent] = background refresh (no spinner, errors ignored).
  Future<void> refresh({bool silent = false}) async {
    if (!silent) {
      loading = true;
      error = null;
      notifyListeners();
    }
    try {
      final data = await api.get('notifications', query: {'page': '1'}) as Map<String, dynamic>;
      items
        ..clear()
        ..addAll(_parse(data));
      _readMeta(data);
    } on ApiException catch (e) {
      if (!silent) error = e;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> loadMore() async {
    if (loadingMore || !hasMore) return;
    loadingMore = true;
    notifyListeners();
    try {
      final data = await api.get('notifications', query: {'page': '${_page + 1}'}) as Map<String, dynamic>;
      items.addAll(_parse(data));
      _readMeta(data);
    } on ApiException {
      // keep what we have; the user can pull to refresh
    } finally {
      loadingMore = false;
      notifyListeners();
    }
  }

  Future<void> markRead(AppNotification n) async {
    if (n.isRead) return;
    final i = items.indexWhere((x) => x.id == n.id);
    if (i >= 0) items[i] = n.copyWith(isRead: true);
    unread = (unread - 1).clamp(0, 1 << 30);
    notifyListeners();
    try {
      final data = await api.post('notifications/${n.id}/read') as Map<String, dynamic>;
      unread = (data['unread_count'] as num?)?.toInt() ?? unread;
      notifyListeners();
    } on ApiException {
      // not critical
    }
  }

  Future<void> markAllRead() async {
    for (var i = 0; i < items.length; i++) {
      items[i] = items[i].copyWith(isRead: true);
    }
    unread = 0;
    notifyListeners();
    try {
      await api.post('notifications/read-all');
    } on ApiException {
      await refresh(silent: true);
    }
  }

  List<AppNotification> _parse(Map<String, dynamic> data) =>
      [for (final n in (data['items'] as List? ?? const [])) AppNotification.fromJson(n as Map<String, dynamic>)];

  void _readMeta(Map<String, dynamic> data) {
    final meta = (data['meta'] as Map?) ?? const {};
    _page = (meta['current_page'] as num?)?.toInt() ?? 1;
    _lastPage = (meta['last_page'] as num?)?.toInt() ?? 1;
    unread = (data['unread_count'] as num?)?.toInt() ?? unread;
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}
