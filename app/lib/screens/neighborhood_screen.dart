import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../core/settings.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'content_screen.dart';

/// "محلهٔ من": waste schedule, local notices and upcoming events.
class NeighborhoodScreen extends StatefulWidget {
  const NeighborhoodScreen({super.key});
  @override
  State<NeighborhoodScreen> createState() => _NeighborhoodScreenState();
}

class _NeighborhoodScreenState extends State<NeighborhoodScreen> {
  late Future<(List<Neighborhood>, List<Post>, List<Post>)> _future = _load();

  Future<(List<Neighborhood>, List<Post>, List<Post>)> _load() async {
    final api = context.read<Api>();
    final hood = context.read<AppSettings>().neighborhood;
    final r = await Future.wait([
      api.getCached('neighborhoods'),
      api.getCached('notices', query: {'neighborhood': hood, 'per_page': '10'}),
      api.getCached('events'),
    ]);
    List<Post> posts(Object? l) => (l as List).map((e) => Post.fromJson(Map<String, dynamic>.from(e as Map))).toList();
    return ((r[0] as List).map((e) => Neighborhood.fromJson(Map<String, dynamic>.from(e as Map))).toList(), posts(r[1]), posts(r[2]));
  }

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<AppSettings>();
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('محلهٔ من')),
      body: FutureBuilder<(List<Neighborhood>, List<Post>, List<Post>)>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) return StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const StateView.loading();
          final (hoods, notices, events) = snap.data!;
          final current = hoods.where((h) => h.slug == settings.neighborhood).firstOrNull;
          final today = todaySatIndex();
          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 28), children: [
            DropdownButtonFormField<String>(
              value: current?.slug,
              decoration: const InputDecoration(labelText: 'محلهٔ سکونت'),
              items: hoods.map((h) => DropdownMenuItem(value: h.slug, child: Text(h.name))).toList(),
              onChanged: (v) {
                settings.setNeighborhood(v ?? '');
                setState(() => _future = _load());
              },
            ),
            const SizedBox(height: 14),
            if (current != null)
              NightPanel(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    const KIcon('waste', color: Brand.sun),
                    const SizedBox(width: 8),
                    const Expanded(child: Text('جمع‌آوری پسماند', style: TextStyle(color: Brand.milk, fontWeight: FontWeight.w800))),
                    if (current.time.isNotEmpty) Text('ساعت ${faDigits(current.time)}', style: const TextStyle(color: Color(0xFFD9CDBB), fontSize: 12)),
                  ]),
                  const SizedBox(height: 12),
                  Row(
                    children: List.generate(7, (i) {
                      final waste = current.wasteDays.contains(i), rec = current.recycleDays.contains(i);
                      return Expanded(
                        child: Container(
                          margin: const EdgeInsets.symmetric(horizontal: 2),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          decoration: BoxDecoration(
                            color: waste ? Brand.qanat : (rec ? Brand.kahgel : Colors.white),
                            borderRadius: Brand.taqche,
                            border: i == today ? Border.all(color: Brand.sun, width: 2.5) : null,
                          ),
                          child: Column(children: [
                            Text(weekdaysSatFirst[i].substring(0, 1), style: TextStyle(fontFamily: 'Lalezar', fontSize: 18, color: waste ? Colors.white : Brand.brick)),
                            Text(i == today ? 'امروز' : '', style: TextStyle(fontSize: 9, color: waste ? Colors.white : Brand.brick)),
                          ]),
                        ),
                      );
                    }),
                  ),
                  const SizedBox(height: 8),
                  const Text('فیروزه‌ای: پسماند عادی · کاهگلی: پسماند خشک', style: TextStyle(color: Color(0xFFD9CDBB), fontSize: 11.5)),
                ]),
              ),
            const SizedBox(height: 16),
            const SectionTitle('اعلان‌های محله'),
            if (notices.isEmpty) const StateView.empty('اعلان فعالی نیست.', icon: 'bell'),
            ...notices.map((n) => Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: KIcon(n.urgent ? 'drop' : 'bell', color: n.urgent ? const Color(0xFFB42318) : Brand.khesht),
                    title: Text(n.title, style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text(n.dateFa),
                    onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => ContentScreen(id: n.id, title: n.title))),
                  ),
                )),
            const SizedBox(height: 8),
            const SectionTitle('رویدادهای این هفته'),
            if (events.isEmpty) const StateView.empty('رویدادی ثبت نشده است.', icon: 'calendar'),
            ...events.map((e) => Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: const Taqche(icon: 'calendar', size: 40),
                    title: Text(e.title, style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text('${e.extra['start_fa'] ?? ''}', style: t.bodySmall),
                    onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => ContentScreen(id: e.id, title: e.title))),
                  ),
                )),
          ]);
        },
      ),
    );
  }
}
