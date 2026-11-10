import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../widgets/brand_widgets.dart';
import 'home_screen.dart';

/// News / notice / event detail.
class ContentScreen extends StatefulWidget {
  const ContentScreen({super.key, required this.id, required this.title});
  final int id;
  final String title;
  @override
  State<ContentScreen> createState() => _ContentScreenState();
}

class _ContentScreenState extends State<ContentScreen> {
  late Future<Map<String, dynamic>> _future = _load();
  Future<Map<String, dynamic>> _load() async => Map<String, dynamic>.from(await context.read<Api>().getCached('content/${widget.id}') as Map);

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: Text(widget.title, overflow: TextOverflow.ellipsis)),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) return StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const StateView.loading();
          final d = snap.data!;
          return ListView(padding: const EdgeInsets.all(16), children: [
            if ('${d['image']}'.isNotEmpty) ClipRRect(borderRadius: BorderRadius.circular(16), child: AspectRatio(aspectRatio: 16 / 9, child: NetImage('${d['image']}'))),
            const SizedBox(height: 14),
            Text('${d['title']}', style: t.headlineSmall),
            Text('${d['date_fa']}', style: t.bodySmall),
            const SizedBox(height: 12),
            Text(stripHtml('${d['content']}'), style: t.bodyLarge?.copyWith(height: 1.95)),
            const SizedBox(height: 16),
            OutlinedButton(onPressed: () => launchUrl(Uri.parse('${d['url']}'), mode: LaunchMode.externalApplication), child: const Text('مشاهده در سایت')),
          ]);
        },
      ),
    );
  }
}
