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
import 'admin_screen.dart';
import 'account_settings_screen.dart';
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
    await auth.setUser({'id': me['id'], 'name': me['name'], 'phone': me['phone'], 'neighborhood': me['neighborhood'], 'staff': me['staff']});
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
      appBar: AppBar(title: const Text('پیشخوان من'), actions: [TextButton.icon(onPressed: () => _settings(context), icon: const Icon(Icons.settings_outlined), label: const Text('تنظیمات'))]),
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
              const SizedBox(height: 12),
              Card(
                child: ListTile(
                  contentPadding: const EdgeInsetsDirectional.fromSTEB(14, 6, 10, 6),
                  leading: const Taqche(icon: 'shield', size: 43),
                  title: const Text('تنظیمات و امنیت حساب', style: TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: const Text('دستگاه‌های متصل، ورودهای اخیر، حریم خصوصی و بروزرسانی', softWrap: true),
                  trailing: const Icon(Icons.chevron_left_rounded),
                  onTap: () => _settings(context),
                ),
              ),
              if (StaffInfo.fromJson(me['staff']).isStaff) ...[
                const SizedBox(height: 12),
                Material(
                  color: Brand.night,
                  borderRadius: BorderRadius.circular(16),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => AdminScreen(staff: StaffInfo.fromJson(me['staff'])))),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Row(children: [
                        const KIcon('shield', color: Brand.sun, size: 28),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('پنل مدیریت', style: Theme.of(context).textTheme.titleMedium?.copyWith(color: Colors.white)),
                            Text(StaffInfo.fromJson(me['staff']).role, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Brand.kahgel)),
                          ]),
                        ),
                        const Icon(Icons.chevron_left, color: Colors.white),
                      ]),
                    ),
                  ),
                ),
              ],
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
        appBar: AppBar(title: const Text('پیشخوان من'), actions: [TextButton.icon(onPressed: () => _settings(context), icon: const Icon(Icons.settings_outlined), label: const Text('تنظیمات'))]),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          const SizedBox(height: 30),
          const Center(child: LogoBadge(size: 110)),
          const SizedBox(height: 16),
          Text('پرونده‌ها، پرداخت‌ها و گزارش‌های شما در یک جا.', textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 20),
          FilledButton(onPressed: () => ensureLogin(context), child: const Text('ورود با شمارهٔ همراه')),
          const SizedBox(height: 12),
          OutlinedButton.icon(onPressed: () => _settings(context), icon: const Icon(Icons.settings_outlined), label: const Text('تنظیمات و بروزرسانی اپ')),
        ]),
      );

  void _settings(BuildContext context) {
    Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => const AccountSettingsScreen()),
    );
  }
}
