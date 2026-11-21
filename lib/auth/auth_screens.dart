import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import '../shared/ph.dart';
import 'package:flutter/services.dart';

import '../core/api.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../shared/motion.dart';
import '../shared/ui.dart';

String get _device => Platform.isIOS ? 'iPhone' : 'Android phone';

/// Red full-screen frame for the sign-in steps (canvas: "Your mobile number", "Karibu tena").
class _RedFrame extends StatelessWidget {
  const _RedFrame({required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Sc.brand,
        body: SafeArea(
          child: LayoutBuilder(
            builder: (context, c) => SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: c.maxHeight - 56),
                child: IntrinsicHeight(
                    child: DefaultTextStyle(
                        style: Sc.body(14, color: Colors.white),
                        child: Appear(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children)))),
              ),
            ),
          ),
        ),
      );
}

final _whiteButton = FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: Sc.brand);

// ---- 0. Welcome: "How do you use scharge?" (canvas: tenant role select) ------------------------------------------

class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key, required this.session});
  final Session session;
  static const _roles = [
    ('I rent a home', 'Pay rent & water, report repairs'),
    ('I own property', 'Income, payouts, statements'),
    ('I manage buildings', 'Collections, tenants, work orders'),
    ('I host a BnB', 'Bookings, keys, cleaning'),
    ('I read meters / caretaker', 'Routes, readings, tasks'),
  ];
  @override
  Widget build(BuildContext context) => _RedFrame(children: [
        const Align(alignment: Alignment.centerLeft, child: BrandLogo(width: 150)),
        const SizedBox(height: 30),
        Text('How do you use scharge?', style: Sc.head(24, color: Colors.white)),
        const SizedBox(height: 14),
        for (final (i, r) in _roles.indexed)
          KeyedSubtree(child: Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Material(
                color: i == 0 ? Colors.white : Colors.white.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  // Everyone signs in the same way; the server then shows the screens for their role.
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PhoneScreen(session: session))),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    child: Row(children: [
                      Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(r.$1, style: Sc.body(16, weight: 600, color: i == 0 ? Sc.red : Colors.white)),
                        const SizedBox(height: 2),
                        Text(r.$2, style: Sc.body(12, color: (i == 0 ? Sc.red : Colors.white).withValues(alpha: .8))),
                      ])),
                      Icon(PhosphorIconsLight.arrowRight, color: i == 0 ? Sc.red : Colors.white),
                    ]),
                  ),
                ),
              ),
            ),
          ),
      ]);
}

// ---- 1. Phone number --------------------------------------------------------------------------------------------

class PhoneScreen extends StatefulWidget {
  const PhoneScreen({super.key, required this.session});
  final Session session;
  @override
  State<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends State<PhoneScreen> {
  final _phone = TextEditingController();
  bool _busy = false;

  Future<void> _send() async {
    final digits = _phone.text.replaceAll(RegExp(r'\D'), '');
    if (digits.length < 9) return toast(context, 'Enter your mobile number, like 0712 345 678.');
    final phone = '+254${digits.substring(digits.length - 9)}';
    setState(() => _busy = true);
    try {
      await widget.session.api.post('/auth/code', {'phone': phone});
      if (!mounted) return;
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => CodeScreen(session: widget.session, phone: phone)));
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => _RedFrame(children: [
        const Align(alignment: Alignment.centerLeft, child: BrandLogo(width: 150)),
        const SizedBox(height: 36),
        Text('Your mobile number', style: Sc.title(22, color: Colors.white)),
        const SizedBox(height: 8),
        Text('The number your landlord, manager or company has for you. We send it a 6-digit code.',
            style: Sc.body(14, color: Colors.white.withValues(alpha: .9))),
        const SizedBox(height: 22),
        Text('Phone number', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .9))),
        const SizedBox(height: 6),
        Row(children: [
          Container(
            height: 56,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            alignment: Alignment.center,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: .16), borderRadius: BorderRadius.circular(12)),
            child: Text('+254', style: Sc.body(16, weight: 600, color: Colors.white)),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumber],
              style: Sc.body(18),
              decoration: const InputDecoration(hintText: '712 345 678', border: OutlineInputBorder(borderSide: BorderSide.none)),
              onSubmitted: (_) => _send(),
            ),
          ),
        ]),
        const Spacer(),
        const SizedBox(height: 24),
        FilledButton(style: _whiteButton, onPressed: _busy ? null : _send, child: Text(_busy ? 'Sending…' : 'Send code')),
        const SizedBox(height: 6),
        TextButton(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => RegisterScreen(session: widget.session))),
          child: Text('New here? Create my account', style: Sc.body(14, weight: 600, color: Colors.white)),
        ),
        TextButton(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => EmailScreen(session: widget.session))),
          child: Text('Sign in with a password instead', style: Sc.body(14, color: Colors.white)),
        ),
      ]);
}

