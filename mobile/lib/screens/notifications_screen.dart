import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/app_theme.dart';
import '../widgets/state_views.dart';

/// Notifications list with unread highlighting (spec 3.7). Tap → mark read and open the category tab.
class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key, required this.onOpenCategory});
  final void Function(int categoryId) onOpenCategory;

  @override
  Widget build(BuildContext context) {
    final n = AppScope.of(context).notifications;
    return ListenableBuilder(
      listenable: n,
      builder: (context, _) {
        if (n.loading && n.items.isEmpty) return const LoadingView();
        if (n.error != null && n.items.isEmpty) return ErrorView(message: n.error!.message, onRetry: n.refresh);

        return RefreshIndicator(
          onRefresh: n.refresh,
          child: n.items.isEmpty
              ? const EmptyView(icon: Icons.notifications_none_rounded, title: 'No notifications yet',
                  message: 'You will see new entries and subscription updates here.')
              : NotificationListener<ScrollNotification>(
                  onNotification: (s) {
                    if (s.metrics.pixels > s.metrics.maxScrollExtent - 200) n.loadMore();
                    return false;
                  },
                  child: ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    itemCount: n.items.length + 1,
                    separatorBuilder: (_, i) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      if (i == 0) {
                        return Row(children: [
                          Text('${n.unread} unread', style: const TextStyle(color: AppColors.muted)),
                          const Spacer(),
                          if (n.unread > 0)
                            TextButton.icon(
                              key: const Key('mark-all-read'),
                              onPressed: n.markAllRead,
                              icon: const Icon(Icons.done_all_rounded),
                              label: const Text('Mark all as read'),
                            ),
                        ]);
                      }
                      final item = n.items[i - 1];
                      return _Tile(
                        item: item,
                        onTap: () {
                          n.markRead(item);
                          if (item.categoryId != null) onOpenCategory(item.categoryId!);
                        },
                      );
                    },
                  ),
                ),
        );
      },
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.item, required this.onTap});
  final AppNotification item;
  final VoidCallback onTap;

  IconData get _icon => switch (item.type) {
        'new_entry' => Icons.insert_chart_outlined,
        'subscription' => Icons.workspace_premium_outlined,
        _ => Icons.event_outlined,
      };

  @override
  Widget build(BuildContext context) => Card(
        color: item.isRead ? Colors.white : AppColors.oliveTint,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: item.isRead ? AppColors.page : AppColors.oliveLight,
                child: Icon(_icon, size: 20, color: AppColors.oliveDark),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(item.message,
                      style: TextStyle(fontWeight: item.isRead ? FontWeight.w500 : FontWeight.w700, color: AppColors.text)),
                  const SizedBox(height: 4),
                  Text('${item.when} · ${item.ago}', style: const TextStyle(fontSize: 12, color: AppColors.muted)),
                ]),
              ),
              if (!item.isRead)
                Container(
                  width: 10,
                  height: 10,
                  margin: const EdgeInsets.only(top: 6, left: 8),
                  decoration: const BoxDecoration(color: AppColors.yellow, shape: BoxShape.circle),
                ),
            ]),
          ),
        ),
      );
}
