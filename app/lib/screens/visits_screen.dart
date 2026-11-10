import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';

/// بازدیدهای شهردار + پروندهٔ پیگیری دستورها (شفافیت).
class VisitsScreen extends StatefulWidget {
  const VisitsScreen({super.key});
  @override
  State<VisitsScreen> createState() => _VisitsScreenState();
}

class _VisitsScreenState extends State<VisitsScreen> {
  late Future<(Map<String, dynamic>, List<Visit>)> _future = _load();
  final _code = TextEditingController();

  Future<(Map<String, dynamic>, List<Visit>)> _load() async {
    final api = context.read<Api>();
    final r = await Future.wait([api.getCached('directives/stats'), api.getCached('visits')]);
    return (
      Map<String, dynamic>.from(r[0] as Map),
      (r[1] as List).map((e) => Visit.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
    );
  }

  Future<void> _lookup() async {
    final code = enDigits(_code.text).replaceAll(RegExp(r'\D'), '');
    if (code.length < 4) {
      showMsg(context, 'کد پیگیری دستور را وارد کنید.');
      return;
    }
    try {
      final list = (await context.read<Api>().get('directives', query: {'code': code}) as List).map((e) => Directive.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      if (!mounted) return;
      if (list.isEmpty) {
        showMsg(context, 'دستوری با این کد پیدا نشد.');
        return;
      }
      Navigator.push(context, MaterialPageRoute<void>(builder: (_) => DirectiveListScreen(title: 'کد ${faDigits(code)}', items: list)));
    } on ApiException catch (e) {
      if (mounted) showMsg(context, e.message);
    }
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('بازدیدهای شهردار')),
      body: RefreshIndicator(
        color: Brand.qanat,
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<(Map<String, dynamic>, List<Visit>)>(
          future: _future,
          builder: (context, snap) {
            if (snap.hasError) return ListView(children: [StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()))]);
            if (!snap.hasData) return const StateView.loading();
            final (stats, visits) = snap.data!;
            return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 28), children: [
              Row(children: [
                _Stat(label: 'بازدید', value: stats['visits']),
                _Stat(label: 'دستور', value: stats['total']),
                _Stat(label: 'انجام شده', value: '${faDigits(stats['percent'] ?? 0)}٪', active: true),
                _Stat(label: 'با تأخیر', value: stats['overdue'], warn: true),
              ]),
              const SizedBox(height: 14),
              Row(children: [
                Expanded(child: TextField(controller: _code, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'کد پیگیری دستور', prefixIcon: Padding(padding: EdgeInsets.all(12), child: KIcon('search', size: 20))))),
                const SizedBox(width: 8),
                FilledButton(onPressed: _lookup, child: const Text('پیگیری')),
              ]),
              const SizedBox(height: 8),
              Wrap(spacing: 8, children: [
                ActionChip(label: const Text('دستورهای با تأخیر'), onPressed: () => _openFiltered('overdue', 'دستورهای با تأخیر')),
                ActionChip(label: const Text('در جریان'), onPressed: () => _openFiltered('open', 'دستورهای در جریان')),
                ActionChip(label: const Text('انجام‌شده'), onPressed: () => _openFiltered('done', 'دستورهای انجام‌شده')),
              ]),
              const SizedBox(height: 8),
              const SectionTitle('گزارش بازدیدها'),
              if (visits.isEmpty) const StateView.empty('هنوز بازدیدی منتشر نشده است.'),
              ...visits.map((v) => _VisitCard(v)),
            ]);
          },
        ),
      ),
    );
  }

  Future<void> _openFiltered(String status, String title) async {
    try {
      final list = (await context.read<Api>().get('directives', query: {'status': status}) as List).map((e) => Directive.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      if (!mounted) return;
      Navigator.push(context, MaterialPageRoute<void>(builder: (_) => DirectiveListScreen(title: title, items: list)));
    } on ApiException catch (e) {
      if (mounted) showMsg(context, e.message);
    }
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value, this.active = false, this.warn = false});
  final String label;
  final Object? value;
  final bool active, warn;
  @override
  Widget build(BuildContext context) {
    final bg = active ? Brand.qanat : (warn ? const Color(0xFFF8E3DA) : Theme.of(context).cardTheme.color);
    final fg = active ? Colors.white : (warn ? Brand.brick : Theme.of(context).colorScheme.onSurface);
    return Expanded(
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 3),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(14)),
        child: Column(children: [
          Text(value is String ? value as String : faDigits(value ?? 0), style: TextStyle(fontFamily: 'Lalezar', fontSize: 24, color: fg)),
          Text(label, style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: active ? Colors.white : Brand.muted)),
        ]),
      ),
    );
  }
}

class _VisitCard extends StatelessWidget {
  const _VisitCard(this.v);
  final Visit v;
  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => VisitDetailScreen(id: v.id, title: v.title))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (v.image.isNotEmpty) AspectRatio(aspectRatio: 16 / 7, child: CachedNetworkImage(imageUrl: v.image, fit: BoxFit.cover, errorWidget: (_, __, ___) => const SizedBox())),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(v.title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15.5)),
              const SizedBox(height: 4),
              Text([v.dateFa, if (v.neighborhood.isNotEmpty) 'محلهٔ ${v.neighborhood}'].join(' | '), style: t.bodySmall),
              const SizedBox(height: 10),
              ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(value: v.total == 0 ? 0 : v.done / v.total, minHeight: 7, color: Brand.qanat, backgroundColor: Brand.tint)),
              const SizedBox(height: 6),
              Text('${faDigits(v.done)} از ${faDigits(v.total)} دستور انجام شده${v.overdue > 0 ? ' | ${faDigits(v.overdue)} مورد با تأخیر' : ''}', style: TextStyle(fontSize: 12.5, color: v.overdue > 0 ? Brand.brick : Brand.muted, fontWeight: FontWeight.w700)),
            ]),
          ),
        ]),
      ),
    );
  }
}

