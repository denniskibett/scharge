import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../shared/motion.dart';
import '../shared/ph.dart';
import '../shared/ui.dart';

// The CRM for scharge's sales people (sysadmin, account managers, the "sales" role), mirroring the web CRM: My day,
// Pipeline, Follow-ups, Sales tracker (with commission), Requests and Waitlist. The server decides what each person
// sees (their own book plus unassigned leads; sysadmins everything) and which buttons they get (screen actions);
// every call is checked again server-side (routes/api.php, CrmController).

List<Map<String, dynamic>> _rows(dynamic d) => ((d is Map ? d['data'] : d) as List? ?? []).cast<Map<String, dynamic>>();
List<Map<String, dynamic>> _list(dynamic v) => ((v as List?) ?? []).cast<Map<String, dynamic>>();

Widget _title(String text, [String? sub]) => Padding(
  padding: const EdgeInsets.only(bottom: 12),
  child: Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      if (sub != null)
        Text(
          sub,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: Sc.body(9.5, color: Sc.muted),
        ),
      Text(text, style: Sc.title(18)),
    ],
  ),
);

String _when(String? iso) {
  if (iso == null) return 'No date';
  final d = DateTime.tryParse(iso)?.toLocal();
  if (d == null) return iso;
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final day = DateTime(d.year, d.month, d.day);
  final diff = day.difference(today).inDays;
  final hm = '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
  if (diff == 0) return 'Today $hm';
  if (diff == -1) return 'Yesterday $hm';
  if (diff == 1) return 'Tomorrow $hm';
  return dateTime(iso);
}

Future<void> _open(BuildContext context, String uri) async {
  try {
    if (!await launchUrl(Uri.parse(uri), mode: LaunchMode.externalApplication) && context.mounted) toast(context, 'Could not open $uri');
  } catch (_) {
    if (context.mounted) toast(context, 'Could not open $uri');
  }
}

/// Date then time; null if cancelled.
Future<DateTime?> pickDateTime(BuildContext context, {DateTime? initial}) async {
  final now = DateTime.now();
  final start = initial ?? now.add(const Duration(days: 1));
  final d = await showDatePicker(
    context: context,
    initialDate: start,
    firstDate: now.subtract(const Duration(days: 1)),
    lastDate: now.add(const Duration(days: 365)),
  );
  if (d == null || !context.mounted) return null;
  final t = await showTimePicker(context: context, initialTime: const TimeOfDay(hour: 10, minute: 0));
  if (t == null) return null;
  return DateTime(d.year, d.month, d.day, t.hour, t.minute);
}

String _iso(DateTime d) => d.toIso8601String();

Widget _stat(String value, String label, {VoidCallback? onTap, bool alert = false}) => Expanded(
  child: Padding(
    padding: const EdgeInsets.symmetric(horizontal: 4),
    child: Material(
      color: alert ? Sc.blush2 : Sc.accentSoft,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              FittedBox(
                child: Text(value, style: Sc.head(18, color: alert ? Sc.alert : null)),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Sc.body(9.5, color: Sc.muted),
              ),
            ],
          ),
        ),
      ),
    ),
  ),
);

String _stageTone(String? stage) => switch (stage) {
  'won' => 'ok',
  'lost' => 'alert',
  'proposal' || 'negotiation' => 'brand',
  _ => 'neutral',
};

// ---- My day -------------------------------------------------------------------------------------------------------

class CrmTodayScreen extends StatelessWidget {
  const CrmTodayScreen({super.key, required this.session, required this.openTab});
  final Session session;
  final void Function(String key) openTab;

  @override
  Widget build(BuildContext context) => Loader<Map<String, dynamic>>(
    load: () => session.api.get('/crm/summary'),
    builder: (context, d, reload) {
      final st = (d['stats'] as Map?)?.cast<String, dynamic>() ?? {};
      final replies = _list(d['replies']);
      final due = _list(d['due']);
      final canUpdate = session.screen('crm_follow_ups')?.can('update') == true;
      return ListView(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
        children: [
          _title('Karibu, ${session.firstName}', d['sees_all'] == true ? 'Whole pipeline' : 'Your book and unassigned leads'),
          HeroCard(
            accent: true,
            children: [
              Text('NEEDS YOU TODAY', style: Sc.body(12, color: Sc.onAccent.withValues(alpha: .85))),
              const SizedBox(height: 6),
              Text('${(st['replied'] ?? 0) + (st['overdue'] ?? 0) + (st['due_today'] ?? 0)}', style: Sc.head(24, color: Sc.onAccent)),
              Text(
                '${st['replied'] ?? 0} replies · ${st['overdue'] ?? 0} overdue · ${st['due_today'] ?? 0} due today',
                style: Sc.body(13, color: Sc.onAccent.withValues(alpha: .9)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _stat('${st['open'] ?? 0}', 'Open deals', onTap: () => openTab('crm_pipeline')),
              _stat('${st['unattended'] ?? 0}', 'No next step', onTap: () => openTab('crm_pipeline'), alert: (st['unattended'] ?? 0) > 0),
              _stat('${st['new_requests'] ?? 0}', 'New requests', onTap: () => openTab('crm_requests')),
            ],
          ),
          if (replies.isNotEmpty) ...[
            SectionTitle('Replied, waiting for you (${replies.length})', icon: PhosphorIconsLight.envelope),
            for (final (i, p) in replies.indexed)
              Appear(
                order: i,
                child: ProspectTile(session: session, p: p, onChanged: reload),
              ),
          ],
          SectionTitle('Due today and overdue', icon: PhosphorIconsLight.clockCounterClockwise),
          if (due.isEmpty) const EmptyState('Nothing due. Book next steps on your open deals so none go cold.'),
          for (final f in due) FollowUpTile(session: session, f: f, canUpdate: canUpdate, onChanged: reload),
        ],
      );
    },
  );
}

// ---- Pipeline -----------------------------------------------------------------------------------------------------

class PipelineScreen extends StatefulWidget {
  const PipelineScreen({super.key, required this.session});
  final Session session;
  @override
  State<PipelineScreen> createState() => _PipelineScreenState();
}

class _PipelineScreenState extends State<PipelineScreen> {
  String _q = '';
  String _attention = '';
  String _stage = 'open';
  bool _mine = false;
  int _page = 1;
  final _items = <Map<String, dynamic>>[];
  int _total = 0;
  int _last = 1;
  bool _loading = false;
  Object? _error;
  List<Map<String, dynamic>> _stages = [];

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  Future<void> _load({bool reset = false}) async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
      if (reset) {
        _page = 1;
        _items.clear();
      }
    });
    try {
      final d = await widget.session.api.get('/crm/prospects', {
        'q': _q,
        'attention': _attention,
        'stage': _stage,
        'owner': _mine ? 'me' : '',
        'page': _page,
        'per_page': 25,
      });
      if (!mounted) return;
      setState(() {
        _items.addAll(_rows(d));
        _total = (d['total'] as num?)?.toInt() ?? _items.length;
        _last = (d['last_page'] as num?)?.toInt() ?? 1;
        _stages = _list(d['stages']);
      });
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _set(VoidCallback f) {
    setState(f);
    _load(reset: true);
  }

  Future<void> _add() async {
    final v = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _FieldsForm(
        title: 'Add a prospect',
        fields: [
          ('name', 'Company name', TextInputType.text),
          ('phone', 'Phone', TextInputType.phone),
          ('email', 'Email', TextInputType.emailAddress),
          ('website', 'Website', TextInputType.url),
          ('business_type', 'What they do (agency, developer…)', TextInputType.text),
        ],
        required: {'name'},
      ),
    );
    if (v == null || !mounted) return;
    try {
      final r = await widget.session.api.post('/crm/prospects', v);
      if (!mounted) return;
      toast(context, '${r['message'] ?? 'Prospect added.'}');
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ProspectScreen(session: widget.session, id: (r['id'] as num).toInt(), name: '${r['name']}'),
        ),
      );
      _load(reset: true);
    } catch (e) {
      if (mounted) toast(context, e);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: _title('Pipeline', '$_total prospects')),
                if (widget.session.screen('crm_pipeline')?.can('add') == true)
                  FilledButton.tonalIcon(onPressed: _add, icon: const Icon(PhosphorIconsLight.plus, size: 18), label: const Text('Add')),
              ],
            ),
            TextField(
              decoration: const InputDecoration(prefixIcon: Icon(PhosphorIconsLight.magnifyingGlass), hintText: 'Name, phone or email'),
              textInputAction: TextInputAction.search,
              onSubmitted: (v) => _set(() => _q = v.trim()),
            ),
            const SizedBox(height: 8),
            PillTabs<String>(
              options: const [('', 'All'), ('replied', 'Replied'), ('overdue', 'Overdue'), ('unattended', 'No next step')],
              selected: _attention,
              onSelect: (v) => _set(() => _attention = v),
              multi: {'Mine': (_mine, () => _set(() => _mine = !_mine))},
            ),
            const SizedBox(height: 6),
            PillTabs<String>(
              options: [('open', 'Open'), ('', 'Any stage'), for (final s in _stages) ('${s['key']}', '${s['label']}')],
              selected: _stage,
              onSelect: (v) => _set(() => _stage = v),
            ),
          ],
        ),
      ),
      Expanded(
        child: RefreshIndicator(
          onRefresh: () => _load(reset: true),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
            children: [
              if (_error != null) ...[
                EmptyState(_error is Exception ? '$_error' : 'Could not load the pipeline.'),
                OutlinedButton(onPressed: () => _load(reset: true), child: const Text('Try again')),
              ],
              if (_items.isEmpty && !_loading && _error == null) const EmptyState('No prospects match.'),
              for (final p in _items) ProspectTile(session: widget.session, p: p, onChanged: () => _load(reset: true)),
              if (_loading)
                const Center(
                  child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator(strokeWidth: 2.5)),
                ),
              if (!_loading && _page < _last)
                OutlinedButton(
                  onPressed: () {
                    _page++;
                    _load();
                  },
                  child: Text('Show more (${_items.length} of $_total)'),
                ),
            ],
          ),
        ),
      ),
    ],
  );
}

