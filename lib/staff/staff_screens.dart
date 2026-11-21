import 'dart:io';

import 'package:flutter/material.dart';
import '../shared/ph.dart';
import 'package:image_picker/image_picker.dart';

import '../core/location.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../shared/documents.dart';
import '../shared/motion.dart';
import '../shared/ui.dart';
import '../tenant/tenant_screens.dart';

List<Map<String, dynamic>> _rows(Map<String, dynamic> d) => ((d['data'] as List?) ?? []).cast<Map<String, dynamic>>();

Widget _title(String text, [String? sub]) => Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (sub != null) Text(sub, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
        Text(text, style: Sc.title(18)),
      ]),
    );

// ---- Rent (canvas: manager "October so far · COLLECTED") ---------------------------------------------------------

class RentScreen extends StatelessWidget {
  const RentScreen({super.key, required this.session, required this.openTab});
  final Session session;
  final void Function(String key) openTab;

  @override
  Widget build(BuildContext context) => Loader<List<Map<String, dynamic>>>(
        load: () => Future.wait([session.api.get('/rent'), session.api.get('/paybill/unmatched')]),
        builder: (context, d, reload) {
          final r = d[0];
          final unmatched = _rows(d[1]);
          final canMatch = session.screen('rent')?.can('match_paybill') == true;
          final billed = double.tryParse('${r['billed']}') ?? 0;
          final paid = double.tryParse('${r['paid_of_billed']}') ?? 0;
          return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
            _title('${r['month']} so far', '${session.firstName} · ${r['units']} units'),
            HeroCard(accent: true, children: [
              Text('COLLECTED', style: Sc.body(12, color: Sc.onAccent.withValues(alpha: .85))),
              const SizedBox(height: 6),
              CountUp(value: double.tryParse('${r['collected']}') ?? 0, format: kes, style: Sc.head(24, color: Sc.onAccent)),
              const SizedBox(height: 4),
              Text('Billed this month ${kes(billed)} · ${billed > 0 ? (paid / billed * 100).round() : 0}% paid',
                  style: Sc.body(13, color: Sc.onAccent.withValues(alpha: .9))),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                    value: billed > 0 ? (paid / billed).clamp(0, 1) : 0, minHeight: 8, color: Sc.onAccent, backgroundColor: Sc.onAccent.withValues(alpha: .25)),
              ),
            ]),
            const SizedBox(height: 12),
            Row(children: [
              _stat(kes(r['outstanding'], symbol: false), 'Arrears (KES)', () => openTab('bills')),
              _stat('${r['unpaid_bills']}', 'Unpaid bills', () => openTab('bills')),
              _stat('${(r['units'] ?? 0) - (r['occupied_units'] ?? 0)}', 'Vacant', () => openTab('units')),
            ]),
            if (unmatched.isNotEmpty) ...[
              SectionTitle('Paybill payments to match (${unmatched.length})'),
              const Notice('These came in without a unit we know', 'Give each one to the right tenant so their bill is paid.', alert: true),
              for (final p in unmatched)
                Tile(
                  child: Row(children: [
                    Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(kes(p['amount']), style: Sc.body(15, weight: 600)),
                      Text('${p['payer'] ?? p['phone'] ?? ''} · account "${p['account'] ?? ''}" · ${shortDate(p['paid_at'] as String?)}',
                          maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                    ])),
                    if (canMatch)
                      TextButton(
                        onPressed: () async {
                          final tenancy = await pickTenant(context, session);
                          if (tenancy == null) return;
                          try {
                            await session.api.post('/paybill/${p['id']}/assign', {'tenancy_id': tenancy});
                            if (context.mounted) toast(context, 'Recorded for the tenant.');
                            await reload();
                          } catch (e) {
                            if (context.mounted) toast(context, e);
                          }
                        },
                        child: const Text('Give to tenant'),
                      ),
                  ]),
                ),
            ],
          ]);
        },
      );

  Widget _stat(String value, String label, VoidCallback onTap) => Expanded(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4),
          child: Material(
            color: Sc.accentSoft,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: onTap,
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  FittedBox(child: Text(value, style: Sc.head(18))),
                  const SizedBox(height: 2),
                  Text(label, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                ]),
              ),
            ),
          ),
        ),
      );
}

