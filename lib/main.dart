import 'dart:async';
import 'dart:ui';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'shared/ph.dart';
import 'package:url_launcher/url_launcher.dart';

import 'auth/auth_screens.dart';
import 'core/api.dart';
import 'core/device.dart';
import 'core/push.dart';
import 'core/session.dart';
import 'core/theme.dart';
import 'shared/gate_screen.dart';
import 'shared/motion.dart';
import 'shared/ui.dart';
import 'staff/crm_screens.dart';
import 'staff/staff_screens.dart';
import 'tenant/tenant_screens.dart';
import 'tenant/water_screen.dart';

/// Which screen the person is on, for crash reports.
String currentScreen = 'start';

void main() {
  runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();
    final session = Session();
    // Uncaught Flutter and Dart errors go to App health (POST /errors), signed in or not.
    FlutterError.onError = (d) {
      FlutterError.presentError(d);
      if (!kDebugMode) reportError(d.exception, d.stack, token: session.api.token, screen: currentScreen);
    };
    PlatformDispatcher.instance.onError = (e, st) {
      if (!kDebugMode) reportError(e, st, token: session.api.token, screen: currentScreen);
      return true;
    };
    await Device.load();
    await Push.init();
    runApp(ScApp(session: session..load()));
  }, (e, st) => reportError(e, st, screen: currentScreen));
}

/// One app for every role. Which step shows is decided here from the session:
/// not signed in -> phone number; no PIN yet -> create PIN; locked -> phone lock / PIN; else the person's screens.
class ScApp extends StatefulWidget {
  const ScApp({super.key, required this.session});
  final Session session;
  @override
  State<ScApp> createState() => _ScAppState();
}

class _ScAppState extends State<ScApp> with WidgetsBindingObserver {
  final _nav = GlobalKey<NavigatorState>();
  DateTime? _pausedAt;
  Session get s => widget.session;

  String? _updateUrl;
  bool _mustUpdate = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    s.addListener(_changed);
    Api.onUpdateRequired = (url) => setState(() {
          _mustUpdate = true;
          _updateUrl = url;
        });
    Push.onOpenBill = (id) {
      if (!(s.signedIn && s.unlocked)) return;
      // Tenants open their own bill; staff open the company's (each endpoint checks access again).
      _nav.currentState?.push(MaterialPageRoute(builder: (_) => BillScreen(session: s, id: id, staff: s.screen('tenant_bills') == null)));
    };
    Push.onForeground = (title, body, invoiceId) {
      final ctx = _nav.currentContext;
      if (ctx == null) return;
      ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(
        content: Text(body.isEmpty ? title : '$title: $body'),
        action: invoiceId == null ? null : SnackBarAction(label: 'Open', onPressed: () => Push.onOpenBill?.call(invoiceId)),
      ));
    };
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    s.removeListener(_changed);
    super.dispose();
  }

  bool? _wasOpen;
  void _changed() {
    final open = s.signedIn && s.unlocked && s.hasPin;
    if (_wasOpen != null && _wasOpen != open) _nav.currentState?.popUntil((r) => r.isFirst);
    _wasOpen = open;
    setState(() {});
  }

  /// Locks again after 5 minutes in the background.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused) _pausedAt = DateTime.now();
    if (state == AppLifecycleState.resumed && _pausedAt != null && s.signedIn && s.hasPin &&
        DateTime.now().difference(_pausedAt!) > const Duration(minutes: 5)) {
      s.lock();
    }
  }

  ThemeData? _themeCache;
  Color? _themeFor;
  ThemeData _theme() {
    if (_themeCache == null || _themeFor != Sc.brand || _accentFor != Sc.accent) {
      _themeFor = Sc.brand;
      _accentFor = Sc.accent;
      _themeCache = Sc.theme(Sc.brand);
    }
    return _themeCache!;
  }

  Color? _accentFor;

  @override
  Widget build(BuildContext context) {
    final Widget home;
    if (_mustUpdate) {
      home = _UpdateScreen(url: _updateUrl);
    } else if (!s.ready) {
      home = Scaffold(
        backgroundColor: Sc.red,
        body: Center(
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: .8, end: 1),
            duration: const Duration(milliseconds: 700),
            curve: Curves.easeOutBack,
            builder: (context, v, child) => Opacity(opacity: ((v - .8) * 5).clamp(0, 1), child: Transform.scale(scale: v, child: child)),
            child: const BrandLogo(width: 160),
          ),
        ),
      );
    } else if (!s.signedIn) {
      home = WelcomeScreen(session: s);
    } else if (!s.hasPin) {
      home = CreatePinScreen(session: s);
    } else if (!s.unlocked) {
      home = UnlockScreen(key: ValueKey(s.user['id']), session: s);
    } else {
      home = HomeShell(session: s);
    }
    return MaterialApp(
      navigatorKey: _nav,
      title: s.signedIn ? s.brand.name : 'scharge',
      debugShowCheckedModeBanner: false,
      theme: _theme(),
      // Respect the phone's text size, but cap it so screens still fit.
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(textScaler: MediaQuery.of(context).textScaler.clamp(minScaleFactor: .9, maxScaleFactor: 1.25)),
        child: child!,
      ),
      home: AnimatedSwitcher(duration: const Duration(milliseconds: 350), child: KeyedSubtree(key: ValueKey(home.runtimeType), child: home)),
    );
  }
}

