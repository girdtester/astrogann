import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../services/push_service.dart';
import '../theme/app_theme.dart';
import '../widgets/brand.dart';
import '../widgets/show_more_card.dart';
import 'entries_screen.dart';
import 'notifications_screen.dart';
import 'subscription_screen.dart';

/// Signed-in shell: Entries · Subscription · Notifications (badge), logout in the app bar.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  /// Set by the notifications screen to jump to a category tab.
  final ValueNotifier<int?> _openCategory = ValueNotifier(null);

  ValueNotifier<PushTarget?>? _pushTarget;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final target = AppScope.of(context).pushTarget;
    if (!identical(target, _pushTarget)) {
      _pushTarget?.removeListener(_onPush);
      _pushTarget = target..addListener(_onPush);
      // Opened from a notification before this screen existed (cold start + login restore).
      if (target.value != null) WidgetsBinding.instance.addPostFrameCallback((_) => _onPush());
    }
  }

  /// Tapped phone notification (D-104): new entry → its category tab; anything else → Notifications.
  void _onPush() {
    final t = _pushTarget?.value;
    if (t == null || !mounted) return;
    _pushTarget!.value = null;
    final scope = AppScope.of(context);
    if (t.categoryId != null) {
      _openCategory.value = t.categoryId;
      setState(() => _index = 0);
      if (!(scope.auth.subscription?.isActive ?? false)) {
        WidgetsBinding.instance.addPostFrameCallback((_) => _showSubscribeSheet());
      }
    } else {
      setState(() => _index = 2);
      scope.notifications.refresh(silent: true);
    }
  }

  @override
  void dispose() {
    _pushTarget?.removeListener(_onPush);
    _openCategory.dispose();
    super.dispose();
  }

  /// "Contact the admin to subscribe" sheet for users without an active subscription (D-94).
  void _showSubscribeSheet() {
    final settings = AppScope.of(context).settings.settings;
    if (!mounted || settings == null) return;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Padding(
          key: const Key('subscribe-sheet'),
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Subscribe to see all the latest updates',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.oliveDark),
              ),
              const SizedBox(height: 6),
              Text(
                'You can see the latest ${settings.entriesLimitExpired} entries. Contact the admin to get full access.',
                style: const TextStyle(color: AppColors.muted),
              ),
              const SizedBox(height: 16),
              ShowMoreCard(showMore: ShowMore.fromSettings(settings), initiallyOpen: true),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _confirmLogout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text('You will need to log in again to see the entries.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(96, 40)),
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Log out'),
          ),
        ],
      ),
    );
    if (ok == true && mounted) await AppScope.of(context).auth.logout();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AppScope.of(context);
    final appName = scope.settings.settings?.appName ?? 'ASTRO GANN';

    // Any tap → live check goes back to its fast interval (D-96).
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => scope.notifications.bump(),
      child: Scaffold(
        appBar: AppBar(
          title: BrandTitle(appName: appName),
          actions: [
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: Center(
                child: Text(scope.auth.user?.name ?? '', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
              ),
            ),
            IconButton(
              key: const Key('logout'),
              tooltip: 'Log out',
              icon: const Icon(Icons.logout_rounded),
              onPressed: _confirmLogout,
            ),
          ],
        ),
        // Phone-like reading width on tablets / desktop browsers.
        body: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 760),
            child: IndexedStack(
              index: _index,
              children: [
                EntriesScreen(openCategory: _openCategory),
                const SubscriptionScreen(),
                NotificationsScreen(
                  onOpenCategory: (id) {
                    _openCategory.value = id;
                    setState(() => _index = 0);
                    // Not subscribed? Ask them to contact the admin right away (D-94).
                    if (!(scope.auth.subscription?.isActive ?? false)) {
                      WidgetsBinding.instance.addPostFrameCallback((_) => _showSubscribeSheet());
                    }
                  },
                ),
              ],
            ),
          ),
        ),
        bottomNavigationBar: ListenableBuilder(
          listenable: scope.notifications,
          builder: (context, _) {
            final unread = scope.notifications.unread;
            return NavigationBar(
              selectedIndex: _index,
              onDestinationSelected: (i) {
                setState(() => _index = i);
                if (i == 2) scope.notifications.refresh(silent: true);
                if (i == 1) scope.auth.refreshSubscription().catchError((_) {});
              },
              destinations: [
                const NavigationDestination(
                  icon: Icon(Icons.grid_view_outlined),
                  selectedIcon: Icon(Icons.grid_view_rounded),
                  label: 'Entries',
                ),
                const NavigationDestination(
                  icon: Icon(Icons.workspace_premium_outlined),
                  selectedIcon: Icon(Icons.workspace_premium),
                  label: 'Subscription',
                ),
                NavigationDestination(
                  icon: Badge(
                    isLabelVisible: unread > 0,
                    label: Text('$unread'),
                    child: const Icon(Icons.notifications_outlined),
                  ),
                  selectedIcon: Badge(
                    isLabelVisible: unread > 0,
                    label: Text('$unread'),
                    child: const Icon(Icons.notifications),
                  ),
                  label: 'Notifications',
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
