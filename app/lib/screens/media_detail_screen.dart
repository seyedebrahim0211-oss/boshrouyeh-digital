import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../models/media.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'explore_screen.dart' show MediaCard;
import 'home_screen.dart' show NetImage;

/// News / photo report / video detail (GET /explore/{id}).
class MediaDetailScreen extends StatefulWidget {
  const MediaDetailScreen({super.key, required this.id, required this.title});
  final int id;
  final String title;
  @override
  State<MediaDetailScreen> createState() => _MediaDetailScreenState();
}

class _MediaDetailScreenState extends State<MediaDetailScreen> {
  late Future<MediaDetail> _future = _load();
  WebViewController? _video;
  int _page = 0;

  Future<MediaDetail> _load() async {
    final d = MediaDetail.fromJson(Map<String, dynamic>.from(await context.read<Api>().getCached('explore/${widget.id}') as Map));
    if (d.embedUrl.isNotEmpty) {
      _video = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setBackgroundColor(Colors.black)
        ..setNavigationDelegate(NavigationDelegate(onNavigationRequest: (r) => r.url.contains('aparat.com') ? NavigationDecision.navigate : NavigationDecision.prevent))
        ..loadRequest(Uri.parse(d.embedUrl));
    }
    return d;
  }

  void _share(MediaDetail d) => Share.share(d.shareText.isNotEmpty ? d.shareText : '${d.item.title}\n${d.item.url}', subject: d.item.title);

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Scaffold(
      body: FutureBuilder<MediaDetail>(
        future: _future,
        builder: (context, snap) {
          if (snap.hasError) {
            return Scaffold(appBar: AppBar(title: Text(widget.title)), body: StateView.error(snap.error.toString(), onRetry: () => setState(() => _future = _load())));
          }
          if (!snap.hasData) return Scaffold(appBar: AppBar(title: Text(widget.title)), body: const StateView.loading());
          final d = snap.data!;
          final imgs = d.gallery;
          return CustomScrollView(slivers: [
            SliverAppBar(
              pinned: true,
              expandedHeight: imgs.isEmpty ? 0 : 300,
              backgroundColor: Brand.night,
              foregroundColor: Colors.white,
              title: Text(d.item.kindLabel),
              actions: [IconButton(tooltip: 'اشتراک‌گذاری', icon: const Icon(Icons.share_rounded), onPressed: () => _share(d))],
              flexibleSpace: imgs.isEmpty
                  ? null
                  : FlexibleSpaceBar(
                      background: Stack(fit: StackFit.expand, children: [
                        PageView.builder(
                          itemCount: imgs.length,
                          onPageChanged: (i) => setState(() => _page = i),
                          itemBuilder: (_, i) => GestureDetector(onTap: () => _openViewer(imgs, i), child: NetImage(imgs[i].full)),
                        ),
                        const DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.center, colors: [Color(0x99000000), Color(0x00000000)]))),
                        if (imgs.length > 1)
                          Positioned(
                            bottom: 12,
                            left: 0,
                            right: 0,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: List.generate(
                                imgs.length.clamp(0, 12),
                                (i) => AnimatedContainer(
                                  duration: const Duration(milliseconds: 200),
                                  margin: const EdgeInsets.symmetric(horizontal: 3),
                                  width: i == _page ? 18 : 7,
                                  height: 7,
                                  decoration: BoxDecoration(color: i == _page ? Brand.sun : Colors.white70, borderRadius: BorderRadius.circular(9)),
                                ),
                              ),
                            ),
                          ),
                      ]),
                    ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              sliver: SliverList.list(children: [
                Text(d.item.title, style: t.headlineSmall?.copyWith(height: 1.6)),
                const SizedBox(height: 4),
                Text([d.item.dateFa, if (d.item.category.isNotEmpty) d.item.category].join(' · '), style: t.bodySmall),
                if (_video != null) ...[
                  const SizedBox(height: 16),
                  ClipRRect(borderRadius: BorderRadius.circular(16), child: AspectRatio(aspectRatio: 16 / 9, child: WebViewWidget(controller: _video!))),
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: TextButton.icon(
                      onPressed: () => launchUrl(Uri.parse(d.aparatUrl), mode: LaunchMode.externalApplication),
                      icon: const Icon(Icons.open_in_new, size: 18),
                      label: const Text('تماشا در آپارات'),
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Text(stripHtml(d.content), style: t.bodyLarge?.copyWith(height: 2)),
                if (imgs.length > 1) ...[
                  const SizedBox(height: 16),
                  SectionTitle('گزارش تصویری · ${faDigits(imgs.length)} تصویر'),
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, mainAxisSpacing: 6, crossAxisSpacing: 6),
                    itemCount: imgs.length,
                    itemBuilder: (_, i) => InkWell(
                      onTap: () => _openViewer(imgs, i),
                      child: ClipRRect(borderRadius: BorderRadius.circular(10), child: NetImage(imgs[i].thumb.isEmpty ? imgs[i].full : imgs[i].thumb)),
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                _ShareBar(detail: d, onSystem: () => _share(d)),
                if (d.related.isNotEmpty) ...[
                  const SizedBox(height: 20),
                  const SectionTitle('مطالب مرتبط'),
                  SizedBox(
                    height: 250,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: d.related.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 10),
                      itemBuilder: (_, i) => SizedBox(width: 180, child: MediaCard(item: d.related[i])),
                    ),
                  ),
                ],
              ]),
            ),
          ]);
        },
      ),
    );
  }

  void _openViewer(List<GalleryImage> imgs, int start) {
    Navigator.push(context, MaterialPageRoute<void>(fullscreenDialog: true, builder: (_) => _Viewer(images: imgs, start: start)));
  }
}

