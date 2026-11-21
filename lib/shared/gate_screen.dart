import 'package:flutter/material.dart';
import 'ph.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/session.dart';
import '../core/theme.dart';
import 'documents.dart';
import 'motion.dart';
import 'ui.dart';

/// Gate & visitors for everyone the server allows (canvas: tenant "Gate pass", host passes, "Security guard").
/// Guards type a guest's code and let them in or out; tenants and hosts send codes for their own units.
class GateScreen extends StatefulWidget {
  const GateScreen({super.key, required this.session});
  final Session session;
  @override
  State<GateScreen> createState() => _GateScreenState();
}

class _GateScreenState extends State<GateScreen> {
  String _tab = 'expected';
  Key _k = UniqueKey();
  void _reload() => setState(() => _k = UniqueKey());

  AppScreen? get _s => widget.session.screen('gate');
  bool get _guard => _s?.can('verify') == true && _s?.can('check_in') == true && !widget.session.roles.contains('tenant');

  Future<void> _typeCode() async {
    final code = await showDialog<String>(context: context, builder: (_) => const _CodeDialog());
    if (code == null || !mounted) return;
    try {
      final pass = await widget.session.api.post('/gate/verify', {'code': code});
      if (!mounted) return;
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PassScreen(session: widget.session, pass: pass)));
      _reload();
    } catch (e) {
      if (mounted) toast(context, e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        floatingActionButton: _s?.can('issue') == true && !_guard
            ? FloatingActionButton.extended(
                backgroundColor: Sc.brand,
                foregroundColor: Colors.white,
                icon: const Icon(PhosphorIconsLight.plus),
                label: const Text('New gate pass'),
                onPressed: () async {
                  await Navigator.of(context).push(MaterialPageRoute(builder: (_) => IssuePassScreen(session: widget.session)));
                  _reload();
                },
              )
            : null,
        body: Loader<Map<String, dynamic>>(
          key: _k,
          load: () => widget.session.api.get('/gate/passes', {'tab': _tab}),
          builder: (context, d, reload) {
            final rows = ((d['data'] as List?) ?? []).cast<Map<String, dynamic>>();
            return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 96), children: [
              Text(_guard ? 'Gate today' : 'Visitors', style: Sc.title(18)),
              const SizedBox(height: 12),
              if (_guard) ...[
                FilledButton.icon(onPressed: _typeCode, icon: const Icon(PhosphorIconsLight.password), label: const Text("Type a guest's code")),
                const SizedBox(height: 8),
                if (_s?.can('issue') == true)
                  OutlinedButton(
                    onPressed: () async {
                      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => IssuePassScreen(session: widget.session, walkIn: true)));
                      _reload();
                    },
                    child: const Text('Walk-in visitor'),
                  ),
                const SizedBox(height: 12),
              ],
              PillTabs<String>(
                options: [('expected', 'Expected'), ('inside', 'Inside now'), if (_s?.can('approve') == true) ('approve', 'To approve'), ('history', 'History')],
                selected: _tab,
                onSelect: (t) => setState(() {
                  _tab = t;
                  _k = UniqueKey();
                }),
              ),
              const SizedBox(height: 10),
              if (rows.isEmpty)
                switch (_tab) {
                  'expected' => EmptyPanel(
                      icon: PhosphorIconsLight.identificationBadge,
                      title: 'No guests expected',
                      text: 'Expecting someone? Send them a gate pass. They get a code and a QR ticket, and the guard lets them in.',
                      action: _s?.can('issue') == true && !_guard ? 'New gate pass' : null,
                      onAction: () async {
                        await Navigator.of(context).push(MaterialPageRoute(builder: (_) => IssuePassScreen(session: widget.session)));
                        _reload();
                      },
                    ),
                  'inside' => const EmptyPanel(icon: PhosphorIconsLight.door, title: 'Nobody inside', text: 'Guests show here once the guard lets them in.'),
                  'approve' => const EmptyPanel(icon: PhosphorIconsLight.userCheck, title: 'Nothing to approve', text: 'When an owner suggests a guest for your home, you approve them here.'),
                  _ => const EmptyPanel(icon: PhosphorIconsLight.clockCounterClockwise, title: 'No history yet', text: 'Past visits show here.'),
                },
              for (final p in rows)
                Tile(
                  onTap: () async {
                    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PassScreen(session: widget.session, pass: p)));
                    _reload();
                  },
                  child: Row(children: [
                    Avatar(p['guest_name'] as String?, size: 40),
                    const SizedBox(width: 12),
                    Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${p['guest_name']}', style: Sc.body(14, weight: 600)),
                      Text('${p['unit'] ?? ''} · ${p['kind']} · ${p['status'] == 'checked_in' ? 'in ${dateTime(p['checked_in_at'] as String?)}' : dateTime(p['valid_from'] as String?)}',
                          maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                    ])),
                    if (p['is_inside'] == true) const Pill('Inside', tone: 'brand'),
                    if (p['status'] == 'suggested') const Pill('Waiting for approval'),
                  ]),
                ),
            ]);
          },
        ),
      );
}