class ProspectTile extends StatelessWidget {
  const ProspectTile({super.key, required this.session, required this.p, this.onChanged});
  final Session session;
  final Map<String, dynamic> p;
  final VoidCallback? onChanged;

  @override
  Widget build(BuildContext context) {
    final reply = p['reply'] as Map?;
    final sub = [
      p['place'],
      if (p['owner'] != null) p['owner'] else if (p['unassigned'] == true) 'Unassigned',
      if (p['next_due_at'] != null) 'Next: ${_when(p['next_due_at'] as String?)}',
    ].where((v) => v != null && '$v'.isNotEmpty).join(' · ');
    return Tile(
      filled: reply != null,
      onTap: () async {
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ProspectScreen(session: session, id: p['id'] as int, name: '${p['name']}'),
          ),
        );
        onChanged?.call();
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text('${p['name']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: Sc.body(15, weight: 600)),
              ),
              const SizedBox(width: 8),
              Pill('${p['stage_label'] ?? p['stage'] ?? ''}', tone: _stageTone(p['stage'] as String?)),
            ],
          ),
          if (sub.isNotEmpty)
            Text(
              sub,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Sc.body(9.5, color: Sc.muted),
            ),
          if (reply != null) ...[
            const SizedBox(height: 6),
            Text(
              '${reply['from'] ?? 'They'} replied: ${reply['snippet'] ?? reply['subject'] ?? ''}',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Sc.body(12, color: Sc.alert),
            ),
          ],
        ],
      ),
    );
  }
}

class ProspectScreen extends StatefulWidget {
  const ProspectScreen({super.key, required this.session, required this.id, required this.name});
  final Session session;
  final int id;
  final String name;
  @override
  State<ProspectScreen> createState() => _ProspectScreenState();
}

class _ProspectScreenState extends State<ProspectScreen> {
  Key _k = UniqueKey();
  Session get s => widget.session;
  AppScreen? get _screen => s.screen('crm_pipeline');

  void _reload() => setState(() => _k = UniqueKey());

  Future<void> _post(String path, Map<String, dynamic> body, String done) async {
    try {
      await s.api.post(path, body);
      if (mounted) toast(context, done);
      _reload();
    } catch (e) {
      if (mounted) toast(context, e);
    }
  }

