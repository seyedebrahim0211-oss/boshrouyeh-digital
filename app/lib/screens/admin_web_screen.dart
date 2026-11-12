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


  /// Mobile-only style for WordPress screens displayed in the app's WebView.
  /// Keep the WordPress menu usable while preventing horizontal clipping.
  static const String _mobileAdminStyle = r"""
(function () {
  if (document.getElementById('bmn-mobile-app-style')) return;
  var css = document.createElement('style');
  css.id = 'bmn-mobile-app-style';
  css.textContent = [
    '@media (max-width: 782px) {',
    'html, body.wp-admin { min-width:0!important; overflow-x:hidden!important; }',
    '#wpbody-content { box-sizing:border-box!important; width:100%!important; min-width:0!important; padding:8px!important; }',
    '#wpcontent, #wpfooter { margin-left:0!important; }',
    '.wrap { box-sizing:border-box!important; max-width:100%!important; min-width:0!important; margin:10px auto!important; padding:0 4px!important; overflow-wrap:anywhere; }',
    '.wrap h1, .wrap h2 { line-height:1.5!important; overflow-wrap:anywhere!important; }',
    'input:not([type=checkbox]):not([type=radio]), textarea, select { box-sizing:border-box!important; max-width:100%!important; }',
    '.form-table, .form-table tbody, .form-table tr, .form-table th, .form-table td { display:block!important; max-width:100%!important; width:auto!important; }',
    '.form-table th { padding-bottom:4px!important; }',
    '.form-table td { padding-top:2px!important; }',
    '.wp-list-table, table.widefat { max-width:100%!important; overflow-x:auto!important; -webkit-overflow-scrolling:touch; }',
    '.bmn-staff-desk { width:auto!important; max-width:100%!important; margin:8px auto!important; padding:14px!important; }',
    '.bmn-users .panel { max-width:100%!important; overflow-x:auto; }',
    '.bmn-users .hero { padding:16px!important; }',
    '}'
  ].join('');
  (document.head || document.documentElement).appendChild(css);
})();
""";

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
      final c = WebViewController();
      await c.setJavaScriptMode(JavaScriptMode.unrestricted);
      await c.setBackgroundColor(Colors.white);
      await c.setNavigationDelegate(NavigationDelegate(
        onProgress: (p) {
          if (mounted) setState(() => _progress = p);
        },
        onPageFinished: (pageUrl) async {
          final target = Uri.tryParse(pageUrl);
          if (target?.scheme == 'https' && target?.host == _host &&
              target!.path.startsWith('/wp-admin')) {
            // CSS is applied only to the site's own administration pages;
            // it does not alter server permissions or page actions.
            try {
              await c.runJavaScript(_mobileAdminStyle);
            } catch (_) {
              // A page that blocks script injection remains usable as normal WordPress.
            }
          }
        },
        onNavigationRequest: (r) {
          final u = Uri.tryParse(r.url);
          if (u == null) return NavigationDecision.prevent;
          if (u.scheme == 'https' && u.host == _host) {
            return NavigationDecision.navigate;
          }
          // External links never receive the one-time login URL.
          if (u.scheme == 'https' || u.scheme == 'http' ||
              u.scheme == 'tel' || u.scheme == 'mailto') {
            launchUrl(u, mode: LaunchMode.externalApplication);
          }
          return NavigationDecision.prevent;
        },
      ));
      await c.loadRequest(Uri.parse(url));
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
