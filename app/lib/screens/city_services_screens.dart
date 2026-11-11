import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'login_screen.dart';

/// استعلام پروانهٔ ساختمانی (GET /permits/track).
class PermitScreen extends StatefulWidget {
  const PermitScreen({super.key});
  @override
  State<PermitScreen> createState() => _PermitScreenState();
}

class _PermitScreenState extends State<PermitScreen> {
  final _num = TextEditingController();
  final _code = TextEditingController();
  Map<String, dynamic>? _res;
  String? _err;
  bool _busy = false;

  @override
  void dispose() {
    _num.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _go() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _err = null;
    });
    try {
      final d = await context.read<Api>().get('permits/track', query: {'number': enDigits(_num.text.trim()), 'code': enDigits(_code.text.trim())});
      setState(() => _res = Map<String, dynamic>.from(d as Map));
    } on ApiException catch (e) {
      setState(() {
        _res = null;
        _err = e.message;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = _res;
    final stages = (r?['stages'] as List? ?? const []).map((e) => '$e').toList();
    final idx = (r?['stage_index'] as num? ?? 0).toInt();
    return Scaffold(
      appBar: AppBar(title: const Text('استعلام پروانهٔ ساختمانی')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        TextField(controller: _num, textDirection: TextDirection.ltr, keyboardType: TextInputType.text, decoration: const InputDecoration(labelText: 'شمارهٔ پرونده')),
        const SizedBox(height: 12),
        TextField(controller: _code, textDirection: TextDirection.ltr, keyboardType: TextInputType.number, maxLength: 4, decoration: const InputDecoration(labelText: '۴ رقم آخر کد ملی مالک', counterText: '')),
        const SizedBox(height: 12),
        FilledButton(onPressed: _busy ? null : _go, child: Text(_busy ? 'در حال استعلام…' : 'استعلام')),
        if (_err != null) Padding(padding: const EdgeInsets.only(top: 16), child: StateView.error(_err!)),
        if (r != null) ...[
          const SizedBox(height: 20),
          NightPanel(
            color: r['stage'] == 'fix' ? Brand.brick : Brand.qanat,
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${r['kind']} · پرونده ${faDigits(r['number'])}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
              if ('${r['owner']}'.isNotEmpty) Text('مالک: ${r['owner']}', style: const TextStyle(color: Colors.white70)),
              const SizedBox(height: 6),
              Text('${r['stage_label']}', style: const TextStyle(fontFamily: 'Lalezar', fontSize: 24, color: Brand.sun)),
            ]),
          ),
          if (r['stage'] != 'fix' && stages.isNotEmpty) ...[
            const SizedBox(height: 14),
            StepProgress(idx + 1, total: stages.length),
            const SizedBox(height: 6),
            Text('مرحلهٔ ${faDigits(idx + 1)} از ${faDigits(stages.length)}', style: Theme.of(context).textTheme.bodySmall),
          ],
          const SizedBox(height: 16),
          const SectionTitle('تاریخچهٔ پرونده'),
          for (final e in (r['timeline'] as List? ?? const []))
            ListTile(
              leading: const Icon(Icons.circle, size: 12, color: Brand.khesht),
              title: Text('${(e as Map)['label']}', style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text([e['date_fa'], if ('${e['note']}'.isNotEmpty) e['note']].join('\n')),
            ),
        ],
      ]),
    );
  }
}

/// نوبت‌دهی اینترنتی واحدها (GET /appointments/units, /appointments/slots, POST /appointments).
class UnitAppointmentScreen extends StatefulWidget {
  const UnitAppointmentScreen({super.key});
  @override
  State<UnitAppointmentScreen> createState() => _UnitAppointmentScreenState();
}

class _UnitAppointmentScreenState extends State<UnitAppointmentScreen> {
  List<Map<String, dynamic>> _units = [];
  List<Map<String, dynamic>> _days = [];
  List<Map<String, dynamic>> _mine = [];
  int? _unit;
  String? _date, _slot, _err;
  bool _loading = true, _busy = false;
  final _subject = TextEditingController();

  @override
  void initState() {
    super.initState();
    _init();
  }

