import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../core/api_exception.dart';
import '../models/models.dart';
import '../theme/app_theme.dart';
import '../widgets/entry_card.dart';
import '../widgets/show_more_card.dart';
import '../widgets/state_views.dart';

/// Tab per ACTIVE category, names/order/count from GET /api/categories (spec 3.9). Never hard-coded.
class EntriesScreen extends StatefulWidget {
  const EntriesScreen({super.key, required this.openCategory});

  /// A notification asked to open this category id.
  final ValueNotifier<int?> openCategory;

  @override
  State<EntriesScreen> createState() => _EntriesScreenState();
}

class _EntriesScreenState extends State<EntriesScreen> with TickerProviderStateMixin {
  List<Category>? _categories;
  ApiException? _error;
  bool _loading = true;
  TabController? _tabs;

  /// Selected tab + a counter, so a tab reloads from the server every time it is shown (fresh data, and a device
  /// whose session was replaced elsewhere is logged out on the very next tap — D-92).
  final ValueNotifier<(int, int)> _shown = ValueNotifier((0, 0));

  @override
  void initState() {
    super.initState();
    widget.openCategory.addListener(_jumpToRequested);
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadCategories());
  }

  @override
  void dispose() {
    widget.openCategory.removeListener(_jumpToRequested);
    _tabs?.dispose();
    _shown.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).api.get('categories') as List;
      final cats = [for (final c in data) Category.fromJson(c as Map<String, dynamic>)];
      final previousId = (_categories != null && _tabs != null && _tabs!.index < _categories!.length)
          ? _categories![_tabs!.index].id
          : null;
      _tabs?.dispose();
      _tabs = cats.isEmpty ? null : TabController(length: cats.length, vsync: this);
      _tabs?.addListener(() {
        if (!_tabs!.indexIsChanging) _shown.value = (_tabs!.index, _shown.value.$2 + 1);
      });
      final keep = cats.indexWhere((c) => c.id == previousId);
      if (keep > 0) _tabs!.index = keep;
      setState(() => _categories = cats);
      _jumpToRequested();
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _jumpToRequested() {
    final id = widget.openCategory.value;
    if (id == null || _categories == null || _tabs == null) return;
    final i = _categories!.indexWhere((c) => c.id == id);
    if (i >= 0) {
      _tabs!.animateTo(i);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('That category is not available.')));
    }
    widget.openCategory.value = null;
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _categories == null) return const LoadingView(label: 'Loading categories…');
    if (_error != null && _categories == null) return ErrorView(message: _error!.message, onRetry: _loadCategories);
    final cats = _categories ?? const <Category>[];
    if (cats.isEmpty) {
      return RefreshIndicator(
        onRefresh: _loadCategories,
        child: const EmptyView(icon: Icons.category_outlined, title: 'No categories yet', message: 'Please check back later.'),
      );
    }

    final notice = AppScope.of(context).settings.settings?.noticeText;
    return Column(children: [
      if (notice != null)
        Container(
          width: double.infinity,
          color: AppColors.blueLight,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Text(notice, style: const TextStyle(color: AppColors.blue)),
        ),
      Material(
        color: Colors.white,
        child: TabBar(
          key: const Key('category-tabs'),
          controller: _tabs,
          isScrollable: true,
          tabs: [for (final c in cats) Tab(text: c.name)],
        ),
      ),
      Expanded(
        child: TabBarView(
          controller: _tabs,
          children: [
            for (var i = 0; i < cats.length; i++)
              _CategoryTab(category: cats[i], index: i, shown: _shown, onCategoriesChanged: _loadCategories),
          ],
        ),
      ),
    ]);
  }
}

class _CategoryTab extends StatefulWidget {
  const _CategoryTab({required this.category, required this.index, required this.shown, required this.onCategoriesChanged});
  final Category category;
  final int index;
  final ValueNotifier<(int, int)> shown;
  final Future<void> Function() onCategoriesChanged;

  @override
  State<_CategoryTab> createState() => _CategoryTabState();
}

class _CategoryTabState extends State<_CategoryTab> with AutomaticKeepAliveClientMixin {
  EntriesPage? _page;
  ApiException? _error;
  bool _loading = true;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
    widget.shown.addListener(_onShown);
  }

  @override
  void dispose() {
    widget.shown.removeListener(_onShown);
    super.dispose();
  }

  /// Tab selected again → quiet refresh (keeps the current cards on screen while loading).
  void _onShown() {
    if (widget.shown.value.$1 == widget.index && _page != null && mounted) _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = _page == null;
      _error = null;
    });
    final scope = AppScope.of(context);
    try {
      final data = await scope.api.get('categories/${widget.category.id}/entries') as Map<String, dynamic>;
      if (mounted) setState(() => _page = EntriesPage.fromJson(data));
      scope.auth.refreshSubscription().catchError((_) {});
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.code == 'not_found') {
        // Category was deactivated meanwhile → rebuild the tabs from the server.
        await widget.onCategoriesChanged();
        return;
      }
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    if (_loading) return const LoadingView();
    if (_error != null && _page == null) return ErrorView(message: _error!.message, onRetry: _load);

    final page = _page!;
    final scope = AppScope.of(context);
    final settings = scope.settings.settings;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        key: PageStorageKey('entries-${widget.category.id}'),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          ListenableBuilder(listenable: scope.auth, builder: (context, _) => _SubscriptionStrip(info: scope.auth.subscription)),
          const SizedBox(height: 12),
          if (page.entries.isEmpty)
            SizedBox(
              height: 260,
              child: EmptyView(icon: Icons.insert_chart_outlined, title: 'No entries in ${widget.category.name} yet'),
            )
          else ...[
            EntryTable(entries: page.entries, categoryName: widget.category.name),
            const SizedBox(height: 14),
          ],
          if (page.restricted) ...[
            const SizedBox(height: 4),
            ShowMoreCard(showMore: page.showMore ?? (settings != null ? ShowMore.fromSettings(settings) : const ShowMore(text: '', phones: [], whatsapp: []))),
          ],
        ],
      ),
    );
  }
}

class _SubscriptionStrip extends StatelessWidget {
  const _SubscriptionStrip({required this.info});
  final SubscriptionInfo? info;

  @override
  Widget build(BuildContext context) {
    final i = info;
    final active = i?.isActive ?? false;
    final text = i == null || i.status == 'none'
        ? 'No subscription'
        : active
            ? 'Subscription active · ${i.daysLeft} day${i.daysLeft == 1 ? '' : 's'} left'
            : 'Subscription expired';
    return Row(children: [
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: active ? AppColors.oliveLight : AppColors.yellowLight,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(active ? Icons.verified_rounded : Icons.info_outline_rounded, size: 16, color: active ? AppColors.oliveDark : AppColors.yellowDark),
          const SizedBox(width: 6),
          Text(text, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: active ? AppColors.oliveDark : AppColors.yellowDark)),
        ]),
      ),
    ]);
  }
}