class _CodeDialog extends StatefulWidget {
  const _CodeDialog();
  @override
  State<_CodeDialog> createState() => _CodeDialogState();
}

class _CodeDialogState extends State<_CodeDialog> {
  final _c = TextEditingController();
  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text("Guest's code"),
        content: TextField(
          controller: _c,
          autofocus: true,
          keyboardType: TextInputType.number,
          textAlign: TextAlign.center,
          style: Sc.head(26),
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(7)],
          decoration: const InputDecoration(hintText: '6 digits'),
          onSubmitted: (v) => Navigator.of(context).pop(v),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
          FilledButton(
              style: FilledButton.styleFrom(minimumSize: const Size(100, 44)),
              onPressed: () => Navigator.of(context).pop(_c.text),
              child: const Text('Find')),
        ],
      );
}

class PassScreen extends StatefulWidget {
  const PassScreen({super.key, required this.session, required this.pass});
  final Session session;
  final Map<String, dynamic> pass;
  @override
  State<PassScreen> createState() => _PassScreenState();
}

class _PassScreenState extends State<PassScreen> {
  late Map<String, dynamic> p = widget.pass;
  final _ticket = GlobalKey();
  bool _busy = false;

  Future<void> _act(String action) async {
    setState(() => _busy = true);
    try {
      final r = await widget.session.api.post('/gate/passes/${p['id']}', {'action': action});
      setState(() => p = r);
      if (mounted) {
        toast(context, {'checkin': 'Let in.', 'checkout': 'Let out.', 'stepout': 'Stepped out.', 'cancel': 'Pass cancelled.',
          'approve': 'Approved. The guest gets the code.', 'decline': 'Declined.'}[action]!);
      }
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.session.screen('gate');
    final roles = widget.session.roles;
    final guard = s?.can('check_in') == true && !(roles.contains('tenant') && roles.length == 1);
    final open = p['status'] == 'issued' || p['status'] == 'checked_in';
    return Scaffold(
      appBar: AppBar(),
      body: ListView(padding: const EdgeInsets.fromLTRB(12, 0, 12, 24), children: [
        Text('Gate pass', style: Sc.body(18, weight: 600)),
        const SizedBox(height: 18),
        Appear(child: PassTicket(pass: p, boundaryKey: _ticket)),
        const SizedBox(height: 14),
        if (p['vehicle_plate'] != null)
          Padding(padding: const EdgeInsets.symmetric(vertical: 10), child: Row(children: [Expanded(child: Text('Car: ${p['vehicle_plate']}', style: Sc.body(14))), Icon(PhosphorIconsFill.checkSquare, color: Sc.brand)])),
        if (p['guest_email'] != null) Padding(padding: const EdgeInsets.only(bottom: 6), child: Text('Sent to ${p['guest_email']}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted))),
        const SizedBox(height: 12),
        Wrap(spacing: 6, children: [
          Pill('${p['status']}'.replaceAll('_', ' '), tone: p['status'] == 'cancelled' ? 'alert' : 'neutral'),
          if (p['is_inside'] == true) const Pill('Inside now', tone: 'brand'),
        ]),
        const SizedBox(height: 24),
        if (guard && p['status'] == 'issued' || guard && p['status'] == 'checked_in' && p['is_inside'] != true)
          FilledButton(onPressed: _busy ? null : () => _act('checkin'), child: const Text('Let in')),
        if (guard && p['is_inside'] == true) ...[
          FilledButton(onPressed: _busy ? null : () => _act('checkout'), child: const Text('Let out (leaving for good)')),
          const SizedBox(height: 8),
          if (p['kind'] == 'stay') OutlinedButton(onPressed: _busy ? null : () => _act('stepout'), child: const Text('Stepping out, coming back')),
        ],
        if (open && p['status'] != 'suggested') ...[
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () => shareOnWhatsApp(context, _passText(p), phone: p['guest_phone'] as String?),
            child: const Text('Share on WhatsApp'),
          ),
          const SizedBox(height: 8),
          Row(children: [
            Expanded(
              child: OutlinedButton.icon(
                icon: const Icon(PhosphorIconsLight.copy, size: 18),
                label: const Text('Copy code'),
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: '${p['code']}'));
                  if (context.mounted) toast(context, 'Code copied.');
                },
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton.icon(
                icon: const Icon(PhosphorIconsLight.qrCode, size: 18),
                label: const Text('Share ticket'),
                onPressed: () => shareAsImage(context, _ticket, 'gate-pass-${p['code']}', _passText(p)),
              ),
            ),
          ]),
          const SizedBox(height: 4),
          if (!guard)
            TextButton.icon(
              icon: const Icon(PhosphorIconsLight.paperPlaneTilt),
              label: Text(p['guest_email'] != null ? 'Send the code by email' : 'Send the code by SMS'),
              onPressed: () => sharePass(context, p),
            ),
        ],
        if (p['status'] == 'suggested') ...[
          const Notice('Waiting for approval', 'The person living in this unit approves it. Then the guest gets the code.'),
          if (s?.can('approve') == true && '${p['created_by']}' != '${widget.session.user['id']}') ...[
            FilledButton(onPressed: _busy ? null : () => _act('approve'), child: const Text('Approve and send the code')),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: _busy ? null : () => _act('decline'), child: const Text('Decline')),
          ],
        ],
        if ((p['status'] == 'issued' || p['status'] == 'suggested') && (s?.can('issue') ?? false))
          OutlinedButton(onPressed: _busy ? null : () => _act('cancel'), child: const Text('Cancel pass')),
      ]),
    );
  }
}

