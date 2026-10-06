import 'dart:convert';

import 'package:astrogann_app/app.dart';
import 'package:astrogann_app/core/api_client.dart';
import 'package:astrogann_app/core/token_storage.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Fake Laravel API returning the documented envelopes (docs/API.md).
class FakeApi {
  bool subscribed = true;
  String roleKind = 'user';
  bool replaceSession = false;
  final List<String> calls = [];

  http.Response _ok(Object? data) => http.Response(jsonEncode({'success': true, 'message': 'OK', 'data': data, 'errors': null}), 200,
      headers: {'content-type': 'application/json'});

  http.Response _err(int status, String message, Map<String, dynamic> errors) =>
      http.Response(jsonEncode({'success': false, 'message': message, 'data': null, 'errors': errors}), status,
          headers: {'content-type': 'application/json'});

  Map<String, dynamic> get _sub => subscribed
      ? {'status': 'active', 'is_active': true, 'start_date': '2026-10-01', 'end_date': '2026-11-05', 'days_left': 30, 'entries_limit': 7}
      : {'status': 'expired', 'is_active': false, 'start_date': '2026-08-01', 'end_date': '2026-09-01', 'days_left': 0, 'entries_limit': 3};

  Map<String, dynamic> _entry(int i) => {
        'id': i,
        'category_id': 1,
        'image_url': 'http://localhost/x$i.png',
        'entry_date': '2026-10-0$i',
        'entry_time': '09:15',
        'display': {'date': '0$i Oct 2026', 'time': '9:15 AM', 'added_on': '', 'added_ago': '1 hour ago'},
      };

  MockClient get client => MockClient((req) async {
        final path = req.url.path.replaceFirst(RegExp(r'^.*/api/'), '');
        calls.add('${req.method} $path');
        if (replaceSession && path != 'auth/login' && path != 'settings') {
          return _err(401, 'You were logged out because your account was signed in on another device.', {'code': 'session_replaced'});
        }
        switch (path) {
          case 'settings':
            return _ok({
              'app_name': 'ASTRO GANN',
              'entries_limit_active': 7,
              'entries_limit_expired': 3,
              'reminder_days': 3,
              'show_more_text': 'Contact the admin to renew.',
              'contact': {
                'email': 'ganndecoder@gmail.com',
                'phones': [{'label': '+91 75583 77920', 'tel': '+917558377920'}],
                'whatsapp_links': [{'label': '+917558377920', 'url': 'https://wa.me/917558377920'}],
              },
              'notice_text': null,
            });
          case 'auth/login':
            final body = jsonDecode(req.body) as Map<String, dynamic>;
            if (body['password'] != 'password') return _err(401, 'These credentials do not match our records.', {'code': 'invalid_credentials'});
            expect(body['device_type'], 'mobile');
            return _ok({
              'token': '1|abc',
              'user': {'id': 1, 'name': 'Active Trader', 'email': body['email'], 'role': {'kind': roleKind}},
              'subscription': roleKind == 'user' ? _sub : null,
            });
          case 'auth/logout':
            return _ok(null);
          case 'subscription':
            return _ok(_sub);
          case 'categories':
            return _ok([
              {'id': 1, 'name': 'Nifty 50', 'code': 'NIFTY50', 'sort_order': 1},
              {'id': 2, 'name': 'NSE', 'code': 'NSE', 'sort_order': 2},
            ]);
          case 'categories/1/entries':
          case 'categories/2/entries':
            final n = subscribed ? 7 : 3;
            return _ok({
              'restricted': !subscribed,
              'limit': n,
              'entries': [for (var i = 1; i <= n; i++) _entry(i)],
              'show_more': subscribed
                  ? null
                  : {
                      'text': 'Contact the admin to renew.',
                      'email': 'ganndecoder@gmail.com',
                      'phones': [{'label': '+91 75583 77920', 'tel': '+917558377920'}],
                      'whatsapp_links': [],
                    },
            });
          case 'notifications':
            return _ok({'items': [], 'meta': {'current_page': 1, 'last_page': 1, 'per_page': 20, 'total': 0}, 'unread_count': 2});
        }
        return _err(404, 'Not found.', {'code': 'not_found'});
      });
}

Future<void> _login(WidgetTester tester, {String password = 'password'}) async {
  await tester.enterText(find.byKey(const Key('email')), 'user.active@astrogann.test');
  await tester.enterText(find.byKey(const Key('password')), password);
  await tester.tap(find.byKey(const Key('login-button')));
  await tester.pumpAndSettle();
}

void main() {
  late FakeApi fake;

  Widget app() => AstroGannApp(api: ApiClient(tokens: TokenStorage(), client: fake.client, baseUrl: 'http://test/api'));

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    fake = FakeApi();
  });

  testWidgets('login screen shows and wrong password shows the server message', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    expect(find.text('Log in'), findsOneWidget);
    await _login(tester, password: 'wrong');
    expect(find.byKey(const Key('login-error')), findsOneWidget);
    expect(find.textContaining('do not match'), findsOneWidget);
  });

  testWidgets('active user sees tabs from the API and 7 entries with date and time', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);

    expect(find.text('Nifty 50'), findsOneWidget);
    expect(find.text('NSE'), findsOneWidget);
    expect(find.text('01 Oct 2026'), findsOneWidget);
    expect(find.text('9:15 AM'), findsWidgets);
    expect(find.byKey(const Key('show-more-button')), findsNothing);
    expect(find.textContaining('Subscription active'), findsOneWidget);
    expect(find.text('2'), findsWidgets); // unread badge
  });

  testWidgets('expired user sees 3 entries and Show More with contacts', (tester) async {
    fake.subscribed = false;
    tester.view.physicalSize = const Size(900, 3000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);

    expect(find.text('03 Oct 2026'), findsOneWidget);
    expect(find.text('04 Oct 2026'), findsNothing);
    await tester.tap(find.byKey(const Key('show-more-button')).first);
    await tester.pumpAndSettle();
    expect(find.textContaining('Call +91 75583 77920'), findsOneWidget);
    expect(find.text('ganndecoder@gmail.com'), findsOneWidget);
  });

  testWidgets('staff accounts are refused by the app', (tester) async {
    fake.roleKind = 'staff';
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);

    expect(find.textContaining('This app is for users'), findsOneWidget);
    expect(fake.calls, contains('POST auth/logout'));
  });

  testWidgets('session replaced elsewhere logs out with the server message', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);
    expect(find.text('Nifty 50'), findsOneWidget);

    fake.replaceSession = true;
    await tester.tap(find.text('Subscription'));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('logout-reason')), findsOneWidget);
    expect(find.textContaining('another device'), findsOneWidget);
  });
}
