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
  String _search = '';

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
            final query = _search.trim().toLowerCase();
            final matched = query.isEmpty ? sum.sections : sum.sections
                .where((sec) => [sec.label, sec.desc, sec.group]
                    .any((label) => label.toLowerCase().contains(query))).toList();
            final grouped = StaffSection.grouped(matched);
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
              _adaptiveGrid(
                sum.tiles.map((tile) => Container(
                  constraints: const BoxConstraints(minHeight: 110),
                  padding: const EdgeInsets.all(13),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(Brand.radiusCard),
                    border: Border.all(color: Brand.line),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        KIcon(tile.$3, color: Brand.qanat, size: 21),
                        const SizedBox(width: 8),
                        Expanded(child: Text(tile.$1, softWrap: true,
                          style: t.bodyMedium?.copyWith(fontWeight: FontWeight.w700))),
                      ]),
                      const SizedBox(height: 12),
                      Text(faDigits(tile.$2), softWrap: true,
                        style: t.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
                    ],
                  ),
                )).toList(),
              ),
              if (s.canApprove) ...[
                const SizedBox(height: 18),
                SectionTitle('منتظر تأیید روابط عمومی (${faDigits('${pending.length}')})'),
                if (pending.isEmpty) const StateView.empty('همه‌چیز تأیید شده است.', icon: 'check'),
                for (final p in pending)
                  Card(
                    margin: const EdgeInsets.only(bottom: 10),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        Text(p.title, style: t.titleMedium, softWrap: true),
                        const SizedBox(height: 6),
                        Text('${p.kind} · ${p.status} · ${faDigits(p.date)}',
                          softWrap: true, style: t.bodySmall),
                        const SizedBox(height: 12),
                        Row(children: [
                          if (p.url.isNotEmpty)
                            Expanded(child: TextButton.icon(
                              onPressed: () => launchUrl(Uri.parse(p.url), mode: LaunchMode.externalApplication),
                              icon: const Icon(Icons.open_in_new, size: 18),
                              label: const Text('مشاهده'),
                            )),
                          if (p.url.isNotEmpty) const SizedBox(width: 8),
                          Expanded(child: FilledButton(
                            onPressed: _busy.contains(p.id) ? null : () => _approve(p),
                            child: _busy.contains(p.id)
                                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                                : const Text('تأیید و انتشار'),
                          )),
                        ]),
                      ]),
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
              const SizedBox(height: 20),
              const SectionTitle('بخش‌های مدیریتی'),
              TextField(
                onChanged: (value) => setState(() => _search = value),
                textInputAction: TextInputAction.search,
                decoration: const InputDecoration(
                  prefixIcon: Icon(Icons.search),
                  labelText: 'جست‌وجوی بخش‌ها',
                  hintText: 'کاربران، گزارش‌ها، تنظیمات و…',
                ),
              ),
              const SizedBox(height: 12),
              if (matched.isEmpty) const StateView.empty('بخشی با این عبارت پیدا نشد.', icon: 'search'),
              for (final g in grouped.entries) ...[
                const SizedBox(height: 14),
                SectionTitle(g.key),
                _adaptiveGrid(g.value.map((sec) => Semantics(
                  button: true,
                  label: '${sec.label}، ${sec.desc}',
                  child: Material(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(Brand.radiusCard),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(Brand.radiusCard),
                      onTap: () => _section(sec),
                      child: Container(
                        constraints: const BoxConstraints(minHeight: 122),
                        padding: const EdgeInsets.all(13),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(Brand.radiusCard),
                          border: Border.all(color: Brand.line),
                        ),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Taqche(icon: sec.icon, size: 36),
                          const SizedBox(height: 8),
                          Text(sec.label, softWrap: true,
                            style: t.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                          const SizedBox(height: 4),
                          Text(sec.desc, softWrap: true, style: t.bodySmall),
                        ]),
                      ),
                    ),
                  ),
                )).toList()),
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
  /// Natural-height cards adapt to phone width, landscape, tablets and larger fonts.
  Widget _adaptiveGrid(List<Widget> cards) => LayoutBuilder(
    builder: (context, constraints) {
      const gap = 10.0;
      final width = constraints.maxWidth;
      final columns = width >= 780 ? 4 : width >= 560 ? 3 : width >= 380 ? 2 : 1;
      final cellWidth = (width - gap * (columns - 1)) / columns;
      return Wrap(
        spacing: gap,
        runSpacing: gap,
        children: [for (final card in cards) SizedBox(width: cellWidth, child: card)],
      );
    },
  );

}