/// Choose a tenant (for matching a paybill payment). Returns the tenancy id.
Future<int?> pickTenant(BuildContext context, Session session) => showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SizedBox(
        height: MediaQuery.of(context).size.height * .8,
        child: SearchList(
          session: session,
          path: '/tenants',
          hint: 'Name, phone or unit',
          item: (t) => ListTile(
            leading: Avatar(t['name'] as String?, size: 40),
            title: Text('${t['name']}'),
            subtitle: Text('${t['unit']} · ${t['property']}'),
            onTap: () => Navigator.of(context).pop(t['tenancy_id'] as int),
          ),
        ),
      ),
    );

/// A searchable, paged list from the server.
class SearchList extends StatefulWidget {
  const SearchList({super.key, required this.session, required this.path, required this.item, this.hint = 'Search', this.title, this.query = const {}});
  final Session session;
  final String path;
  final String hint;
  final String? title;
  final Map<String, dynamic> query;
  final Widget Function(Map<String, dynamic>) item;
  @override
  State<SearchList> createState() => _SearchListState();
}

class _SearchListState extends State<SearchList> {
  String _q = '';
  Key _k = UniqueKey();
  @override
  Widget build(BuildContext context) => Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (widget.title != null) _title(widget.title!),
            TextField(
              decoration: InputDecoration(prefixIcon: const Icon(PhosphorIconsLight.magnifyingGlass), hintText: widget.hint),
              textInputAction: TextInputAction.search,
              onSubmitted: (v) => setState(() {
                _q = v.trim();
                _k = UniqueKey();
              }),
            ),
          ]),
        ),
        Expanded(
          child: Loader<Map<String, dynamic>>(
            key: _k,
            load: () => widget.session.api.get(widget.path, {...widget.query, 'q': _q, 'per_page': 50}),
            builder: (context, d, _) {
              final rows = _rows(d);
              return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
                if (rows.isEmpty) const EmptyState('Nothing found.'),
                for (final r in rows) widget.item(r),
                if ((d['total'] ?? 0) > rows.length) Text('Showing ${rows.length} of ${d['total']}. Search to narrow it down.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
              ]);
            },
          ),
        ),
      ]);
}

// ---- Unpaid bills (canvas: "Arrears · 11 tenants") --------------------------------------------------------------

class ArrearsScreen extends StatelessWidget {
  const ArrearsScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => SearchList(
        session: session,
        path: '/bills',
        title: 'Unpaid bills',
        hint: 'Tenant, phone or unit',
        item: (b) => BillTile(
          bill: b,
          showTenant: true,
          onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => BillScreen(session: session, id: b['id'] as int, staff: true))),
        ),
      );
}

class PaymentsScreen extends StatelessWidget {
  const PaymentsScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => Loader<Map<String, dynamic>>(
        load: () => session.api.get('/payments', {'per_page': 50}),
        builder: (context, d, _) {
          final rows = _rows(d);
          final today = DateTime.now();
          final todayTotal = rows.where((p) {
            final t = DateTime.tryParse('${p['paid_at']}')?.toLocal();
            return t != null && t.year == today.year && t.month == today.month && t.day == today.day;
          }).fold<double>(0, (t, p) => t + (double.tryParse('${p['amount']}') ?? 0));
          return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
            _title('Payments'),
            HeroCard(accent: true, children: [
              Text('RECEIVED TODAY', style: Sc.body(12, color: Sc.onAccent.withValues(alpha: .85))),
              const SizedBox(height: 6),
              CountUp(value: todayTotal, format: kes, style: Sc.head(26, color: Sc.onAccent)),
              Text('${d['total'] ?? rows.length} payments in all · tap one for its receipt', style: Sc.body(13, color: Sc.onAccent.withValues(alpha: .9))),
            ]),
            const SizedBox(height: 14),
            if (rows.isEmpty) const EmptyState('No payments yet.'),
            for (final (i, p) in rows.indexed) PaymentRow(payment: p, session: session, order: i, showPayer: true),
          ]);
        },
      );
}

