import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'home_screen.dart';

class TourismScreen extends StatefulWidget {
  const TourismScreen({super.key});
  @override
  State<TourismScreen> createState() => _TourismScreenState();
}

class _TourismScreenState extends State<TourismScreen> {
  late Future<List<Place>> _future = _load();
  Future<List<Place>> _load() async => (await context.read<Api>().getCached('places') as List).map((e) => Place.fromJson(Map<String, dynamic>.from(e as Map))).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('بشرویه را بگرد')),
      body: FutureBuilder<List<Place>>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) return StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const StateView.loading();
          final list = snap.data!;
          if (list.isEmpty) return const StateView.empty('هنوز جاذبه‌ای ثبت نشده است.', icon: 'badgir');
          return GridView.builder(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: .78),
            itemCount: list.length,
            itemBuilder: (_, i) {
              final p = list[i];
              return Card(
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => PlaceScreen(id: p.id, title: p.title))),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    AspectRatio(aspectRatio: 4 / 3, child: NetImage(p.image)),
                    Padding(
                      padding: const EdgeInsets.all(10),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(p.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 4),
                        if (p.era.isNotEmpty) StatusChip(p.era, kind: 'review'),
                      ]),
                    ),
                  ]),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class PlaceScreen extends StatefulWidget {
  const PlaceScreen({super.key, required this.id, required this.title});
  final int id;
  final String title;
  @override
  State<PlaceScreen> createState() => _PlaceScreenState();
}

class _PlaceScreenState extends State<PlaceScreen> {
  late Future<Place> _future = _load();
  final _player = AudioPlayer();
  String? _playing;

  Future<Place> _load() async => Place.fromJson(Map<String, dynamic>.from(await context.read<Api>().getCached('places/${widget.id}') as Map));

  @override
  void dispose() {
    _player.dispose();
    super.dispose();
  }

  Future<void> _toggle(String url) async {
    try {
      if (_playing == url) {
        await _player.pause();
        setState(() => _playing = null);
        return;
      }
      setState(() => _playing = url);
      await _player.setUrl(url);
      await _player.play();
    } catch (_) {
      if (mounted) {
        setState(() => _playing = null);
        showMsg(context, 'پخش راهنمای صوتی ممکن نشد.');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Scaffold(
      body: FutureBuilder<Place>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) return SafeArea(child: StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load())));
          if (!snap.hasData) return const StateView.loading();
          final p = snap.data!;
          return CustomScrollView(slivers: [
            SliverAppBar(
              expandedHeight: 280,
              pinned: true,
              backgroundColor: Brand.kahgel,
              flexibleSpace: FlexibleSpaceBar(background: NetImage(p.imageLg.isNotEmpty ? p.imageLg : p.image)),
            ),
            SliverToBoxAdapter(
              child: Container(
                transform: Matrix4.translationValues(0, -20, 0),
                decoration: BoxDecoration(color: Theme.of(context).scaffoldBackgroundColor, borderRadius: const BorderRadius.vertical(top: Radius.circular(24))),
                padding: const EdgeInsets.fromLTRB(18, 22, 18, 32),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(p.title, style: t.headlineMedium),
                  const SizedBox(height: 8),
                  Wrap(spacing: 6, runSpacing: 6, children: [if (p.era.isNotEmpty) StatusChip(p.era), ...p.tags.map((x) => StatusChip(x))]),
                  const SizedBox(height: 12),
                  Text(p.excerpt, style: t.bodyLarge),
                  const SizedBox(height: 12),
                  if (p.hours.isNotEmpty) _fact('clock', p.hours),
                  if (p.ticket.isNotEmpty) _fact('ticket', p.ticket),
                  if (p.address.isNotEmpty) _fact('pin', p.address),
                  const SizedBox(height: 12),
                  Row(children: [
                    if (p.audio.isNotEmpty)
                      Expanded(child: OutlinedButton.icon(onPressed: () => _toggle(p.audio), icon: Icon(_playing == p.audio ? Icons.pause : Icons.volume_up_outlined), label: const Text('راهنمای صوتی'))),
                    if (p.audio.isNotEmpty && p.audioEn.isNotEmpty) const SizedBox(width: 8),
                    if (p.audioEn.isNotEmpty) Expanded(child: OutlinedButton(onPressed: () => _toggle(p.audioEn), child: Text(_playing == p.audioEn ? 'Pause' : 'English'))),
                  ]),
                  if (p.lat != 0 && p.lng != 0) ...[
                    const SizedBox(height: 10),
                    FilledButton.icon(
                      onPressed: () => launchUrl(Uri.parse('geo:${p.lat},${p.lng}?q=${p.lat},${p.lng}(${Uri.encodeComponent(p.title)})'), mode: LaunchMode.externalApplication),
                      icon: const KIcon('route', color: Colors.white),
                      label: const Text('مسیریابی'),
                    ),
                  ],
                  if (p.content.isNotEmpty) ...[const SizedBox(height: 16), Text(stripHtml(p.content), style: t.bodyMedium?.copyWith(height: 1.9))],
                ]),
              ),
            ),
          ]);
        },
      ),
    );
  }

  Widget _fact(String icon, String text) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(children: [KIcon(icon, size: 18, color: Brand.khesht), const SizedBox(width: 8), Expanded(child: Text(text))]),
      );
}