  Future<void> _moveStage(List<Map<String, dynamic>> stages, String? current) async {
    final picked = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.all(16),
          children: [
            Text('Move to', style: Sc.title(16)),
            for (final st in stages)
              ListTile(
                title: Text('${st['label']}'),
                trailing: st['key'] == current ? const Icon(PhosphorIconsLight.check) : null,
                onTap: () => Navigator.of(c).pop('${st['key']}'),
              ),
          ],
        ),
      ),
    );
    if (picked == null || picked == current || !mounted) return;
    String? reason;
    if (picked == 'lost') {
      reason = await _ask('Why was it lost?', 'Price, went with someone else, not ready…');
      if (reason == null) return;
    }
    await _post('/crm/prospects/${widget.id}/stage', {'stage': picked, 'reason': ?reason}, 'Stage updated.');
  }

  Future<String?> _ask(String title, String hint, {int lines = 1}) {
    final c = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (d) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: c,
          autofocus: true,
          maxLines: lines,
          decoration: InputDecoration(hintText: hint),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(d).pop(), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(d).pop(c.text.trim().isEmpty ? null : c.text.trim()), child: const Text('OK')),
        ],
      ),
    );
  }

  Future<void> _edit(Map<String, dynamic> p) async {
    final v = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _FieldsForm(
        title: 'Edit details',
        initial: {'name': p['name'], 'phone': p['phone'], 'email': p['email'], 'website': p['website'], 'city': p['city'], 'estimated_units': p['units']},
        fields: const [
          ('name', 'Company name', TextInputType.text),
          ('phone', 'Phone', TextInputType.phone),
          ('email', 'Email', TextInputType.emailAddress),
          ('website', 'Website', TextInputType.url),
          ('city', 'Town or city', TextInputType.text),
          ('estimated_units', 'About how many units', TextInputType.number),
          ('decision_maker', 'Who decides', TextInputType.text),
          ('current_system', 'What they use now', TextInputType.text),
          ('notes', 'Notes', TextInputType.multiline),
        ],
        required: const {'name'},
        switches: const {'do_not_email': 'They asked not to be emailed'},
        switchesOn: {'do_not_email': p['do_not_email'] == true},
      ),
    );
    if (v == null) return;
    const profile = {'website', 'city', 'estimated_units', 'decision_maker', 'current_system', 'notes'};
    final body = <String, dynamic>{'profile': <String, dynamic>{}};
    v.forEach((k, val) => profile.contains(k) ? (body['profile'] as Map)[k] = val : body[k] = val);
    await _post('/crm/prospects/${widget.id}', body, 'Saved.');
  }

  Future<void> _addPerson() async {
    final v = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _FieldsForm(
        title: 'Add a person',
        fields: [
          ('name', 'Name', TextInputType.name),
          ('job_title', 'Role (manager, owner…)', TextInputType.text),
          ('phone', 'Phone', TextInputType.phone),
          ('email', 'Email', TextInputType.emailAddress),
        ],
        required: {'name'},
      ),
    );
    if (v != null) await _post('/crm/prospects/${widget.id}/contacts', v, 'Person added.');
  }

  Future<void> _feedback() async {
    const contexts = {'demo': 'After a demo', 'onboarding': 'After onboarding', 'support': 'After support', 'general': 'General'};
    final picked = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.all(16),
          children: [
            Text('Ask for feedback', style: Sc.title(16)),
            for (final e in contexts.entries) ListTile(title: Text(e.value), onTap: () => Navigator.of(c).pop(e.key)),
          ],
        ),
      ),
    );
    if (picked == null || !mounted) return;
    try {
      final r = await s.api.post('/crm/prospects/${widget.id}/feedback', {'context': picked});
      if (!mounted) return;
      final link = '${r['link']}';
      await showDialog<void>(
        context: context,
        builder: (d) => AlertDialog(
          title: const Text('Feedback link'),
          content: SelectableText(link),
          actions: [
            TextButton(
              onPressed: () {
                Clipboard.setData(ClipboardData(text: link));
                Navigator.of(d).pop();
                toast(context, 'Link copied.');
              },
              child: const Text('Copy'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.of(d).pop();
                _open(context, 'https://wa.me/?text=${Uri.encodeComponent('Please tell us how we did: $link')}');
              },
              child: const Text('WhatsApp'),
            ),
          ],
        ),
      );
    } catch (e) {
      if (mounted) toast(context, e);
    }
  }

  Future<void> _addFollowUp(Map<String, dynamic> types, {required bool schedule}) async {
    final result = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (c) => _FollowUpForm(types: types.map((k, v) => MapEntry(k, '$v')), schedule: schedule),
    );
    if (result == null) return;
    await _post('/crm/prospects/${widget.id}/follow-ups', {...result, 'mode': schedule ? 'schedule' : 'log'}, schedule ? 'Follow-up booked.' : 'Logged.');
  }

  Map<String, dynamic>? _p;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.name, maxLines: 1, overflow: TextOverflow.ellipsis),
      actions: [
        if (_screen?.can('edit') == true)
          PopupMenuButton<String>(
            icon: const Icon(PhosphorIconsLight.dotsThree),
            onSelected: (v) {
              final p = _p;
              if (p == null) return;
              switch (v) {
                case 'edit':
                  _edit(p);
                case 'person':
                  _addPerson();
                case 'feedback':
                  _feedback();
              }
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'edit', child: Text('Edit details')),
              PopupMenuItem(value: 'person', child: Text('Add a person')),
              PopupMenuItem(value: 'feedback', child: Text('Feedback link')),
            ],
          ),
      ],
    ),
    body: Loader<Map<String, dynamic>>(
      key: _k,
      load: () => s.api.get('/crm/prospects/${widget.id}'),
      builder: (context, p, reload) {
        final reply = p['reply'] as Map?;
        final stages = _list(p['stages']);
        final types = (p['action_types'] as Map?)?.cast<String, dynamic>() ?? {'call': 'Call'};
        final contacts = _list(p['contacts']);
        final todo = _list(p['todo']);
        final history = _list(p['history']);
        final canUpdate = s.screen('crm_follow_ups')?.can('update') == true;
        _p = p;
        return ListView(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 32),
          children: [
            Row(
              children: [
                Expanded(child: Text('${p['name']}', style: Sc.title(18))),
                InkWell(
                  borderRadius: BorderRadius.circular(20),
                  onTap: _screen?.can('move_stage') == true ? () => _moveStage(stages, p['stage'] as String?) : null,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Pill('${p['stage_label'] ?? ''}', tone: _stageTone(p['stage'] as String?)),
                      if (_screen?.can('move_stage') == true) Icon(PhosphorIconsLight.caretRight, size: 16, color: Sc.muted),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              [
                p['segment'],
                p['place'],
                if (p['units'] != null) '${p['units']} units',
                p['owner'] ?? (p['unassigned'] == true ? 'Unassigned' : null),
              ].where((v) => v != null && '$v'.isNotEmpty).join(' · '),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: Sc.body(10.5, color: Sc.muted),
            ),
            if (p['lost_reason'] != null) Text('Lost: ${p['lost_reason']}', style: Sc.body(12, color: Sc.alert)),
            const SizedBox(height: 14),
            // One row of quick actions instead of a stack of full-width buttons.
            Row(
              children: [
                if (p['phone'] != null) ...[
                  _QuickAction(PhosphorIconsLight.phone, 'Call', () => _open(context, 'tel:${p['phone']}')),
                  _QuickAction(
                    PhosphorIconsLight.whatsappLogo,
                    'WhatsApp',
                    () => _open(context, 'https://wa.me/${'${p['phone']}'.replaceAll(RegExp(r'\D'), '')}'),
                  ),
                ],
                if (p['email'] != null) _QuickAction(PhosphorIconsLight.envelope, 'Mail app', () => _open(context, 'mailto:${p['email']}')),
                if (_screen?.can('log') == true) _QuickAction(PhosphorIconsLight.pencilSimple, 'Log', () => _addFollowUp(types, schedule: false)),
                if (_screen?.can('schedule') == true) _QuickAction(PhosphorIconsLight.calendarBlank, 'Next step', () => _addFollowUp(types, schedule: true)),
              ],
            ),
            if (reply != null) ...[
              const SizedBox(height: 12),
              Notice(
                '${reply['from'] ?? 'They'} replied ${_when(reply['at'] as String?)}',
                '${reply['subject'] != null ? '${reply['subject']}: ' : ''}${reply['snippet'] ?? ''}${reply['opt_out'] == true ? ' (asked not to be emailed)' : ''}',
                alert: true,
              ),
              if (_screen?.can('handle_reply') == true)
                Row(
                  children: [
                    Expanded(
                      child: FilledButton(
                        onPressed: () async {
                          final body = await _ask('Answer by email', 'Your answer goes in the same thread, from your work address.', lines: 6);
                          if (body != null) await _post('/crm/prospects/${widget.id}/reply', {'body': body}, 'Answer sent.');
                        },
                        child: const Text('Answer'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () async {
                          final note = await _ask('Mark handled', 'What did you do? (called, WhatsApp…)');
                          await _post('/crm/prospects/${widget.id}/reply/handled', {'note': ?note}, 'Reply marked handled.');
                        },
                        child: const Text('Mark handled'),
                      ),
                    ),
                  ],
                ),
            ],
            if (_screen?.can('send_email') == true) ...[
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () async {
                  final sent = await Navigator.of(context).push<bool>(
                    MaterialPageRoute(
                      builder: (_) => PlaybookEmailScreen(session: s, id: widget.id, name: '${p['name']}'),
                    ),
                  );
                  if (sent == true) _reload();
                },
                icon: const Icon(PhosphorIconsLight.paperPlaneTilt, size: 18),
                label: const Text('Reach out: email or demo form'),
              ),
            ],
            if (contacts.isNotEmpty) ...[
              const SectionTitle('People', icon: PhosphorIconsLight.users),
              for (final c in contacts)
                Tile(
                  onTap: c['phone'] != null
                      ? () => _open(context, 'tel:${c['phone']}')
                      : (c['email'] != null ? () => _open(context, 'mailto:${c['email']}') : null),
                  child: Row(
                    children: [
                      Avatar(c['name'] as String?, size: 36),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${c['name'] ?? ''}${c['primary'] == true ? ' (main)' : ''}', style: Sc.body(14, weight: 600)),
                            Text(
                              [c['role'], c['phone'], c['email']].where((v) => v != null).join(' · '),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Sc.body(9.5, color: Sc.muted),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
            ],
            SectionTitle('To do (${todo.length})', icon: PhosphorIconsLight.clockCounterClockwise),
            if (todo.isEmpty) const EmptyState('No next step booked. Book one so they don\'t go cold.'),
            for (final f in todo) FollowUpTile(session: s, f: f, canUpdate: canUpdate, showCompany: false, onChanged: _reload),
            if (history.isNotEmpty) ...[const SectionTitle('History', icon: PhosphorIconsLight.fileText), for (final f in history) _HistoryRow(f)],
          ],
        );
      },
    ),
  );
}

class _FollowUpForm extends StatefulWidget {
  const _FollowUpForm({required this.types, required this.schedule});
  final Map<String, String> types;
  final bool schedule;
  @override
  State<_FollowUpForm> createState() => _FollowUpFormState();
}

class _FollowUpFormState extends State<_FollowUpForm> {
  late String _type = widget.types.keys.first;
  final _title = TextEditingController();
  final _notes = TextEditingController();
  DateTime? _due;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.of(context).viewInsets.bottom),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(widget.schedule ? 'Book the next step' : 'Log what happened', style: Sc.title(16)),
        const SizedBox(height: 10),
        PillTabs<String>(options: [for (final e in widget.types.entries) (e.key, e.value)], selected: _type, onSelect: (v) => setState(() => _type = v)),
        const SizedBox(height: 10),
        TextField(
          controller: _title,
          decoration: const InputDecoration(hintText: 'Title (e.g. Intro call with the director)'),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _notes,
          maxLines: 3,
          decoration: const InputDecoration(hintText: 'Notes (optional)'),
        ),
        if (widget.schedule) ...[
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () async {
              final d = await pickDateTime(context);
              if (d != null) setState(() => _due = d);
            },
            icon: const Icon(PhosphorIconsLight.calendarBlank, size: 18),
            label: Text(_due == null ? 'Pick date and time' : _when(_iso(_due!))),
          ),
        ],
        const SizedBox(height: 12),
        FilledButton(
          onPressed: () {
            if (_title.text.trim().isEmpty) return toast(context, 'Add a title.');
            if (widget.schedule && _due == null) return toast(context, 'Pick the date and time.');
            Navigator.of(context).pop(<String, dynamic>{
              'type': _type,
              'title': _title.text.trim(),
              if (_notes.text.trim().isNotEmpty) 'notes': _notes.text.trim(),
              if (_due != null) 'due_at': _iso(_due!),
            });
          },
          child: Text(widget.schedule ? 'Book' : 'Log'),
        ),
      ],
    ),
  );
}

