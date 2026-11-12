import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/config.dart';
import '../core/format.dart';
import '../models/staff.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';

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

  void _open(String path) => launchUrl(Uri.parse('${AppConfig.siteUrl}$path'), mode: LaunchMode.externalApplication);

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
              const SizedBox(height: 18),
              const SectionTitle('دسترسی سریع'),
              Wrap(spacing: 8, runSpacing: 8, children: [
                OutlinedButton.icon(onPressed: () => _open('/wp-admin/'), icon: const KIcon('home', size: 18), label: const Text('پیشخوان سایت')),
                if (s.canRequests) OutlinedButton.icon(onPressed: () => _open('/wp-admin/edit.php?post_type=bmn_request'), icon: const KIcon('doc-check', size: 18), label: const Text('میز درخواست‌ها')),
                if (s.canApprove) OutlinedButton.icon(onPressed: () => _open('/wp-admin/post-new.php'), icon: const KIcon('plus', size: 18), label: const Text('خبر تازه')),
                if (s.canSms) OutlinedButton.icon(onPressed: () => _open('/wp-admin/admin.php?page=bmnm-sms'), icon: const KIcon('mail', size: 18), label: const Text('پیامک گروهی')),
                if (s.canPayments) OutlinedButton.icon(onPressed: () => _open('/wp-admin/admin.php?page=bmn-payments'), icon: const KIcon('payment', size: 18), label: const Text('پرداخت‌ها')),
              ]),
              const SizedBox(height: 22),
              Text('برای امنیت: ورود مدیر در اپ ۷ روز اعتبار دارد و حداکثر روی ۲ گوشی فعال است. کارهای حساس فقط از پیشخوان سایت انجام می‌شود.', style: t.bodySmall),
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
