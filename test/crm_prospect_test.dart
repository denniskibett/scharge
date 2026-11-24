import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:scharge_app/core/session.dart';
import 'package:scharge_app/core/theme.dart';
import 'package:scharge_app/staff/crm_screens.dart';

/// The prospect page and the reach-out page (email or demo form) fit a small phone. Writes PNGs to test/goldens to look at.
const _prospect = {
  'id': 7, 'name': 'Eskartec Solutions Limited', 'stage': 'contacted', 'stage_label': 'Contacted', 'segment': 'Electricity / KPLC & Metering',
  'place': 'Nairobi, Embakasi East', 'owner': 'Annete Sidi Kenga', 'phone': '+254700222255', 'email': 'info@eskartec.co.ke', 'reply': null,
  'contacts': [], 'stages': [{'key': 'new', 'label': 'New'}, {'key': 'contacted', 'label': 'Contacted'}], 'action_types': {'call': 'Call', 'note': 'Note'},
  'todo': [
    {'id': 1, 'type': 'call', 'type_label': 'Call', 'title': 'Follow up on the cold email to Eskartec Solutions Limited', 'open': true, 'status_label': 'Open',
     'due_at': '2026-11-27T10:00:00Z'},
  ],
  'history': [
    {'id': 2, 'type': 'stage', 'type_label': 'Stage', 'title': 'Stage: New → Contacted', 'created_at': '2026-11-24T09:41:00Z'},
    {'id': 3, 'type': 'email', 'type_label': 'Email', 'title': 'Cold email: Eskartec Solutions Limited: clients who install meters but still bill tenants?',
     'notes': 'Hello Eskartec Solutions Limited team,\n\nWe are from scharge. We were looking at Eskartec…', 'created_at': '2026-11-24T09:41:00Z'},
    {'id': 4, 'type': 'note', 'type_label': 'Note', 'title': 'Owner: nobody → Annete Sidi Kenga', 'notes': 'Assigned by the UTILITIES 2026-11 campaign.',
     'created_at': '2026-11-24T09:41:00Z'},
    {'id': 5, 'type': 'import', 'type_label': 'Import', 'title': 'Details merged from overture_places_kenya.csv', 'created_at': '2026-11-19T14:00:00Z'},
  ],
};
const _book = {
  'label': 'Utilities', 'approach': ['Ask how they bill tenants after installing meters.'], 'offers': ['Water and power billing per unit'], 'objections': [],
  'subject': 'Eskartec: clients who install meters but still bill tenants?', 'body': 'Hello Eskartec team,\n\nWe are from scharge.', 'saved_draft': false,
  'do_not_email': false, 'to': 'info@eskartec.co.ke', 'emails': ['info@eskartec.co.ke'], 'phones': [{'name': 'Eskartec Solutions Limited', 'phone': '+254700222255'}],
  'signed_by': 'Annete Sidi Kenga', 'signals': ['Based in Embakasi East, Nairobi'], 'pains': [], 'chosen_pains': [], 'research': null,
  'form_url': 'https://scharge.sharet.africa/demo/SC-AB12CD', 'form_message': 'Hello Eskartec, this is Annete from scharge. https://scharge.sharet.africa/demo/SC-AB12CD',
  'form_filled': false, 'follow_up_days': {'2': '2 working days', '3': '3 working days'},
};

Future<void> _fonts() async {
  for (final (family, file) in [('DMSans', 'assets/fonts/DMSans.ttf'), ('Outfit', 'assets/fonts/Outfit.ttf'), ('Unbounded', 'assets/fonts/Unbounded.ttf')]) {
    await (FontLoader(family)..addFont(rootBundle.load(file))).load();
  }
}

void main() {
  late Session s;
  setUp(() {
    s = http.runWithClient(() => Session(), () => MockClient((r) async {
          final body = r.url.path.endsWith('/playbook') ? _book : _prospect;
          return http.Response(jsonEncode(body), 200, headers: {'content-type': 'application/json'});
        }));
    s.profile = {
      'user': {'name': 'Annete Sidi Kenga', 'roles': ['account_manager']}, 'brand': {}, 'session': {},
      'screens': [
        {'key': 'crm_pipeline', 'title': 'Pipeline', 'actions': ['move_stage', 'log', 'schedule', 'handle_reply', 'send_email', 'add', 'edit']},
        {'key': 'crm_follow_ups', 'title': 'Follow-ups', 'actions': ['update']},
      ],
    };
  });

  Future<void> shot(WidgetTester t, Widget w, String name) async {
    await _fonts();
    await t.binding.setSurfaceSize(const Size(360, 740));
    await t.pumpWidget(MaterialApp(debugShowCheckedModeBanner: false, theme: Sc.theme(Sc.red), home: RepaintBoundary(child: w)));
    await t.pumpAndSettle();
    await expectLater(find.byType(RepaintBoundary).first, matchesGoldenFile('goldens/$name.png'));
  }

  testWidgets('prospect page', (t) async {
    await shot(t, ProspectScreen(session: s, id: 7, name: 'Eskartec Solutions Limited'), 'crm_prospect');
    expect(find.text('Reach out: email or demo form'), findsOneWidget);
    expect(find.text('Stage: Stage: New → Contacted'), findsNothing);
    expect(find.text('New → Contacted'), findsOneWidget);
  });

  testWidgets('reach out: email, then the demo form', (t) async {
    await shot(t, PlaybookEmailScreen(session: s, id: 7, name: 'Eskartec Solutions Limited'), 'crm_reach_email');
    await t.tap(find.text('Demo form'));
    await t.pumpAndSettle();
    await expectLater(find.byType(RepaintBoundary).first, matchesGoldenFile('goldens/crm_reach_form.png'));
    expect(find.text('Copy message and link'), findsOneWidget);
  });
}
