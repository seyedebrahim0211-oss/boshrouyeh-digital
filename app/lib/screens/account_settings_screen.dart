import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/auth_store.dart';
import '../core/format.dart';
import '../core/settings.dart';
import '../core/updater.dart';
import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';

/// Account settings are a real, scrollable page, not a cramped modal bottom sheet.
/// Session data is always loaded from the authenticated server, never from a cache.
class AccountSettingsScreen extends StatefulWidget {
  const AccountSettingsScreen({super.key});

  @override
  State<AccountSettingsScreen> createState() => _AccountSettingsScreenState();
}

class _AccountSettingsScreenState extends State<AccountSettingsScreen> {
  Future<Map<String, dynamic>>? _security;
  bool _updateAlerts = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reloadSecurity();
    _loadUpdatePreference();
  }

  void _reloadSecurity() {
    if (!context.read<AuthStore>().isLoggedIn) return;
    setState(() {
      _security = context.read<Api>()
          .get('me/security', authed: true)
          .then((data) => Map<String, dynamic>.from(data as Map));
    });
  }

  Future<void> _loadUpdatePreference() async {
    final enabled = await Updater.alertsEnabled();
    if (mounted) setState(() => _updateAlerts = enabled);
  }

  Future<void> _revokeSession(Map<String, dynamic> entry) async {
    final current = entry['current'] == true;
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('خروج دستگاه از حساب'),
        content: Text(current
            ? 'این گوشی از حساب خارج می‌شود و برای ورود دوباره کد پیامکی لازم است.'
            : 'دسترسی این دستگاه به حساب شما قطع شود؟'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialog, false), child: const Text('انصراف')),
          FilledButton(onPressed: () => Navigator.pop(dialog, true), child: const Text('قطع دسترسی')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _busy = true);
    try {
      final response = Map<String, dynamic>.from(
        await context.read<Api>().post(
          'me/sessions/revoke', {'id': entry['id']}, authed: true,
        ) as Map,
      );
      if (response['current'] == true || current) {
        await context.read<AuthStore>().clear();
        if (mounted) Navigator.of(context).pop();
      } else if (mounted) {
        showMsg(context, 'دسترسی دستگاه قطع شد.');
        _reloadSecurity();
      }
    } on ApiException catch (error) {
      if (mounted) showMsg(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _revokeAll() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('خروج از همهٔ دستگاه‌ها'),
        content: const Text('تمام نشست‌های فعال اپ، از جمله همین گوشی، باطل می‌شوند. برای ورود مجدد باید کد پیامکی دریافت کنید.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialog, false), child: const Text('انصراف')),
          FilledButton(onPressed: () => Navigator.pop(dialog, true), child: const Text('خروج از همه')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await context.read<Api>().post('auth/revoke-all', const {}, authed: true);
      await context.read<AuthStore>().clear();
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (error) {
      if (mounted) showMsg(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _changeName() async {
    final auth = context.read<AuthStore>();
    final name = TextEditingController(
      text: auth.displayName == 'شهروند گرامی' ? '' : auth.displayName,
    );
    try {
      final save = await showDialog<String>(
        context: context,
        builder: (dialog) => AlertDialog(
          title: const Text('ویرایش نام نمایشی'),
          content: TextField(
            controller: name,
            autofocus: true,
            maxLength: 60,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'نام و نام خانوادگی'),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialog), child: const Text('انصراف')),
            FilledButton(onPressed: () => Navigator.pop(dialog, name.text.trim()), child: const Text('ذخیره')),
          ],
        ),
      );
      if (save == null || save.trim().isEmpty || !mounted) return;
      final profile = Map<String, dynamic>.from(
        await context.read<Api>().post('me', {'name': save}, authed: true) as Map,
      );
      await auth.setUser(profile);
      if (mounted) showMsg(context, 'نام نمایشی به‌روزرسانی شد.');
    } on ApiException catch (error) {
      if (mounted) showMsg(context, error.message);
    } finally {
      name.dispose();
    }
  }

  Future<void> _signOut() async {
    await context.read<Api>().logout();
    if (mounted) Navigator.of(context).pop();
  }

  static String _date(Object? value) {
    final stamp = value is num ? value.toInt() : int.tryParse('$value') ?? 0;
    if (stamp <= 0) return 'نامشخص';
    final time = DateTime.fromMillisecondsSinceEpoch(stamp * 1000).toLocal();
    String two(int v) => v.toString().padLeft(2, '0');
    return faDigits('${time.year}/${two(time.month)}/${two(time.day)}  ${two(time.hour)}:${two(time.minute)}');
  }

  Widget _heading(String title, {String? subtitle}) => Padding(
    padding: const EdgeInsetsDirectional.fromSTEB(4, 18, 4, 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleLarge),
        if (subtitle != null) ...[
          const SizedBox(height: 3),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
        ],
      ],
    ),
  );

  Widget _item({
    required IconData icon,
    required String title,
    String? subtitle,
    VoidCallback? onTap,
    Widget? trailing,
    Color? iconColor,
  }) => Card(
    margin: const EdgeInsets.only(bottom: 9),
    child: ListTile(
      contentPadding: const EdgeInsetsDirectional.fromSTEB(14, 5, 10, 5),
      minVerticalPadding: 12,
      leading: Container(
        width: 43, height: 43,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Theme.of(context).brightness == Brightness.dark
              ? const Color(0xFF2B3956) : Brand.tint,
          borderRadius: BorderRadius.circular(13),
        ),
        child: Icon(icon, color: iconColor ?? Brand.qanat, size: 23),
      ),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700), softWrap: true),
      subtitle: subtitle == null ? null : Text(subtitle, softWrap: true),
      trailing: trailing ?? (onTap == null ? null : const Icon(Icons.chevron_left_rounded)),
      onTap: onTap,
    ),
  );

  Widget _securityPanel({VoidCallback? onRefresh}) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _security,
      builder: (context, snap) {
        if (snap.hasError) {
          return _item(
            icon: Icons.wifi_off_rounded,
            title: 'دریافت اطلاعات امنیت حساب ممکن نشد',
            subtitle: 'اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.',
            onTap: onRefresh ?? _reloadSecurity,
          );
        }
        if (!snap.hasData) {
          return const Card(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
          );
        }
        final data = snap.data!;
        final sessions = (data['sessions'] as List? ?? const [])
            .whereType<Map<dynamic, dynamic>>().map((row) => Map<String, dynamic>.from(row)).toList();
        final recent = (data['recent_logins'] as List? ?? const [])
            .whereType<Map<dynamic, dynamic>>().map((row) => Map<String, dynamic>.from(row)).take(10).toList();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _heading('دستگاه‌های متصل',
                subtitle: '${faDigits(sessions.length)} نشست فعال در اپ؛ فقط دستگاه‌های همین حساب'),
            if (sessions.isEmpty)
              _item(icon: Icons.phonelink_off_rounded, title: 'نشست فعالی ثبت نشده است')
            else
              for (final entry in sessions)
                _item(
                  icon: Icons.phone_android_rounded,
                  title: '${entry['device'] ?? 'دستگاه ناشناس'}${entry['current'] == true ? ' · این گوشی' : ''}',
                  subtitle: 'آخرین فعالیت: ${_date(entry['last_seen'])}\nورود: ${_date(entry['signed_in'])}',
                  trailing: TextButton(
                    onPressed: _busy ? null : () async {
                      await _revokeSession(entry);
                      if (mounted) onRefresh?.call();
                    },
                    child: const Text('خروج'),
                  ),
                ),
            _item(
              icon: Icons.phonelink_erase_rounded,
              title: 'خروج از تمام دستگاه‌ها',
              subtitle: 'قطع همهٔ نشست‌های فعال حساب',
              iconColor: Brand.brick,
              onTap: _busy ? null : _revokeAll,
            ),
            _heading('ورودهای اخیر', subtitle: 'ورودهای موفق ثبت‌شده از زمان فعال‌شدن این قابلیت'),
            if (recent.isEmpty)
              _item(icon: Icons.history_rounded, title: 'هنوز ورود جدیدی ثبت نشده است')
            else
              for (final entry in recent)
                _item(
                  icon: Icons.verified_user_outlined,
                  title: '${entry['device'] ?? 'ورود به حساب'}',
                  subtitle: _date(entry['at']),
                ),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                onPressed: onRefresh ?? _reloadSecurity,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('تازه‌سازی دستگاه‌ها و ورودها'),
              ),
            ),
          ],
        );
      },
    );
  }

  /// Open security as a separate screen instead of rendering every session
  /// and login on the main settings page.
  Future<void> _openSecurity() async {
    _reloadSecurity();
    await Navigator.push<void>(context, MaterialPageRoute(
      builder: (routeContext) => StatefulBuilder(
        builder: (routeContext, redraw) {
          void refresh() {
            _reloadSecurity();
            if (routeContext.mounted) redraw(() {});
          }
          return Scaffold(
            appBar: AppBar(
              title: const Text('حساب و امنیت'),
              actions: [
                IconButton(
                  tooltip: 'تازه‌سازی',
                  onPressed: refresh,
                  icon: const Icon(Icons.refresh_rounded),
                ),
              ],
            ),
            body: SafeArea(
              child: RefreshIndicator(
                color: Brand.qanat,
                onRefresh: () async {
                  refresh();
                  try { await _security; } catch (_) {}
                },
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 30),
                  children: [
                    Center(child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 720),
                      child: _securityPanel(onRefresh: refresh),
                    )),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    ));
  }

  Widget _group(Widget child) => Card(
    margin: const EdgeInsets.only(bottom: 4),
    child: child,
  );

  Widget _row({
    required IconData icon,
    required String label,
    String? detail,
    VoidCallback? onTap,
    bool danger = false,
    Widget? trailing,
  }) => ListTile(
    contentPadding: const EdgeInsetsDirectional.fromSTEB(16, 6, 12, 6),
    leading: Icon(icon, color: danger ? Brand.brick : Brand.qanat, size: 23),
    title: Text(label, softWrap: true,
      style: TextStyle(fontWeight: FontWeight.w700, color: danger ? Brand.brick : null)),
    subtitle: detail == null ? null : Text(detail, maxLines: 2,
      overflow: TextOverflow.ellipsis, softWrap: true),
    trailing: trailing ?? (onTap == null ? null : const Icon(Icons.chevron_left_rounded, size: 20)),
    onTap: onTap,
  );

  Widget _accountCard(AuthStore auth) {
    final isLoggedIn = auth.isLoggedIn;
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
        child: Row(children: [
          Container(
            width: 58, height: 64,
            decoration: const BoxDecoration(color: Brand.tint, borderRadius: Brand.taqche),
            child: const Center(child: Icon(Icons.person_outline_rounded,
              size: 30, color: Brand.brick)),
          ),
          const SizedBox(width: 13),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(isLoggedIn ? auth.displayName : 'حساب مهمان',
              maxLines: 2, overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 3),
            Text(isLoggedIn ? 'مدیریت اطلاعات شخصی و امنیت' : 'تنظیمات عمومی برنامه',
              style: Theme.of(context).textTheme.bodySmall),
          ])),
          if (isLoggedIn)
            IconButton(
              tooltip: 'ویرایش نام نمایشی',
              onPressed: _busy ? null : _changeName,
              icon: const Icon(Icons.edit_outlined)),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthStore>();
    final settings = context.watch<AppSettings>();
    final loggedIn = auth.isLoggedIn;
    return Scaffold(
      appBar: AppBar(title: const Text('تنظیمات')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
          children: [Center(child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 760),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              _accountCard(auth),
              if (loggedIn) ...[
                _heading('حساب و حریم خصوصی'),
                _group(Column(children: [
                  _row(icon: Icons.account_circle_outlined,
                    label: 'ویرایش مشخصات', detail: 'نام نمایشی حساب',
                    onTap: _busy ? null : _changeName),
                  const Divider(height: 1, indent: 16, endIndent: 16),
                  _row(icon: Icons.phonelink_lock_outlined,
                    label: 'دستگاه‌ها و ورودهای اخیر',
                    detail: 'نشست‌های فعال، تاریخچه ورود و خروج دستگاه‌ها',
                    onTap: _busy ? null : _openSecurity),
                  const Divider(height: 1, indent: 16, endIndent: 16),
                  _row(icon: Icons.privacy_tip_outlined,
                    label: 'حریم خصوصی',
                    detail: 'شیوه محافظت و استفاده از اطلاعات',
                    onTap: () => launchUrl(Uri.parse('https://varsin-bsh.ir/privacy/'),
                      mode: LaunchMode.externalApplication)),
                ])),
              ],
              _heading('ظاهر برنامه'),
              _group(Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('حالت نمایش', style: TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 10),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    ChoiceChip(
                      label: const Text('روشن'),
                      selected: settings.themeMode == ThemeMode.light,
                      onSelected: (_) => settings.setTheme(ThemeMode.light)),
                    ChoiceChip(
                      label: const Text('خودکار'),
                      selected: settings.themeMode == ThemeMode.system,
                      onSelected: (_) => settings.setTheme(ThemeMode.system)),
                    ChoiceChip(
                      label: const Text('شب کویر'),
                      selected: settings.themeMode == ThemeMode.dark,
                      onSelected: (_) => settings.setTheme(ThemeMode.dark)),
                  ]),
                ]),
              )),
              _heading('اعلان و بروزرسانی'),
              _group(Column(children: [
                SwitchListTile.adaptive(
                  contentPadding: const EdgeInsetsDirectional.fromSTEB(16, 4, 10, 4),
                  secondary: const Icon(Icons.notifications_active_outlined,
                    color: Brand.qanat),
                  title: const Text('اعلان نسخه جدید',
                    style: TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: const Text('هنگام بازکردن اپ و بررسی دوره‌ای در پس‌زمینه'),
                  value: _updateAlerts,
                  onChanged: (enabled) async {
                    setState(() => _updateAlerts = enabled);
                    await Updater.setAlertsEnabled(enabled);
                  },
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                FutureBuilder<String>(
                  future: Updater.currentVersion(),
                  builder: (context, version) => _row(
                    icon: Icons.system_update_rounded,
                    label: 'بررسی و نصب بروزرسانی',
                    detail: 'نسخه نصب‌شده: ' + faDigits(version.data ?? '…'),
                    onTap: () => Updater.checkManually(context, context.read<Api>()),
                  ),
                ),
              ])),
              _heading('راهنما و پشتیبانی'),
              _group(Column(children: [
                _row(icon: Icons.help_outline_rounded,
                  label: 'راهنما و خدمات شهرداری',
                  detail: 'مراجعه به درگاه خدمات الکترونیک',
                  onTap: () => launchUrl(Uri.parse('https://varsin-bsh.ir/'),
                    mode: LaunchMode.externalApplication)),
                const Divider(height: 1, indent: 16, endIndent: 16),
                _row(icon: Icons.info_outline_rounded,
                  label: 'درباره برنامه',
                  detail: 'بشرویه من · سامانه شهروندی شهرداری بشرویه',
                  onTap: () => showAboutDialog(
                    context: context, applicationName: 'بشرویه من',
                    applicationVersion: 'اپ خدمات الکترونیک شهرداری',
                    children: [const Text('برای دریافت نسخه‌های تازه از بخش بروزرسانی استفاده کنید.')],
                  )),
              ])),
              if (loggedIn) ...[
                const SizedBox(height: 18),
                _group(_row(
                  icon: Icons.logout_rounded,
                  label: 'خروج از حساب این گوشی',
                  danger: true,
                  onTap: _busy ? null : _signOut)),
              ] else ...[
                const SizedBox(height: 12),
                _group(_row(
                  icon: Icons.privacy_tip_outlined,
                  label: 'سیاست حریم خصوصی',
                  onTap: () => launchUrl(Uri.parse('https://varsin-bsh.ir/privacy/'),
                    mode: LaunchMode.externalApplication))),
              ],
            ]),
          ))],
        ),
      ),
    );
  }
}
