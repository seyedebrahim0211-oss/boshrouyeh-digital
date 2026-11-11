import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../models/media.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'login_screen.dart';

/// Polls and participatory budgeting (GET /polls).
class ParticipationScreen extends StatefulWidget {
  const ParticipationScreen({super.key});
  @override
  State<ParticipationScreen> createState() => _ParticipationScreenState();
}

class _ParticipationScreenState extends State<ParticipationScreen> {
  late Future<List<Poll>> _future = _load();
  Future<List<Poll>> _load() async =>
      (await context.read<Api>().getCached('polls') as List).map((e) => Poll.fromJson(Map<String, dynamic>.from(e as Map))).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('مشارکت شهروندی')),
      body: RefreshIndicator(
        color: Brand.qanat,
        onRefresh: () async => setState(() => _future = _load()),
        child: FutureBuilder<List<Poll>>(
          future: _future,
          builder: (context, snap) {
            if (snap.hasError) return ListView(children: [StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()))]);
            if (!snap.hasData) return const StateView.loading();
            final polls = snap.data!;
            return ListView(padding: const EdgeInsets.all(16), children: [
              const NightPanel(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('شهر را با هم بسازیم', style: TextStyle(fontFamily: 'Lalezar', fontSize: 24, color: Brand.sun)),
                  SizedBox(height: 4),
                  Text('در نظرسنجی‌ها شرکت کنید و در بودجه‌ریزی مشارکتی، طرح‌های محلهٔ خود را انتخاب کنید.', style: TextStyle(color: Colors.white, height: 1.8)),
                ]),
              ),
              const SizedBox(height: 16),
              if (polls.isEmpty) const StateView.empty('فعلاً نظرسنجی فعالی نیست.'),
              for (final p in polls)
                Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    title: Text(p.title, style: const TextStyle(fontWeight: FontWeight.w800)),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Row(children: [
                        StatusChip(p.isBudget ? 'بودجهٔ مشارکتی' : 'نظرسنجی', kind: p.isBudget ? 'fast' : 'online'),
                        const SizedBox(width: 8),
                        StatusChip(p.open ? (p.endFa.isNotEmpty ? 'تا ${p.endFa}' : 'باز') : 'پایان‌یافته', kind: p.open ? 'done' : 'closed'),
                      ]),
                    ),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => PollScreen(id: p.id, title: p.title))),
                  ),
                ),
            ]);
          },
        ),
      ),
    );
  }
}

class PollScreen extends StatefulWidget {
  const PollScreen({super.key, required this.id, required this.title});
  final int id;
  final String title;
  @override
  State<PollScreen> createState() => _PollScreenState();
}