// ---- 2. SMS code ------------------------------------------------------------------------------------------------

class CodeScreen extends StatefulWidget {
  const CodeScreen({super.key, required this.session, required this.phone});
  final Session session;
  final String phone;
  @override
  State<CodeScreen> createState() => _CodeScreenState();
}

class _CodeScreenState extends State<CodeScreen> {
  final _code = TextEditingController();
  bool _busy = false;
  int _wait = 45;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _tick();
  }

  void _tick() {
    _timer?.cancel();
    _wait = 45;
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted || _wait <= 0) return t.cancel();
      setState(() => _wait--);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _verify([int? accountId]) async {
    if (_code.text.length != 6) return;
    setState(() => _busy = true);
    try {
      final res = await widget.session.api.post('/auth/verify',
          {'phone': widget.phone, 'code': _code.text, 'device_name': _device, 'platform': Platform.isIOS ? 'ios' : 'android', 'account_id': accountId});
      if (!mounted) return;
      if (res['choose_account'] is List) {
        final picked = await Navigator.of(context).push<int>(MaterialPageRoute(
            builder: (_) => ChooseAccountScreen(accounts: (res['choose_account'] as List).cast<Map<String, dynamic>>())));
        if (picked != null) await _verify(picked);
        return;
      }
      // The root (main.dart) now shows Create PIN.
      if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
      await widget.session.signedInWith(res);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    try {
      await widget.session.api.post('/auth/code', {'phone': widget.phone});
      _tick();
      if (mounted) toast(context, 'A new code is on its way.');
    } catch (e) {
      if (mounted) toast(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final masked = '0${widget.phone.substring(4, 7)} ••• ${widget.phone.substring(widget.phone.length - 3)}';
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
          Text('Enter the code', style: Sc.body(18, weight: 600)),
          const SizedBox(height: 8),
          Text('Sent by SMS to $masked. It works once, for 10 minutes.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
          const SizedBox(height: 22),
          // One field shown as six boxes (paste and SMS autofill keep working).
          Stack(children: [
            Row(
              children: [
                for (var i = 0; i < 6; i++)
                  Expanded(
                    child: Container(
                      margin: EdgeInsets.only(right: i < 5 ? 8 : 0),
                      height: 60,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: i == _code.text.length ? Colors.white : Sc.blush,
                        border: i == _code.text.length ? Border.all(color: Sc.brand, width: 2) : null,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Text(i < _code.text.length ? _code.text[i] : '', style: Sc.head(22)),
                    ),
                  ),
              ],
            ),
            Positioned.fill(
              child: Opacity(
                opacity: 0,
                child: TextField(
                  controller: _code,
                  autofocus: true,
                  keyboardType: TextInputType.number,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                  onChanged: (v) {
                    setState(() {});
                    if (v.length == 6) _verify();
                  },
                ),
              ),
            ),
          ]),
          const SizedBox(height: 14),
          Row(children: [
            Text('No SMS? ', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            _wait > 0
                ? Text('Send again in 0:${_wait.toString().padLeft(2, '0')}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted))
                : InkWell(onTap: _resend, child: Text('Send again', style: Sc.body(13, weight: 600, color: Sc.brand))),
          ]),
          const SizedBox(height: 32),
          FilledButton(onPressed: _busy || _code.text.length != 6 ? null : _verify, child: Text(_busy ? 'Checking…' : 'Verify')),
        ]),
      ),
    );
  }
}

// ---- 2b. One number, several accounts ---------------------------------------------------------------------------

class ChooseAccountScreen extends StatelessWidget {
  const ChooseAccountScreen({super.key, required this.accounts});
  final List<Map<String, dynamic>> accounts;
  static const _roles = {
    'tenant': 'Tenant', 'admin': 'Admin', 'property_manager': 'Property manager', 'accountant': 'Accountant',
    'meter_reader': 'Meter reader', 'maintenance': 'Maintenance', 'security': 'Security', 'bnb_host': 'BnB host',
  };
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
          Text('Which account?', style: Sc.body(18, weight: 600)),
          const SizedBox(height: 8),
          Text('This number has more than one scharge account. Pick the one this phone opens.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
          const SizedBox(height: 16),
          for (final a in accounts)
            Tile(
              filled: a != accounts.first,
              onTap: () => Navigator.of(context).pop(a['id'] as int),
              child: Row(children: [
                Avatar(a['name'] as String?, light: a == accounts.first),
                const SizedBox(width: 14),
                Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(_roles[a['role']] ?? '${a['role'] ?? 'Account'}', style: Sc.body(15, weight: 600)),
                  Text('${a['company'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                ])),
                const Icon(PhosphorIconsLight.arrowRight),
              ]),
            ),
        ]),
      );
}