Future<void> sharePass(BuildContext context, Map<String, dynamic> p) async {
  final digits = '${p['guest_phone'] ?? ''}'.replaceAll(RegExp(r'\D'), '');
  final text = 'Your gate code for ${p['property'] ?? ''} ${p['unit'] ?? ''} is ${p['code']}. Show it at the gate.';
  final email = p['guest_email'] as String?;
  final uri = email != null && email.isNotEmpty
      ? Uri.parse('mailto:$email?subject=${Uri.encodeComponent('Your gate code')}&body=${Uri.encodeComponent(text)}')
      : Uri.parse('sms:$digits?body=${Uri.encodeComponent(text)}');
  final ok = await launchUrl(uri);
  if (!ok && context.mounted) toast(context, 'Could not open SMS.');
}

class IssuePassScreen extends StatefulWidget {
  const IssuePassScreen({super.key, required this.session, this.walkIn = false});
  final Session session;
  final bool walkIn;
  @override
  State<IssuePassScreen> createState() => _IssuePassScreenState();
}

class _IssuePassScreenState extends State<IssuePassScreen> {
  static const _kinds = {'visitor': 'Visitor', 'stay': 'Staying guest', 'delivery': 'Delivery', 'contractor': 'Fundi / contractor'};
  late String _kind = widget.session.roles.contains('bnb_host') ? 'stay' : 'visitor';
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _plate = TextEditingController();
  bool _byEmail = true;
  bool _bySms = false;
  int _guests = 1;
  int? _unit;
  bool _busy = false;