// ---- Follow-ups ---------------------------------------------------------------------------------------------------

class FollowUpTile extends StatelessWidget {
  const FollowUpTile({super.key, required this.session, required this.f, required this.canUpdate, this.onChanged, this.showCompany = true});
  final Session session;
  final Map<String, dynamic> f;
  final bool canUpdate;
  final bool showCompany;
  final VoidCallback? onChanged;

  @override
  Widget build(BuildContext context) {
    final company = f['company'] as Map?;
    final contact = f['contact'] as Map?;
    return Tile(
      onTap: canUpdate && f['draft'] is Map
          ? () => _review(context)
          : canUpdate && f['open'] == true
          ? () => _update(context)
          : (company != null ? () => _openCompany(context, company) : null),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${f['type_label']}: ${f['title'] ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis, style: Sc.body(14, weight: 600)),
                Text(
                  [
                    if (showCompany && company != null) company['name'],
                    contact?['name'],
                    f['open'] == true ? _when(f['due_at'] as String?) : _when((f['done_at'] ?? f['created_at']) as String?),
                    if (f['outcome'] != null) f['outcome'],
                  ].where((v) => v != null && '$v'.isNotEmpty).join(' · '),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Sc.body(9.5, color: Sc.muted),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Pill(f['overdue'] == true ? 'Overdue' : '${f['status_label'] ?? ''}', tone: f['overdue'] == true ? 'alert' : (f['open'] == true ? 'neutral' : 'ok')),
        ],
      ),
    );
  }

  void _openCompany(BuildContext context, Map company) => Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => ProspectScreen(session: session, id: company['id'] as int, name: '${company['name']}'),
    ),
  );

  /// A win-back email the system drafted: read, edit, then send or skip (nothing goes out without this).
  Future<void> _review(BuildContext context) async {
    final d = (f['draft'] as Map).cast<String, dynamic>();
    final subject = TextEditingController(text: '${d['subject'] ?? ''}');
    final body = TextEditingController(text: '${d['body'] ?? ''}');
    final action = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('Email to ${d['to']}'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: subject,
                decoration: const InputDecoration(labelText: 'Subject'),
              ),
              TextField(
                controller: body,
                minLines: 6,
                maxLines: 14,
                decoration: const InputDecoration(labelText: 'Email'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(c).pop('skip'), child: const Text('Skip')),
          TextButton(onPressed: () => Navigator.of(c).pop(), child: const Text('Later')),
          FilledButton(onPressed: () => Navigator.of(c).pop('send'), child: const Text('Send')),
        ],
      ),
    );
    if (action == null || !context.mounted) return;
    try {
      final r = await session.api.post('/crm/follow-ups/${f['id']}/draft', {'action': action, 'subject': subject.text, 'body': body.text});
      if (context.mounted) toast(context, '${r['message'] ?? 'Done.'}');
      onChanged?.call();
    } catch (e) {
      if (context.mounted) toast(context, e);
    }
  }

  Future<void> _update(BuildContext context) async {
    final result = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (c) => _CloseForm(f: f, onOpenCompany: f['company'] is Map ? () => _openCompany(context, f['company'] as Map) : null),
    );
    if (result == null || !context.mounted) return;
    try {
      final r = await session.api.post('/crm/follow-ups/${f['id']}', result);
      if (context.mounted) toast(context, r['warning'] as String? ?? 'Saved.');
      onChanged?.call();
    } catch (e) {
      if (context.mounted) toast(context, e);
    }
  }
}

class _CloseForm extends StatefulWidget {
  const _CloseForm({required this.f, this.onOpenCompany});
  final Map<String, dynamic> f;
  final VoidCallback? onOpenCompany;
  @override
  State<_CloseForm> createState() => _CloseFormState();
}

