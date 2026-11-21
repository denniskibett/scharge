import 'package:flutter/material.dart';

import '../core/session.dart';
import '../core/theme.dart';
import 'motion.dart';
import 'ui.dart';

/// How a payment was confirmed: the phone's lock / fingerprint, or the app PIN (sent to the server to check).
class PayConfirm {
  PayConfirm.device() : pin = null;
  PayConfirm.pin(this.pin);
  final String? pin;
  Map<String, dynamic> get fields => pin == null ? {'confirmed_with': 'device'} : {'confirmed_with': 'pin', 'pin': pin};
}

/// Every payment is confirmed first: fingerprint / face / phone lock when switched on, otherwise the PIN.
Future<PayConfirm?> confirmPayment(BuildContext context, Session session, String what) async {
  if (session.biometrics && await session.confirmWithPhone('Confirm: $what')) return PayConfirm.device();
  if (!context.mounted) return null;
  final pin = await showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    builder: (_) => _PinSheet(length: session.pinLength, what: what),
  );
  return pin == null ? null : PayConfirm.pin(pin);
}

class _PinSheet extends StatefulWidget {
  const _PinSheet({required this.length, required this.what});
  final int length;
  final String what;
  @override
  State<_PinSheet> createState() => _PinSheetState();
}

class _PinSheetState extends State<_PinSheet> {
  String _pin = '';
  @override
  Widget build(BuildContext context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 16),
          child: Appear(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text('Confirm with your PIN', textAlign: TextAlign.center, style: Sc.body(18, weight: 600)),
              const SizedBox(height: 4),
              Text(widget.what, textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
              const SizedBox(height: 20),
              PinDots(_pin.length, length: widget.length),
              const SizedBox(height: 20),
              Keypad(
                onDigit: (d) {
                  if (_pin.length >= widget.length) return;
                  setState(() => _pin += d);
                  if (_pin.length == widget.length) Navigator.of(context).pop(_pin);
                },
                onDelete: () => setState(() => _pin = _pin.isEmpty ? '' : _pin.substring(0, _pin.length - 1)),
              ),
            ]),
          ),
        ),
      );
}
