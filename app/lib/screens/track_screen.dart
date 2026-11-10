import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';

class TrackScreen extends StatefulWidget {
  const TrackScreen({super.key});
  @override
  State<TrackScreen> createState() => _TrackScreenState();
}

class _TrackScreenState extends State<TrackScreen> {
  final _code = TextEditingController();
  final _four = TextEditingController();
  RequestItem? _item;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    _four.dispose();
    super.dispose();
  }

  Future<void> _go() async {
    setState(() {
      _busy = true;
      _error = null;
      _item = null;
    });
    try {
      final q = {'code': enDigits(_code.text).replaceAll(RegExp(r'\D'), ''), 'phone4': enDigits(_four.text).replaceAll(RegExp(r'\D'), '')};
      final d = await context.read<Api>().get('requests/track', query: q, authed: true);
      setState(() => _item = RequestItem.fromJson(Map<String, dynamic>.from(d as Map)));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final logged = context.watch<AuthStore>().isLoggedIn;
    return Scaffold(
      appBar: AppBar(title: const Text('پیگیری درخواست')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        TextField(controller: _code, keyboardType: TextInputType.number, textDirection: TextDirection.ltr, decoration: const InputDecoration(labelText: 'کد پیگیری ۱۰ رقمی')),
        if (!logged) ...[
          const SizedBox(height: 12),
          TextField(controller: _four, keyboardType: TextInputType.number, maxLength: 4, textDirection: TextDirection.ltr, decoration: const InputDecoration(labelText: '۴ رقم آخر شمارهٔ همراه', counterText: '')),
        ],
        const SizedBox(height: 12),
        FilledButton(onPressed: _busy ? null : _go, child: const Text('پیگیری')),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: const TextStyle(color: Color(0xFFB42318)))),
        if (_item != null) ...[const SizedBox(height: 16), RequestCard(item: _item!, expanded: true)],
      ]),
    );
  }
}

class RequestCard extends StatelessWidget {
  const RequestCard({super.key, required this.item, this.expanded = false});
  final RequestItem item;
  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: Text('${item.typeLabel}: ${item.title}', style: const TextStyle(fontWeight: FontWeight.w800))),
            StatusChip(item.statusLabel, kind: item.chipKind),
          ]),
          const SizedBox(height: 4),
          Text('کد پیگیری ${faDigits(item.tracking)} | ${item.createdFa}', style: t.bodySmall),
          const SizedBox(height: 10),
          StepProgress(item.progress),
          if (expanded && item.timeline.isNotEmpty) ...[
            const SizedBox(height: 14),
            ...item.timeline.reversed.map((e) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Container(margin: const EdgeInsets.only(top: 6), width: 10, height: 10, decoration: const BoxDecoration(color: Brand.qanat, shape: BoxShape.circle)),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(e.label, style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(e.date, style: t.bodySmall),
                        if (e.note.isNotEmpty) Text(e.note),
                      ]),
                    ),
                  ]),
                )),
          ],
        ]),
      ),
    );
  }
}