// ---- Email sign-in (staff without a phone) ----------------------------------------------------------------------

class EmailScreen extends StatefulWidget {
  const EmailScreen({super.key, required this.session});
  final Session session;
  @override
  State<EmailScreen> createState() => _EmailScreenState();
}

class _EmailScreenState extends State<EmailScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;

  Future<void> _go() async {
    setState(() => _busy = true);
    try {
      final res = await widget.session.api
          .post('/auth/password', {'login': _email.text.trim(), 'password': _password.text, 'device_name': _device});
      // The root (main.dart) now shows Create PIN.
      if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
      await widget.session.signedInWith(res);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Sign in with a password')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          Text('Use your phone number or your email, with the same password.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
          const SizedBox(height: 14),
          TextField(controller: _email, keyboardType: TextInputType.emailAddress, autofillHints: const [AutofillHints.username],
              decoration: const InputDecoration(labelText: 'Phone or email')),
          const SizedBox(height: 14),
          TextField(controller: _password, obscureText: true, decoration: const InputDecoration(labelText: 'Password')),
          const SizedBox(height: 24),
          FilledButton(onPressed: _busy ? null : _go, child: Text(_busy ? 'Signing in…' : 'Sign in')),
        ]),
      );
}

// ---- 3. Create PIN ----------------------------------------------------------------------------------------------

class CreatePinScreen extends StatefulWidget {
  const CreatePinScreen({super.key, required this.session});
  final Session session;
  @override
  State<CreatePinScreen> createState() => _CreatePinScreenState();
}

class _CreatePinScreenState extends State<CreatePinScreen> {
  int _shake = 0;
  int _len = 4;
  String _pin = '';
  String? _first;
  bool _bio = true;
  bool _bioAvailable = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    widget.session.canUseBiometrics().then((ok) => mounted ? setState(() => _bioAvailable = _bio = ok) : null);
  }

  Future<void> _digit(String d) async {
    if (_pin.length >= _len || _busy) return;
    setState(() => _pin += d);
    if (_pin.length < _len) return;
    if (_first == null) {
      setState(() {
        _first = _pin;
        _pin = '';
      });
      return;
    }
    if (_pin != _first) {
      toast(context, 'The PINs do not match. Start again.');
      setState(() {
        _shake++;
        _first = null;
        _pin = '';
      });
      return;
    }
    setState(() => _busy = true);
    try {
      await widget.session.createPin(_pin);
      await widget.session.setBiometrics(_bio && _bioAvailable);
    } catch (e) {
      if (mounted) toast(context, e);
      setState(() {
        _first = null;
        _pin = '';
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(_first == null ? 'Create your PIN' : 'Type it again', style: Sc.body(18, weight: 600)),
              const SizedBox(height: 8),
              Text('One PIN for opening the app and confirming payments, when fingerprint or face is not used. Not 1234 or one digit repeated.',
                  maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
              if (_first == null) ...[
                const SizedBox(height: 14),
                Center(
                  child: SegmentedButton<int>(
                    segments: const [ButtonSegment(value: 4, label: Text('4 digits')), ButtonSegment(value: 6, label: Text('6 digits'))],
                    selected: {_len},
                    onSelectionChanged: (v) => setState(() {
                      _len = v.first;
                      _pin = '';
                    }),
                  ),
                ),
              ],
              const SizedBox(height: 28),
              Shake(trigger: _shake, child: PinDots(_pin.length, length: _len)),
              const Spacer(),
              Keypad(onDigit: _digit, onDelete: () => setState(() => _pin = _pin.isEmpty ? '' : _pin.substring(0, _pin.length - 1))),
              if (_bioAvailable)
                SwitchListTile(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                  title: Text('Unlock with fingerprint, face or phone lock', style: Sc.body(15)),
                  value: _bio,
                  onChanged: (v) => setState(() => _bio = v),
                ),
            ]),
          ),
        ),
      );
}

