import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/config.dart';
import '../core/format.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'login_screen.dart';

const _categories = {
  'lighting': 'روشنایی',
  'waste': 'پسماند',
  'asphalt': 'آسفالت',
  'green': 'فضای سبز',
  'obstruction': 'سد معبر',
  'water': 'آب‌گرفتگی',
  'other': 'سایر',
};

/// 137 report form (also reused for service request / appointment / feedback).
class ReportScreen extends StatefulWidget {
  const ReportScreen({super.key, this.standalone = false, this.type = 'report137', this.serviceId, this.title});
  final bool standalone;
  final String type;
  final int? serviceId;
  final String? title;
  @override
  State<ReportScreen> createState() => _ReportScreenState();
}

class _ReportScreenState extends State<ReportScreen> {
  final _desc = TextEditingController();
  final _address = TextEditingController();
  final _subject = TextEditingController();
  final _date = TextEditingController();
  final _map = MapController();
  String? _category;
  String _kind = 'suggest';
  int _rating = 0;
  File? _photo;
  LatLng? _point;
  bool _busy = false;
  String? _error;
  String? _tracking;
  String _unit = '';

  bool get _is137 => widget.type == 'report137';

  @override
  void dispose() {
    for (final c in [_desc, _address, _subject, _date]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _pickPhoto(ImageSource src) async {
    try {
      final x = await ImagePicker().pickImage(source: src, maxWidth: 1600, imageQuality: 82);
      if (x != null) setState(() => _photo = File(x.path));
    } catch (_) {
      if (mounted) showMsg(context, 'دسترسی به دوربین/گالری ممکن نشد.');
    }
  }

  Future<void> _locate() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) throw Exception();
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) throw Exception();
      final pos = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 15)));
      final p = LatLng(pos.latitude, pos.longitude);
      setState(() => _point = p);
      _map.move(p, 17);
    } catch (_) {
      if (mounted) showMsg(context, 'موقعیت دریافت نشد؛ روی نقشه انتخاب کنید.');
    }
  }

  Future<void> _submit() async {
    final needsLogin = widget.type != 'feedback';
    if (needsLogin && !await ensureLogin(context, reason: 'کد پیگیری به شمارهٔ شما پیامک می‌شود.')) return;
    if (!mounted) return;
    if (_is137 && _category == null) {
      setState(() => _error = 'موضوع گزارش را انتخاب کنید.');
      return;
    }
    if (widget.type == 'appointment' && _subject.text.trim().isEmpty) {
      setState(() => _error = 'موضوع ملاقات را بنویسید.');
      return;
    }
    if (_desc.text.trim().length < 5) {
      setState(() => _error = 'توضیحات را کامل‌تر بنویسید.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final fields = <String, String>{
      'type': widget.type,
      'description': _desc.text.trim(),
      if (_category != null) 'category': _category!,
      if (_address.text.trim().isNotEmpty) 'address': _address.text.trim(),
      if (_point != null) 'lat': _point!.latitude.toStringAsFixed(6),
      if (_point != null) 'lng': _point!.longitude.toStringAsFixed(6),
      if (widget.serviceId != null) 'service_id': '${widget.serviceId}',
      if (widget.type == 'appointment') 'subject': _subject.text.trim(),
      if (widget.type == 'appointment' && _date.text.trim().isNotEmpty) 'preferred_date': enDigits(_date.text.trim()),
      if (widget.type == 'feedback') 'kind': _kind,
      if (widget.type == 'feedback' && _rating > 0) 'rating': '$_rating',
    };
    try {
      final api = context.read<Api>();
      final d = Map<String, dynamic>.from(await api.multipart('requests', fields, file: _photo) as Map);
      setState(() {
        _tracking = d['tracking'] as String?;
        _unit = (d['unit'] ?? '').toString();
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _reset() => setState(() {
        _tracking = null;
        _photo = null;
        _point = null;
        _category = null;
        _desc.clear();
        _address.clear();
      });

  String get _title => switch (widget.type) {
        'appointment' => 'نوبت ملاقات با شهردار',
        'feedback' => 'انتقاد و پیشنهاد',
        'complaint' => 'شکایت از عملکرد (بازرسی)',
        'request' => widget.title ?? 'ثبت درخواست',
        _ => 'گزارش به ۱۳۷',
      };

  @override
  Widget build(BuildContext context) {
    context.watch<AuthStore>();
    final t = Theme.of(context).textTheme;
    if (_tracking != null) {
      return Scaffold(
        appBar: AppBar(title: Text(_title), automaticallyImplyLeading: widget.standalone),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const CircleAvatar(radius: 40, backgroundColor: Brand.qanat, child: Icon(Icons.check, color: Colors.white, size: 44)),
              const SizedBox(height: 14),
              Text('با موفقیت ثبت شد', style: t.headlineSmall?.copyWith(color: Brand.qanat)),
              const SizedBox(height: 8),
              const Text('کد پیگیری شما:'),
              SelectableText(faDigits(_tracking), style: const TextStyle(fontFamily: 'Lalezar', fontSize: 34, color: Brand.brick, letterSpacing: 2)),
              Text('کد پیگیری پیامک هم شد.', style: t.bodySmall),
              if (_unit.isNotEmpty) ...[
                const SizedBox(height: 10),
                Text('ارجاع به: $_unit', style: const TextStyle(fontWeight: FontWeight.w800, color: Brand.qanat)),
                Text('وضعیت و ارجاع‌های بعدی را از «پیگیری درخواست» یا «پیشخوان من» ببینید.', textAlign: TextAlign.center, style: t.bodySmall),
              ],
              const SizedBox(height: 18),
              OutlinedButton(onPressed: _reset, child: const Text('ثبت مورد جدید')),
            ]),
          ),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(title: Text(_title), automaticallyImplyLeading: widget.standalone),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 28), children: [
        if (_is137) ...[
          Text('مشکل را با عکس و موقعیت ثبت کنید.', style: t.bodySmall),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: SizedBox(
              height: 210,
              child: Stack(children: [
                FlutterMap(
                  mapController: _map,
                  options: MapOptions(
                    initialCenter: LatLng(AppConfig.cityLat, AppConfig.cityLng),
                    initialZoom: 15,
                    onTap: (_, p) => setState(() => _point = p),
                  ),
                  children: [
                    TileLayer(urlTemplate: AppConfig.tileUrl, userAgentPackageName: 'ir.boshrooyeh.app'),
                    if (_point != null) MarkerLayer(markers: [Marker(point: _point!, width: 40, height: 40, child: const KIcon('pin', size: 38, color: Brand.brick))]),
                  ],
                ),
                Positioned(
                  bottom: 10,
                  left: 10,
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(backgroundColor: Brand.sun, foregroundColor: Brand.night, minimumSize: const Size(0, 40)),
                    onPressed: _locate,
                    icon: const KIcon('pin', size: 18, color: Brand.night),
                    label: const Text('موقعیت فعلی من'),
                  ),
                ),
              ]),
            ),
          ),
          const SizedBox(height: 14),
          Text('موضوع', style: t.titleMedium),
          const SizedBox(height: 6),
          Wrap(spacing: 8, runSpacing: 8, children: _categories.entries.map((e) => ChoiceChip(label: Text(e.value), selected: _category == e.key, showCheckmark: false, onSelected: (_) => setState(() => _category = e.key))).toList()),
          const SizedBox(height: 14),
        ],
        if (widget.type == 'appointment') ...[
          TextField(controller: _subject, decoration: const InputDecoration(labelText: 'موضوع ملاقات')),
          const SizedBox(height: 12),
          TextField(controller: _date, textDirection: TextDirection.ltr, decoration: const InputDecoration(labelText: 'تاریخ پیشنهادی (اختیاری)', hintText: '1405/09/12')),
          const SizedBox(height: 12),
        ],
        if (widget.type == 'feedback') ...[
          Wrap(spacing: 8, children: {'critic': 'انتقاد', 'suggest': 'پیشنهاد', 'thanks': 'قدردانی'}.entries.map((e) => ChoiceChip(label: Text(e.value), selected: _kind == e.key, showCheckmark: false, onSelected: (_) => setState(() => _kind = e.key))).toList()),
          const SizedBox(height: 8),
          Row(children: [
            const Text('رضایت: '),
            for (var i = 1; i <= 5; i++)
              IconButton(
                tooltip: faDigits(i),
                onPressed: () => setState(() => _rating = i),
                icon: Icon(i <= _rating ? Icons.star_rounded : Icons.star_outline_rounded, color: Brand.sun, size: 30),
              ),
          ]),
        ],
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (widget.type != 'feedback' && widget.type != 'appointment') ...[
            InkWell(
              onTap: () => showModalBottomSheet<void>(
                context: context,
                builder: (c) => SafeArea(
                  child: Wrap(children: [
                    ListTile(leading: const Icon(Icons.photo_camera_outlined), title: const Text('دوربین'), onTap: () { Navigator.pop(c); _pickPhoto(ImageSource.camera); }),
                    ListTile(leading: const Icon(Icons.photo_library_outlined), title: const Text('گالری'), onTap: () { Navigator.pop(c); _pickPhoto(ImageSource.gallery); }),
                  ]),
                ),
              ),
              borderRadius: BorderRadius.circular(14),
              child: Container(
                width: 104,
                height: 120,
                decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: Brand.khesht, width: 1.6), color: Theme.of(context).cardTheme.color),
                clipBehavior: Clip.antiAlias,
                child: _photo != null
                    ? Image.file(_photo!, fit: BoxFit.cover)
                    : const Column(mainAxisAlignment: MainAxisAlignment.center, children: [KIcon('camera', size: 30, color: Brand.khesht), SizedBox(height: 4), Text('افزودن عکس', style: TextStyle(color: Brand.khesht, fontWeight: FontWeight.w700, fontSize: 12))]),
              ),
            ),
            const SizedBox(width: 10),
          ],
          Expanded(child: TextField(controller: _desc, minLines: 5, maxLines: 8, maxLength: 2000, decoration: InputDecoration(labelText: _is137 ? 'شرح مشکل' : 'متن', hintText: _is137 ? 'مثلاً: چراغ‌های کوچه از دیشب خاموش است…' : null, alignLabelWithHint: true))),
        ]),
        if (_is137 || widget.type == 'request') TextField(controller: _address, maxLength: 200, decoration: const InputDecoration(labelText: 'نشانی یا نشانه (اختیاری)', counterText: '')),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text(_error!, style: const TextStyle(color: Color(0xFFB42318)))),
        const SizedBox(height: 14),
        FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'در حال ارسال…' : 'ثبت')),
      ]),
    );
  }
}
