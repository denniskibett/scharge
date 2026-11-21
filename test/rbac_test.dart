import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:scharge_app/core/session.dart';
import 'package:scharge_app/core/theme.dart';
import 'package:scharge_app/main.dart';
import 'package:scharge_app/shared/ui.dart';
import 'package:scharge_app/staff/crm_screens.dart';

/// Role-based screens: the app draws only what the server lists (MobileAccess::screens), never crashes on an empty
/// list, and the CRM tiles for account managers lay out on a small phone. No device or emulator needed.
Session _as(List<String> roles, List<Map<String, dynamic>> screens) => Session()
  ..profile = {
    'user': {'id': 1, 'name': 'Wanjiru Account', 'roles': roles},
    'brand': {},
    'screens': screens,
    'session': {'has_pin': true},
  };

Widget _phone(Widget child) => MaterialApp(theme: Sc.theme(Sc.red), home: MediaQuery(data: const MediaQueryData(size: Size(360, 740)), child: child));

Map<String, dynamic> _screen(String key, String title, [List<String> actions = const []]) => {'key': key, 'title': title, 'actions': actions};

void main() {
  testWidgets('a role with no screens gets an explanation, not "Invalid argument(s): 0"', (t) async {
    await t.binding.setSurfaceSize(const Size(360, 740));
    await t.pumpWidget(_phone(HomeShell(session: _as(['sales'], []))));
    await t.pump();
    expect(t.takeException(), isNull);
    expect(find.text('Nothing to show for your role yet'), findsOneWidget);
  });

  testWidgets('an account manager opens on the CRM tabs, the rest under More', (t) async {
    await t.binding.setSurfaceSize(const Size(360, 740));
    final s = _as(['account_manager'], [
      _screen('crm_today', 'My day'),
      _screen('crm_pipeline', 'Pipeline', ['move_stage', 'log', 'schedule', 'handle_reply']),
      _screen('crm_follow_ups', 'Follow-ups', ['update']),
      _screen('crm_sales', 'Sales'),
      _screen('crm_requests', 'Requests', ['update']),
      _screen('crm_waitlist', 'Waitlist', ['update']),
      _screen('rent', 'Rent'),
      _screen('payments', 'Payments'),
      _screen('companies', 'Companies'),
    ]);
    await t.pumpWidget(_phone(HomeShell(session: s)));
    await t.pump();
    for (final label in ['Today', 'Pipeline', 'To do', 'Sales', 'More']) {
      expect(find.text(label), findsWidgets, reason: label);
    }
    expect(find.text('Rent'), findsNothing); // not a tab: under More
    expect(s.screen('crm_pipeline')!.can('move_stage'), isTrue);
    expect(s.screen('crm_sales')!.can('move_stage'), isFalse);
  });

  testWidgets('CRM tiles fit a small phone', (t) async {
    await t.binding.setSurfaceSize(const Size(360, 740));
    final s = _as(['account_manager'], [_screen('crm_follow_ups', 'Follow-ups', ['update'])]);
    await t.pumpWidget(_phone(Scaffold(
      body: ListView(padding: const EdgeInsets.all(12), children: [
        ProspectTile(session: s, p: {
          'id': 1, 'name': 'Mwangaza Court Property Managers and Letting Agents Limited', 'stage': 'proposal', 'stage_label': 'Proposal',
          'place': 'Kilimani, Dagoretti North, Nairobi', 'owner': 'Wanjiru', 'next_due_at': '2026-11-20T10:00:00Z',
          'reply': {'from': 'Asha', 'snippet': 'Please call me tomorrow about the 40 units in Kileleshwa, we are interested'},
        }),
        FollowUpTile(session: s, canUpdate: true, f: {
          'id': 3, 'type_label': 'Call', 'title': 'Call back about the proposal and the per-unit price', 'status_label': 'Open',
          'open': true, 'overdue': true, 'due_at': '2026-11-18T09:00:00Z', 'company': {'id': 1, 'name': 'Mwangaza Court'}, 'contact': {'name': 'Asha Njeri'},
        }),
      ]),
    )));
    await t.pump();
    expect(t.takeException(), isNull);
    expect(find.text('Overdue'), findsOneWidget);
    expect(find.text('Proposal'), findsOneWidget);
  });

  test('photo URLs skip the empty-array address that 404d', () {
    expect(photoUrls(['https://scharge.sharet.africa/storage/%5B%5D']), isEmpty);
    expect(photoUrls(['https://scharge.sharet.africa/storage/[]', null, 3, '']), isEmpty);
    expect(photoUrls('[]'), isEmpty);
    expect(photoUrls(['https://scharge.sharet.africa/storage/repair-photos/a.jpg']), hasLength(1));
  });
}
