// Data models matching docs/API.md (the contract). Only fields the app uses are mapped.

String _s(dynamic v, [String fallback = '']) => v == null ? fallback : v.toString();
int _i(dynamic v, [int fallback = 0]) => v is int ? v : int.tryParse(_s(v)) ?? fallback;

class ContactLink {
  const ContactLink(this.label, this.target);
  final String label;

  /// tel number (+91…) for phones, full https://wa.me/… URL for WhatsApp.
  final String target;
}

/// GET /api/settings — nothing user-visible is hard-coded in the app (spec 3.9).
class AppSettings {
  const AppSettings({
    required this.appName,
    required this.entriesLimitActive,
    required this.entriesLimitExpired,
    required this.reminderDays,
    required this.showMoreText,
    required this.phones,
    required this.whatsapp,
    required this.email,
    required this.noticeText,
  });

  final String appName;
  final int entriesLimitActive;
  final int entriesLimitExpired;
  final int reminderDays;
  final String showMoreText;
  final List<ContactLink> phones;
  final List<ContactLink> whatsapp;
  final String? email;
  final String? noticeText;

  factory AppSettings.fromJson(Map<String, dynamic> j) {
    final c = (j['contact'] as Map?) ?? const {};
    return AppSettings(
      appName: _s(j['app_name'], 'ASTRO GANN'),
      entriesLimitActive: _i(j['entries_limit_active'], 7),
      entriesLimitExpired: _i(j['entries_limit_expired'], 3),
      reminderDays: _i(j['reminder_days'], 3),
      showMoreText: _s(j['show_more_text']),
      phones: [for (final p in (c['phones'] as List? ?? const [])) ContactLink(_s(p['label']), _s(p['tel']))],
      whatsapp: [for (final w in (c['whatsapp_links'] as List? ?? const [])) ContactLink(_s(w['label']), _s(w['url']))],
      email: c['email'] as String?,
      noticeText: (j['notice_text'] as String?)?.trim().isEmpty ?? true ? null : j['notice_text'] as String,
    );
  }
}

class UserProfile {
  const UserProfile({required this.id, required this.name, required this.email, required this.roleKind});
  final int id;
  final String name;
  final String email;

  /// superadmin | staff | user — the app only accepts "user".
  final String roleKind;

  factory UserProfile.fromJson(Map<String, dynamic> j) => UserProfile(
        id: _i(j['id']),
        name: _s(j['name']),
        email: _s(j['email']),
        roleKind: _s((j['role'] as Map?)?['kind']),
      );
}

class SubscriptionInfo {
  const SubscriptionInfo({
    required this.status,
    required this.isActive,
    this.startDate,
    this.endDate,
    required this.daysLeft,
    required this.entriesLimit,
  });

  /// active | expired | none
  final String status;
  final bool isActive;
  final DateTime? startDate;
  final DateTime? endDate;
  final int daysLeft;
  final int entriesLimit;

  factory SubscriptionInfo.fromJson(Map<String, dynamic> j) => SubscriptionInfo(
        status: _s(j['status'], 'none'),
        isActive: j['is_active'] == true,
        startDate: DateTime.tryParse(_s(j['start_date'])),
        endDate: DateTime.tryParse(_s(j['end_date'])),
        daysLeft: _i(j['days_left']),
        entriesLimit: _i(j['entries_limit']),
      );
}

class Category {
  const Category({required this.id, required this.name, required this.code, required this.sortOrder});
  final int id;
  final String name;
  final String code;
  final int sortOrder;

  factory Category.fromJson(Map<String, dynamic> j) =>
      Category(id: _i(j['id']), name: _s(j['name']), code: _s(j['code']), sortOrder: _i(j['sort_order']));
}

class Entry {
  const Entry({
    required this.id,
    required this.imageUrl,
    required this.date,
    required this.time,
    required this.addedAgo,
  });

  final int id;
  final String imageUrl;

  /// Ready-to-show strings from the server (D-78): "06 Oct 2026", "3:45 PM", "2 hours ago".
  final String date;
  final String time;
  final String addedAgo;

  factory Entry.fromJson(Map<String, dynamic> j) {
    final d = (j['display'] as Map?) ?? const {};
    return Entry(
      id: _i(j['id']),
      imageUrl: _s(j['image_url']),
      date: _s(d['date'], _s(j['entry_date'])),
      time: _s(d['time'], _s(j['entry_time'])),
      addedAgo: _s(d['added_ago']),
    );
  }
}

class ShowMore {
  const ShowMore({required this.text, required this.phones, required this.whatsapp, this.email});
  final String text;
  final List<ContactLink> phones;
  final List<ContactLink> whatsapp;
  final String? email;

  factory ShowMore.fromJson(Map<String, dynamic> j) => ShowMore(
        text: _s(j['text']),
        phones: [for (final p in (j['phones'] as List? ?? const [])) ContactLink(_s(p['label']), _s(p['tel']))],
        whatsapp: [for (final w in (j['whatsapp_links'] as List? ?? const [])) ContactLink(_s(w['label']), _s(w['url']))],
        email: j['email'] as String?,
      );

  factory ShowMore.fromSettings(AppSettings s) =>
      ShowMore(text: s.showMoreText, phones: s.phones, whatsapp: s.whatsapp, email: s.email);
}

/// GET /api/categories/{id}/entries
class EntriesPage {
  const EntriesPage({required this.restricted, required this.limit, required this.entries, this.showMore});
  final bool restricted;
  final int limit;
  final List<Entry> entries;
  final ShowMore? showMore;

  factory EntriesPage.fromJson(Map<String, dynamic> j) => EntriesPage(
        restricted: j['restricted'] == true,
        limit: _i(j['limit']),
        entries: [for (final e in (j['entries'] as List? ?? const [])) Entry.fromJson(e as Map<String, dynamic>)],
        showMore: j['show_more'] is Map ? ShowMore.fromJson(j['show_more'] as Map<String, dynamic>) : null,
      );
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.message,
    this.categoryId,
    this.categoryName,
    required this.isRead,
    required this.when,
    required this.ago,
  });

  final int id;

  /// new_entry | expiry
  final String type;
  final String message;
  final int? categoryId;
  final String? categoryName;
  final bool isRead;
  final String when;
  final String ago;

  AppNotification copyWith({bool? isRead}) => AppNotification(
        id: id,
        type: type,
        message: message,
        categoryId: categoryId,
        categoryName: categoryName,
        isRead: isRead ?? this.isRead,
        when: when,
        ago: ago,
      );

  factory AppNotification.fromJson(Map<String, dynamic> j) {
    final d = (j['display'] as Map?) ?? const {};
    return AppNotification(
      id: _i(j['id']),
      type: _s(j['type']),
      message: _s(j['message']),
      categoryId: j['category_id'] == null ? null : _i(j['category_id']),
      categoryName: j['category_name'] as String?,
      isRead: j['is_read'] == true,
      when: _s(d['date_time']),
      ago: _s(d['ago']),
    );
  }
}
