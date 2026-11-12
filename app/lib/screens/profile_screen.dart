import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../models/staff.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'account_settings_screen.dart';
import 'admin_screen.dart';
import 'login_screen.dart';
import 'track_screen.dart';

/// Minimal personal hub; all permissions are enforced by the server endpoints.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Future<(Map<String, dynamic>, List<RequestItem>, List<Payment>)>? _future;
  bool _wasLoggedIn = false;

  Future<(Map<String, dynamic>, List<RequestItem>, List<Payment>)> _load() async {
    final api = context.read<Api>();
    // Always request current staff permissions; never infer roles from local UI.
    final me = Map<String, dynamic>.from(await api.get('me', authed: true) as Map);
    if (mounted) {
      await context.read<AuthStore>().setUser({
        'id': me['id'], 'name': me['name'], 'phone': me['phone'],
        'neighborhood': me['neighborhood'], 'staff': me['staff'],
      });
    }
    List<RequestItem> requests = [];
    List<Payment> payments = [];
    // Optional services should not block staff access if temporarily unavailable.
    try {
      final response = await api.get('me/requests', authed: true);
      requests = (response as List).map((e) =>
          RequestItem.fromJson(Map<String, dynamic>.from(e as Map))).toList();
    } catch (_) {}
    try {
      final response = await api.get('me/payments', authed: true);
      payments = (response as List).map((e) =>
          Payment.fromJson(Map<String, dynamic>.from(e as Map))).toList();
    } catch (_) {}
    return (me, requests, payments);
  }

  Future<void> _refresh() async {
    if (!mounted || !context.read<AuthStore>().isLoggedIn) return;
    final loading = _load();
    setState(() => _future = loading);
    try { await loading; } catch (_) {}
  }

  Future<void> _settings() async {
    await Navigator.push<void>(context, MaterialPageRoute(
      builder: (_) => const AccountSettingsScreen(),
    ));
    if (mounted && context.read<AuthStore>().isLoggedIn) await _refresh();
  }

  Future<void> _requests(List<RequestItem> items) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: .75,
      builder: (context, scroll) => ListView(
        controller: scroll,
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
        children: [
          Text('درخواست‌های من', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          if (items.isEmpty) const StateView.empty('هنوز درخواستی ثبت نکرده‌اید.'),
          for (final item in items)
            Padding(padding: const EdgeInsets.only(bottom: 10),
              child: RequestCard(item: item, expanded: true)),
        ],
      ),
    ),
  );

  Future<void> _payments(List<Payment> items) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: .75,
      builder: (context, scroll) => ListView(
        controller: scroll,
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
        children: [
          Text('پرداخت‌های من', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          if (items.isEmpty) const StateView.empty('پرداختی ثبت نشده است.', icon: 'payment'),
          for (final p in items)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                title: Text(p.title, maxLines: 2, overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text(faDigits(p.dateFa) + ' · ' + p.statusLabel),
                trailing: Text(money(p.amount), style: const TextStyle(fontWeight: FontWeight.w800)),
                onTap: () {
                  final uri = Uri.tryParse(p.receiptUrl);
                  if (p.status == 'paid' && uri?.scheme == 'https') {
                    launchUrl(uri!, mode: LaunchMode.externalApplication);
                  }
                },
              ),
            ),
        ],
      ),
    ),
  );

  Widget _profileHeader(Map<String, dynamic> me, StaffInfo staff) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final role = staff.isStaff
        ? (staff.role.trim().isEmpty ? 'کارکنان شهرداری' : staff.role.trim())
        : 'شهروند';
    final phone = (me['phone'] ?? '').toString();
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: dark ? const Color(0xFF364463) : Brand.line),
      ),
      child: Row(children: [
        Container(width: 58, height: 64,
          decoration: const BoxDecoration(color: Brand.tint, borderRadius: Brand.taqche),
          child: const Center(child: KIcon('user', size: 29, color: Brand.brick))),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(context.watch<AuthStore>().displayName,
            maxLines: 2, overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 3),
          Text(role, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700,
              color: dark ? Brand.kahgel : Brand.qanat)),
          if (phone.isNotEmpty) Text(faDigits(phone),
              textDirection: TextDirection.ltr,
              style: Theme.of(context).textTheme.bodySmall),
        ])),
        IconButton(
          tooltip: 'ویرایش حساب', onPressed: _settings,
          icon: const Icon(Icons.edit_outlined)),
      ]),
    );
  }

  Widget _shortcut(String label, IconData icon, VoidCallback action, String detail) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Material(
      color: Theme.of(context).cardColor,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        borderRadius: BorderRadius.circular(15),
        onTap: action,
        child: Container(
          constraints: const BoxConstraints(minHeight: 88),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            border: Border.all(color: dark ? const Color(0xFF364463) : Brand.line),
            borderRadius: BorderRadius.circular(15),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center, children: [
              Icon(icon, color: Brand.qanat, size: 23),
              const SizedBox(height: 6),
              Text(label, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800)),
              if (detail.isNotEmpty) Text(detail,
                maxLines: 1, overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall),
            ]),
        ),
      ),
    );
  }

  Widget _quickLinks(List<RequestItem> requests, List<Payment> payments) {
    return LayoutBuilder(builder: (context, box) {
      const gap = 9.0;
      final columns = box.maxWidth >= 620 ? 4 : 2;
      final width = (box.maxWidth - (columns - 1) * gap) / columns;
      final actions = <Widget>[
        _shortcut('درخواست‌های من', Icons.description_outlined,
            () => _requests(requests), faDigits(requests.length) + ' پرونده'),
        _shortcut('پرداخت‌های من', Icons.receipt_long_outlined,
            () => _payments(payments), faDigits(payments.length) + ' پرداخت'),
        _shortcut('پیگیری با کد', Icons.manage_search_rounded,
            () => Navigator.push<void>(context,
              MaterialPageRoute(builder: (_) => const TrackScreen())), ''),
        _shortcut('حساب و امنیت', Icons.verified_user_outlined,
            () => _settings(), ''),
      ];
      return Wrap(spacing: gap, runSpacing: gap,
        children: [for (final item in actions) SizedBox(width: width, child: item)]);
    });
  }

  Widget _management(StaffInfo staff) => Material(
    color: Brand.night,
    borderRadius: BorderRadius.circular(18),
    child: InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: () => Navigator.push<void>(context,
        MaterialPageRoute(builder: (_) => AdminScreen(staff: staff))),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Row(children: [
          const Icon(Icons.admin_panel_settings_outlined, size: 31, color: Brand.sun),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('میز کار من', style: TextStyle(color: Colors.white,
                fontWeight: FontWeight.w800, fontSize: 16)),
            Text('امکانات متناسب با دسترسی شما',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Brand.kahgel)),
          ])),
          const Icon(Icons.chevron_left_rounded, color: Colors.white),
        ]),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthStore>();
    if (auth.isLoggedIn && (!_wasLoggedIn || _future == null)) _future = _load();
    _wasLoggedIn = auth.isLoggedIn;
    if (!auth.isLoggedIn) return _guest();
    return Scaffold(
      appBar: AppBar(title: const Text('من'),
        actions: [IconButton(tooltip: 'تنظیمات', onPressed: _settings,
            icon: const Icon(Icons.settings_outlined))]),
      body: RefreshIndicator(
        color: Brand.qanat,
        onRefresh: _refresh,
        child: FutureBuilder<(Map<String, dynamic>, List<RequestItem>, List<Payment>)>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.hasError) return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [StateView.error('بارگذاری اطلاعات حساب ممکن نشد.', onRetry: _refresh)]);
            if (!snapshot.hasData) return const StateView.loading();
            final (me, requests, payments) = snapshot.data!;
            final staff = StaffInfo.fromJson(me['staff']);
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 30),
              children: [Center(child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 760),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  _profileHeader(me, staff),
                  const SizedBox(height: 20),
                  Text('دسترسی سریع', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 10),
                  _quickLinks(requests, payments),
                  if (staff.isStaff) ...[
                    const SizedBox(height: 18),
                    _management(staff),
                  ],
                  const SizedBox(height: 18),
                  Card(child: Column(children: [
                    const ListTile(
                      leading: Icon(Icons.history_rounded, color: Brand.qanat),
                      title: Text('فعالیت‌های من',
                        style: TextStyle(fontWeight: FontWeight.w800))),
                    const Divider(height: 1),
                    ListTile(
                      title: const Text('درخواست‌ها و گزارش‌ها'),
                      trailing: Text(faDigits(requests.length) + ' مورد'),
                      onTap: () => _requests(requests)),
                    ListTile(
                      title: const Text('سوابق پرداخت'),
                      trailing: Text(faDigits(payments.length) + ' مورد'),
                      onTap: () => _payments(payments)),
                  ])),
                  const SizedBox(height: 12),
                  TextButton.icon(onPressed: _settings,
                    icon: const Icon(Icons.tune_rounded),
                    label: const Text('تنظیمات، حریم خصوصی و بروزرسانی اپ')),
                ]),
              ))],
            );
          },
        ),
      ),
    );
  }

  Widget _guest() => Scaffold(
    appBar: AppBar(title: const Text('من'),
      actions: [IconButton(tooltip: 'تنظیمات', onPressed: _settings,
          icon: const Icon(Icons.settings_outlined))]),
    body: ListView(padding: const EdgeInsets.all(24), children: [
      const SizedBox(height: 24),
      const Center(child: LogoBadge(size: 100)),
      const SizedBox(height: 18),
      Text('پیشخوان شخصی شما', textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleLarge),
      const SizedBox(height: 8),
      Text('درخواست‌ها، پرداخت‌ها و پیگیری‌ها در یک جا.',
          textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
      const SizedBox(height: 24),
      FilledButton.icon(
        onPressed: () async {
          await ensureLogin(context);
          if (mounted) setState(() {});
        },
        icon: const Icon(Icons.login_rounded),
        label: const Text('ورود با شماره همراه')),
      const SizedBox(height: 10),
      OutlinedButton.icon(onPressed: _settings,
        icon: const Icon(Icons.settings_outlined),
        label: const Text('تنظیمات و بروزرسانی اپ')),
    ]),
  );
}
