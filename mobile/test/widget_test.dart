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
          case 'auth/register':
            final body = jsonDecode(req.body) as Map<String, dynamic>;
            return http.Response(
                jsonEncode({
                  'success': true,
                  'message': 'Welcome! Your account is ready.',
                  'data': {
                    'token': '2|new',
                    'user': {'id': 9, 'name': body['name'], 'email': body['email'], 'role': {'kind': 'user'}},
                    'subscription': {'status': 'none', 'is_active': false, 'days_left': 0, 'entries_limit': 3},
                  },
                  'errors': null,
                }),
                201);
          case 'auth/forgot-password':
            return http.Response(
                jsonEncode({'success': true, 'message': 'If an account exists for this e-mail, we have sent a password reset link.', 'data': null, 'errors': null}),
                200);
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
            return _ok({
              'items': [
                {'id': 1, 'type': 'new_entry', 'message': subscribed ? 'New entry added in Nifty 50. Check latest update now!' : 'New entry added in Nifty 50. Renew your subscription to see all the latest updates.',
                 'category_id': 1, 'category_name': 'Nifty 50', 'is_read': false, 'display': {'date_time': '09 Oct 2026, 3:30 PM', 'ago': '1 minute ago'}},
              ],
              'meta': {'current_page': 1, 'last_page': 1, 'per_page': 20, 'total': 1},
              'unread_count': 2,
            });
          case 'notifications/1/read':
            return _ok({'unread_count': 1});
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

  testWidgets('sign up creates an account and shows 3 entries with Show More', (tester) async {
    fake.subscribed = false;
    tester.view.physicalSize = const Size(900, 3000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('signup-link')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('signup-name')), 'New Trader');
    await tester.enterText(find.byKey(const Key('signup-email')), 'new@example.com');
    await tester.enterText(find.byKey(const Key('signup-password')), 'password1');
    await tester.enterText(find.byKey(const Key('signup-confirm')), 'password1');
    await tester.tap(find.byKey(const Key('signup-button')));
    await tester.pumpAndSettle();

    expect(fake.calls, contains('POST auth/register'));
    expect(find.text('Nifty 50'), findsOneWidget);
    expect(find.text('03 Oct 2026'), findsOneWidget);
    expect(find.byKey(const Key('show-more-button')), findsWidgets);
  });

  testWidgets('forgot password shows the server message', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('forgot-link')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('forgot-email')), 'user.active@astrogann.test');
    await tester.tap(find.byKey(const Key('forgot-button')));
    await tester.pumpAndSettle();

    expect(fake.calls, contains('POST auth/forgot-password'));
    expect(find.byKey(const Key('forgot-done')), findsOneWidget);
    expect(find.textContaining('password reset link'), findsOneWidget);
  });

  testWidgets('staff accounts are refused by the app', (tester) async {
    fake.roleKind = 'staff';
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);

    expect(find.textContaining('This app is for users'), findsOneWidget);
    expect(fake.calls, contains('POST auth/logout'));
  });

  testWidgets('switching category tab after another device logged in logs out (D-92)', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);
    await tester.tap(find.text('NSE'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Nifty 50')); // back to an already-loaded tab: still calls the server
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('logout-reason')), findsNothing);

    fake.replaceSession = true; // same email/password used on another phone
    await tester.tap(find.text('NSE'));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('logout-reason')), findsOneWidget);
    expect(find.textContaining('another device'), findsOneWidget);
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
  testWidgets('not subscribed: tapping a new-entry notification asks to contact the admin (D-94)', (tester) async {
    fake.subscribed = false;
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);

    await tester.tap(find.text('Notifications'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Renew your subscription'));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('subscribe-sheet')), findsOneWidget);
    expect(find.text('Subscribe to see all the latest updates'), findsOneWidget);
    expect(find.textContaining('Call +91 75583 77920'), findsWidgets);
  });

  testWidgets('subscribed: tapping a new-entry notification just opens the category', (tester) async {
    await tester.pumpWidget(app());
    await tester.pumpAndSettle();
    await _login(tester);

    await tester.tap(find.text('Notifications'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Check latest update'));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('subscribe-sheet')), findsNothing);
    expect(find.text('01 Oct 2026'), findsOneWidget);
  });
}
