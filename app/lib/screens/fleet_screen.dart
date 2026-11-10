import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/config.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';

Color _hex(String h, [Color fallback = Brand.qanat]) {
  final v = int.tryParse(h.replaceFirst('#', ''), radix: 16);
  return v == null || h.length != 7 ? fallback : Color(0xFF000000 | v);
}

/// مینی‌بوس آنلاین: live positions from GPS trackers, polled every 10 s.
class FleetScreen extends StatefulWidget {
  const FleetScreen({super.key});
  @override
  State<FleetScreen> createState() => _FleetScreenState();
}

class _FleetScreenState extends State<FleetScreen> with WidgetsBindingObserver {
  List<BusRoute>? _routes;
  String? _error;
  Timer? _timer;
  int? _selected;
  final _map = MapController();
  bool _fitted = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _tick();
    _timer = Timer.periodic(const Duration(seconds: 10), (_) => _tick());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _tick();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _tick() async {
    try {
      final d = Map<String, dynamic>.from(await context.read<Api>().get('fleet/live') as Map);
      final routes = (d['routes'] as List? ?? const []).map((r) => BusRoute.fromJson(Map<String, dynamic>.from(r as Map))).toList();
      if (!mounted) return;
      setState(() {
        _routes = routes;
        _error = null;
        _selected ??= routes.isNotEmpty ? routes.first.id : null;
      });
      _fit();
    } on ApiException catch (e) {
      if (mounted && _routes == null) setState(() => _error = e.message);
    }
  }

  BusRoute? get _route => _routes?.where((r) => r.id == _selected).firstOrNull;

  void _fit() {
    final r = _route;
    if (_fitted || r == null || r.stops.length < 2) return;
    _fitted = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      try {
        _map.fitCamera(CameraFit.bounds(bounds: LatLngBounds.fromPoints(r.stops.map((s) => LatLng(s.lat, s.lng)).toList()), padding: const EdgeInsets.all(36)));
      } catch (_) {}
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('مینی‌بوس آنلاین'), actions: [IconButton(tooltip: 'بروزرسانی', onPressed: _tick, icon: const Icon(Icons.refresh))]),
      body: _routes == null
          ? (_error != null ? StateView.error(_error!, onRetry: _tick) : const StateView.loading())
          : _routes!.isEmpty
              ? const StateView.empty('هنوز خطی تعریف نشده است.')
              : _body(),
    );
  }

  Widget _body() {
    final r = _route ?? _routes!.first;
    final color = _hex(r.color);
    return Column(children: [
      if (_routes!.length > 1)
        SizedBox(
          height: 52,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            children: _routes!
                .map((x) => Padding(
                      padding: const EdgeInsets.only(left: 8),
                      child: ChoiceChip(
                        label: Text(x.name),
                        selected: x.id == r.id,
                        onSelected: (_) => setState(() {
                          _selected = x.id;
                          _fitted = false;
                          _fit();
                        }),
                      ),
                    ))
                .toList(),
          ),
        ),
      Expanded(
        flex: 5,
        child: FlutterMap(
          mapController: _map,
          options: MapOptions(initialCenter: LatLng(AppConfig.cityLat, AppConfig.cityLng), initialZoom: 14),
          children: [
            TileLayer(urlTemplate: AppConfig.tileUrl, userAgentPackageName: 'ir.boshrooyeh.app'),
            if (r.stops.length > 1) PolylineLayer(polylines: [Polyline(points: r.stops.map((s) => LatLng(s.lat, s.lng)).toList(), color: color.withValues(alpha: 0.75), strokeWidth: 5)]),
            MarkerLayer(markers: [
              ...r.stops.map((s) => Marker(
                    point: LatLng(s.lat, s.lng),
                    width: 18,
                    height: 18,
                    child: Tooltip(message: s.name, child: Container(decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle, border: Border.all(color: color, width: 3)))),
                  )),
              ...r.buses.where((b) => b.lat != null).map((b) => Marker(
                    point: LatLng(b.lat!, b.lng!),
                    width: 38,
                    height: 38,
                    child: Tooltip(
                      message: '${b.name}\n${b.statusText}',
                      child: Opacity(
                        opacity: b.online ? 1 : 0.45,
                        child: Container(
                          decoration: BoxDecoration(color: color, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2), boxShadow: const [BoxShadow(color: Color(0x55000000), blurRadius: 6)]),
                          child: Transform.rotate(angle: b.heading * math.pi / 180, child: const Icon(Icons.navigation, color: Colors.white, size: 20)),
                        ),
                      ),
                    ),
                  )),
            ]),
          ],
        ),
      ),
      Expanded(
        flex: 4,
        child: ListView(padding: const EdgeInsets.fromLTRB(16, 12, 16, 20), children: [
          Text(r.name, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          if ([r.hours, r.headway, r.fare].any((e) => e.isNotEmpty))
            Padding(
              padding: const EdgeInsets.only(top: 4, bottom: 8),
              child: Text([if (r.hours.isNotEmpty) 'ساعات کار: ${r.hours}', if (r.headway.isNotEmpty) r.headway, if (r.fare.isNotEmpty) 'کرایه: ${r.fare}'].join(' | '), style: Theme.of(context).textTheme.bodySmall),
            ),
          if (r.buses.isEmpty) const StateView.empty('خودرویی برای این خط ثبت نشده است.'),
          ...r.buses.map((b) => Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  onTap: b.lat == null ? null : () => _map.move(LatLng(b.lat!, b.lng!), 16),
                  leading: CircleAvatar(backgroundColor: b.online ? color : Brand.line, child: const KIcon('bus', size: 20, color: Colors.white)),
                  title: Text('${b.name}${b.plate.isNotEmpty ? ' | ${b.plate}' : ''}', style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text(faDigits(b.statusText)),
                  trailing: b.etaMin != null ? Text('${faDigits(b.etaMin)}\nدقیقه', textAlign: TextAlign.center, style: TextStyle(color: color, fontWeight: FontWeight.w800)) : null,
                ),
              )),
          const SizedBox(height: 8),
          const SectionTitle('ایستگاه‌ها'),
          ...r.stops.asMap().entries.map((e) {
            final here = r.buses.where((b) => b.online && b.atStop == e.value.name).map((b) => b.name).toList();
            final coming = r.buses.where((b) => b.online && b.atStop.isEmpty && b.next == e.value.name).toList();
            return ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(radius: 13, backgroundColor: here.isNotEmpty ? color : Brand.tint, child: Text(faDigits(e.key + 1), style: TextStyle(fontSize: 12, color: here.isNotEmpty ? Colors.white : Brand.brick))),
              title: Text(e.value.name),
              subtitle: here.isNotEmpty
                  ? Text('هم‌اکنون: ${here.join('، ')}', style: TextStyle(color: color, fontWeight: FontWeight.w700))
                  : coming.isNotEmpty
                      ? Text('در راه: ${coming.map((b) => '${b.name}${b.etaMin != null ? ' (${faDigits(b.etaMin)} دقیقه)' : ''}').join('، ')}')
                      : null,
              onTap: () => _map.move(LatLng(e.value.lat, e.value.lng), 16),
            );
          }),
        ]),
      ),
    ]);
  }
}
