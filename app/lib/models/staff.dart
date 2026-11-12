/// Staff access returned by GET /me under "staff" (absent or is_staff=false for citizens).
class StaffInfo {
  const StaffInfo({
    required this.isStaff,
    required this.role,
    required this.canApprove,
    required this.canRequests,
    required this.canPayments,
    required this.canSms,
  });

  final bool isStaff;
  final String role;
  final bool canApprove;
  final bool canRequests;
  final bool canPayments;
  final bool canSms;

  static const none = StaffInfo(isStaff: false, role: '', canApprove: false, canRequests: false, canPayments: false, canSms: false);

  factory StaffInfo.fromJson(Object? raw) {
    if (raw is! Map) return none;
    final caps = raw['caps'] is Map ? raw['caps'] as Map : const {};
    bool c(String k) => caps[k] == true;
    return StaffInfo(
      isStaff: raw['is_staff'] == true,
      role: '${raw['role'] ?? ''}',
      canApprove: c('approve'),
      canRequests: c('requests'),
      canPayments: c('payments'),
      canSms: c('sms'),
    );
  }
}

/// GET /admin/summary
class AdminSummary {
  const AdminSummary({required this.tiles, required this.appVersion});
  final List<(String label, String value, String icon)> tiles;
  final String appVersion;

  factory AdminSummary.fromJson(Map<String, dynamic> d) {
    final tiles = <(String, String, String)>[];
    for (final t in (d['tiles'] as List? ?? const [])) {
      if (t is Map) tiles.add(('${t['label'] ?? ''}', '${t['value'] ?? ''}', '${t['icon'] ?? 'chart'}'));
    }
    return AdminSummary(tiles: tiles, appVersion: '${d['app_version'] ?? ''}');
  }
}

/// One item of GET /admin/pending (content waiting for PR approval).
class PendingItem {
  const PendingItem({required this.id, required this.title, required this.kind, required this.status, required this.date, required this.url});
  final int id;
  final String title;
  final String kind;
  final String status;
  final String date;
  final String url;

  factory PendingItem.fromJson(Map<String, dynamic> d) => PendingItem(
        id: (d['id'] as num?)?.toInt() ?? 0,
        title: '${d['title'] ?? ''}',
        kind: '${d['kind'] ?? ''}',
        status: '${d['status'] ?? ''}',
        date: '${d['date_fa'] ?? ''}',
        url: '${d['url'] ?? ''}',
      );
}
