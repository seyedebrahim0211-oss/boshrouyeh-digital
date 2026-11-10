/// Data models mirroring the bmn/v1 REST API.
String _s(Object? v) => v == null ? '' : '$v';
int _i(Object? v) => v is num ? v.toInt() : int.tryParse('$v') ?? 0;
double _d(Object? v) => v is num ? v.toDouble() : double.tryParse('$v') ?? 0;
List<String> _ls(Object? v) => v is List ? v.map((e) => '$e').toList() : const [];

class Service {
  Service({required this.id, required this.title, required this.shortTitle, required this.excerpt, required this.icon, required this.mode, required this.duration, required this.eservice, required this.categories, required this.url, this.steps = const [], this.documents = const [], this.faq = const [], this.cost = '', this.unit = '', this.externalUrl = ''});

  factory Service.fromJson(Map<String, dynamic> j) => Service(
        id: _i(j['id']),
        title: _s(j['title']),
        shortTitle: _s(j['short_title']).isEmpty ? _s(j['title']) : _s(j['short_title']),
        excerpt: _s(j['excerpt']),
        icon: _s(j['icon']).isEmpty ? 'grid' : _s(j['icon']),
        mode: _s(j['mode']),
        duration: _s(j['duration']),
        eservice: _s(j['eservice']),
        categories: (j['categories'] as List? ?? const []).map((c) => _s((c as Map)['slug'])).toList(),
        url: _s(j['url']),
        steps: _ls(j['steps']),
        documents: _ls(j['documents']),
        faq: (j['faq'] as List? ?? const []).map((f) => (q: _s((f as Map)['q']), a: _s(f['a']))).toList(),
        cost: _s(j['cost']),
        unit: _s(j['unit']),
        externalUrl: _s(j['external_url']),
      );

  final int id;
  final String title, shortTitle, excerpt, icon, mode, duration, eservice, url, cost, unit, externalUrl;
  final List<String> categories, steps, documents;
  final List<({String q, String a})> faq;

  String get modeLabel => const {'online': 'آنلاین', 'semi': 'نیمه‌حضوری', 'onsite': 'حضوری'}[mode] ?? '';
}

class Category {
  Category(this.slug, this.name, this.count, this.icon);
  factory Category.fromJson(Map<String, dynamic> j) => Category(_s(j['slug']), _s(j['name']), _i(j['count']), _s(j['icon']).isEmpty ? 'grid' : _s(j['icon']));
  final String slug, name, icon;
  final int count;
}

class Post {
  Post({required this.id, required this.type, required this.title, required this.excerpt, required this.dateFa, required this.image, required this.url, this.urgent = false, this.extra = const {}});
  factory Post.fromJson(Map<String, dynamic> j) => Post(
        id: _i(j['id']),
        type: _s(j['type']),
        title: _s(j['title']),
        excerpt: _s(j['excerpt']),
        dateFa: _s(j['date_fa']),
        image: _s(j['image']),
        url: _s(j['url']),
        urgent: j['urgent'] == true,
        extra: j,
      );
  final int id;
  final String type, title, excerpt, dateFa, image, url;
  final bool urgent;
  final Map<String, dynamic> extra;
}

class Place {
  Place({required this.id, required this.title, required this.excerpt, required this.era, required this.tags, required this.image, required this.imageLg, required this.lat, required this.lng, required this.url, this.hours = '', this.ticket = '', this.address = '', this.audio = '', this.audioEn = '', this.content = ''});
  factory Place.fromJson(Map<String, dynamic> j) => Place(
        id: _i(j['id']),
        title: _s(j['title']),
        excerpt: _s(j['excerpt']),
        era: _s(j['era']),
        tags: _ls(j['tags']),
        image: _s(j['image']),
        imageLg: _s(j['image_lg']),
        lat: _d(j['lat']),
        lng: _d(j['lng']),
        url: _s(j['url']),
        hours: _s(j['hours']),
        ticket: _s(j['ticket']),
        address: _s(j['address']),
        audio: _s(j['audio']),
        audioEn: _s(j['audio_en']),
        content: _s(j['content']),
      );
  final int id;
  final String title, excerpt, era, image, imageLg, url, hours, ticket, address, audio, audioEn, content;
  final List<String> tags;
  final double lat, lng;
}

class RequestItem {
  RequestItem({required this.id, required this.tracking, required this.type, required this.typeLabel, required this.title, required this.status, required this.statusLabel, required this.progress, required this.createdFa, required this.timeline});
  factory RequestItem.fromJson(Map<String, dynamic> j) => RequestItem(
        id: _i(j['id']),
        tracking: _s(j['tracking']),
        type: _s(j['type']),
        typeLabel: _s(j['type_label']),
        title: _s(j['title']),
        status: _s(j['status']),
        statusLabel: _s(j['status_label']),
        progress: _i(j['progress']),
        createdFa: _s(j['created_fa']),
        timeline: (j['timeline'] as List? ?? const []).map((t) => (label: _s((t as Map)['status_label']), note: _s(t['note']), date: _s(t['date_fa']))).toList(),
      );
  final int id, progress;
  final String tracking, type, typeLabel, title, status, statusLabel, createdFa;
  final List<({String label, String note, String date})> timeline;

  String get chipKind => switch (status) { 'done' => 'done', 'need' => 'need', 'rejected' => 'closed', 'progress' => 'fast', _ => 'review' };
}

class Payment {
  Payment({required this.token, required this.title, required this.amount, required this.status, required this.statusLabel, required this.bankRef, required this.dateFa, required this.receiptUrl, required this.demo});
  factory Payment.fromJson(Map<String, dynamic> j) => Payment(
        token: _s(j['token']),
        title: _s(j['title']),
        amount: _i(j['amount']),
        status: _s(j['status']),
        statusLabel: _s(j['status_label']),
        bankRef: _s(j['bank_ref']),
        dateFa: _s(j['date_fa']),
        receiptUrl: _s(j['receipt_url']),
        demo: j['demo'] == true,
      );
  final String token, title, status, statusLabel, bankRef, dateFa, receiptUrl;
  final int amount;
  final bool demo;
}

class Bill {
  Bill({required this.id, required this.title, required this.subtitle, required this.items, required this.discount, required this.discountLabel, required this.total, required this.demo});
  factory Bill.fromJson(Map<String, dynamic> j) => Bill(
        id: _s(j['bill_id']),
        title: _s(j['title']),
        subtitle: _s(j['subtitle']),
        items: (j['items'] as List? ?? const []).map((i) => (label: _s((i as Map)['label']), amount: _i(i['amount']))).toList(),
        discount: _i(j['discount']),
        discountLabel: _s(j['discount_label']),
        total: _i(j['total']),
        demo: j['demo'] == true,
      );
  final String id, title, subtitle, discountLabel;
  final List<({String label, int amount})> items;
  final int discount, total;
  final bool demo;
}

class Neighborhood {
  Neighborhood(this.slug, this.name, this.wasteDays, this.recycleDays, this.time);
  factory Neighborhood.fromJson(Map<String, dynamic> j) => Neighborhood(
        _s(j['slug']),
        _s(j['name']),
        (j['waste_days'] as List? ?? const []).map(_i).toList(),
        (j['recycle_days'] as List? ?? const []).map(_i).toList(),
        _s(j['waste_time']),
      );
  final String slug, name, time;
  final List<int> wasteDays, recycleDays;
}