class _CloseFormState extends State<_CloseForm> {
  String _status = 'done';
  final _outcome = TextEditingController();
  final _next = TextEditingController();
  DateTime? _when2;
  DateTime? _nextDue;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.of(context).viewInsets.bottom),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('${widget.f['title'] ?? 'Follow-up'}', maxLines: 2, overflow: TextOverflow.ellipsis, style: Sc.title(16)),
        if (widget.onOpenCompany != null)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                widget.onOpenCompany!();
              },
              child: Text('Open ${(widget.f['company'] as Map)['name']}'),
            ),
          ),
        const SizedBox(height: 6),
        PillTabs<String>(
          options: const [('done', 'Done'), ('missed', 'Missed'), ('cancelled', 'Cancel'), ('scheduled', 'Reschedule')],
          selected: _status,
          onSelect: (v) => setState(() => _status = v),
        ),
        const SizedBox(height: 10),
        if (_status == 'scheduled')
          OutlinedButton.icon(
            onPressed: () async {
              final d = await pickDateTime(context);
              if (d != null) setState(() => _when2 = d);
            },
            icon: const Icon(PhosphorIconsLight.calendarBlank, size: 18),
            label: Text(_when2 == null ? 'New date and time' : _when(_iso(_when2!))),
          )
        else ...[
          TextField(
            controller: _outcome,
            decoration: const InputDecoration(hintText: 'What happened? (needed)'),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _next,
            decoration: const InputDecoration(hintText: 'Next step (optional)'),
          ),
          if (_next.text.isNotEmpty || _nextDue != null)
            OutlinedButton.icon(
              onPressed: () async {
                final d = await pickDateTime(context);
                if (d != null) setState(() => _nextDue = d);
              },
              icon: const Icon(PhosphorIconsLight.calendarBlank, size: 18),
              label: Text(_nextDue == null ? 'When is the next step?' : _when(_iso(_nextDue!))),
            )
          else
            TextButton(onPressed: () => setState(() => _next.text = 'Follow up'), child: const Text('Book a next step too')),
        ],
        const SizedBox(height: 12),
        FilledButton(
          onPressed: () {
            if (_status == 'scheduled') {
              if (_when2 == null) return toast(context, 'Pick the date and time.');
              return Navigator.of(context).pop(<String, dynamic>{'status': 'scheduled', 'due_at': _iso(_when2!)});
            }
            if (_outcome.text.trim().isEmpty) return toast(context, 'Say what happened so the next person has context.');
            final next = _next.text.trim();
            if (next.isNotEmpty && _nextDue == null) return toast(context, 'Pick when the next step is.');
            Navigator.of(context).pop(<String, dynamic>{
              'status': _status,
              'outcome': _outcome.text.trim(),
              if (next.isNotEmpty) 'next_title': next,
              if (next.isNotEmpty) 'next_due_at': _iso(_nextDue!),
            });
          },
          child: const Text('Save'),
        ),
      ],
    ),
  );
}

class CrmFollowUpsScreen extends StatefulWidget {
  const CrmFollowUpsScreen({super.key, required this.session});
  final Session session;
  @override
  State<CrmFollowUpsScreen> createState() => _CrmFollowUpsScreenState();
}

class _CrmFollowUpsScreenState extends State<CrmFollowUpsScreen> {
  String _view = 'overdue';
  bool _mine = false;
  Key _k = UniqueKey();

  @override
  Widget build(BuildContext context) {
    final canUpdate = widget.session.screen('crm_follow_ups')?.can('update') == true;
    return Loader<Map<String, dynamic>>(
      key: _k,
      load: () => widget.session.api.get('/crm/follow-ups', {'view': _view, 'mine': _mine ? 1 : '', 'per_page': 50}),
      builder: (context, d, reload) {
        final counts = (d['counts'] as Map?) ?? {};
        final rows = _rows(d);
        return ListView(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
          children: [
            _title('Follow-ups', '${counts['overdue'] ?? 0} overdue · ${counts['today'] ?? 0} today · ${counts['open'] ?? 0} open'),
            PillTabs<String>(
              options: [
                ('overdue', 'Overdue (${counts['overdue'] ?? 0})'),
                ('today', 'Today (${counts['today'] ?? 0})'),
                ('open', 'All open'),
                ('done', 'Done'),
              ],
              selected: _view,
              onSelect: (v) => setState(() {
                _view = v;
                _k = UniqueKey();
              }),
              multi: {
                'Mine': (
                  _mine,
                  () => setState(() {
                    _mine = !_mine;
                    _k = UniqueKey();
                  }),
                ),
              },
            ),
            const SizedBox(height: 8),
            if (rows.isEmpty) EmptyState(_view == 'overdue' ? 'Nothing overdue. Nice.' : 'Nothing here.'),
            for (final f in rows) FollowUpTile(session: widget.session, f: f, canUpdate: canUpdate, onChanged: reload),
            if ((d['total'] ?? 0) > rows.length) Text('Showing ${rows.length} of ${d['total']}.', style: Sc.body(9.5, color: Sc.muted)),
          ],
        );
      },
    );
  }
}

// ---- Sales tracker ------------------------------------------------------------------------------------------------

class SalesScreen extends StatefulWidget {
  const SalesScreen({super.key, required this.session});
  final Session session;
  @override
  State<SalesScreen> createState() => _SalesScreenState();
}

class _SalesScreenState extends State<SalesScreen> {
  String _period = 'month';
  Key _k = UniqueKey();

  @override
  Widget build(BuildContext context) => Loader<Map<String, dynamic>>(
    key: _k,
    load: () => widget.session.api.get('/crm/sales', {'period': _period}),
    builder: (context, d, reload) {
      final t = (d['totals'] as Map?)?.cast<String, dynamic>() ?? {};
      final periods = (d['periods'] as Map?)?.cast<String, dynamic>() ?? {'month': 'This month'};
      final funnel = _list(d['funnel']);
      final maxCount = funnel.fold<int>(1, (m, s) => ((s['count'] as num?)?.toInt() ?? 0) > m ? (s['count'] as num).toInt() : m);
      final closing = _list(d['closing']);
      final wins = _list(d['wins']);
      final lines = _list(d['lines']);
      final team = d['scope'] == 'team';
      return ListView(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
        children: [
          _title('Sales tracker', team ? 'Whole team' : 'Your book'),
          PillTabs<String>(
            options: [for (final e in periods.entries) (e.key, '${e.value}')],
            selected: _period,
            onSelect: (v) => setState(() {
              _period = v;
              _k = UniqueKey();
            }),
          ),
          const SizedBox(height: 10),
          HeroCard(
            accent: true,
            children: [
              Text(team ? 'SETTLED PLATFORM FEES' : 'YOUR COMMISSION', style: Sc.body(12, color: Sc.onAccent.withValues(alpha: .85))),
              const SizedBox(height: 6),
              CountUp(
                value: double.tryParse('${team ? t['fees'] : t['commission']}') ?? 0,
                format: kes,
                style: Sc.head(24, color: Sc.onAccent),
              ),
              Text(
                team
                    ? 'Commission due ${kes(t['commission'])}'
                    : '${t['rate'] ?? 0}% of ${kes(t['fees'])} settled fees · forecast ${kes(t['forecast_commission'])}',
                style: Sc.body(13, color: Sc.onAccent.withValues(alpha: .9)),
              ),
              if (!team)
                Text(
                  'Paid once the landlord\'s bill is settled. ${t['units'] ?? 0} units in ${t['properties'] ?? 0} properties.',
                  style: Sc.body(12, color: Sc.onAccent.withValues(alpha: .85)),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _stat('${t['won'] ?? 0}', 'Won'),
              _stat('${t['lost'] ?? 0}', 'Lost'),
              _stat(t['win_rate'] == null ? '–' : '${t['win_rate']}%', 'Win rate'),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              _stat('${t['open'] ?? 0}', 'Open deals'),
              _stat(kes(t['pipeline'], symbol: false), 'Pipeline KES/mo'),
              _stat('${t['no_next_step'] ?? 0}', 'No next step', alert: (t['no_next_step'] ?? 0) > 0),
            ],
          ),
          const SectionTitle('Funnel', icon: PhosphorIconsLight.chartBar),
          for (final s in funnel)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  SizedBox(
                    width: 96,
                    child: Text('${s['label']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: Sc.body(12)),
                  ),
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: ((s['count'] as num?)?.toDouble() ?? 0) / maxCount,
                        minHeight: 10,
                        color: Sc.accent,
                        backgroundColor: Sc.accentSoft,
                      ),
                    ),
                  ),
                  SizedBox(
                    width: 48,
                    child: Text('${s['count']}', textAlign: TextAlign.right, style: Sc.body(12, weight: 600)),
                  ),
                ],
              ),
            ),
          if (closing.isNotEmpty) ...[
            SectionTitle('Closing (${closing.length})', icon: PhosphorIconsLight.handshake),
            for (final p in closing) ProspectTile(session: widget.session, p: p, onChanged: reload),
          ],
          if (wins.isNotEmpty) ...[
            const SectionTitle('Recent wins', icon: PhosphorIconsLight.checkCircle),
            for (final w in wins)
              Tile(
                child: Row(
                  children: [
                    Expanded(child: Text('${w['name']}', style: Sc.body(14, weight: 600))),
                    Text('${shortDate(w['at'] as String?)} · ${kes(w['value'])}/mo', style: Sc.body(12, color: Sc.muted)),
                  ],
                ),
              ),
          ],
          if (!team) ...[
            SectionTitle('Commission lines (${lines.length})', icon: PhosphorIconsLight.receipt),
            if (lines.isEmpty) const EmptyState('No settled platform bills for your companies in this period yet.'),
            for (final l in lines)
              Tile(
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${l['company']} · ${l['property']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: Sc.body(14, weight: 600)),
                          Text(
                            '${l['invoice']} · ${l['period'] ?? ''} · ${l['units']} units · paid ${shortDate(l['paid_at'] as String?)}',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Sc.body(9.5, color: Sc.muted),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(kes(l['commission']), style: Sc.body(14, weight: 600)),
                        Text('of ${kes(l['fee'])}', style: Sc.body(9.5, color: Sc.muted)),
                      ],
                    ),
                  ],
                ),
              ),
          ],
        ],
      );
    },
  );
}