// ---- 4. Every day: unlock ---------------------------------------------------------------------------------------

class UnlockScreen extends StatefulWidget {
  const UnlockScreen({super.key, required this.session});
  final Session session;
  @override
  State<UnlockScreen> createState() => _UnlockScreenState();
}

class _UnlockScreenState extends State<UnlockScreen> {
  int _shake = 0;
  bool _usePin = false;
  String _pin = '';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _usePin = !widget.session.biometrics;
    if (widget.session.biometrics) WidgetsBinding.instance.addPostFrameCallback((_) => _phoneLock());
  }

  Future<void> _phoneLock() async {
    setState(() => _busy = true);
    try {
      final ok = await widget.session.unlockWithPhone();
      if (!ok && mounted) setState(() => _usePin = true);
    } on ApiException catch (e) {
      if (e.signedOut) await widget.session.signOut(tellServer: false);
      if (mounted) toast(context, e);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _digit(String d) async {
    final len = widget.session.pinLength;
    if (_pin.length >= len || _busy) return;
    setState(() => _pin += d);
    if (_pin.length < len) return;
    setState(() => _busy = true);
    try {
      await widget.session.unlockWithPin(_pin);
    } on ApiException catch (e) {
      if (mounted) setState(() => _shake++);
      if (e.signedOut) await widget.session.signOut(tellServer: false);
      if (mounted) toast(context, e);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _pin = '';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.session;
    return _RedFrame(children: [
      Center(child: BrandLogo(width: 130, logoUrl: s.brand.logoUrl)),
      const SizedBox(height: 36),
      Text('Karibu tena, ${s.firstName}', textAlign: TextAlign.center, style: Sc.title(20, color: Colors.white)),
      const SizedBox(height: 6),
      Text(s.roleLabel, textAlign: TextAlign.center, style: Sc.body(14, color: Colors.white.withValues(alpha: .9))),
      const Spacer(),
      if (!_usePin) ...[
        Center(
          child: Pulse(child: Semantics(
            button: true,
            label: 'Unlock with fingerprint or phone lock',
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: _busy ? null : _phoneLock,
              child: Container(
                width: 104,
                height: 104,
                decoration: BoxDecoration(
                    shape: BoxShape.circle, color: Colors.white.withValues(alpha: .12), border: Border.all(color: Colors.white, width: 2)),
                child: const Icon(PhosphorIconsLight.fingerprint, size: 52, color: Colors.white),
              ),
            ),
          )),
        ),
        const SizedBox(height: 14),
        Text("Touch the sensor, or use your phone's lock", textAlign: TextAlign.center, style: Sc.body(15, weight: 600, color: Colors.white)),
        TextButton(
            onPressed: () => setState(() => _usePin = true),
            child: Text('Use PIN instead', style: Sc.body(15, weight: 600, color: Colors.white))),
      ] else ...[
        Text('Enter your PIN', textAlign: TextAlign.center, style: Sc.body(15, weight: 600, color: Colors.white)),
        const SizedBox(height: 16),
        Shake(trigger: _shake, child: PinDots(_pin.length, light: false, length: widget.session.pinLength)),
        const SizedBox(height: 24),
        Keypad(light: false, onDigit: _digit, onDelete: () => setState(() => _pin = _pin.isEmpty ? '' : _pin.substring(0, _pin.length - 1))),
        if (s.biometrics)
          TextButton(
              onPressed: () => setState(() => _usePin = false),
              child: Text('Use fingerprint instead', style: Sc.body(15, weight: 600, color: Colors.white))),
      ],
      const Spacer(),
      Text('5 wrong PINs sign this phone out. Then it is an SMS code again.',
          textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .85))),
      TextButton(
          onPressed: () => s.signOut(),
          child: Text('Not you? Sign out', style: Sc.body(13, color: Colors.white))),
    ]);
  }
}