class SuggestionsScreen extends StatelessWidget {
  const SuggestionsScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => Loader<Map<String, dynamic>>(
        load: () => session.api.get('/suggestions', {'per_page': 50}),
        builder: (context, d, _) => ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
          _title('Suggestions', 'From tenants, anonymous'),
          if (_rows(d).isEmpty) const EmptyState('No suggestions yet. Tenants send them from the app without their name.'),
          for (final s in _rows(d))
            KeyedSubtree(child: Tile(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Pill('${s['topic']}'),
                    if (s['new'] == true) ...[const SizedBox(width: 6), const Pill('New', tone: 'brand')],
                    const Spacer(),
                    Text(shortDate(s['sent_at'] as String?), maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                  ]),
                  const SizedBox(height: 8),
                  Text('${s['body']}', style: Sc.body(15)),
                ]),
              ),
            ),
        ]),
      );
}

class TenantsScreen extends StatelessWidget {
  const TenantsScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => SearchList(
        session: session,
        path: '/tenants',
        title: 'Tenants',
        hint: 'Name, phone or unit',
        item: (t) => Tile(
          child: Row(children: [
            Avatar(t['name'] as String?, size: 40),
            const SizedBox(width: 12),
            Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${t['name'] ?? ''}', style: Sc.body(15, weight: 600)),
              Text('${t['unit']} · ${t['property']} · ${t['phone'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            ])),
            if ((double.tryParse('${t['balance']}') ?? 0) > 0) Pill('Owes ${kes(t['balance'])}', tone: 'alert') else const Pill('Paid up', tone: 'ok'),
          ]),
        ),
      );
}

class UnitsScreen extends StatelessWidget {
  const UnitsScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => SearchList(
        session: session,
        path: '/units',
        title: 'Units',
        hint: 'Unit number',
        item: (u) => Tile(
          child: Row(children: [
            Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${u['number']} · ${u['property'] ?? ''}', style: Sc.body(15, weight: 600)),
              Text('${u['type'] ?? ''} · ${kes(u['rent'])} a month', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            ])),
            Pill('${u['status'] ?? ''}'.replaceAll('_', ' '), tone: u['status'] == 'vacant' ? 'alert' : 'neutral'),
          ]),
        ),
      );
}

class CompaniesScreen extends StatelessWidget {
  const CompaniesScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => SearchList(
        session: session,
        path: '/companies',
        title: 'Companies',
        hint: 'Company name',
        item: (c) => Tile(
          child: Row(children: [
            Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${c['name']}', style: Sc.body(15, weight: 600)),
              Text([c['phone'], c['email']].where((v) => v != null).join(' · '), maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            ])),
            Pill(c['active'] == true ? 'Active' : 'Off', tone: c['active'] == true ? 'ok' : 'alert'),
          ]),
        ),
      );
}

// ---- Meter reader (canvas: route -> capture -> anomaly) ---------------------------------------------------------

class ReadingsScreen extends StatefulWidget {
  const ReadingsScreen({super.key, required this.session});
  final Session session;
  @override
  State<ReadingsScreen> createState() => _ReadingsScreenState();
}

