import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';

/// Opens the login screen if needed. Returns true when the user is signed in.
Future<bool> ensureLogin(BuildContext context, {String? reason}) async {
  if (context.read<AuthStore>().isLoggedIn) return true;
  final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => LoginScreen(reason: reason)));
  return ok == true;
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, this.reason});
  final String? reason;
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  String? _normalized;
  bool _codeStep = false, _busy = false;
  String? _error, _info;
  int _wait = 0;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    _phone.dispose();
    _code.dispose();
    super.dispose();
  }

  void _countdown(int s) {
    _timer?.cancel();
    setState(() => _wait = s);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) {
        t.cancel();
        return;
      }
      setState(() => _wait--);
      if (_wait <= 0) t.cancel();
    });
  }

  Future<void> _send() async {
    final p = normalizeMobile(_phone.text);
    if (p == null) {
      setState(() => _error = 'شمارهٔ همراه معتبر نیست.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final d = await context.read<Api>().requestOtp(p);
      _normalized = p;
      setState(() {
        _codeStep = true;
        _info = 'کد ۵ رقمی به ${faDigits(p)} پیامک شد.${d['dev_code'] != null ? ' (کد آزمایشی: ${faDigits(d['dev_code'])})' : ''}';
      });
      _countdown((d['resend_in'] as num?)?.toInt() ?? 120);
    } on ApiException catch (e) {
      final wait = (e.data?['resend_in'] as num?)?.toInt();
      if (wait != null) {
        _normalized = p;
        setState(() => _codeStep = true);
        _countdown(wait);
      }
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify() async {
    final code = enDigits(_code.text).replaceAll(RegExp(r'\D'), '');
    if (code.length != 5 || _normalized == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Api>().verifyOtp(_normalized!, code);
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('ورود شهروند')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        const Center(child: Taqche(icon: 'user', size: 72, active: true)),
        const SizedBox(height: 16),
        Text('بدون رمز عبور؛ فقط شمارهٔ همراه و کد پیامکی.', textAlign: TextAlign.center, style: t.bodyMedium),
        if (widget.reason != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text(widget.reason!, textAlign: TextAlign.center, style: t.bodySmall)),
        const SizedBox(height: 24),
        if (!_codeStep) ...[
          TextField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            textDirection: TextDirection.ltr,
            autofillHints: const [AutofillHints.telephoneNumber],
            decoration: const InputDecoration(labelText: 'شمارهٔ همراه', hintText: '09xx xxx xxxx'),
            onSubmitted: (_) => _send(),
          ),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _send, child: Text(_busy ? 'در حال ارسال…' : 'دریافت کد')),
        ] else ...[
          if (_info != null) Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: const Color(0xFFE6F3F4), borderRadius: BorderRadius.circular(12)), child: Text(_info!, style: const TextStyle(color: Color(0xFF0D4C51)))),
          const SizedBox(height: 16),
          TextField(
            controller: _code,
            autofocus: true,
            keyboardType: TextInputType.number,
            textAlign: TextAlign.center,
            textDirection: TextDirection.ltr,
            maxLength: 5,
            autofillHints: const [AutofillHints.oneTimeCode],
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹]'))],
            style: const TextStyle(fontSize: 26, letterSpacing: 14, fontWeight: FontWeight.w800),
            decoration: const InputDecoration(labelText: 'کد تأیید', counterText: ''),
            onChanged: (v) {
              if (enDigits(v).length == 5) _verify();
            },
          ),
          const SizedBox(height: 12),
          FilledButton(onPressed: _busy ? null : _verify, child: Text(_busy ? 'در حال بررسی…' : 'ورود')),
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            TextButton(onPressed: _wait > 0 || _busy ? null : _send, child: Text(_wait > 0 ? 'ارسال دوباره تا ${faDigits(_wait)} ثانیه' : 'ارسال دوباره کد')),
            TextButton(onPressed: () => setState(() => _codeStep = false), child: const Text('ویرایش شماره')),
          ]),
        ],
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFFB42318)))),
        const SizedBox(height: 20),
        Text('با ورود، شرایط استفاده و حریم خصوصی را می‌پذیرید.', textAlign: TextAlign.center, style: t.bodySmall?.copyWith(color: Brand.muted)),
      ]),
    );
  }
}
