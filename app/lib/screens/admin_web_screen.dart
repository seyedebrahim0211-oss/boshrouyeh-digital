import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../core/api.dart';
import '../core/config.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';

/// Opens a WordPress admin page inside the app.
/// The server hands out a one-time link (60 s, single use) that signs this staff member into
/// wp-admin with their own permissions; the link never leaves the app.
class AdminWebScreen extends StatefulWidget {
  const AdminWebScreen({super.key, required this.title, required this.path});
  final String title;

  /// Admin path such as "edit.php" or "admin.php?page=bmnm-hub" ("" = dashboard home).
  final String path;

  @override
  State<AdminWebScreen> createState() => _AdminWebScreenState();
}

class _AdminWebScreenState extends State<AdminWebScreen> {
  WebViewController? _web;
  String? _error;
  int _progress = 0;
  late final String _host = Uri.parse(AppConfig.siteUrl).host;

  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    setState(() {
      _error = null;
      _web = null;
    });
    try {
      final d = Map<String, dynamic>.from(await context.read<Api>().post('auth/web-session', {'to': widget.path}, authed: true) as Map);
      final url = '${d['url'] ?? ''}';
      if (!url.startsWith('https://')) throw ApiException('پاسخ نامعتبر از سرور.');
      final c = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setBackgroundColor(Colors.white)
        ..setNavigationDelegate(NavigationDelegate(
          onProgress: (p) => mounted ? setState(() => _progress = p) : null,
          onNavigationRequest: (r) {
            final u = Uri.tryParse(r.url);
            if (u == null) return NavigationDecision.prevent;
            if (u.host == _host) return NavigationDecision.navigate;
            // Links to other sites (Aparat, channels, maps…) open outside the app.
            if (u.scheme == 'https' || u.scheme == 'http' || u.scheme == 'tel' || u.scheme == 'mailto') {
              launchUrl(u, mode: LaunchMode.externalApplication);
            }
            return NavigationDecision.prevent;
          },
        ))
        ..loadRequest(Uri.parse(url));
      if (mounted) setState(() => _web = c);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'اتصال برقرار نشد؛ دوباره تلاش کنید.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final web = _web;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (web != null && await web.canGoBack()) {
          await web.goBack();
        } else if (context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(widget.title),
          actions: [IconButton(tooltip: 'بارگذاری دوباره', onPressed: web == null ? _open : () => web.reload(), icon: const Icon(Icons.refresh))],
          bottom: _progress > 0 && _progress < 100
              ? PreferredSize(preferredSize: const Size.fromHeight(3), child: LinearProgressIndicator(value: _progress / 100, minHeight: 3, color: Brand.qanat))
              : null,
        ),
        body: _error != null
            ? StateView.error(_error!, onRetry: _open)
            : web == null
                ? const StateView.loading()
                : WebViewWidget(controller: web),
      ),
    );
  }
}