class _ShareBar extends StatelessWidget {
  const _ShareBar({required this.detail, required this.onSystem});
  final MediaDetail detail;
  final VoidCallback onSystem;

  static const _colors = {'bale': Color(0xFF00A884), 'eitaa': Color(0xFFEF7F1A), 'telegram': Color(0xFF229ED9), 'whatsapp': Color(0xFF25D366)};

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Brand.tint, borderRadius: BorderRadius.circular(18), border: Border.all(color: Brand.kahgel)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('هم‌رسانی در فضای مجازی', style: TextStyle(fontWeight: FontWeight.w800, color: Brand.brick)),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final e in detail.shareLinks.entries)
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: _colors[e.key] ?? Brand.qanat, visualDensity: VisualDensity.compact),
              onPressed: () => launchUrl(Uri.parse(e.value.url), mode: LaunchMode.externalApplication),
              child: Text(e.value.label),
            ),
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: Brand.night, visualDensity: VisualDensity.compact),
            onPressed: onSystem,
            icon: const Icon(Icons.share_rounded, size: 18),
            label: const Text('سایر برنامه‌ها'),
          ),
        ]),
      ]),
    );
  }
}

class _Viewer extends StatefulWidget {
  const _Viewer({required this.images, required this.start});
  final List<GalleryImage> images;
  final int start;
  @override
  State<_Viewer> createState() => _ViewerState();
}

class _ViewerState extends State<_Viewer> {
  late final _ctrl = PageController(initialPage: widget.start);
  late int _i = widget.start;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final img = widget.images[_i];
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: Text('${faDigits(_i + 1)} از ${faDigits(widget.images.length)}')),
      body: Column(children: [
        Expanded(
          child: PageView.builder(
            controller: _ctrl,
            itemCount: widget.images.length,
            onPageChanged: (i) => setState(() => _i = i),
            itemBuilder: (_, i) => InteractiveViewer(minScale: 1, maxScale: 4, child: Center(child: NetImage(widget.images[i].full, fit: BoxFit.contain))),
          ),
        ),
        if (img.caption.isNotEmpty) Padding(padding: const EdgeInsets.all(16), child: Text(img.caption, style: const TextStyle(color: Colors.white))),
      ]),
    );
  }
}
