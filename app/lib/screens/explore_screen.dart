import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../models/media.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'home_screen.dart' show NetImage;
import 'media_detail_screen.dart';

/// Explore: news, photo reports and videos (GET /explore).
class ExploreScreen extends StatefulWidget {
  const ExploreScreen({super.key, this.initialKind = 'all'});
  final String initialKind;
  @override
  State<ExploreScreen> createState() => _ExploreScreenState();
}

class _ExploreScreenState extends State<ExploreScreen> {
  static const _kinds = [('all', 'همه'), ('news', 'اخبار'), ('report', 'گزارش تصویری'), ('video', 'ویدیو')];
  static const _per = 12;
  late String _kind = widget.initialKind;
  String _q = '';
  final _items = <MediaItem>[];
  int _page = 0;
  bool _loading = false, _done = false;
  String? _error;
  Timer? _debounce;
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 600) _load();
    });
    _reset();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _reset() async {
    setState(() {
      _items.clear();
      _page = 0;
      _done = false;
      _error = null;
    });
    await _load();
  }

  Future<void> _load() async {
    if (_loading || _done) return;
    setState(() => _loading = true);
    try {
      final next = _page + 1;
      final query = {'kind': _kind, 'page': '$next', 'per_page': '$_per', if (_q.length > 1) 'q': _q};
      final data = next == 1 ? await context.read<Api>().getCached('explore', query: query) : await context.read<Api>().get('explore', query: query);
      final list = (data as List).map((e) => MediaItem.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      if (!mounted) return;
      setState(() {
        _items.addAll(list);
        _page = next;
        _done = list.length < _per;
      });
    } on ApiException catch (e) {
      // Past the last page WordPress answers 400 (rest_post_invalid_page_number).
      if (mounted) {
        setState(() {
          if (_page > 0) {
            _done = true;
          } else {
            _error = e.message;
          }
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('اخبار و گزارش‌های تصویری')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
          child: TextField(
            textInputAction: TextInputAction.search,
            decoration: const InputDecoration(hintText: 'جست‌وجو در اخبار و گزارش‌ها…', prefixIcon: Padding(padding: EdgeInsets.all(12), child: KIcon('search', size: 20))),
            onChanged: (v) {
              _debounce?.cancel();
              _debounce = Timer(const Duration(milliseconds: 450), () {
                _q = v.trim();
                _reset();
              });
            },
          ),
        ),
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            children: _kinds
                .map((k) => Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: ChoiceChip(
                        label: Text(k.$2),
                        selected: _kind == k.$1,
                        selectedColor: Brand.qanat,
                        labelStyle: TextStyle(color: _kind == k.$1 ? Colors.white : null, fontWeight: FontWeight.w700),
                        onSelected: (_) {
                          if (_kind == k.$1) return;
                          _kind = k.$1;
                          _reset();
                        },
                      ),
                    ))
                .toList(),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            color: Brand.qanat,
            onRefresh: _reset,
            child: _error != null && _items.isEmpty
                ? ListView(children: [StateView.error(_error!, onRetry: _reset)])
                : !_loading && _items.isEmpty
                    ? ListView(children: const [StateView.empty('موردی یافت نشد.')])
                    : GridView.builder(
                        controller: _scroll,
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                        gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 260, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: .72),
                        itemCount: _items.length + (_done ? 0 : 1),
                        itemBuilder: (_, i) => i >= _items.length ? const Center(child: CircularProgressIndicator(color: Brand.qanat)) : MediaCard(item: _items[i]),
                      ),
          ),
        ),
      ]),
    );
  }
}

/// Card used by explore and the home strip.
class MediaCard extends StatelessWidget {
  const MediaCard({super.key, required this.item});
  final MediaItem item;

  static Color kindColor(String k) => k == 'video' ? Brand.brick : (k == 'report' ? Brand.khesht : Brand.qanat);

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => MediaDetailScreen(id: item.id, title: item.title))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AspectRatio(
            aspectRatio: 4 / 3,
            child: Stack(fit: StackFit.expand, children: [
              NetImage(item.image),
              if (item.hasVideo)
                Center(
                  child: Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(color: Brand.milk.withValues(alpha: .92), shape: BoxShape.circle),
                    child: const Icon(Icons.play_arrow_rounded, color: Brand.brick, size: 34),
                  ),
                ),
              PositionedDirectional(
                top: 8,
                start: 8,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
                  decoration: BoxDecoration(color: kindColor(item.kind), borderRadius: BorderRadius.circular(99)),
                  child: Text(item.kindLabel, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
                ),
              ),
              if (item.photos > 1)
                PositionedDirectional(
                  top: 8,
                  end: 8,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(color: Brand.night.withValues(alpha: .75), borderRadius: BorderRadius.circular(99)),
                    child: Text('📷 ${faDigits(item.photos)}', style: const TextStyle(color: Colors.white, fontSize: 11)),
                  ),
                ),
            ]),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(item.title, maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, height: 1.6)),
                const Spacer(),
                Text(item.dateFa, style: const TextStyle(color: Brand.khesht, fontSize: 11.5, fontWeight: FontWeight.w700)),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}