class _ReadingsScreenState extends State<ReadingsScreen> {
  bool _dueOnly = true;
  Key _k = UniqueKey();
  @override
  Widget build(BuildContext context) {
    final canRecord = widget.session.screen('readings')?.can('record') == true;
    return Loader<Map<String, dynamic>>(
      key: _k,
      load: () => widget.session.api.get('/meters', {'due': _dueOnly ? 1 : ''}),
      builder: (context, d, reload) {
        final meters = _rows(d);
        return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
          _title('Meter readings', widget.session.brand.name),
          HeroCard(accent: true, children: [
            Text(_dueOnly ? 'STILL TO READ THIS MONTH' : 'ALL METERS', style: Sc.body(12, color: Sc.onAccent.withValues(alpha: .85))),
            const SizedBox(height: 6),
            Text('${meters.length}', style: Sc.head(24, color: Sc.onAccent)),
            Text('Read them so bills go out on time.', style: Sc.body(13, color: Sc.onAccent.withValues(alpha: .9))),
          ]),
          const SizedBox(height: 8),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text('Only meters not read this month', style: Sc.body(15)),
            value: _dueOnly,
            onChanged: (v) => setState(() {
              _dueOnly = v;
              _k = UniqueKey();
            }),
          ),
          if (meters.isEmpty) EmptyState(_dueOnly ? 'Route done. Every meter is read this month.' : 'No meters yet.'),
          for (final m in meters)
            Tile(
              onTap: canRecord
                  ? () async {
                      final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => CaptureScreen(session: widget.session, meter: m)));
                      if (saved == true) await reload();
                    }
                  : null,
              child: Row(children: [
                Icon(m['utility'] == 'electricity' ? PhosphorIconsLight.lightning : PhosphorIconsLight.drop, color: Sc.brand),
                const SizedBox(width: 12),
                Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${m['unit'] ?? 'Common'} · ${m['property'] ?? ''}', style: Sc.body(15, weight: 600)),
                  Text('${m['serial_number']} · last ${m['last_reading']} ${m['unit_of_measure'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                ])),
                if (m['due'] == true) const Pill('Due', tone: 'brand') else const Pill('Read', tone: 'ok'),
              ]),
            ),
        ]);
      },
    );
  }
}

class CaptureScreen extends StatefulWidget {
  const CaptureScreen({super.key, required this.session, required this.meter});
  final Session session;
  final Map<String, dynamic> meter;
  @override
  State<CaptureScreen> createState() => _CaptureScreenState();
}

class _CaptureScreenState extends State<CaptureScreen> {
  final _reading = TextEditingController();
  final _note = TextEditingController();
  File? _photo;
  DateTime? _capturedAt;
  bool _busy = false;
  late final String _ref = 'app-${widget.meter['id']}-${DateTime.now().millisecondsSinceEpoch}'; // safe retry
  Map<String, dynamic>? _result;

  Future<void> _snap() async {
    final x = await ImagePicker().pickImage(source: ImageSource.camera, maxWidth: 1600, imageQuality: 75);
    if (x != null) {
      setState(() {
        _photo = File(x.path);
        _capturedAt = DateTime.now();
      });
    }
  }