class _PollScreenState extends State<PollScreen> {
  Poll? _poll;
  String? _error;
  final _sel = <int>{};
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final auth = context.read<AuthStore>();
      final d = await context.read<Api>().get('polls/${widget.id}', authed: auth.isLoggedIn);
      if (mounted) setState(() => _poll = Poll.fromJson(Map<String, dynamic>.from(d as Map)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  int get _sum => _poll == null ? 0 : _poll!.options.where((o) => _sel.contains(o.index)).fold(0, (a, o) => a + o.cost);

  Future<void> _vote() async {
    if (!await ensureLogin(context, reason: 'برای شرکت در نظرسنجی وارد شوید.')) return;
    if (!mounted) return;
    setState(() => _busy = true);
    try {
      final d = await context.read<Api>().post('polls/${widget.id}/vote', {'choices': _sel.toList()}, authed: true);
      if (mounted) setState(() => _poll = Poll.fromJson(Map<String, dynamic>.from(d as Map)));
      if (mounted) showMsg(context, 'رأی شما ثبت شد. سپاس از مشارکت‌تان.');
    } on ApiException catch (e) {
      if (mounted) showMsg(context, e.message);
      if (e.status == 409) await _load();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = _poll;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title, overflow: TextOverflow.ellipsis),
        actions: [if (p != null) IconButton(icon: const Icon(Icons.share_rounded), tooltip: 'اشتراک‌گذاری', onPressed: () => Share.share('${p.title}\n${p.url}'))],
      ),
      body: p == null
          ? (_error != null ? StateView.error(_error!, onRetry: _load) : const StateView.loading())
          : ListView(padding: const EdgeInsets.all(16), children: [
              Text(p.title, style: Theme.of(context).textTheme.headlineSmall),
              if (p.content.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text(stripHtml(p.content), style: const TextStyle(height: 1.9))),
              const SizedBox(height: 16),
              if (p.isBudget && p.budget > 0)
                Container(
                  padding: const EdgeInsets.all(12),
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: _sum > p.budget ? const Color(0xFFFDE8E4) : Brand.tint, borderRadius: BorderRadius.circular(14)),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text('سقف بودجه: ${money(p.budget)} تومان', style: const TextStyle(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    LinearProgressIndicator(value: (_sum / p.budget).clamp(0, 1).toDouble(), color: _sum > p.budget ? Colors.red : Brand.qanat, backgroundColor: Colors.white, minHeight: 8, borderRadius: BorderRadius.circular(8)),
                    const SizedBox(height: 6),
                    Text('انتخاب‌شده: ${money(_sum)} تومان', style: TextStyle(color: _sum > p.budget ? Colors.red.shade800 : Brand.muted)),
                  ]),
                ),
              for (final o in p.options) _option(p, o),
              if (p.total != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('${faDigits(p.total)} شهروند شرکت کرده‌اند.', style: Theme.of(context).textTheme.bodySmall)),
              const SizedBox(height: 16),
              if (p.myVote != null)
                const NightPanel(color: Brand.qanat, child: Text('رأی شما ثبت شده است. سپاس از مشارکت‌تان.', style: TextStyle(color: Colors.white)))
              else if (!p.open)
                const StateView.empty('مهلت شرکت در این نظرسنجی تمام شده است.')
              else
                FilledButton(
                  onPressed: _busy || _sel.isEmpty || (p.isBudget && p.budget > 0 && _sum > p.budget) ? null : _vote,
                  child: Text(_busy ? 'در حال ثبت…' : (p.isBudget ? 'ثبت طرح‌های انتخابی' : 'ثبت رأی')),
                ),
            ]),
    );
  }

  Widget _option(Poll p, PollOption o) {
    final voted = p.myVote != null || !p.open;
    final pct = (p.total ?? 0) > 0 && o.votes != null ? o.votes! / p.total! : null;
    final selected = voted ? (p.myVote?.contains(o.index) ?? false) : _sel.contains(o.index);
    final multi = p.isBudget || p.multi;
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: selected ? Brand.qanat : Brand.line, width: selected ? 2 : 1)),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: voted
            ? null
            : () => setState(() {
                  if (multi && _sel.contains(o.index)) {
                    _sel.remove(o.index);
                  } else {
                    if (!multi) _sel.clear();
                    _sel.add(o.index);
                  }
                }),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Row(children: [
              Icon(multi ? (selected ? Icons.check_box : Icons.check_box_outline_blank) : (selected ? Icons.radio_button_checked : Icons.radio_button_off), color: Brand.qanat),
              const SizedBox(width: 8),
              Expanded(child: Text(o.title, style: const TextStyle(fontWeight: FontWeight.w700))),
              if (p.isBudget && o.cost > 0) Text('${money(o.cost)} ت', style: const TextStyle(color: Brand.khesht, fontWeight: FontWeight.w800)),
            ]),
            if (o.desc.isNotEmpty) Padding(padding: const EdgeInsetsDirectional.only(start: 32, top: 4), child: Text(o.desc, style: Theme.of(context).textTheme.bodySmall)),
            if (pct != null) ...[
              const SizedBox(height: 8),
              Row(children: [
                Expanded(child: LinearProgressIndicator(value: pct, minHeight: 6, color: Brand.sun, backgroundColor: Brand.tint, borderRadius: BorderRadius.circular(6))),
                const SizedBox(width: 8),
                Text('${faDigits((pct * 100).round())}٪'),
              ]),
            ],
          ]),
        ),
      ),
    );
  }
}