// ---- Requests (demos, API keys, listing requests, listing alerts) ------------------------------------------------

class CrmRequestsScreen extends StatefulWidget {
  const CrmRequestsScreen({super.key, required this.session});
  final Session session;
  @override
  State<CrmRequestsScreen> createState() => _CrmRequestsScreenState();
}

class _CrmRequestsScreenState extends State<CrmRequestsScreen> {
  String _type = 'demo';
  Key _k = UniqueKey();

  @override
  Widget build(BuildContext context) {
    final canUpdate = widget.session.screen('crm_requests')?.can('update') == true;
    return Loader<Map<String, dynamic>>(
      key: _k,
      load: () => widget.session.api.get('/crm/requests', {'type': _type, 'per_page': 50}),
      builder: (context, d, reload) {
        final types = (d['types'] as Map?)?.cast<String, dynamic>() ?? {'demo': 'Demos'};
        final open = (d['open'] is Map ? d['open'] as Map : const {});
        final rows = _rows(d);
        return ListView(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
          children: [
            _title('Requests', 'From the website and listings'),
            PillTabs<String>(
              options: [for (final e in types.entries) (e.key, '${e.value}${(open[e.key] ?? 0) > 0 ? ' (${open[e.key]})' : ''}')],
              selected: _type,
              onSelect: (v) => setState(() {
                _type = v;
                _k = UniqueKey();
              }),
            ),
            const SizedBox(height: 8),
            if (rows.isEmpty) const EmptyState('No requests here yet.'),
            for (final f in rows) FollowUpTile(session: widget.session, f: f, canUpdate: canUpdate && _type != 'listing_alert', onChanged: reload),
          ],
        );
      },
    );
  }
}

// ---- Waitlist -----------------------------------------------------------------------------------------------------

class WaitlistScreen extends StatefulWidget {
  const WaitlistScreen({super.key, required this.session});
  final Session session;
  @override
  State<WaitlistScreen> createState() => _WaitlistScreenState();
}

class _WaitlistScreenState extends State<WaitlistScreen> {
  String _status = '';
  String _q = '';
  Key _k = UniqueKey();

  @override
  Widget build(BuildContext context) {
    final canUpdate = widget.session.screen('crm_waitlist')?.can('update') == true;
    return Loader<Map<String, dynamic>>(
      key: _k,
      load: () => widget.session.api.get('/crm/waitlist', {'status': _status, 'q': _q, 'per_page': 50}),
      builder: (context, d, reload) {
        final statuses = (d['statuses'] as Map?)?.cast<String, dynamic>() ?? {};
        final rows = _rows(d);
        return ListView(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
          children: [
            _title('Waitlist', '${d['total'] ?? rows.length} people in countries we are not in yet'),
            TextField(
              decoration: const InputDecoration(prefixIcon: Icon(PhosphorIconsLight.magnifyingGlass), hintText: 'Name, phone or email'),
              textInputAction: TextInputAction.search,
              onSubmitted: (v) => setState(() {
                _q = v.trim();
                _k = UniqueKey();
              }),
            ),
            const SizedBox(height: 8),
            PillTabs<String>(
              options: [('', 'All'), for (final e in statuses.entries) (e.key, '${e.value}')],
              selected: _status,
              onSelect: (v) => setState(() {
                _status = v;
                _k = UniqueKey();
              }),
            ),
            const SizedBox(height: 8),
            if (rows.isEmpty) const EmptyState('Nobody here.'),
            for (final e in rows)
              Tile(
                onTap: !canUpdate
                    ? null
                    : () async {
                        final picked = await showModalBottomSheet<String>(
                          context: context,
                          builder: (c) => SafeArea(
                            child: ListView(
                              shrinkWrap: true,
                              padding: const EdgeInsets.all(16),
                              children: [
                                Text('${e['name'] ?? 'Entry'}', style: Sc.title(16)),
                                for (final s in statuses.entries)
                                  ListTile(
                                    title: Text('${s.value}'),
                                    trailing: s.key == e['status'] ? const Icon(PhosphorIconsLight.check) : null,
                                    onTap: () => Navigator.of(c).pop(s.key),
                                  ),
                              ],
                            ),
                          ),
                        );
                        if (picked == null || picked == e['status'] || !context.mounted) return;
                        try {
                          await widget.session.api.post('/crm/waitlist/${e['id']}', {'status': picked});
                          await reload();
                        } catch (err) {
                          if (context.mounted) toast(context, err);
                        }
                      },
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${e['name'] ?? e['email'] ?? e['phone'] ?? ''}', style: Sc.body(14, weight: 600)),
                          Text(
                            [
                              e['country'],
                              e['company'],
                              e['project'],
                              if (e['units'] != null) '${e['units']} units',
                              e['phone'] ?? e['email'],
                            ].where((v) => v != null && '$v'.isNotEmpty).join(' · '),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Sc.body(9.5, color: Sc.muted),
                          ),
                        ],
                      ),
                    ),
                    Pill('${e['status_label'] ?? e['status']}', tone: e['status'] == 'demo_requested' ? 'brand' : 'neutral'),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}

/// Playbook and the first email to a prospect, as on the web CRM: how to approach them, what to sell, and the email
/// (their saved draft or the segment template). Sent from scharge under the account manager's name; replies go to them,
/// and a follow-up is booked. Server: CrmController::playbook / savePlaybookDraft / sendPlaybook.
class PlaybookEmailScreen extends StatefulWidget {
  const PlaybookEmailScreen({super.key, required this.session, required this.id, required this.name});
  final Session session;
  final int id;
  final String name;
  @override
  State<PlaybookEmailScreen> createState() => _PlaybookEmailScreenState();
}

class _PlaybookEmailScreenState extends State<PlaybookEmailScreen> {
  Map<String, dynamic>? _book;
  Object? _error;
  bool _busy = false;
  final _to = TextEditingController();
  final _subject = TextEditingController();
  final _body = TextEditingController();
  final Set<String> _cc = {};
  int _days = 3;
  String _way = 'email'; // or 'form': their private demo form, sent by WhatsApp or SMS from this phone
  final _message = TextEditingController();
  final _research = TextEditingController();
  final Set<String> _pains = {};

  String get _base => '/crm/prospects/${widget.id}/playbook';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _to.dispose();
    _subject.dispose();
    _body.dispose();
    _message.dispose();
    _research.dispose();
    super.dispose();
  }

  void _fill(Map<String, dynamic> b) {
    _book = b;
    _to.text = (b['to'] as String?) ?? _to.text;
    _subject.text = '${b['subject'] ?? ''}';
    _body.text = '${b['body'] ?? ''}';
    _message.text = '${b['form_message'] ?? ''}';
    _research.text = '${b['research'] ?? ''}';
    _pains
      ..clear()
      ..addAll(((b['chosen_pains'] as List?) ?? []).map((e) => '$e'));
    // Nobody to email (or they asked not to be emailed): start on the form.
    if (b['do_not_email'] == true || ((b['emails'] as List?) ?? []).isEmpty) _way = 'form';
  }

  Future<void> _rewrite() => _run(() async {
    final b = await widget.session.api.post('$_base/compose', {'pains': _pains.toList(), 'research': _research.text.trim()});
    if (!mounted) return;
    setState(() => _fill(b));
    toast(context, 'Email rewritten around their problems. Check it, then send.');
  });

  Future<void> _shareForm(String via, String? phone) async {
    final text = _message.text.trim();
    final digits = (phone ?? '').replaceAll(RegExp(r'\D'), '');
    final url = switch (via) {
      'whatsapp' => 'https://wa.me/$digits?text=${Uri.encodeComponent(text)}',
      'sms' => 'sms:${phone ?? ''}?body=${Uri.encodeComponent(text)}',
      _ => '',
    };
    if (via == 'other') {
      await Clipboard.setData(ClipboardData(text: text));
      if (mounted) toast(context, 'Message and link copied.');
    } else {
      await _open(context, url);
    }
    if (!mounted) return;
    final sent = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Did you send it?'),
        content: const Text('We log it on their record and book a check in 3 working days. When they fill the form it shows under Requests.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(d).pop(false), child: const Text('Not yet')),
          FilledButton(onPressed: () => Navigator.of(d).pop(true), child: const Text('Yes, sent')),
        ],
      ),
    );
    if (sent != true) return;
    await _run(() async {
      final r = await widget.session.api.post('$_base/form', {'via': via, 'follow_up_days': _days});
      if (!mounted) return;
      toast(context, '${r['message'] ?? 'Logged.'}');
      Navigator.of(context).pop(true);
    });
  }

