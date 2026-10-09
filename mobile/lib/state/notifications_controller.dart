import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../models/models.dart';

/// In-app notifications + unread badge (spec 3.7). Checks the server while the app is open and logged in.
///
/// Adaptive live check (D-96), sized for 1 lakh users: starts at the server's `live_check_seconds`,
/// doubles while nothing changes (max 2 min), resets on a new notification or any tap ([bump]),
/// adds ±20% jitter, and stops completely while the app is in the background ([pause]/[resume]).
class NotificationsController extends ChangeNotifier {
  NotificationsController(this.api, {Duration Function()? baseInterval})
    : _base = baseInterval ?? (() => AppConfig.notificationPollInterval);

  final ApiClient api;
  final Duration Function() _base;
  static const _max = Duration(minutes: 2);
  final _rnd = Random();
  Duration? _delay;
  bool _running = false;

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
    _running = true;
    _delay = _base();
    refresh(silent: true);
    _schedule();
  }

  /// App went to the background: no checks at all (saves battery and server load).
  void pause() {
    _timer?.cancel();
    _timer = null;
  }

  /// Back in the app: check now (also detects "logged in elsewhere"), then fast again.
  void resume() {
    if (!_running) return;
    _delay = _base();
    refresh(silent: true);
    _schedule();
  }

  /// The user did something: go back to the fast interval.
  void bump() {
    if (!_running) return;
    final base = _base();
    if (_delay != base) {
      _delay = base;
      _schedule();
    }
  }

  void _schedule() {
    _timer?.cancel();
    final ms = (_delay ?? _base()).inMilliseconds * (0.8 + _rnd.nextDouble() * 0.4);
    _timer = Timer(Duration(milliseconds: ms.round()), () async {
      final changed = await refresh(silent: true);
      if (!_running) return;
      final next = (_delay ?? _base()) * 2;
      _delay = changed ? _base() : (next > _max ? _max : next);
      _schedule();
    });
  }

  void stopPolling() {
    _running = false;
    _timer?.cancel();
    _timer = null;
    items.clear();
    unread = 0;
    _page = 0;
    notifyListeners();
  }

  /// Reload page 1. [silent] = background refresh (no spinner, errors ignored).
  /// Returns true when something changed (new notification or different unread count).
  Future<bool> refresh({bool silent = false}) async {
    final beforeTop = items.isEmpty ? null : items.first.id;
    final beforeUnread = unread;
    var changed = false;
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
      changed = unread != beforeUnread || (items.isEmpty ? null : items.first.id) != beforeTop;
    } on ApiException catch (e) {
      if (!silent) error = e;
    } finally {
      loading = false;
      notifyListeners();
    }
    return changed;
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

  List<AppNotification> _parse(Map<String, dynamic> data) => [
    for (final n in (data['items'] as List? ?? const [])) AppNotification.fromJson(n as Map<String, dynamic>),
  ];

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