class VisitDetailScreen extends StatefulWidget {
  const VisitDetailScreen({super.key, required this.id, required this.title});
  final int id;
  final String title;
  @override
  State<VisitDetailScreen> createState() => _VisitDetailScreenState();
}

class _VisitDetailScreenState extends State<VisitDetailScreen> {
  late Future<Visit> _future = _load();
  Future<Visit> _load() async => Visit.fromJson(Map<String, dynamic>.from(await context.read<Api>().getCached('visits/${widget.id}') as Map));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('پروندهٔ پیگیری بازدید')),
      body: FutureBuilder<Visit>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) return StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const StateView.loading();
          final v = snap.data!;
          final plain = v.report.replaceAll(RegExp(r'<[^>]+>'), ' ').replaceAll(RegExp(r'\s+'), ' ').trim();
          return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 28), children: [
            Text(v.title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Wrap(spacing: 14, runSpacing: 4, children: [
              _Meta('calendar', v.dateFa),
              if (v.place.isNotEmpty) _Meta('pin', v.place),
              if (v.neighborhood.isNotEmpty) _Meta('home', 'محلهٔ ${v.neighborhood}'),
            ]),
            if (plain.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 12), child: Text(plain, style: const TextStyle(height: 1.9))),
            const SizedBox(height: 8),
            SectionTitle('دستورها (${faDigits(v.directives.length)})'),
            if (v.directives.isEmpty) const StateView.empty('هنوز دستوری ثبت نشده است.'),
            ...v.directives.map((d) => DirectiveCard(d)),
          ]);
        },
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta(this.icon, this.text);
  final String icon, text;
  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [KIcon(icon, size: 16, color: Brand.khesht), const SizedBox(width: 4), Text(text, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13))]);
}

class DirectiveListScreen extends StatelessWidget {
  const DirectiveListScreen({super.key, required this.title, required this.items});
  final String title;
  final List<Directive> items;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title)),
        body: items.isEmpty
            ? const StateView.empty('موردی نیست.')
            : ListView(padding: const EdgeInsets.all(16), children: items.map((d) => DirectiveCard(d, showVisit: true)).toList()),
      );
}

/// The six fields of a follow-up dossier + progress + action history.
class DirectiveCard extends StatelessWidget {
  const DirectiveCard(this.d, {super.key, this.showVisit = false});
  final Directive d;
  final bool showVisit;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    String deadline = d.deadlineFa.isEmpty ? '—' : d.deadlineFa;
    if (d.daysLeft != null && d.status != 'done') {
      deadline += d.daysLeft! >= 0 ? ' (${faDigits(d.daysLeft)} روز مانده)' : ' (${faDigits(-d.daysLeft!)} روز تأخیر)';
    }
    final rows = <(String, String)>[
      ('مسئلهٔ دقیق', d.issue),
      ('دستور مشخص', d.order),
      ('واحد مسئول', d.unit),
      ('مهلت اجرا', deadline),
      ('منبع اعتبار', [d.funding, d.fundingNote].where((e) => e.isNotEmpty).join(' — ')),
    ];
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: d.overdue ? const Color(0xFFC46A4D) : Colors.transparent, width: 1.4)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            StatusChip(d.statusLabel, kind: d.chipKind),
            const Spacer(),
            Text('کد ${faDigits(d.code)}', style: t.bodySmall),
          ]),
          const SizedBox(height: 10),
          ...rows.map((r) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  SizedBox(width: 92, child: Text(r.$1, style: const TextStyle(fontWeight: FontWeight.w800, color: Brand.qanat, fontSize: 13))),
                  Expanded(child: Text(r.$2.isEmpty ? '—' : r.$2, style: const TextStyle(height: 1.6))),
                ]),
              )),
          ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(value: d.progress / 100, minHeight: 7, color: Brand.qanat, backgroundColor: Brand.tint)),
          const SizedBox(height: 6),
          Text('وضعیت فعلی: ${d.statusLabel} — ${faDigits(d.progress)}٪ | بروزرسانی ${d.updatedFa}', style: t.bodySmall),
          if (showVisit && d.visitTitle.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text('بازدید: ${d.visitTitle}', style: t.bodySmall?.copyWith(color: Brand.khesht))),
          if (d.log.isNotEmpty)
            Theme(
              data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
              child: ExpansionTile(
                tilePadding: EdgeInsets.zero,
                title: const Text('سابقهٔ اقدامات', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                children: d.log
                    .map((e) => ListTile(
                          dense: true,
                          contentPadding: EdgeInsets.zero,
                          title: Text('${e.label} — ${faDigits(e.progress)}٪'),
                          subtitle: Text([e.date, if (e.note.isNotEmpty) e.note].join('\n')),
                        ))
                    .toList(),
              ),
            ),
        ]),
      ),
    );
  }
}
