import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:scharge_app/auth/auth_screens.dart';
import 'package:scharge_app/core/session.dart';
import 'package:scharge_app/core/theme.dart';
import 'package:scharge_app/shared/documents.dart';

/// Screens fit a small phone (360 x 740) with large text, without overflow. Writes PNGs to test/goldens to look at.
Future<void> _fonts() async {
  for (final (family, file) in [('DMSans', 'assets/fonts/DMSans.ttf'), ('Outfit', 'assets/fonts/Outfit.ttf'), ('Unbounded', 'assets/fonts/Unbounded.ttf')]) {
    final loader = FontLoader(family)..addFont(rootBundle.load(file));
    await loader.load();
  }
}

Widget _phone(Widget child, {double text = 1.0}) => MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: Sc.theme(Sc.red),
      home: MediaQuery(data: MediaQueryData(size: const Size(360, 740), textScaler: TextScaler.linear(text)), child: child),
    );

final _bill = {
  'id': 1, 'number': 'INV-2041', 'month_label': 'October 2026', 'status': 'partial', 'total': '38445.00', 'paid': '10000.00', 'balance': '28445.00',
  'tenant': 'Brian Kamau', 'unit': 'B04', 'property': 'Mwangaza Court', 'issued_by': 'Mwangaza Court Ltd', 'created_at': '2026-10-01T06:00:00Z',
  'items': [
    {'description': 'Rent', 'amount': '35000.00', 'paid': '10000.00'},
    {'description': 'Water · 12.3 m³', 'amount': '1845.00', 'paid': '0.00'},
    {'description': 'Service charge', 'amount': '1600.00', 'paid': '0.00'},
  ],
  'payments': [{'amount': '10000.00', 'reference': 'SJK3H2X1PQ', 'paid_at': '2026-10-03T06:14:00Z'}],
};

void main() {
  setUpAll(_fonts);

  for (final scale in [1.0, 1.25]) {
    testWidgets('welcome fits at text x$scale', (t) async {
      await t.binding.setSurfaceSize(const Size(360, 740));
      await t.pumpWidget(_phone(WelcomeScreen(session: Session()), text: scale));
      await t.pumpAndSettle();
      await expectLater(find.byType(WelcomeScreen), matchesGoldenFile('goldens/welcome_$scale.png'));
    });

    testWidgets('invoice fits at text x$scale', (t) async {
      await t.binding.setSurfaceSize(const Size(360, 740));
      await t.pumpWidget(_phone(Scaffold(body: ListView(padding: const EdgeInsets.all(16), children: [InvoiceDocument(bill: _bill)])), text: scale));
      await t.pumpAndSettle();
      await expectLater(find.byType(Scaffold), matchesGoldenFile('goldens/invoice_$scale.png'));
    });

    testWidgets('gate ticket fits at text x$scale', (t) async {
      await t.binding.setSurfaceSize(const Size(360, 740));
      final pass = {'kind': 'visitor', 'property': 'Mwangaza Court', 'guest_name': 'Mercy Wambui', 'unit': 'B04', 'code': '482619',
        'valid_from': '2026-10-10T11:00:00Z', 'valid_until': '2026-10-10T17:00:00Z', 'qr': 'https://scharge.sharet.africa/gate/verify?code=482619'};
      await t.pumpWidget(_phone(Scaffold(body: ListView(padding: const EdgeInsets.all(16), children: [PassTicket(pass: pass, boundaryKey: GlobalKey())])), text: scale));
      await t.pumpAndSettle();
      await expectLater(find.byType(Scaffold), matchesGoldenFile('goldens/ticket_$scale.png'));
    });

    testWidgets('receipt fits at text x$scale', (t) async {
      await t.binding.setSurfaceSize(const Size(360, 740));
      final pay = {'receipt_no': 'RCT-0412', 'issued_by': 'Mwangaza Court Ltd', 'amount': '38445.00', 'payer': 'Brian Kamau', 'unit': 'B04',
        'property': 'Mwangaza Court', 'invoice_number': 'INV-2041', 'invoice_month': 'October 2026', 'method': 'mpesa', 'reference': 'SJK3H2X1PQ',
        'paid_at': '2026-10-03T06:14:00Z'};
      await t.pumpWidget(_phone(ReceiptScreen(payment: pay, session: Session()), text: scale));
      await t.pumpAndSettle();
      await expectLater(find.byType(ReceiptScreen), matchesGoldenFile('goldens/receipt_$scale.png'));
    });

    testWidgets('register fits at text x$scale', (t) async {
      await t.binding.setSurfaceSize(const Size(360, 740));
      await t.pumpWidget(_phone(RegisterScreen(session: Session()), text: scale));
      await t.pumpAndSettle();
      await expectLater(find.byType(RegisterScreen), matchesGoldenFile('goldens/register_$scale.png'));
    });
  }
}