  Future<void> _save() async {
    final v = double.tryParse(_reading.text.replaceAll(',', '.'));
    if (v == null) return toast(context, 'Type the number on the meter.');
    if (_photo == null) return toast(context, 'Take a photo of the meter face first.');
    setState(() => _busy = true);
    try {
      final pos = await positionNow(context, 'Saving where you took this reading proves it was read at the meter.');
      final r = await widget.session.api.upload('/meters/${widget.meter['id']}/readings', {
        'reading': '$v',
        'client_ref': _ref,
        if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
        'captured_at': (_capturedAt ?? DateTime.now()).toUtc().toIso8601String(),
        ...positionFields(pos),
      }, file: _photo);
      setState(() => _result = r);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final m = widget.meter;
    final r = _result;
    return Scaffold(
      appBar: AppBar(title: Text('${m['unit'] ?? 'Meter'} · ${m['serial_number']}')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        if (r == null) ...[
          Material(
            color: Sc.blush,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: _snap,
              child: SizedBox(
                height: 220,
                child: _photo != null
                    ? ClipRRect(borderRadius: BorderRadius.circular(16), child: Image.file(_photo!, fit: BoxFit.cover, width: double.infinity))
                    : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Icon(PhosphorIconsLight.camera, size: 40, color: Sc.brand),
                        const SizedBox(height: 8),
                        Text('Photo of the meter face', style: Sc.body(15, weight: 600)),
                        Text('Fit the digits inside the frame', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                      ]),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('Last: ${m['last_reading']} ${m['unit_of_measure'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
          const SizedBox(height: 8),
          TextField(
            controller: _reading,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            style: Sc.head(22),
            decoration: InputDecoration(labelText: 'Reading (${m['unit_of_measure'] ?? ''})'),
          ),
          const SizedBox(height: 12),
          TextField(controller: _note, decoration: const InputDecoration(labelText: 'Note (optional)', hintText: 'Seal broken, no access…')),
          const SizedBox(height: 20),
          if (_photo != null) OutlinedButton(onPressed: _snap, child: const Text('Retake photo')),
          const SizedBox(height: 10),
          FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving…' : 'Save reading')),
        ] else ...[
          Icon(r['status'] == 'flagged' ? PhosphorIconsLight.warning : PhosphorIconsFill.checkCircle, size: 56, color: r['status'] == 'flagged' ? Sc.alert : Sc.ok),
          const SizedBox(height: 12),
          Text(r['status'] == 'flagged' ? 'Unusual reading' : 'Saved', textAlign: TextAlign.center, style: Sc.title(20)),
          const SizedBox(height: 8),
          Text(
              r['status'] == 'flagged'
                  ? (r['flag_reason'] == 'lower_than_previous'
                      ? 'This is lower than the last reading. The manager checks it before it is billed.'
                      : 'Much more than this unit usually uses. The bill waits for the manager to check it.')
                  : '${r['consumption']} used since the last reading${r['charge'] != null ? ' · ${kes(r['charge'])}' : ''}.',
              textAlign: TextAlign.center,
              maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
          const SizedBox(height: 10),
          Text(r['location'] != null ? 'Photo, time and location saved with the reading.' : 'Photo and time saved. Location was not shared.',
              textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
          const SizedBox(height: 24),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Next meter')),
        ],
      ]),
    );
  }
}

// ---- Work orders (canvas: "Work orders") ------------------------------------------------------------------------

class WorkOrdersScreen extends StatefulWidget {
  const WorkOrdersScreen({super.key, required this.session});
  final Session session;
  @override
  State<WorkOrdersScreen> createState() => _WorkOrdersScreenState();
}

class _WorkOrdersScreenState extends State<WorkOrdersScreen> {
  String _status = 'open';
  bool _mine = false;
  Key _k = UniqueKey();

  @override
  Widget build(BuildContext context) {
    final canUpdate = widget.session.screen('repairs')?.can('update') == true;
    return Loader<Map<String, dynamic>>(
      key: _k,
      load: () => widget.session.api.get('/repairs', {'status': _status, 'mine': _mine ? 1 : ''}),
      builder: (context, d, reload) => ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
        _title('Work orders'),
        PillTabs<String>(
          options: const [('open', 'Open'), ('completed', 'Done')],
          selected: _status,
          onSelect: (v) => setState(() {
            _status = v;
            _k = UniqueKey();
          }),
          multi: {
            'Mine': (_mine, () => setState(() {
                  _mine = !_mine;
                  _k = UniqueKey();
                })),
          },
        ),
        const SizedBox(height: 10),
        if (_rows(d).isEmpty) const EmptyState('No work orders here.'),
        for (final r in _rows(d))
          RepairTile(
            repair: r,
            onTap: canUpdate && _status == 'open'
                ? () async {
                    final changed = await showModalBottomSheet<bool>(
                        context: context, isScrollControlled: true, builder: (_) => _RepairSheet(session: widget.session, repair: r));
                    if (changed == true) await reload();
                  }
                : null,
          ),
      ]),
    );
  }
}

class _RepairSheet extends StatefulWidget {
  const _RepairSheet({required this.session, required this.repair});
  final Session session;
  final Map<String, dynamic> repair;
  @override
  State<_RepairSheet> createState() => _RepairSheetState();
}

class _RepairSheetState extends State<_RepairSheet> {
  final _notes = TextEditingController();
  final _cost = TextEditingController();
  bool _busy = false;

  /// "I've arrived" / "Finished": the visit with the phone's position at that moment (proof of being on site).
  Future<void> _visit(String kind) async {
    setState(() => _busy = true);
    try {
      final pos = await positionNow(context, 'Saving where you are when you arrive and finish shows the job was done on site.');
      final r = await widget.session.api.post('/repairs/${widget.repair['id']}/visit', {
        'kind': kind,
        if (pos != null) 'lat': pos.latitude,
        if (pos != null) 'lng': pos.longitude,
        if (pos != null) 'accuracy_m': pos.accuracy,
        if (_notes.text.trim().isNotEmpty) 'note': _notes.text.trim(),
      });
      final mins = (r['minutes_on_site'] as num?)?.round();
      if (!mounted) return;
      toast(context, kind == 'checkin' ? 'Arrival saved.' : 'Finished${mins != null ? ' after $mins min on site' : ''}.');
      if (kind == 'checkout') {
        await _set('completed');
      } else {
        Navigator.of(context).pop(true);
      }
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _set(String status) async {
    setState(() => _busy = true);
    try {
      await widget.session.api.post('/repairs/${widget.repair['id']}', {
        'status': status,
        if (_notes.text.trim().isNotEmpty) 'resolution_notes': _notes.text.trim(),
        if (_cost.text.trim().isNotEmpty) 'cost': double.tryParse(_cost.text.trim()),
        if (status == 'in_progress') 'assign_to_me': true,
      });
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.repair;
    return Padding(
      padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('#${r['number']} · ${r['unit'] ?? ''} ${r['property'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
        Text('${r['title']}', style: Sc.body(18, weight: 600)),
        const SizedBox(height: 6),
        Text('${r['description'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
        if (r['tenant'] != null) Text('Reported by ${r['tenant']}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
        const SizedBox(height: 16),
        TextField(controller: _notes, decoration: const InputDecoration(labelText: 'What was done (optional)')),
        const SizedBox(height: 10),
        TextField(controller: _cost, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Cost in KES (optional)')),
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: FilledButton.icon(onPressed: _busy ? null : () => _visit('checkin'), icon: const Icon(PhosphorIconsLight.mapPin), label: const Text("I've arrived"))),
          const SizedBox(width: 10),
          Expanded(child: OutlinedButton.icon(onPressed: _busy ? null : () => _visit('checkout'), icon: const Icon(PhosphorIconsLight.checkCircle), label: const Text('Finished'))),
        ]),
        const SizedBox(height: 8),
        if (r['status'] == 'open') OutlinedButton(onPressed: _busy ? null : () => _set('in_progress'), child: const Text('Start without arriving yet')),
        const SizedBox(height: 8),
        if (r['status'] != 'pending_parts') OutlinedButton(onPressed: _busy ? null : () => _set('pending_parts'), child: const Text('Waiting for parts')),
        if (r['status'] != 'open') ...[
          const SizedBox(height: 8),
          TextButton(onPressed: _busy ? null : () => _set('completed'), child: const Text('Mark done without a visit')),
        ],
      ]),
    );
  }
}

// ---- BnB host (canvas: calendar, arriving today) ----------------------------------------------------------------

class BookingsScreen extends StatelessWidget {
  const BookingsScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => Loader<Map<String, dynamic>>(
        load: () => session.api.get('/bookings'),
        builder: (context, d, _) {
          final rows = _rows(d);
          final today = DateTime.now();
          bool tonight(Map b) {
            final i = DateTime.tryParse('${b['check_in']}'), o = DateTime.tryParse('${b['check_out']}');
            return i != null && o != null && !i.isAfter(today) && o.isAfter(today);
          }

          return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
            _title('Bookings', '${(d['units'] as List?)?.length ?? 0} short-stay units'),
            HeroCard(accent: true, children: [
              Text('STAYING TONIGHT', style: Sc.body(12, color: Sc.onAccent.withValues(alpha: .85))),
              const SizedBox(height: 6),
              Text('${rows.where(tonight).length}', style: Sc.head(24, color: Sc.onAccent)),
              Text('${rows.length} upcoming stays', style: Sc.body(13, color: Sc.onAccent.withValues(alpha: .9))),
            ]),
            const SectionTitle('Next arrivals'),
            if (rows.isEmpty) const EmptyState('No upcoming stays.'),
            for (final b in rows)
              Tile(
                child: Row(children: [
                  Avatar(b['guest'] as String?, size: 40),
                  const SizedBox(width: 12),
                  Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${b['guest']} · ${b['guests'] ?? 1} guests', style: Sc.body(15, weight: 600)),
                    Text('${b['unit']} · ${shortDate(b['check_in'] as String?)} – ${shortDate(b['check_out'] as String?)} · ${b['nights']} nights',
                        maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                  ])),
                  Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Pill('${b['source']}', tone: tonight(b) ? 'brand' : 'neutral'),
                    if (b['amount'] != null) Text(kes(b['amount']), maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                  ]),
                ]),
              ),
          ]);
        },
      );
}
