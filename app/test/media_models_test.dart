import 'package:boshrouyeh_man/models/media.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('MediaDetail parses explore detail payload', () {
    final d = MediaDetail.fromJson({
      'id': 7,
      'kind': 'video',
      'kind_label': 'ویدیو',
      'title': 'عنوان',
      'photos': 3,
      'has_video': true,
      'gallery': [
        {'full': 'https://x/a.jpg', 'thumb': 'https://x/a-s.jpg', 'caption': ''},
      ],
      'embed_url': 'https://www.aparat.com/video/video/embed/videohash/abc/vt/frame',
      'share_links': {
        'bale': {'label': 'بله', 'url': 'https://ble.ir/share/url?url=x'},
      },
      'related': [],
    });
    expect(d.item.hasVideo, isTrue);
    expect(d.gallery.single.thumb, 'https://x/a-s.jpg');
    expect(d.shareLinks['bale']!.label, 'بله');
  });

  test('Poll parses budget and my vote', () {
    final p = Poll.fromJson({
      'id': 1,
      'kind': 'budget',
      'open': true,
      'budget': 1000,
      'options': [
        {'index': 0, 'title': 'پارک', 'cost': 600, 'votes': 2},
      ],
      'total': 2,
      'my_vote': [0],
    });
    expect(p.isBudget, isTrue);
    expect(p.options.first.votes, 2);
    expect(p.myVote, [0]);
  });
}
