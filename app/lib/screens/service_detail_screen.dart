import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'dues_screen.dart';
import 'neighborhood_screen.dart';
import 'report_screen.dart';
import 'track_screen.dart';

class ServiceDetailScreen extends StatefulWidget {
  const ServiceDetailScreen({super.key, required this.id, required this.title});
  final int id;
  final String title;
  @override
  State<ServiceDetailScreen> createState() => _ServiceDetailScreenState();
}

class _ServiceDetailScreenState extends State<ServiceDetailScreen> {
  late Future<Service> _future = _load();
  Future<Service> _load() async => Service.fromJson(Map<String, dynamic>.from(await context.read<Api>().getCached('services/${widget.id}') as Map));

  void _start(Service s) {
    Widget? page;
    switch (s.eservice) {
      case 'dues_renovation':
        page = const DuesScreen(type: 'renovation');
      case 'dues_vehicle':
        page = const DuesScreen(type: 'vehicle');
      case 'dues_business':
        page = const DuesScreen(type: 'business');
      case 'report137':
        page = const ReportScreen(standalone: true);
      case 'tracking':
        page = const TrackScreen();
      case 'waste':
        page = const NeighborhoodScreen();
      case 'request':
      case 'appointment':
      case 'feedback':
        page = ReportScreen(standalone: true, type: s.eservice == 'request' ? 'request' : s.eservice, serviceId: s.id, title: s.title);
    }
    if (page != null) {
      Navigator.push(context, MaterialPageRoute<void>(builder: (_) => page!));
    } else if (s.externalUrl.isNotEmpty) {
      launchUrl(Uri.parse(s.externalUrl), mode: LaunchMode.externalApplication);
    } else {
      launchUrl(Uri.parse(s.url), mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: Text(widget.title, overflow: TextOverflow.ellipsis)),
      body: FutureBuilder<Service>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) return StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const StateView.loading();
          final s = snap.data!;
          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 110), children: [
            Row(children: [
              Taqche(icon: s.icon, size: 60, active: true),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(s.title, style: t.headlineSmall),
                  Text([s.modeLabel, if (s.duration.isNotEmpty) 'زمان انجام: ${s.duration}'].join(' | '), style: t.bodySmall),
                ]),
              ),
            ]),
            const SizedBox(height: 16),
            if (s.steps.isNotEmpty) _box(context, 'مراحل', Column(children: [for (var i = 0; i < s.steps.length; i++) _step(i + 1, s.steps[i])])),
            if (s.documents.isNotEmpty) _box(context, 'مدارک لازم', Column(crossAxisAlignment: CrossAxisAlignment.start, children: s.documents.map((d) => Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Row(children: [Transform.rotate(angle: .785, child: Container(width: 8, height: 8, color: Brand.khesht)), const SizedBox(width: 10), Expanded(child: Text(d))]))).toList())),
            if (s.cost.isNotEmpty || s.unit.isNotEmpty) _box(context, 'اطلاعات', Column(crossAxisAlignment: CrossAxisAlignment.start, children: [if (s.cost.isNotEmpty) Text('هزینه: ${s.cost}'), if (s.unit.isNotEmpty) Text('واحد مسئول: ${s.unit}')])),
            if (s.faq.isNotEmpty) _box(context, 'پرسش‌های پرتکرار', Column(children: s.faq.map((f) => ExpansionTile(tilePadding: EdgeInsets.zero, title: Text(f.q, style: const TextStyle(fontWeight: FontWeight.w700)), children: [Align(alignment: AlignmentDirectional.centerStart, child: Padding(padding: const EdgeInsets.only(bottom: 10), child: Text(f.a)))])).toList())),
          ]);
        },
      ),
      bottomSheet: FutureBuilder<Service>(
        future: _future,
        builder: (context, snap) => snap.hasData
            ? SafeArea(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: FilledButton(onPressed: () => _start(snap.data!), child: Text(snap.data!.externalUrl.isNotEmpty && snap.data!.eservice.isEmpty ? 'ورود به سامانه' : 'شروع خدمت')),
                ),
              )
            : const SizedBox.shrink(),
      ),
    );
  }

  Widget _box(BuildContext context, String title, Widget child) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: Theme.of(context).textTheme.titleMedium), const SizedBox(height: 10), child]))),
      );

  Widget _step(int n, String text) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          CircleAvatar(radius: 15, backgroundColor: Brand.qanat, child: Text(faDigits(n), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13))),
          const SizedBox(width: 12),
          Expanded(child: Text(text)),
        ]),
      );
}