  @override
  void dispose() {
    _subject.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> _maps(Object? d) => (d as List? ?? const []).map((e) => Map<String, dynamic>.from(e as Map)).toList();

  Future<void> _init() async {
    try {
      _units = _maps(await context.read<Api>().get('appointments/units'));
      await _loadMine();
    } on ApiException catch (e) {
      _err = e.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _loadMine() async {
    if (!context.read<AuthStore>().isLoggedIn) return;
    try {
      final d = await context.read<Api>().get('appointments', authed: true);
      if (mounted) setState(() => _mine = _maps(d));
    } on ApiException catch (_) {}
  }

  Future<void> _pickUnit(int? id) async {
    setState(() {
      _unit = id;
      _days = [];
      _date = null;
      _slot = null;
    });
    if (id == null) return;
    try {
      final d = await context.read<Api>().get('appointments/slots', query: {'unit': '$id'});
      if (mounted) setState(() => _days = _maps(d));
    } on ApiException catch (e) {
      if (mounted) showMsg(context, e.message);
    }
  }

  Future<void> _book() async {
    if (!await ensureLogin(context, reason: 'برای گرفتن نوبت وارد شوید.')) return;
    if (!mounted) return;
    setState(() => _busy = true);
    try {
      final d = Map<String, dynamic>.from(await context.read<Api>().post('appointments', {'unit': _unit, 'date': _date, 'slot': _slot, 'subject': _subject.text.trim()}, authed: true) as Map);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('نوبت شما ثبت شد'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            Text('${d['unit']}\n${d['date_fa']} ساعت ${d['time_fa']}', textAlign: TextAlign.center, style: const TextStyle(height: 1.9)),
            const SizedBox(height: 12),
            const Text('کد نوبت'),
            Text('${d['code']}', style: const TextStyle(fontFamily: 'Lalezar', fontSize: 34, color: Brand.qanat, letterSpacing: 4)),
            const Text('پیامک تأیید ارسال شد. ۱۰ دقیقه زودتر حاضر شوید.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: Brand.muted)),
          ]),
          actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('باشه'))],
        ),
      );
      _subject.clear();
      await _pickUnit(_unit);
      await _loadMine();
    } on ApiException catch (e) {
      if (mounted) showMsg(context, e.message);
      if (e.status == 409) await _pickUnit(_unit);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel(int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('لغو نوبت'),
        content: const Text('این نوبت لغو شود؟'),
        actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('خیر')), FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('لغو نوبت'))],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await context.read<Api>().post('appointments/$id/cancel', {}, authed: true);
      await _loadMine();
      if (_unit != null) await _pickUnit(_unit);
    } on ApiException catch (e) {
      if (mounted) showMsg(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final day = _days.where((d) => d['date'] == _date).firstOrNull;
    return Scaffold(
      appBar: AppBar(title: const Text('نوبت‌دهی واحدها')),
      body: _loading
          ? const StateView.loading()
          : _err != null
              ? StateView.error(_err!, onRetry: () {
                  setState(() {
                    _loading = true;
                    _err = null;
                  });
                  _init();
                })
              : ListView(padding: const EdgeInsets.all(16), children: [
                  if (_units.isEmpty) const StateView.empty('فعلاً واحدی نوبت اینترنتی ندارد.'),
                  if (_units.isNotEmpty) ...[
                    DropdownButtonFormField<int>(
                      value: _unit,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'واحد'),
                      items: _units.map((u) => DropdownMenuItem<int>(value: (u['id'] as num).toInt(), child: Text('${u['name']}'))).toList(),
                      onChanged: _pickUnit,
                    ),
                    if (_unit != null) ...[
                      const SizedBox(height: 16),
                      const SectionTitle('روز مراجعه'),
                      if (_days.isEmpty) const Text('نوبت خالی در دو هفتهٔ آینده نیست.'),
                      SizedBox(
                        height: 46,
                        child: ListView(
                          scrollDirection: Axis.horizontal,
                          children: _days
                              .map((d) => Padding(
                                    padding: const EdgeInsetsDirectional.only(end: 8),
                                    child: ChoiceChip(
                                      label: Text('${d['date_fa']}'),
                                      selected: _date == d['date'],
                                      selectedColor: Brand.qanat,
                                      labelStyle: TextStyle(color: _date == d['date'] ? Colors.white : null),
                                      onSelected: (_) => setState(() {
                                        _date = '${d['date']}';
                                        _slot = null;
                                      }),
                                    ),
                                  ))
                              .toList(),
                        ),
                      ),
                    ],
                    if (day != null) ...[
                      const SizedBox(height: 12),
                      const SectionTitle('ساعت'),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: _maps(day['slots'])
                            .map((s) => ChoiceChip(
                                  label: Text('${s['time_fa']}'),
                                  selected: _slot == s['time'],
                                  selectedColor: Brand.qanat,
                                  labelStyle: TextStyle(color: _slot == s['time'] ? Colors.white : null),
                                  onSelected: (s['left'] as num? ?? 0) > 0 ? (_) => setState(() => _slot = '${s['time']}') : null,
                                ))
                            .toList(),
                      ),
                    ],
                    if (_slot != null) ...[
                      const SizedBox(height: 16),
                      TextField(controller: _subject, maxLength: 250, decoration: const InputDecoration(labelText: 'موضوع مراجعه')),
                      FilledButton(onPressed: _busy ? null : _book, child: Text(_busy ? 'در حال ثبت…' : 'ثبت نوبت')),
                    ],
                  ],
                  if (_mine.isNotEmpty) ...[
                    const SizedBox(height: 24),
                    const SectionTitle('نوبت‌های من'),
                    for (final a in _mine)
                      Card(
                        child: ListTile(
                          title: Text('${a['unit']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                          subtitle: Text('${a['date_fa']} · ساعت ${a['time_fa']}\nکد: ${a['code']}'),
                          isThreeLine: true,
                          trailing: a['can_cancel'] == true
                              ? TextButton(onPressed: () => _cancel((a['id'] as num).toInt()), child: const Text('لغو'))
                              : StatusChip('${a['status_label']}', kind: a['status'] == 'done' ? 'done' : (a['status'] == 'booked' ? 'online' : 'closed')),
                        ),
                      ),
                  ],
                ]),
    );
  }
}
