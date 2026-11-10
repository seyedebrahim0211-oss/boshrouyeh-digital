import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'service_detail_screen.dart';

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});
  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  late Future<(List<Service>, List<Category>)> _future = _load();
  String _cat = '';
  String _q = '';

  Future<(List<Service>, List<Category>)> _load() async {
    final api = context.read<Api>();
    final r = await Future.wait([api.getCached('services'), api.getCached('service-categories')]);
    return (
      (r[0] as List).map((e) => Service.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
      (r[1] as List).map((e) => Category.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
    );
  }

  String _norm(String s) => s.replaceAll('ي', 'ی').replaceAll('ك', 'ک').replaceAll('‌', ' ').toLowerCase();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('خدمات الکترونیک')),
      body: FutureBuilder<(List<Service>, List<Category>)>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) return StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const StateView.loading();
          final (services, cats) = snap.data!;
          final list = services.where((s) => (_cat.isEmpty || s.categories.contains(_cat)) && (_q.isEmpty || _norm('${s.title} ${s.excerpt}').contains(_norm(_q)))).toList();
          return Column(children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: TextField(
                decoration: InputDecoration(hintText: 'جست‌وجو در ${services.length} خدمت…', prefixIcon: const Icon(Icons.search)),
                onChanged: (v) => setState(() => _q = v.trim()),
                textInputAction: TextInputAction.search,
              ),
            ),
            SizedBox(
              height: 46,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                children: [
                  _chip('همه', ''),
                  ...cats.where((c) => c.count > 0).map((c) => _chip(c.name, c.slug)),
                ],
              ),
            ),
            Expanded(
              child: list.isEmpty
                  ? const StateView.empty('خدمتی پیدا نشد. از سامانه ۱۳۷ کمک بگیرید.')
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                      itemCount: list.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (_, i) => _ServiceTile(list[i]),
                    ),
            ),
          ]);
        },
      ),
    );
  }

  Widget _chip(String label, String slug) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: ChoiceChip(
          label: Text(label),
          selected: _cat == slug,
          showCheckmark: false,
          onSelected: (_) => setState(() => _cat = slug),
        ),
      );
}

class _ServiceTile extends StatelessWidget {
  const _ServiceTile(this.s);
  final Service s;
  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(Brand.radiusCard),
        onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => ServiceDetailScreen(id: s.id, title: s.title))),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Taqche(icon: s.icon, size: 46),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(s.title, style: const TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(s.excerpt, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 6),
                Wrap(spacing: 6, children: [
                  StatusChip(s.modeLabel, kind: s.mode == 'online' ? 'online' : 'review'),
                  if (s.duration.isNotEmpty) StatusChip(s.duration, kind: 'fast'),
                ]),
              ]),
            ),
          ]),
        ),
      ),
    );
  }
}