/// Bottom tabs = the screens the server listed for this person (at most 4 + "More").
class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.session});
  final Session session;
  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _i = 0;
  static const _icons = {
    'tenant_bills': PhosphorIconsLight.house, 'tenant_payments': PhosphorIconsLight.receipt, 'tenant_repairs': PhosphorIconsLight.wrench,
    'gate': PhosphorIconsLight.identificationBadge, 'rent': PhosphorIconsLight.money, 'bills': PhosphorIconsLight.warning, 'payments': PhosphorIconsLight.receipt,
    'readings': PhosphorIconsLight.drop, 'repairs': PhosphorIconsLight.wrench, 'bookings': PhosphorIconsLight.calendarBlank,
    'tenants': PhosphorIconsLight.users, 'units': PhosphorIconsLight.buildings, 'companies': PhosphorIconsLight.buildings,
    'suggestions': PhosphorIconsLight.lightbulb,
    'crm_today': PhosphorIconsLight.house, 'crm_pipeline': PhosphorIconsLight.buildings, 'crm_follow_ups': PhosphorIconsLight.clockCounterClockwise,
    'crm_sales': PhosphorIconsLight.piggyBank, 'crm_requests': PhosphorIconsLight.megaphone, 'crm_waitlist': PhosphorIconsLight.users,
  };
  static const _filled = {
    'tenant_bills': PhosphorIconsFill.house, 'tenant_payments': PhosphorIconsFill.receipt, 'tenant_repairs': PhosphorIconsFill.wrench,
    'gate': PhosphorIconsFill.identificationBadge, 'rent': PhosphorIconsFill.money, 'bills': PhosphorIconsFill.warning, 'payments': PhosphorIconsFill.receipt,
    'readings': PhosphorIconsFill.drop, 'repairs': PhosphorIconsFill.wrench, 'bookings': PhosphorIconsFill.calendarBlank,
    'tenants': PhosphorIconsFill.users, 'units': PhosphorIconsFill.buildings, 'companies': PhosphorIconsFill.buildings, 'suggestions': PhosphorIconsFill.lightbulb,
    'crm_today': PhosphorIconsFill.house, 'crm_pipeline': PhosphorIconsFill.buildings, 'crm_follow_ups': PhosphorIconsFill.checkSquare,
    'crm_sales': PhosphorIconsFill.money, 'crm_requests': PhosphorIconsFill.radioButton, 'crm_waitlist': PhosphorIconsFill.users,
  };
  // Screens reached from a tile or the menu, not a tab.
  static const _notTabs = {'tenant_suggest', 'tenant_water'};
  static const _short = {'tenant_bills': 'Home', 'tenant_payments': 'Receipts', 'tenant_repairs': 'Repairs', 'bills': 'Arrears',
    'crm_today': 'Today', 'crm_follow_ups': 'To do'};

  Session get s => widget.session;

  Widget _page(String key) {
    currentScreen = key;
    return switch (key) {
        'tenant_bills' => TenantHome(session: s, openTab: _open, trailing: _headerActions()),
        'tenant_payments' => ReceiptsScreen(session: s),
        'tenant_repairs' => TenantRepairs(session: s),
        'gate' => GateScreen(session: s),
        'rent' => RentScreen(session: s, openTab: _open),
        'bills' => ArrearsScreen(session: s),
        'payments' => PaymentsScreen(session: s),
        'readings' => ReadingsScreen(session: s),
        'repairs' => WorkOrdersScreen(session: s),
        'bookings' => BookingsScreen(session: s),
        'tenants' => TenantsScreen(session: s),
        'units' => UnitsScreen(session: s),
        'companies' => CompaniesScreen(session: s),
        'suggestions' => SuggestionsScreen(session: s),
        'tenant_suggest' => SuggestScreen(session: s),
        'tenant_water' => WaterScreen(session: s),
        'crm_today' => CrmTodayScreen(session: s, openTab: _open),
        'crm_pipeline' => PipelineScreen(session: s),
        'crm_follow_ups' => CrmFollowUpsScreen(session: s),
        'crm_sales' => SalesScreen(session: s),
        'crm_requests' => CrmRequestsScreen(session: s),
        'crm_waitlist' => WaitlistScreen(session: s),
        _ => const EmptyState('This screen needs a newer version of the app.'),
      };
  }

  void _open(String key) {
    final screens = _tabScreens;
    if (_notTabs.contains(key)) {
      final sc = s.screen(key);
      if (sc != null) {
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => Scaffold(appBar: AppBar(title: Text(sc.title)), body: _page(key))));
      }
      return;
    }
    final i = screens.indexWhere((x) => x.key == key);
    if (i < 0) return;
    if (i < 4 || screens.length <= 5) {
      setState(() => _i = i);
    } else {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => Scaffold(appBar: AppBar(title: Text(screens[i].title)), body: _page(key))));
    }
  }

  List<AppScreen> get _tabScreens => s.screens.where((x) => !_notTabs.contains(x.key)).toList();

  int _bell = 0;
  List<Map<String, dynamic>> _alerts = [];

  @override
  void initState() {
    super.initState();
    _loadBell();
    Push.register(s.api); // asks for notification permission the first time, then sends the push address
  }

  /// The bell: what needs this person now (GET /notifications). Hidden when there is nothing.
  DateTime? _bellAt;

  Future<void> _loadBell({bool force = false}) async {
    if (!force && _bellAt != null && DateTime.now().difference(_bellAt!) < const Duration(minutes: 1)) return;
    _bellAt = DateTime.now();
    try {
      final d = await Api.fresh(() => s.api.get('/notifications')); // own 1-minute throttle above
      if (mounted) {
        setState(() {
          _bell = (d['count'] as num?)?.toInt() ?? 0;
          _alerts = ((d['data'] as List?) ?? []).cast<Map<String, dynamic>>();
        });
      }
    } catch (_) {}
  }

  void _openBell() => showModalBottomSheet(
        context: context,
        builder: (c) => SafeArea(
          child: ListView(shrinkWrap: true, padding: const EdgeInsets.fromLTRB(20, 20, 20, 12), children: [
            Text('Needs you', style: Sc.title(16)),
            const SizedBox(height: 10),
            for (final a in _alerts)
              KeyedSubtree(child: Tile(
                  onTap: () {
                    Navigator.of(c).pop();
                    _open('${a['screen']}');
                    Future.delayed(const Duration(seconds: 2), () => _loadBell(force: true));
                  },
                  child: Row(children: [
                    Container(
                      width: 30,
                      height: 30,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(color: Sc.brand, shape: BoxShape.circle),
                      child: Text('${a['count']}', style: Sc.body(13, weight: 700, color: Colors.white)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${a['title']}', style: Sc.body(15, weight: 600)),
                      Text('${a['text']}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                    ])),
                    const Icon(PhosphorIconsLight.caretRight),
                  ]),
                ),
              ),
          ]),
        ),
      );

  /// The bell (only when something needs you) and the one avatar.
  Widget _headerActions() => Row(mainAxisSize: MainAxisSize.min, children: [
        if (_bell > 0)
          Appear(
            child: IconButton(
              tooltip: '$_bell things need you',
              onPressed: _openBell,
              icon: Badge(
                label: Text(_bell > 9 ? '9+' : '$_bell', style: Sc.body(10, weight: 700, color: Colors.white)),
                child: const Icon(PhosphorIconsLight.bell, color: Sc.ink),
              ),
            ),
          ),
        IconButton(
          tooltip: 'Account',
          icon: Avatar(s.name, size: 40),
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AccountScreen(session: s))),
        ),
      ]);

  bool get _tenant => s.screen('tenant_bills') != null;

  @override
  Widget build(BuildContext context) {
    final screens = _tabScreens;
    // No screens for this role (e.g. access changed on the Roles page): an explanation, never a crash.
    // (clamp(0, -1) on an empty list threw "Invalid argument(s): 0", App health, Nov 2026.)
    if (screens.isEmpty) return _NoScreens(session: s, trailing: _headerActions());
    final tabs = screens.length <= 5 ? screens : screens.take(4).toList();
    final more = screens.length > 5;
    final last = tabs.length - (more ? 0 : 1);
    final index = _i.clamp(0, last < 0 ? 0 : last);
    return Scaffold(
      appBar: _tenant
          ? null
          : AppBar(
        toolbarHeight: 48,
        titleSpacing: 20,
        title: Row(children: [
          if (s.brand.logoUrl != null) BrandLogo(logoUrl: s.brand.logoUrl, width: 96) else const BrandLogo(onRed: false, width: 96),
        ]),
        actions: [
          if (_bell > 0)
            Appear(
              child: IconButton(
                tooltip: '$_bell things need you',
                onPressed: _openBell,
                icon: Badge(
                  backgroundColor: Sc.brand,
                  label: Text(_bell > 9 ? '9+' : '$_bell', style: Sc.body(10, weight: 700, color: Colors.white)),
                  child: const Icon(PhosphorIconsLight.bell, color: Sc.ink),
                ),
              ),
            ),
          IconButton(
            tooltip: 'Account',
            icon: Avatar(s.name, size: 36),
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AccountScreen(session: s))),
          ),
          const SizedBox(width: 12),
        ],
      ),
      body: SafeArea(
        top: _tenant,
        child: AnimatedSwitcher(
          duration: reduceMotion(context) ? Duration.zero : const Duration(milliseconds: 260),
          switchInCurve: Curves.easeOutCubic,
          transitionBuilder: (child, a) => FadeTransition(
              opacity: a, child: SlideTransition(position: Tween(begin: const Offset(0, .02), end: Offset.zero).animate(a), child: child)),
          child: index < tabs.length
              ? KeyedSubtree(key: ValueKey(tabs[index].key), child: _page(tabs[index].key))
              : KeyedSubtree(key: const ValueKey('more'), child: _MoreList(session: s, screens: screens.skip(4).toList(), page: _page)),
        ),
      ),
      bottomNavigationBar: tabs.length < 2 && !more
          ? null
          : NavigationBar(
              selectedIndex: index,
              onDestinationSelected: (i) {
                setState(() => _i = i);
                _loadBell();
              },
              animationDuration: const Duration(milliseconds: 400),
              destinations: [
                for (final t in tabs)
                  NavigationDestination(
                    icon: Icon(_icons[t.key] ?? PhosphorIconsLight.circle),
                    selectedIcon: Icon(_filled[t.key] ?? PhosphorIconsFill.circle),
                    label: _short[t.key] ?? t.title,
                  ),
                if (more) const NavigationDestination(icon: Icon(PhosphorIconsLight.dotsThree), selectedIcon: Icon(PhosphorIconsFill.dotsThree), label: 'More'),
              ],
            ),
    );
  }
}