  Future<void> _load() async {
    try {
      final b = await Api.fresh(() => widget.session.api.get(_base));
      if (mounted) setState(() => _fill(b));
    } catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Future<void> _run(Future<void> Function() work) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await work();
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _save({bool reset = false}) => _run(() async {
    final b = await widget.session.api.post('$_base/draft', reset ? {'reset': true} : {'subject': _subject.text.trim(), 'body': _body.text});
    if (!mounted) return;
    setState(() => _fill(b));
    toast(context, reset ? 'Back to the playbook email.' : 'Draft saved.');
  });

  Future<void> _send() async {
    final to = _to.text.trim();
    if (to.isEmpty || _subject.text.trim().isEmpty || _body.text.trim().isEmpty) {
      toast(context, 'Fill in To, Subject and the email first.');
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Send this email?'),
        content: Text('To $to${_cc.isEmpty ? '' : ' (cc ${_cc.join(', ')})'}, signed by ${_book?['signed_by'] ?? 'you'}. Replies come back to your inbox.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(d).pop(false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.of(d).pop(true), child: const Text('Send')),
        ],
      ),
    );
    if (ok != true) return;
    await _run(() async {
      final r = await widget.session.api.post('$_base/send', {
        'to': to,
        'cc': _cc.where((e) => e != to).toList(),
        'subject': _subject.text.trim(),
        'body': _body.text,
        'follow_up_days': _days,
      });
      if (!mounted) return;
      toast(context, '${r['message'] ?? 'Email sent.'}');
      Navigator.of(context).pop(true);
    });
  }

  Widget _bullets(String title, List items, IconData icon) => items.isEmpty
      ? const SizedBox.shrink()
      : Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SectionTitle(title, icon: icon),
            for (final i in items)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text('• $i', style: Sc.body(13)),
              ),
          ],
        );

  @override
  Widget build(BuildContext context) {
    final b = _book;
    return Scaffold(
      appBar: AppBar(title: Text(widget.name, maxLines: 1, overflow: TextOverflow.ellipsis)),
      body: b == null
          ? Center(
              child: _error != null
                  ? Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text('$_error', textAlign: TextAlign.center),
                    )
                  : const CircularProgressIndicator(),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 40),
              children: [
                _title('Playbook', b['label'] as String?),
                ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  title: Text('How to approach them', style: Sc.body(14, weight: 600)),
                  children: [
                    _bullets('Approach', (b['approach'] as List?) ?? [], PhosphorIconsLight.lightbulb),
                    _bullets('What to sell', (b['offers'] as List?) ?? [], PhosphorIconsLight.tag),
                    if (_list(b['objections']).isNotEmpty) ...[
                      const SectionTitle('If they say', icon: PhosphorIconsLight.handshake),
                      for (final o in _list(b['objections']))
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('"${o['q']}"', style: Sc.body(13, weight: 600)),
                              Text('${o['a']}', style: Sc.body(12.5, color: Sc.muted)),
                            ],
                          ),
                        ),
                    ],
                  ],
                ),
                if (((b['signals'] as List?) ?? []).isNotEmpty) _bullets('What we know', (b['signals'] as List?) ?? [], PhosphorIconsLight.magnifyingGlass),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'email', label: Text('Email'), icon: Icon(PhosphorIconsLight.envelope, size: 18)),
                    ButtonSegment(value: 'form', label: Text('Demo form'), icon: Icon(PhosphorIconsLight.whatsappLogo, size: 18)),
                  ],
                  selected: {_way},
                  onSelectionChanged: (v) => setState(() => _way = v.first),
                ),
                if (b['form_filled'] == true) ...[const SizedBox(height: 8), const Notice('They filled the demo form', 'Their answers are under Requests.')],
                if (_way == 'form') ...[
                  const SectionTitle('Their private demo form', icon: PhosphorIconsLight.fileText),
                  Text(
                    'Send the link by WhatsApp or SMS from your phone. They tell us how they run their properties; it lands under Requests and on their record.',
                    style: Sc.body(12, color: Sc.muted),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _message,
                    minLines: 3,
                    maxLines: 8,
                    decoration: const InputDecoration(labelText: 'Message with the link', alignLabelWithHint: true),
                  ),
                  const SizedBox(height: 8),
                  for (final ph in _list(b['phones']))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Row(
                        children: [
                          Expanded(child: Text('${ph['name']}\n${ph['phone']}', style: Sc.body(12.5))),
                          IconButton.filledTonal(
                            tooltip: 'WhatsApp',
                            onPressed: _busy ? null : () => _shareForm('whatsapp', '${ph['phone']}'),
                            icon: const Icon(PhosphorIconsLight.whatsappLogo),
                          ),
                          IconButton.outlined(
                            tooltip: 'SMS',
                            onPressed: _busy ? null : () => _shareForm('sms', '${ph['phone']}'),
                            icon: const Icon(PhosphorIconsLight.deviceMobile),
                          ),
                        ],
                      ),
                    ),
                  if (_list(b['phones']).isEmpty) const EmptyState('No phone number yet. Add a person with a phone, or copy the link.'),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _shareForm('other', null),
                    icon: const Icon(PhosphorIconsLight.copy, size: 18),
                    label: const Text('Copy message and link'),
                  ),
                ] else ...[
                  ExpansionTile(
                    tilePadding: EdgeInsets.zero,
                    title: Text('Research: write it around their problems', style: Sc.body(14, weight: 600)),
                    children: [
                      for (final pn in _list(b['pains']))
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          dense: true,
                          value: _pains.contains('${pn['key']}'),
                          title: Text('${pn['problem']}', style: Sc.body(13, weight: 600)),
                          subtitle: Text('${pn['fix']}', style: Sc.body(11.5, color: Sc.muted)),
                          onChanged: (v) => setState(() => v == true ? _pains.add('${pn['key']}') : _pains.remove('${pn['key']}')),
                        ),
                      TextField(
                        controller: _research,
                        maxLength: 600,
                        maxLines: 3,
                        decoration: const InputDecoration(labelText: 'What you found about them (optional)'),
                      ),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: FilledButton.tonal(onPressed: _busy ? null : _rewrite, child: const Text('Rewrite the email')),
                      ),
                      const SizedBox(height: 8),
                    ],
                  ),
                  const SectionTitle('Email', icon: PhosphorIconsLight.envelope),
                  if (b['do_not_email'] == true)
                    const Notice('They asked not to be emailed', 'Call or WhatsApp instead. This can only be changed on the web CRM.', alert: true)
                  else ...[
                    TextField(
                      controller: _to,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(labelText: 'To'),
                    ),
                    for (final e in ((b['emails'] as List?) ?? []).cast<String>().where((e) => e != _to.text.trim()))
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        dense: true,
                        value: _cc.contains(e),
                        title: Text('Copy $e', style: Sc.body(13)),
                        onChanged: (v) => setState(() {
                          if (v == true && _cc.length < 2) {
                            _cc.add(e);
                          } else {
                            _cc.remove(e);
                          }
                        }),
                      ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _subject,
                      decoration: const InputDecoration(labelText: 'Subject'),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _body,
                      minLines: 10,
                      maxLines: null,
                      keyboardType: TextInputType.multiline,
                      decoration: const InputDecoration(labelText: 'Email', alignLabelWithHint: true),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Sent from scharge as ${b['signed_by'] ?? 'you'}; their reply comes to your inbox and shows here.',
                      style: Sc.body(10.5, color: Sc.muted),
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<int>(
                      initialValue: _days,
                      decoration: const InputDecoration(labelText: 'Follow up in'),
                      items: [
                        for (final e in ((b['follow_up_days'] as Map?) ?? {'3': '3 working days'}).entries)
                          DropdownMenuItem(value: int.parse('${e.key}'), child: Text('${e.value}')),
                      ],
                      onChanged: (v) => setState(() => _days = v ?? 3),
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _busy ? null : _send,
                      icon: const Icon(PhosphorIconsLight.paperPlaneTilt, size: 18),
                      label: const Text('Send email'),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      children: [
                        OutlinedButton(onPressed: _busy ? null : () => _save(), child: const Text('Save draft')),
                        if (b['saved_draft'] == true)
                          TextButton(onPressed: _busy ? null : () => _save(reset: true), child: const Text('Back to playbook email')),
                      ],
                    ),
                  ],
                ],
              ],
            ),
    );
  }
}