  Future<void> _send(List<Map<String, dynamic>> units) async {
    final unit = _unit ?? (units.length == 1 ? units.first['id'] as int : null);
    if (unit == null) return toast(context, 'Pick the unit.');
    if (_name.text.trim().isEmpty) return toast(context, "Add the guest's name.");
    if (_email.text.trim().isEmpty && _phone.text.trim().isEmpty) return toast(context, "Add the guest's email or phone.");
    setState(() => _busy = true);
    try {
      var pass = await widget.session.api.post('/gate/passes', {
        'unit_id': unit,
        'kind': _kind,
        'guest_name': _name.text.trim(),
        if (_phone.text.trim().isNotEmpty) 'guest_phone': _phone.text.trim(),
        if (_email.text.trim().isNotEmpty) 'guest_email': _email.text.trim(),
        'send_email': _byEmail,
        'send_sms': _bySms || _email.text.trim().isEmpty,
        'guests_count': _guests,
        if (_plate.text.trim().isNotEmpty) 'vehicle_plate': _plate.text.trim(),
      });
      if (widget.walkIn) pass = await widget.session.api.post('/gate/passes/${pass['id']}', {'action': 'checkin'});
      if (!mounted) return;
      if (pass['needs_approval'] == true) toast(context, 'Suggested. The person living there approves it, then the guest gets the code.');
      await Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => PassScreen(session: widget.session, pass: pass)));
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.walkIn ? 'Walk-in visitor' : 'New gate pass')),
        body: Loader<Map<String, dynamic>>(
          load: () => widget.session.api.get('/gate/passes', {'tab': 'expected'}),
          builder: (context, d, _) {
            final units = ((d['units'] as List?) ?? []).cast<Map<String, dynamic>>();
            return ListView(padding: const EdgeInsets.all(16), children: [
              if (units.length > 1)
                DropdownButtonFormField<int>(
                  initialValue: _unit,
                  decoration: const InputDecoration(labelText: 'Unit'),
                  items: [
                    for (final u in units)
                      DropdownMenuItem(
                          value: u['id'] as int,
                          child: Text('${u['number']} · ${u['property'] ?? ''}${u['home'] == true ? ' (my home)' : ' (suggest only)'}')),
                  ],
                  onChanged: (v) => setState(() => _unit = v),
                ),
              const SizedBox(height: 14),
              PillTabs<String>(options: [for (final k in _kinds.entries) (k.key, k.value)], selected: _kind, onSelect: (k) => setState(() => _kind = k)),
              const SizedBox(height: 14),
              TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: "Guest's name")),
              const SizedBox(height: 12),
              TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: "Guest's email")),
              const SizedBox(height: 12),
              TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: "Guest's phone", hintText: '0712 345 678')),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('Send the code by email', style: Sc.body(15)),
                value: _byEmail,
                onChanged: (v) => setState(() => _byEmail = v),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('Also send it by SMS', style: Sc.body(15)),
                subtitle: Text('Always by SMS when there is no email.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                value: _bySms,
                onChanged: (v) => setState(() => _bySms = v),
              ),
              const SizedBox(height: 12),
              TextField(controller: _plate, textCapitalization: TextCapitalization.characters, decoration: const InputDecoration(labelText: 'Car plate (optional)')),
              const SizedBox(height: 8),
              Row(children: [
                Expanded(child: Text('Guests', style: Sc.body(15))),
                IconButton(onPressed: _guests > 1 ? () => setState(() => _guests--) : null, icon: const Icon(PhosphorIconsLight.minusCircle), tooltip: 'Fewer'),
                Text('$_guests', style: Sc.body(16, weight: 600)),
                IconButton(onPressed: () => setState(() => _guests++), icon: const Icon(PhosphorIconsLight.plusCircle), tooltip: 'More'),
              ]),
              const SizedBox(height: 8),
              Text(widget.walkIn ? 'The guest is let in now and gets the code too.' : 'The guard checks the code at the gate.',
                  maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
              const SizedBox(height: 16),
              FilledButton(onPressed: _busy ? null : () => _send(units), child: Text(_busy ? 'Sending…' : (widget.walkIn ? 'Let in' : 'Send code'))),
            ]);
          },
        ),
      );
}

String _passText(Map p) => 'Hello ${'${p['guest_name']}'.split(' ').first}, your gate code for ${p['property'] ?? ''} ${p['unit'] ?? ''} is ${p['code']}. '
    'Valid ${dateTime(p['valid_from'] as String?)} to ${dateTime(p['valid_until'] as String?)}. Show it at the gate.'
    '${p['pass_url'] != null ? ' Your pass: ${p['pass_url']}' : ''}';
