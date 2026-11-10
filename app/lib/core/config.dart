/// Build-time configuration. Override with:
///   flutter run --dart-define=API_BASE=https://boshrooyeh.ir/wp-json/bmn/v1/
class AppConfig {
  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'https://boshrooyeh.ir/wp-json/bmn/v1/',
  );

  static const String siteUrl = String.fromEnvironment(
    'SITE_URL',
    defaultValue: 'https://boshrooyeh.ir',
  );

  /// OpenStreetMap tiles (replace with a self-hosted/Iranian tile server for production load).
  static const String tileUrl = String.fromEnvironment(
    'TILE_URL',
    defaultValue: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  );

  static const double cityLat = 33.8680;
  static const double cityLng = 57.4290;
}
