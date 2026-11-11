/// Models for the media / participation / permits / unit-appointment endpoints (plugin boshrouyeh-media).
String _s(Object? v) => v == null ? '' : '$v';
int _i(Object? v) => v is num ? v.toInt() : int.tryParse('$v') ?? 0;

class MediaItem {
  MediaItem({
    required this.id,
    required this.kind,
    required this.kindLabel,
    required this.title,
    required this.excerpt,
    required this.dateFa,
    required this.image,
    required this.photos,
    required this.hasVideo,
    required this.category,
    required this.url,
  });

  factory MediaItem.fromJson(Map<String, dynamic> j) => MediaItem(
        id: _i(j['id']),
        kind: _s(j['kind']),
        kindLabel: _s(j['kind_label']),
        title: _s(j['title']),
        excerpt: _s(j['excerpt']),
        dateFa: _s(j['date_fa']),
        image: _s(j['image']),
        photos: _i(j['photos']),
        hasVideo: j['has_video'] == true,
        category: _s(j['category']),
        url: _s(j['url']),
      );

  final int id, photos;
  final String kind, kindLabel, title, excerpt, dateFa, image, category, url;
  final bool hasVideo;
}

class GalleryImage {
  GalleryImage(this.full, this.thumb, this.caption);
  factory GalleryImage.fromJson(Map<String, dynamic> j) => GalleryImage(_s(j['full']), _s(j['thumb']), _s(j['caption']));
  final String full, thumb, caption;
}

class MediaDetail {
  MediaDetail({
    required this.item,
    required this.content,
    required this.gallery,
    required this.aparatUrl,
    required this.embedUrl,
    required this.shareText,
    required this.shareLinks,
    required this.related,
  });

  factory MediaDetail.fromJson(Map<String, dynamic> j) => MediaDetail(
        item: MediaItem.fromJson(j),
        content: _s(j['content']),
        gallery: (j['gallery'] as List? ?? const []).map((e) => GalleryImage.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
        aparatUrl: _s(j['aparat_url']),
        embedUrl: _s(j['embed_url']),
        shareText: _s(j['share_text']),
        shareLinks: (j['share_links'] as Map? ?? const {}).map((k, v) => MapEntry('$k', (label: _s((v as Map)['label']), url: _s(v['url'])))),
        related: (j['related'] as List? ?? const []).map((e) => MediaItem.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
      );

  final MediaItem item;
  final String content, aparatUrl, embedUrl, shareText;
  final List<GalleryImage> gallery;
  final Map<String, ({String label, String url})> shareLinks;
  final List<MediaItem> related;
}

class PollOption {
  PollOption({required this.index, required this.title, required this.cost, required this.desc, this.votes});
  factory PollOption.fromJson(Map<String, dynamic> j) =>
      PollOption(index: _i(j['index']), title: _s(j['title']), cost: _i(j['cost']), desc: _s(j['desc']), votes: j['votes'] == null ? null : _i(j['votes']));
  final int index, cost;
  final String title, desc;
  final int? votes;
}

class Poll {
  Poll({
    required this.id,
    required this.title,
    required this.excerpt,
    required this.kind,
    required this.open,
    required this.endFa,
    required this.image,
    required this.url,
    this.content = '',
    this.multi = false,
    this.budget = 0,
    this.options = const [],
    this.total,
    this.myVote,
    this.canVote = false,
  });

  factory Poll.fromJson(Map<String, dynamic> j) => Poll(
        id: _i(j['id']),
        title: _s(j['title']),
        excerpt: _s(j['excerpt']),
        kind: _s(j['kind']),
        open: j['open'] == true,
        endFa: _s(j['end_fa']),
        image: _s(j['image']),
        url: _s(j['url']),
        content: _s(j['content']),
        multi: j['multi'] == true,
        budget: _i(j['budget']),
        options: (j['options'] as List? ?? const []).map((e) => PollOption.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
        total: j['total'] == null ? null : _i(j['total']),
        myVote: j['my_vote'] is List ? (j['my_vote'] as List).map(_i).toList() : null,
        canVote: j['can_vote'] == true,
      );

  final int id, budget;
  final String title, excerpt, kind, endFa, image, url, content;
  final bool open, multi, canVote;
  final List<PollOption> options;
  final int? total;
  final List<int>? myVote;
  bool get isBudget => kind == 'budget';
}
