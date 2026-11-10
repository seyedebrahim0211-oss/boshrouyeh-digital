import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../models/models.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'login_screen.dart';

const _duesTypes = {
  'renovation': ('عوارض نوسازی', 'کد نوسازی یا شناسهٔ ملک', '12-04-0318-002'),
  'vehicle': ('عوارض سالانهٔ خودرو', 'کد ملی مالک خودرو', '0012345679'),
  'business': ('عوارض کسب و پیشه', 'شمارهٔ پروانهٔ کسب', '123456'),
};

class DuesScreen extends StatefulWidget {
  const DuesScreen({super.key, required this.type});
  final String type;
  @override
  State<DuesScreen> createState() => _DuesScreenState();
}

class _DuesScreenState extends State<DuesScreen> with WidgetsBindingObserver {
  final _id = TextEditingController();
  Bill? _bill;
  bool _busy = false;
  String? _error;
  String? _pendingToken;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _id.dispose();
    super.dispose();
  }

  /// When the user returns from the bank, check the payment result.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _pendingToken != null) _checkPayment();
  }

  Future<void> _inquiry() async {
    if (!await ensureLogin(context, reason: 'برای دیدن فیش و پرداخت وارد شوید.')) return;
    if (!mounted) return;
    setState(() {
      _busy = true;
      _error = null;
      _bill = null;
    });
    try {
      final d = await context.read<Api>().post('dues/inquiry', {'type': widget.type, 'identifier': enDigits(_id.text.trim())}, authed: true);
      setState(() => _bill = Bill.fromJson(Map<String, dynamic>.from(d as Map)));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pay() async {
    final api = context.read<Api>();
    setState(() => _busy = true);
    try {
      final d = Map<String, dynamic>.from(await api.post('payments/start', {'bill_id': _bill!.id, 'return': 'app'}, authed: true) as Map);
      _pendingToken = d['token'] as String;
      final ok = await launchUrl(Uri.parse(d['go'] as String), mode: LaunchMode.externalApplication);
      if (!ok && mounted) showMsg(context, 'باز کردن درگاه بانک ممکن نشد.');
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _checkPayment() async {
    final token = _pendingToken;
    if (token == null) return;
    try {
      final p = Payment.fromJson(Map<String, dynamic>.from(await context.read<Api>().get('payments/$token', authed: true) as Map));
      if (p.status == 'pending') return;
      _pendingToken = null;
      if (!mounted) return;
      await showModalBottomSheet<void>(context: context, showDragHandle: true, builder: (_) => PaymentResult(payment: p));
      if (p.status == 'paid' && mounted) setState(() => _bill = null);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final cfg = _duesTypes[widget.type] ?? _duesTypes['renovation']!;
    return Scaffold(
      appBar: AppBar(title: Text(cfg.$1)),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        TextField(
          controller: _id,
          textDirection: TextDirection.ltr,
          decoration: InputDecoration(labelText: cfg.$2, hintText: faDigits(cfg.$3)),
          onSubmitted: (_) => _inquiry(),
        ),
        const SizedBox(height: 12),
        FilledButton(onPressed: _busy ? null : _inquiry, child: Text(_busy && _bill == null ? 'در حال استعلام…' : 'دریافت فیش')),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: const TextStyle(color: Color(0xFFB42318)))),
        if (_pendingToken != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: OutlinedButton(onPressed: _checkPayment, child: const Text('بررسی نتیجهٔ پرداخت')),
          ),
        if (_bill != null) ...[const SizedBox(height: 18), _BillCard(bill: _bill!, busy: _busy, onPay: _pay)],
      ]),
    );
  }
}

class _BillCard extends StatelessWidget {
  const _BillCard({required this.bill, required this.busy, required this.onPay});
  final Bill bill;
  final bool busy;
  final VoidCallback onPay;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        NightPanel(
          color: Brand.brick,
          radius: 0,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(bill.title, style: const TextStyle(color: Color(0xDDF6EEE1), fontSize: 12.5)),
            Text(bill.subtitle, style: const TextStyle(fontFamily: 'Lalezar', fontSize: 22, color: Brand.milk)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(children: [
            ...bill.items.map((i) => _row(i.label, money(i.amount))),
            if (bill.discount > 0) _row(bill.discountLabel, '−${money(bill.discount)}', color: Brand.qanat),
            const SizedBox(height: 10),
            Row(children: [
              Expanded(child: Text('مبلغ قابل پرداخت', style: t.bodySmall)),
              Text(money(bill.total), style: const TextStyle(fontFamily: 'Lalezar', fontSize: 28, color: Brand.brick)),
              const SizedBox(width: 4),
              Text('تومان', style: t.bodySmall),
            ]),
            if (bill.demo)
              Container(
                margin: const EdgeInsets.only(top: 10),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: const Color(0xFFFBF0D9), borderRadius: BorderRadius.circular(10)),
                child: const Text('حالت آزمایشی: مبالغ نمونه‌اند.', style: TextStyle(color: Color(0xFF6A4A0F), fontSize: 12.5)),
              ),
            const SizedBox(height: 12),
            FilledButton.icon(onPressed: busy ? null : onPay, icon: const Icon(Icons.credit_card), label: const Text('پرداخت آنلاین')),
          ]),
        ),
      ]),
    );
  }

  Widget _row(String a, String b, {Color? color}) => Container(
        padding: const EdgeInsets.symmetric(vertical: 9),
        decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Brand.line))),
        child: Row(children: [Expanded(child: Text(a)), Text(b, style: TextStyle(fontWeight: FontWeight.w800, color: color))]),
      );
}

class PaymentResult extends StatelessWidget {
  const PaymentResult({super.key, required this.payment});
  final Payment payment;
  @override
  Widget build(BuildContext context) {
    final ok = payment.status == 'paid';
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        CircleAvatar(radius: 34, backgroundColor: ok ? Brand.qanat : const Color(0xFFB42318), child: Icon(ok ? Icons.check : Icons.close, color: Colors.white, size: 36)),
        const SizedBox(height: 10),
        Text(payment.statusLabel, style: TextStyle(fontFamily: 'Lalezar', fontSize: 26, color: ok ? Brand.qanat : const Color(0xFFB42318))),
        const SizedBox(height: 6),
        Text('${money(payment.amount)} تومان — ${payment.title}', textAlign: TextAlign.center),
        if (payment.bankRef.isNotEmpty) Text('کد رهگیری: ${faDigits(payment.bankRef)}'),
        const SizedBox(height: 14),
        if (ok) OutlinedButton(onPressed: () => launchUrl(Uri.parse(payment.receiptUrl), mode: LaunchMode.externalApplication), child: const Text('مشاهده و ذخیرهٔ رسید')),
      ]),
    );
  }
}