/// A small form in a bottom sheet: text fields (and optional switches) in, a map of what was filled out.
class _FieldsForm extends StatefulWidget {
  const _FieldsForm({
    required this.title,
    required this.fields,
    this.required = const {},
    this.initial = const {},
    this.switches = const {},
    this.switchesOn = const {},
  });
  final String title;
  final List<(String, String, TextInputType)> fields;
  final Set<String> required;
  final Map<String, dynamic> initial;
  final Map<String, String> switches;
  final Map<String, bool> switchesOn;
  @override
  State<_FieldsForm> createState() => _FieldsFormState();
}

class _FieldsFormState extends State<_FieldsForm> {
  late final Map<String, TextEditingController> _c = {
    for (final f in widget.fields) f.$1: TextEditingController(text: widget.initial[f.$1] == null ? '' : '${widget.initial[f.$1]}'),
  };
  late final Map<String, bool?> _sw = {for (final k in widget.switches.keys) k: null};

  @override
  void dispose() {
    for (final c in _c.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _done() {
    for (final k in widget.required) {
      if (_c[k]!.text.trim().isEmpty) {
        toast(context, 'Fill in ${widget.fields.firstWhere((f) => f.$1 == k).$2}.');
        return;
      }
    }
    final out = <String, dynamic>{};
    _c.forEach((k, c) {
      final v = c.text.trim();
      final was = widget.initial[k] == null ? '' : '${widget.initial[k]}';
      if (v.isNotEmpty || was.isNotEmpty) {
        if (widget.initial.isEmpty || v != was) out[k] = v.isEmpty ? null : v;
      }
    });
    _sw.forEach((k, v) {
      if (v != null) out[k] = v;
    });
    Navigator.of(context).pop(out);
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.of(context).viewInsets.bottom),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(widget.title, style: Sc.title(16)),
          const SizedBox(height: 8),
          for (final f in widget.fields)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: TextField(
                controller: _c[f.$1],
                keyboardType: f.$3,
                maxLines: f.$3 == TextInputType.multiline ? 4 : 1,
                decoration: InputDecoration(labelText: widget.required.contains(f.$1) ? f.$2 : '${f.$2} (optional)'),
              ),
            ),
          for (final e in widget.switches.entries)
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(e.value, style: Sc.body(13)),
              value: _sw[e.key] ?? widget.switchesOn[e.key] ?? false,
              onChanged: (v) => setState(() => _sw[e.key] = v),
            ),
          const SizedBox(height: 8),
          FilledButton(onPressed: _done, child: const Text('Save')),
        ],
      ),
    ),
  );
}

/// A round quick action (Call, WhatsApp, Log…), several to a row.
class _QuickAction extends StatelessWidget {
  const _QuickAction(this.icon, this.label, this.onTap);
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: Theme.of(context).colorScheme.primary.withValues(alpha: .35)),
              ),
              child: Icon(icon, size: 21, color: Theme.of(context).colorScheme.primary),
            ),
            const SizedBox(height: 4),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: Sc.body(11.5)),
          ],
        ),
      ),
    ),
  );
}

/// One line of history: the title once (the type is the icon, not repeated as "Stage: Stage: …"), then when and a short detail.
class _HistoryRow extends StatelessWidget {
  const _HistoryRow(this.f);
  final Map<String, dynamic> f;

  static const _icons = {
    'call': PhosphorIconsLight.phone,
    'email': PhosphorIconsLight.envelope,
    'whatsapp': PhosphorIconsLight.whatsappLogo,
    'meeting': PhosphorIconsLight.users,
    'stage': PhosphorIconsLight.arrowRight,
    'import': PhosphorIconsLight.export,
    'demo': PhosphorIconsLight.fileText,
    'feedback': PhosphorIconsLight.handHeart,
  };

  @override
  Widget build(BuildContext context) {
    final label = '${f['type_label'] ?? ''}';
    var title = '${f['title'] ?? ''}';
    if (label.isNotEmpty && title.toLowerCase().startsWith('${label.toLowerCase()}: ')) title = title.substring(label.length + 2);
    final detail = [
      f['outcome'],
      f['notes'],
    ].where((v) => v != null && '$v'.trim().isNotEmpty).map((v) => '$v'.replaceAll(RegExp(r'\s+'), ' ').trim()).join(' · ');
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 2, right: 10),
            child: Icon(_icons[f['type']] ?? PhosphorIconsLight.fileText, size: 18, color: Sc.muted),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, maxLines: 2, overflow: TextOverflow.ellipsis, style: Sc.body(13.5, weight: 600)),
                Text(
                  [_when((f['done_at'] ?? f['created_at']) as String?), if (detail.isNotEmpty) detail].join(' · '),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Sc.body(10.5, color: Sc.muted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
