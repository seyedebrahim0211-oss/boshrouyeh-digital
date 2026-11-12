import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../core/settings.dart';
import '../core/updater.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'login_screen.dart';
import 'track_screen.dart';

/// "پیشخوان شهروند": profile, stats, requests with progress, payments, settings.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Future<(Map<String, dynamic>, List<RequestItem>, List<Payment>)>? _future;
  bool _wasLogged = false;

  Future<(Map<String, dynamic>, List<RequestItem>, List<Payment>)> _load() async {
    final api = context.read<Api>();
    final auth = context.read<AuthStore>();
    final r = await Future.wait([api.get('me', authed: true), api.get('me/requests', authed: true), api.get('me/payments', authed: true)]);
    final me = Map<String, dynamic>.from(r[0] as Map);
    await auth.setUser({'id': me['id'], 'name': me['name'], 'phone': me['phone'], 'neighborhood': me['neighborhood']});
    return (
      me,
      (r[1] as List).map((e) => RequestItem.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
      (r[2] as List).map((e) => Payment.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthStore>();
    if (auth.isLoggedIn && (!_wasLogged || _future == null)) _future = _load();
    _wasLogged = auth.isLoggedIn;
    if (!auth.isLoggedIn) return _guest(context);
    return Scaffold(
      appBar: AppBar(title: const Text('پیشخوان من'), actions: [IconButton(tooltip: 'تنظیمات', onPressed: () => _settings(context), icon: const KIcon('filter'))]),
      body: RefreshIndicator(
        color: Brand.qanat,
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<(Map<String, dynamic>, List<RequestItem>, List<Payment>)>(
          future: _future,
          builder: (context, snap) {
            if (snap.hasError) return ListView(children: [StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()))]);
            if (!snap.hasData) return const StateView.loading();
            final (me, reqs, pays) = snap.data!;
            final stats = Map<String, dynamic>.from(me['stats'] as Map? ?? const {});
            return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 28), children: [
              Row(children: [
                Container(width: 60, height: 66, decoration: const BoxDecoration(color: Brand.kahgel, borderRadius: Brand.taqche), child: const Center(child: KIcon('user', size: 30, color: Brand.brick))),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(auth.displayName, style: Theme.of(context).textTheme.titleLarge),
                    Text('پیشخوان شهروند · ${faDigits(me['phone'])}', style: Theme.of(context).textTheme.bodySmall),
                  ]),
                ),
              ]),
              const SizedBox(height: 14),
              Row(children: [
                _stat(context, 'پرونده', stats['requests']),
                const SizedBox(width: 8),
                _stat(context, 'پرداخت', stats['payments']),
                const SizedBox(width: 8),
                _stat(context, 'گزارش ۱۳۷', stats['reports'], active: true),
              ]),
              const SizedBox(height: 10),
              OutlinedButton.icon(onPressed: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const TrackScreen())), icon: const KIcon('search', size: 18), label: const Text('پیگیری با کد')),
              const SizedBox(height: 16),
              const SectionTitle('پرونده‌های من'),
              if (reqs.isEmpty) const StateView.empty('هنوز درخواستی ثبت نکرده‌اید.'),
              ...reqs.map((r) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: InkWell(
                      onTap: () => showModalBottomSheet<void>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24), child: SingleChildScrollView(child: RequestCard(item: r, expanded: true)))),
                      child: RequestCard(item: r),
                    ),
                  )),
              const SizedBox(height: 8),
              const SectionTitle('پرداخت‌های من'),
              if (pays.isEmpty) const StateView.empty('پرداختی ثبت نشده است.', icon: 'payment'),
              ...pays.map((p) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(p.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: Text(p.dateFa),
                      trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                        Text(money(p.amount), style: const TextStyle(fontWeight: FontWeight.w800)),
                        StatusChip(p.statusLabel, kind: p.status == 'paid' ? 'done' : 'need'),
                      ]),
                      onTap: p.status == 'paid' ? () => launchUrl(Uri.parse(p.receiptUrl), mode: LaunchMode.externalApplication) : null,
                    ),
                  )),
            ]);
          },
        ),
      ),
    );
  }

  Widget _stat(BuildContext context, String label, Object? n, {bool active = false}) => Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(color: active ? Brand.qanat : Theme.of(context).cardTheme.color, borderRadius: BorderRadius.circular(16), border: active ? null : Border.all(color: Theme.of(context).dividerTheme.color ?? Brand.line)),
          child: Column(children: [
            Text(faDigits(n ?? 0), style: TextStyle(fontFamily: 'Lalezar', fontSize: 28, color: active ? Colors.white : Theme.of(context).colorScheme.onSurface)),
            Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5, color: active ? Colors.white : Brand.muted)),
          ]),
        ),
      );

  Widget _guest(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('پیشخوان من'), actions: [IconButton(tooltip: 'تنظیمات', onPressed: () => _settings(context), icon: const KIcon('filter'))]),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          const SizedBox(height: 30),
          const Center(child: LogoBadge(size: 110)),
          const SizedBox(height: 16),
          Text('پرونده‌ها، پرداخت‌ها و گزارش‌های شما در یک جا.', textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 20),
          FilledButton(onPressed: () => ensureLogin(context), child: const Text('ورود با شمارهٔ همراه')),
        ]),
      );

  void _settings(BuildContext context) {
    final settings = context.read<AppSettings>();
    final auth = context.read<AuthStore>();
    final name = TextEditingController(text: auth.displayName == 'شهروند گرامی' ? '' : auth.displayName);
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(c).viewInsets.bottom + 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('تنظیمات', style: Theme.of(c).textTheme.headlineSmall),
          const SizedBox(height: 12),
          SegmentedButton<ThemeMode>(
            segments: const [
              ButtonSegment(value: ThemeMode.light, label: Text('روز')),
              ButtonSegment(value: ThemeMode.system, label: Text('خودکار')),
              ButtonSegment(value: ThemeMode.dark, label: Text('شب کویر')),
            ],
            selected: {settings.themeMode},
            onSelectionChanged: (s) {
              settings.setTheme(s.first);
              Navigator.pop(c);
            },
          ),
          const SizedBox(height: 12),
          FutureBuilder<String>(
            future: Updater.currentVersion(),
            builder: (_, v) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const KIcon('download'),
              title: const Text('بروزرسانی اپ'),
              subtitle: Text('نسخهٔ نصب‌شده: ${faDigits(v.data ?? '…')}'),
              onTap: () {
                final api = context.read<Api>();
                Navigator.pop(c);
                Updater.checkManually(context, api);
              },
            ),
          ),
          if (auth.isLoggedIn) ...[
            const SizedBox(height: 16),
            TextField(controller: name, maxLength: 60, decoration: const InputDecoration(labelText: 'نام و نام خانوادگی')),
            FilledButton(
              onPressed: () async {
                final api = context.read<Api>();
                try {
                  final u = await api.post('me', {'name': name.text.trim()}, authed: true);
                  await auth.setUser(Map<String, dynamic>.from(u as Map));
                  if (c.mounted) Navigator.pop(c);
                } on ApiException catch (e) {
                  if (c.mounted) showMsg(c, e.message);
                }
              },
              child: const Text('ذخیره'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () async {
                final api = context.read<Api>();
                Navigator.pop(c);
                await api.logout();
              },
              child: const Text('خروج از حساب'),
            ),
          ],
        ]),
      ),
    );
  }
}