// ---- Create my account (tenants added by their landlord) --------------------------------------------------------

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, required this.session});
  final Session session;
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _id = TextEditingController();
  final _password = TextEditingController();
  final _password2 = TextEditingController();
  bool _sent = false;
  bool _busy = false;
  bool _show = false;
  int? _account;

  String get _e164 {
    final d = _phone.text.replaceAll(RegExp(r'\D'), '');
    return d.length >= 9 ? '+254${d.substring(d.length - 9)}' : '';
  }

  Future<void> _sendCode() async {
    if (_e164.isEmpty) return toast(context, 'Enter your mobile number, like 0712 345 678.');
    setState(() => _busy = true);
    try {
      await widget.session.api.post('/auth/code', {'phone': _e164});
      setState(() => _sent = true);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _create() async {
    if (_password.text.length < 8) return toast(context, 'Use at least 8 characters for your password.');
    if (_password.text != _password2.text) return toast(context, 'The passwords do not match.');
    setState(() => _busy = true);
    try {
      final res = await widget.session.api.post('/auth/register', {
        'phone': _e164,
        'code': _code.text.trim(),
        'name': _name.text.trim(),
        if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
        if (_id.text.trim().isNotEmpty) 'id_number': _id.text.trim(),
        'password': _password.text,
        'password_confirmation': _password2.text,
        'device_name': _device,
        if (_account != null) 'account_id': _account,
      });
      if (!mounted) return;
      if (res['choose_account'] is List) {
        final picked = await Navigator.of(context).push<int>(MaterialPageRoute(
            builder: (_) => ChooseAccountScreen(accounts: (res['choose_account'] as List).cast<Map<String, dynamic>>())));
        if (picked != null) {
          _account = picked;
          await _create();
        }
        return;
      }
      Navigator.of(context).popUntil((r) => r.isFirst); // the root now shows Create PIN
      await widget.session.signedInWith(res);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.status == 404) {
        await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const _NotAddedScreen()));
      } else {
        toast(context, e);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: ListView(padding: const EdgeInsets.fromLTRB(12, 0, 12, 24), children: [
          Text('Create my account', style: Sc.title(20)),
          const SizedBox(height: 8),
          Text('Use the phone number your landlord or manager has for you. One password works with your phone or your email.',
              maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
          const SizedBox(height: 20),
          TextField(
            controller: _phone,
            enabled: !_sent,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'Phone number', prefixText: '+254 ', hintText: '712 345 678'),
          ),
          const SizedBox(height: 12),
          if (!_sent)
            FilledButton(onPressed: _busy ? null : _sendCode, child: Text(_busy ? 'Sending…' : 'Send code'))
          else
            Appear(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                TextField(
                  controller: _code,
                  keyboardType: TextInputType.number,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                  decoration: const InputDecoration(labelText: '6-digit code from the SMS'),
                ),
                const SizedBox(height: 12),
                TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Full name (as on your ID)')),
                const SizedBox(height: 12),
                TextField(controller: _id, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'National ID or passport number')),
                const SizedBox(height: 12),
                TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email (optional)')),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  obscureText: !_show,
                  decoration: InputDecoration(
                    labelText: 'Password (8 or more characters)',
                    suffixIcon: IconButton(
                        tooltip: _show ? 'Hide' : 'Show', icon: Icon(_show ? PhosphorIconsLight.eyeSlash : PhosphorIconsLight.eye), onPressed: () => setState(() => _show = !_show)),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(controller: _password2, obscureText: !_show, decoration: const InputDecoration(labelText: 'Password again')),
                const SizedBox(height: 8),
                Text('Next you choose a 4- or 6-digit PIN for opening the app and confirming payments.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                const SizedBox(height: 16),
                FilledButton(onPressed: _busy ? null : _create, child: Text(_busy ? 'Creating…' : 'Create my account')),
                TextButton(onPressed: _busy ? null : () => setState(() => _sent = false), child: const Text('Change the number')),
              ]),
            ),
        ]),
      );
}

class _NotAddedScreen extends StatelessWidget {
  const _NotAddedScreen();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Container(
              width: 96,
              height: 96,
              decoration: const BoxDecoration(color: Sc.blush, shape: BoxShape.circle),
              child: Icon(PhosphorIconsLight.userFocus, size: 44, color: Sc.brand),
            ),
            const SizedBox(height: 18),
            Text('Your landlord has not added you yet', textAlign: TextAlign.center, style: Sc.title(17)),
            const SizedBox(height: 10),
            Text('Ask your landlord or manager to add you on scharge with this phone number. Then come back and create your account.',
                textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
            const SizedBox(height: 24),
            FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('OK')),
          ]),
        ),
      );
}
