import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:scharge_app/auth/auth_screens.dart';
import 'package:scharge_app/core/session.dart';
import 'package:scharge_app/core/theme.dart';
import 'package:scharge_app/shared/ui.dart';

/// The sign-in screens lay out on a small phone (no overflow / layout errors) and the PIN pad works.
void main() {
  Widget wrap(Widget w) => MaterialApp(theme: Sc.theme(Sc.red), home: MediaQuery(data: const MediaQueryData(size: Size(360, 740)), child: w));

  testWidgets('phone number screen', (t) async {
    await t.binding.setSurfaceSize(const Size(360, 740));
    await t.pumpWidget(wrap(PhoneScreen(session: Session())));
    expect(find.text('Your mobile number'), findsOneWidget);
    expect(find.text('Send code'), findsOneWidget);
  });

  testWidgets('unlock screen with PIN pad', (t) async {
    await t.binding.setSurfaceSize(const Size(360, 740));
    final s = Session()..profile = {'user': {'name': 'Brian Kamau', 'roles': ['tenant']}, 'brand': {}, 'screens': [], 'session': {'has_pin': true}};
    await t.pumpWidget(wrap(UnlockScreen(session: s)));
    expect(find.text('Karibu tena, Brian'), findsOneWidget);
    expect(find.text('Enter your PIN'), findsOneWidget); // no fingerprint set up -> PIN
    await t.tap(find.text('4'));
    await t.pump();
  });

  test('money format', () {
    expect(kes('13450.00'), 'KES 13,450');
    expect(kes('99.5', symbol: false), '99.50');
  });
}
