import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'content_screen.dart';
import 'neighborhood_screen.dart';
import 'service_detail_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.onNavigate});
  final ValueChanged<int> onNavigate;
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async => Map<String, dynamic>.from(await context.read<Api>().getCached('home') as Map);

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future.catchError((_) => <String, dynamic>{});
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthStore>();
    final t = Theme.of(context).textTheme;
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          color: Brand.qanat,
          onRefresh: _refresh,
          child: FutureBuilder<Map<String, dynamic>>(
            future: _future,
            builder: (context, snap) {
              final data = snap.data;
              final services = (data?['services'] as List? ?? const []).map((e) => Service.fromJson(Map<String, dynamic>.from(e as Map))).toList();
              final notices = (data?['notices'] as List? ?? const []).map((e) => Post.fromJson(Map<String, dynamic>.from(e as Map))).toList();
              final news = (data?['news'] as List? ?? const []).map((e) => Post.fromJson(Map<String, dynamic>.from(e as Map))).toList();
              return ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                children: [
                  Row(children: [
                    const BrandLogo(size: 44),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(jalaliToday(), style: t.bodySmall),
                        Text('سلام، ${auth.isLoggedIn ? auth.displayName : 'شهروند گرامی'}', style: t.titleLarge),
                      ]),
                    ),
                    IconButton(
                      tooltip: 'محلهٔ من و اعلان‌ها',
                      onPressed: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const NeighborhoodScreen())),
                      icon: Badge(isLabelVisible: notices.any((n) => n.urgent), backgroundColor: Brand.sun, child: const KIcon('bell')),
                    ),
                  ]),
                  const SizedBox(height: 12),
                  const _HeroCard(),
                  const SizedBox(height: 16),
                  if (snap.connectionState == ConnectionState.waiting && data == null) const StateView.loading(),
                  if (snap.hasError && data == null) StateView.error(snap.error.toString(), onRetry: _refresh),
                  if (services.isNotEmpty) _ServiceGrid(services: services.take(5).toList(), onMore: () => widget.onNavigate(1)),
                  if (notices.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    _NoticeCard(post: notices.firstWhere((n) => n.urgent, orElse: () => notices.first)),
                  ],
                  if (news.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    const SectionTitle('تازه‌ترین اخبار'),
                    ...news.map((p) => Padding(padding: const EdgeInsets.only(bottom: 10), child: _NewsTile(post: p))),
                  ],
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _HeroCard extends StatelessWidget {
  const _HeroCard();
  @override
  Widget build(BuildContext context) {
    return Container(
      height: 150,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: const LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFFF8EFE2), Color(0xFFEBD5B3)]),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(children: [
        Positioned(left: 24, top: 18, child: Container(width: 46, height: 46, decoration: const BoxDecoration(color: Brand.sun, shape: BoxShape.circle))),
        const Positioned(left: 86, top: 10, child: Opacity(opacity: .16, child: BrandLogo(size: 88, color: Brand.brick))),
        const Positioned.fill(top: 50, child: Skyline(height: 100)),
        const Positioned(
          right: 18,
          top: 16,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('بشرویه', style: TextStyle(fontFamily: 'Lalezar', fontSize: 30, color: Brand.brick, height: 1.1)),
            Text('شهرِ خشت، باد و قنات', style: TextStyle(fontFamily: 'Amiri', fontSize: 16, color: Brand.qanat)),
          ]),
        ),
      ]),
    );
  }
}

class _ServiceGrid extends StatelessWidget {
  const _ServiceGrid({required this.services, required this.onMore});
  final List<Service> services;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    final tiles = <Widget>[
      ...services.map((s) => _Tile(icon: s.icon, label: s.shortTitle, onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => ServiceDetailScreen(id: s.id, title: s.title))))),
      _Tile(icon: 'grid', label: 'بیشتر', onTap: onMore),
    ];
    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: .95,
      children: tiles,
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.icon, required this.label, required this.onTap});
  final String icon, label;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(Brand.radiusCard),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Taqche(icon: icon, size: 44),
            const SizedBox(height: 8),
            Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
          ]),
        ),
      ),
    );
  }
}

class _NoticeCard extends StatelessWidget {
  const _NoticeCard({required this.post});
  final Post post;
  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => ContentScreen(id: post.id, title: post.title))),
      borderRadius: BorderRadius.circular(18),
      child: NightPanel(
        color: Brand.qanat,
        radius: 18,
        child: Row(children: [
          const KIcon('drop', size: 28, color: Colors.white),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(post.urgent ? 'اطلاعیهٔ فوری' : 'اطلاعیه', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
              Text(post.title, style: const TextStyle(color: Colors.white)),
              Text(post.dateFa, style: const TextStyle(color: Color(0xCCFFFFFF), fontSize: 12)),
            ]),
          ),
        ]),
      ),
    );
  }
}

class _NewsTile extends StatelessWidget {
  const _NewsTile({required this.post});
  final Post post;
  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => ContentScreen(id: post.id, title: post.title))),
        child: Row(children: [
          SizedBox(width: 96, height: 84, child: NetImage(post.image)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(post.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text(post.dateFa, style: Theme.of(context).textTheme.bodySmall),
            ]),
          ),
          const SizedBox(width: 8),
        ]),
      ),
    );
  }
}

/// Network image with brand illustration fallback (SVG URLs supported).
class NetImage extends StatelessWidget {
  const NetImage(this.url, {super.key, this.fit = BoxFit.cover});
  final String url;
  final BoxFit fit;
  @override
  Widget build(BuildContext context) {
    const fallback = DecoratedBox(decoration: BoxDecoration(color: Brand.kahgel), child: Center(child: KIcon('badgir', color: Brand.khesht)));
    if (url.isEmpty) return SvgPicture.asset('assets/images/badgir-house.svg', fit: fit);
    if (url.endsWith('.svg')) return SvgPicture.network(url, fit: fit, placeholderBuilder: (_) => fallback);
    return CachedNetworkImage(imageUrl: url, fit: fit, placeholder: (_, __) => fallback, errorWidget: (_, __, ___) => fallback);
  }
}
