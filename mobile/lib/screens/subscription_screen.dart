import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../core/api_exception.dart';
import '../models/models.dart';
import '../theme/app_theme.dart';
import '../widgets/show_more_card.dart';
import '../widgets/state_views.dart';

/// Subscription status and expiry date (spec 3.2). Show More opens when not active.
class SubscriptionScreen extends StatefulWidget {
  const SubscriptionScreen({super.key});

  @override
  State<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends State<SubscriptionScreen> {
  ApiException? _error;
  bool _loading = false;
  static final _fmt = DateFormat('dd MMM yyyy');

  Future<void> _refresh() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await AppScope.of(context).auth.refreshSubscription();
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AppScope.of(context);
    return ListenableBuilder(
      listenable: scope.auth,
      builder: (context, _) {
        final info = scope.auth.subscription;
        if (info == null && _loading) return const LoadingView();
        if (info == null && _error != null) return ErrorView(message: _error!.message, onRetry: _refresh);

        final settings = scope.settings.settings;
        final active = info?.isActive ?? false;
        final status = info == null || info.status == 'none' ? 'No subscription' : (active ? 'Active' : 'Expired');

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(16),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Icon(active ? Icons.verified_rounded : Icons.info_outline_rounded,
                          color: active ? AppColors.olive : AppColors.yellowDark, size: 28),
                      const SizedBox(width: 10),
                      Text(status,
                          key: const Key('subscription-status'),
                          style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: active ? AppColors.oliveDark : AppColors.yellowDark)),
                    ]),
                    const SizedBox(height: 18),
                    _Row(label: 'Start date', value: info?.startDate != null ? _fmt.format(info!.startDate!) : '—'),
                    _Row(label: 'Expiry date', value: info?.endDate != null ? _fmt.format(info!.endDate!) : '—'),
                    _Row(label: 'Days left', value: active ? '${info!.daysLeft}' : '—'),
                    const Divider(height: 28),
                    Text(
                      'You can see the latest ${info?.entriesLimit ?? settings?.entriesLimitExpired ?? 3} entries in each category.'
                      '${active ? ' You get a notification for every new entry and an e-mail ${settings?.reminderDays ?? 3} days before expiry.' : ''}',
                      style: const TextStyle(color: AppColors.muted),
                    ),
                  ]),
                ),
              ),
              if (!active && settings != null) ...[
                const SizedBox(height: 16),
                ShowMoreCard(showMore: ShowMore.fromSettings(settings), initiallyOpen: true),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppColors.muted))),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.text)),
        ]),
      );
}