/// Signed in, but this role has nothing in the app (yet). Says so, lets them refresh after an admin changes access.
class _NoScreens extends StatelessWidget {
  const _NoScreens({required this.session, required this.trailing});
  final Session session;
  final Widget trailing;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(toolbarHeight: 48, actions: [trailing, const SizedBox(width: 12)]),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(20), children: [
            EmptyPanel(
              icon: PhosphorIconsLight.lock,
              title: 'Nothing to show for your role yet',
              text: 'Your ${session.roleLabel.isEmpty ? 'account' : session.roleLabel.toLowerCase()} access has no app screens. Ask your admin, then refresh.',
              action: 'Refresh',
              onAction: () async {
                try {
                  await session.refresh();
                } catch (e) {
                  if (context.mounted) toast(context, e);
                }
              },
            ),
          ]),
        ),
      );
}

class _MoreList extends StatelessWidget {
  const _MoreList({required this.session, required this.screens, required this.page});
  final Session session;
  final List<AppScreen> screens;
  final Widget Function(String) page;
  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(20), children: [
        for (final sc in screens)
          Tile(
            onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => Scaffold(appBar: AppBar(title: Text(sc.title)), body: page(sc.key)))),
            child: Row(children: [Expanded(child: Text(sc.title, style: Sc.body(15, weight: 600))), const Icon(PhosphorIconsLight.caretRight)]),
          ),
      ]);
}

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Account')),
        body: ListenableBuilder(
          listenable: session,
          builder: (context, _) => ListView(padding: const EdgeInsets.all(16), children: [
            if (session.screen('tenant_suggest') != null)
              Tile(
                filled: true,
                onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => Scaffold(appBar: AppBar(title: const Text('Suggestion box')), body: SuggestScreen(session: session)))),
                child: Row(children: [
                  Icon(PhosphorIconsLight.lightbulb, color: Sc.brand),
                  const SizedBox(width: 12),
                  Expanded(child: Text('Suggestion box (anonymous)', style: Sc.body(15, weight: 600))),
                  const Icon(PhosphorIconsLight.caretRight),
                ]),
              ),
            Row(children: [
              Avatar(session.name, size: 56),
              const SizedBox(width: 14),
              Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(session.name, style: Sc.body(18, weight: 600)),
                Text('${session.roleLabel} · ${session.brand.name}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                if (session.user['phone'] != null) Text('${session.user['phone']}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
              ])),
            ]),
            const SizedBox(height: 24),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text('Unlock with fingerprint, face or phone lock', style: Sc.body(15)),
              value: session.biometrics,
              onChanged: (v) => session.setBiometrics(v),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text('Lock now', style: Sc.body(15)),
              trailing: const Icon(PhosphorIconsLight.lock),
              onTap: session.lock,
            ),
            if (session.brand.supportPhone != null)
              ListTile(contentPadding: EdgeInsets.zero, title: Text('Help: ${session.brand.supportPhone}', style: Sc.body(15))),
            const SizedBox(height: 24),
            OutlinedButton(
              onPressed: () async {
                final ok = await showDialog<bool>(
                  context: context,
                  builder: (c) => AlertDialog(
                    title: const Text('Sign out of this phone?'),
                    content: const Text('Next time you need an SMS code to get back in.'),
                    actions: [
                      TextButton(onPressed: () => Navigator.of(c).pop(false), child: const Text('Stay')),
                      TextButton(onPressed: () => Navigator.of(c).pop(true), child: const Text('Sign out')),
                    ],
                  ),
                );
                if (ok == true) await session.signOut();
              },
              child: const Text('Sign out'),
            ),
            if (session.brand.poweredBy) Padding(padding: const EdgeInsets.only(top: 24), child: Text('Powered by scharge', textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted))),
          ]),
        ),
      );
}

/// HTTP 426 from the server: this version is too old.
class _UpdateScreen extends StatelessWidget {
  const _UpdateScreen({this.url});
  final String? url;
  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Sc.red,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const SizedBox(height: 40),
              const Center(child: BrandLogo(width: 140)),
              const Spacer(),
              Text('Update the app', textAlign: TextAlign.center, style: Sc.title(22, color: Colors.white)),
              const SizedBox(height: 10),
              Text('This version is too old to keep working. The new one takes a minute to install and keeps your sign-in.',
                  textAlign: TextAlign.center, style: Sc.body(15, color: Colors.white)),
              const Spacer(),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: Sc.red),
                onPressed: url == null ? null : () => launchUrl(Uri.parse(url!), mode: LaunchMode.externalApplication),
                child: const Text('Download the update'),
              ),
              const SizedBox(height: 8),
              Text(url == null ? 'Ask your manager for the new app.' : 'Allow "install unknown apps" for your browser when asked.',
                  textAlign: TextAlign.center, style: Sc.body(12, color: Colors.white)),
            ]),
          ),
        ),
      );
}
