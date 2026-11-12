import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../models/staff.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'admin_web_screen.dart';

/// «پنل مدیریت» for staff signed in to the app: key numbers, PR approvals, quick links.
/// Every action is re-checked on the server with the staff member's own capabilities.
class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key, required this.staff});
  final StaffInfo staff;
  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  late Future<(AdminSummary, List<PendingItem>)> _future = _load();
  final Set<int> _busy = {};

  Future<(AdminSummary, List<PendingItem>)> _load() async {
    final api = context.read<Api>();
    final r = await Future.wait([
      api.get('admin/summary', authed: true),
      widget.staff.canApprove ? api.get('admin/pending', authed: true) : Future<dynamic>.value(const []),
    ]);
    return (
      AdminSummary.fromJson(Map<String, dynamic>.from(r[0] as Map)),
      (r[1] as List).map((e) => PendingItem.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
    );
  }

  Future<void> _approve(PendingItem p) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('تأیید و انتشار'),
        content: Text('«${p.title}» منتشر و به کانال‌های پیام‌رسان ارسال شود؟'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('انصراف')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('تأیید و ارسال')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _busy.add(p.id));
    try {
      await context.read<Api>().post('admin/approve/${p.id}', const {}, authed: true);
      if (!mounted) return;
      showMsg(context, 'تأیید شد؛ خبر منتشر و برای کانال‌ها در صف ارسال قرار گرفت.');
      setState(() => _future = _load());
    } on ApiException catch (e) {
      if (mounted) showMsg(context, e.message);
    } finally {
      if (mounted) setState(() => _busy.remove(p.id));
    }
  }

  Future<void> _revokeAll() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('خروج از همهٔ دستگاه‌ها'),
        content: const Text('همهٔ ورودهای اپ با این حساب (از جمله همین گوشی) باطل می‌شود. اگر گوشی‌ای گم شده، این کار را انجام دهید.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('انصراف')),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: Brand.brick), onPressed: () => Navigator.pop(c, true), child: const Text('خروج از همه')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final api = context.read<Api>();
    final auth = context.read<AuthStore>();
    try {
      await api.post('auth/revoke-all', const {}, authed: true);
    } catch (_) {}
    await auth.clear();
    if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
  }

  void _section(StaffSection sec) =>
      Navigator.push(context, MaterialPageRoute<void>(builder: (_) => AdminWebScreen(title: sec.label, path: sec.path)));

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    final s = widget.staff;
    return Scaffold(
      appBar: AppBar(title: const Text('پنل مدیریت')),
      body: RefreshIndicator(
        color: Brand.qanat,
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<(AdminSummary, List<PendingItem>)>(
          future: _future,
          builder: (context, snap) {
            if (snap.hasError) return ListView(children: [StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()))]);
            if (!snap.hasData) return const StateView.loading();
            final (sum, pending) = snap.data!;
            return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 32), children: [
              NightPanel(
                child: Row(children: [
                  const KIcon('shield', color: Brand.sun, size: 30),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(context.read<AuthStore>().displayName, style: t.titleMedium?.copyWith(color: Colors.white)),
                      Text(s.role.isEmpty ? 'کارکنان شهرداری' : s.role, style: t.bodySmall?.copyWith(color: Brand.kahgel)),
                    ]),
                  ),
                ]),
              ),
              const SizedBox(height: 14),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 2.1,
                children: [
                  for (final (label, value, icon) in sum.tiles)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      decoration: BoxDecoration(color: Theme.of(context).cardColor, borderRadius: BorderRadius.circular(Brand.radiusCard), border: Border.all(color: Brand.line)),
                      child: Row(children: [
                        KIcon(icon, color: Brand.qanat),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(faDigits(value), style: t.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: t.bodySmall),
                          ]),
                        ),
                      ]),
                    ),
                ],
              ),
              if (s.canApprove) ...[
                const SizedBox(height: 18),
                SectionTitle('منتظر تأیید روابط عمومی (${faDigits('${pending.length}')})'),
                if (pending.isEmpty) const StateView.empty('همه‌چیز تأیید شده است.', icon: 'check'),
                for (final p in pending)
                  Card(
                    child: ListTile(
                      title: Text(p.title, maxLines: 2, overflow: TextOverflow.ellipsis),
                      subtitle: Text('${p.kind} · ${p.status} · ${faDigits(p.date)}'),
                      onTap: p.url.isEmpty ? null : () => launchUrl(Uri.parse(p.url), mode: LaunchMode.externalApplication),
                      trailing: _busy.contains(p.id)
                          ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))
                          : FilledButton(onPressed: () => _approve(p), child: const Text('تأیید')),
                    ),
                  ),
              ],
              if (sum.sections.any((x) => x.isFullDashboard)) ...[
                const SizedBox(height: 18),
                Material(
                  color: Brand.qanat,
                  borderRadius: BorderRadius.circular(16),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: () => _section(sum.sections.firstWhere((x) => x.isFullDashboard)),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(children: [
                        const KIcon('home', color: Colors.white, size: 30),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('پیشخوان کامل وردپرس', style: t.titleMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
                            Text('همهٔ بخش‌های مدیریت سایت، داخل همین اپ', style: t.bodySmall?.copyWith(color: Colors.white70)),
                          ]),
                        ),
                        const Icon(Icons.chevron_left, color: Colors.white),
                      ]),
                    ),
                  ),
                ),
              ],
              for (final g in StaffSection.grouped(sum.sections).entries) ...[
                const SizedBox(height: 18),
                SectionTitle(g.key),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 8,
                  crossAxisSpacing: 8,
                  childAspectRatio: 1.9,
                  children: [
                    for (final sec in g.value)
                      Material(
                        color: Theme.of(context).cardColor,
                        borderRadius: BorderRadius.circular(Brand.radiusCard),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(Brand.radiusCard),
                          onTap: () => _section(sec),
                          child: Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(borderRadius: BorderRadius.circular(Brand.radiusCard), border: Border.all(color: Brand.line)),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                              KIcon(sec.icon, color: Brand.qanat, size: 22),
                              const SizedBox(height: 4),
                              Text(sec.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: t.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                              Text(sec.desc, maxLines: 1, overflow: TextOverflow.ellipsis, style: t.bodySmall),
                            ]),
                          ),
                        ),
                      ),
                  ],
                ),
              ],
              const SizedBox(height: 22),
              Text('برای امنیت: ورود مدیر در اپ ۷ روز اعتبار دارد و حداکثر روی ۲ گوشی فعال است. هر بخش با دسترسی‌های خود شما باز می‌شود.', style: t.bodySmall),
              const SizedBox(height: 8),
              TextButton.icon(onPressed: _revokeAll, icon: const KIcon('logout', size: 18, color: Brand.brick), label: const Text('خروج از همهٔ دستگاه‌ها', style: TextStyle(color: Brand.brick))),
              if (sum.appVersion.isNotEmpty) Text('نسخهٔ منتشرشدهٔ اپ روی سایت: ${faDigits(sum.appVersion)}', style: t.bodySmall),
            ]);
          },
        ),
      ),
    );
  }
}
