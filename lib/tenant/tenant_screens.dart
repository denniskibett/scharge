import 'dart:io';
import 'package:flutter/material.dart';
import '../shared/ph.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/session.dart';
import '../core/theme.dart';
import '../shared/documents.dart';
import '../shared/motion.dart';
import '../shared/ui.dart';
import '../shared/confirm_pay.dart';
import 'water_screen.dart';

/// Opens the secret bill link: the same page tenants get by SMS, with the M-Pesa prompt (or Sharet) on it.
Future<void> openPayLink(BuildContext context, String? url) async {
  if (url == null || !await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication)) {
    if (context.mounted) toast(context, 'Could not open the payment page.');
  }
}

// ---- Home (canvas: tenant home) ---------------------------------------------------------------------------------

class TenantHome extends StatelessWidget {
  const TenantHome({super.key, required this.session, required this.openTab, this.trailing});
  final Session session;
  final void Function(String key) openTab;

  /// The bell (when something needs you) and the one avatar, top right (canvas: "Habari, Brian" + BK).
  final Widget? trailing;

  static const _tiles = [
    ('tenant_repairs', 'Repair', PhosphorIconsLight.pencilSimple),
    ('gate', 'Gate pass', PhosphorIconsLight.sidebarSimple),
    ('tenant_water', 'Water', PhosphorIconsLight.drop),
    ('lease', 'Lease', PhosphorIconsLight.fileText),
  ];

  @override
  Widget build(BuildContext context) => Loader<List<Map<String, dynamic>>>(
        load: () => Future.wait([session.api.get('/tenant/summary'), session.api.get('/tenant/bills')]),
        builder: (context, data, reload) {
          final sum = data[0];
          final bills = ((data[1]['data'] as List?) ?? []).cast<Map<String, dynamic>>();
          final next = sum['next_bill'] as Map<String, dynamic>?;
          final items = ((next?['items'] as List?) ?? []).cast<Map>();
          final days = next?['days_left'] as int?;
          final credit = double.tryParse('${sum['credit']}') ?? 0;
          return ListView(padding: const EdgeInsets.fromLTRB(12, 6, 12, 24), children: [
            Row(children: [
              Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text([if (sum['unit'] != null) 'Unit ${sum['unit']}', sum['property']].where((v) => v != null).join(' · '), maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                Text('Habari, ${session.firstName}', style: Sc.title(18)),
              ])),
              ?trailing,
            ]),
            const SizedBox(height: 14),
            HeroCard(children: [
              Row(children: [
                Expanded(
                    child: Text(
                        next != null ? '${'${next['month_label'] ?? ''}'.split(' ').first.toUpperCase()}${next['due_label'] != null ? ' · DUE ${'${next['due_label']}'.toUpperCase()}' : ''}' : 'ALL PAID',
                        maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .85)))),
                if (days != null)
                  Text(days > 1 ? '$days days left' : days == 1 ? '1 day left' : days == 0 ? 'Due today' : '${-days} days late',
                      maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .85))),
              ]),
              const SizedBox(height: 8),
              CountUp(value: double.tryParse('${next?['balance'] ?? sum['balance']}') ?? 0, format: kes, style: Sc.head(24, color: Colors.white)),
              const SizedBox(height: 8),
              for (final it in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 3),
                  child: Row(children: [
                    Expanded(child: Text('${it['description']}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .9)))),
                    Text(kes(it['amount'], symbol: false), maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .9))),
                  ]),
                ),
              if (next == null) Text('Nothing to pay right now. Asante!', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .9))),
              if (next != null) ...[
                const SizedBox(height: 10),
                FilledButton(
                  style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: Sc.brand, minimumSize: const Size.fromHeight(44)),
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PayScreen(session: session, bill: next))),
                  child: const Text('Pay now'),
                ),
              ],
            ]),
            const SizedBox(height: 12),
            Row(children: [
              for (final t in _tiles)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Pressable(
                      child: Material(
                        color: Sc.blush,
                        borderRadius: BorderRadius.circular(12),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(12),
                          onTap: () => t.$1 == 'lease'
                              ? Navigator.of(context).push(MaterialPageRoute(
                                  builder: (_) => const ComingSoonScreen(
                                      title: 'Lease', text: 'Your lease, renewal dates and new terms to review and sign, in the app.', icon: PhosphorIconsLight.fileText)))
                              : openTab(t.$1),
                          child: SizedBox(
                            height: 66,
                            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                              Icon(t.$3, color: Sc.brand, size: 20),
                              const SizedBox(height: 4),
                              FittedBox(child: Text(t.$2, style: Sc.body(12, weight: 600))),
                            ]),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
            ]),
            const SectionTitle('Building notices', icon: PhosphorIconsLight.megaphone),
            if (((sum['notices'] as List?) ?? []).isEmpty)
              Text('Nothing new. Notices from your building and updates on your repairs and guests show here.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            for (final n in ((sum['notices'] as List?) ?? []).cast<Map>())
              KeyedSubtree(child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: n['screen'] == null ? null : () => openTab('${n['screen']}'),
                  child: Notice('${n['title']}', '${n['text']}', alert: n['alert'] == true),
                ),
              ),
            const SizedBox(height: 8),
            Pressable(
              child: Material(
                color: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: const BorderSide(color: Sc.blush2)),
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => WalletScreen(session: session))),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(color: Sc.brand, borderRadius: BorderRadius.circular(14)),
                        child: const Icon(PhosphorIconsLight.wallet, color: Colors.white),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('My wallet', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                        CountUp(value: credit, format: kes, style: Sc.head(18)),
                        Text(credit > 0 ? 'Paid ahead · used for your next bill' : 'Pay ahead and keep money here', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                      ])),
                      Text('Add money', style: Sc.body(13, weight: 600, color: Sc.brand)),
                      Icon(PhosphorIconsLight.caretRight, color: Sc.brand),
                    ]),
                  ),
                ),
              ),
            ),
            const SectionTitle('Your neighbourhood', icon: PhosphorIconsLight.mapTrifold),
            Row(children: [
              for (final n in [
                ('Offers', PhosphorIconsLight.tag, 'Deals from shops, fundis and services near you, picked for your building.'),
                ('Contributions', PhosphorIconsLight.handHeart, 'Chip in for a neighbour or a building cause together, with M-Pesa, and see the total grow.'),
              ])
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Tile(
                      filled: true,
                      onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ComingSoonScreen(title: n.$1, text: n.$3, icon: n.$2))),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Icon(n.$2, color: Sc.brand),
                        const SizedBox(height: 8),
                        Text(n.$1, style: Sc.body(15, weight: 600)),
                        Text('Coming soon', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                      ]),
                    ),
                  ),
                ),
            ]),
            if (session.screen('tenant_suggest') != null)
              Tile(
                onTap: () => openTab('tenant_suggest'),
                child: Row(children: [
                  Icon(PhosphorIconsLight.lightbulb, color: Sc.brand),
                  const SizedBox(width: 12),
                  Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('Suggestion box', style: Sc.body(15, weight: 600)),
                    Text('Anonymous ideas for your landlord', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                  ])),
                  const Icon(PhosphorIconsLight.caretRight),
                ]),
              ),
            const SectionTitle('Your bills', icon: PhosphorIconsLight.receipt),
            if (bills.isEmpty) const EmptyState('No bills yet.'),
            for (final b in bills) BillTile(bill: b, onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => BillScreen(session: session, id: b['id'] as int)))),
          ]);
        },
      );
}

class BillTile extends StatelessWidget {
  const BillTile({super.key, required this.bill, this.onTap, this.showTenant = false});
  final Map<String, dynamic> bill;
  final VoidCallback? onTap;
  final bool showTenant;
  @override
  Widget build(BuildContext context) {
    final paid = bill['status'] == 'paid' || (double.tryParse('${bill['balance']}') ?? 0) <= 0;
    return Tile(
      onTap: onTap,
      child: Row(children: [
        if (showTenant) ...[Avatar(bill['tenant'] as String?, size: 40), const SizedBox(width: 12)],
        Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(showTenant ? '${bill['tenant'] ?? 'Tenant'}' : '${bill['month_label'] ?? bill['number']}', style: Sc.body(15, weight: 600)),
          const SizedBox(height: 2),
          Text(showTenant ? '${bill['unit'] ?? ''} · ${bill['property'] ?? ''} · ${bill['month_label'] ?? ''}' : '${bill['number']} · total ${kes(bill['total'])}',
              maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text(paid ? kes(bill['total']) : kes(bill['balance']), style: Sc.body(15, weight: 600)),
          const SizedBox(height: 4),
          Pill(paid ? 'Paid' : (bill['status'] == 'partial' ? 'Part paid' : 'Unpaid'), tone: paid ? 'ok' : 'alert'),
        ]),
      ]),
    );
  }
}

// ---- One bill (canvas: "Pay October") ---------------------------------------------------------------------------

class BillScreen extends StatelessWidget {
  BillScreen({super.key, required this.session, required this.id, this.staff = false});
  final Session session;
  final int id;
  final bool staff;
  final _doc = GlobalKey();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Invoice')),
        body: Loader<Map<String, dynamic>>(
          load: () => session.api.get(staff ? '/bills/$id' : '/tenant/bills/$id'),
          builder: (context, b, _) {
            final owed = (double.tryParse('${b['balance']}') ?? 0) > 0;
            return ListView(padding: const EdgeInsets.fromLTRB(12, 6, 12, 24), children: [
              RepaintBoundary(key: _doc, child: InvoiceDocument(bill: b)),
              const SizedBox(height: 20),
              if (owed && !staff) ...[
                FilledButton(
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PayScreen(session: session, bill: b))),
                  child: Text('Pay ${kes(b['balance'])}'),
                ),
                const SizedBox(height: 10),
              ],
              if (owed && staff && session.screen('bills')?.can('share_link') == true) ...[
                FilledButton.icon(icon: const Icon(PhosphorIconsLight.whatsappLogo), label: const Text('Remind on WhatsApp'), onPressed: () => remindOnWhatsApp(context, b)),
                const SizedBox(height: 10),
              ],
              OutlinedButton.icon(
                icon: const Icon(PhosphorIconsLight.export),
                label: const Text('Share invoice'),
                onPressed: () => shareAsImage(context, _doc, '${b['number']}',
                    'Invoice ${b['number']} · ${b['month_label'] ?? ''} · balance ${kes(b['balance'])}${owed ? '. Pay here: ${b['pay_url']}' : ''}'),
              ),
            ]);
          },
        ),
      );
}

/// Opens WhatsApp with the tenant's bill link filled in (the landlord sends it from their own phone).
Future<void> remindOnWhatsApp(BuildContext context, Map<String, dynamic> b) async {
  final digits = '${b['phone'] ?? ''}'.replaceAll(RegExp(r'\D'), '');
  final phone = digits.length >= 9 ? '254${digits.substring(digits.length - 9)}' : '';
  final text = 'Hello ${(b['tenant'] ?? '').toString().split(' ').first}, your ${b['month_label'] ?? ''} bill for ${b['unit'] ?? ''} '
      'has ${kes(b['balance'])} left to pay. Pay here: ${b['pay_url']}';
  final ok = await launchUrl(Uri.parse('https://wa.me/$phone?text=${Uri.encodeComponent(text)}'), mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) toast(context, 'WhatsApp is not installed.');
}

// ---- Receipts ---------------------------------------------------------------------------------------------------

class ReceiptsScreen extends StatelessWidget {
  const ReceiptsScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => Loader<Map<String, dynamic>>(
        load: () => session.api.get('/tenant/payments'),
        builder: (context, d, _) {
          final rows = ((d['data'] as List?) ?? []).cast<Map<String, dynamic>>();
          final total = rows.fold<double>(0, (t, p) => t + (double.tryParse('${p['amount']}') ?? 0));
          return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
            Text('Receipts', style: Sc.title(18)),
            const SizedBox(height: 12),
            HeroCard(children: [
              Text('PAID SO FAR', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .85))),
              const SizedBox(height: 6),
              CountUp(value: total, format: kes, style: Sc.head(24, color: Colors.white)),
              Text('${rows.length} payments · tap one for its receipt', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .9))),
            ]),
            const SizedBox(height: 14),
            if (rows.isEmpty) const EmptyPanel(icon: PhosphorIconsLight.receipt, title: 'No receipts yet', text: 'Every payment you make shows here with a receipt you can share.'),
            for (final (i, p) in rows.indexed) PaymentRow(payment: p, session: session, order: i),
          ]);
        },
      );
}

// ---- Repairs (canvas: "Report a repair") ------------------------------------------------------------------------

class TenantRepairs extends StatefulWidget {
  const TenantRepairs({super.key, required this.session});
  final Session session;
  @override
  State<TenantRepairs> createState() => _TenantRepairsState();
}

class _TenantRepairsState extends State<TenantRepairs> {
  Key _k = UniqueKey();

  Future<void> _report() async {
    final done = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => ReportRepairScreen(session: widget.session)));
    if (done == true) setState(() => _k = UniqueKey());
  }

  @override
  Widget build(BuildContext context) {
    final canReport = widget.session.screen('tenant_repairs')?.can('create') == true;
    return Loader<Map<String, dynamic>>(
      key: _k,
      load: () => widget.session.api.get('/tenant/repairs'),
      builder: (context, d, _) {
        final rows = ((d['data'] as List?) ?? []).cast<Map<String, dynamic>>();
        return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
          Text('Repairs', style: Sc.body(18, weight: 600)),
          const SizedBox(height: 14),
          if (canReport) FilledButton.icon(onPressed: _report, icon: const Icon(PhosphorIconsLight.plus), label: const Text('Report a repair')),
          const SizedBox(height: 14),
          if (rows.isEmpty)
            const EmptyPanel(icon: PhosphorIconsLight.wrench, title: 'No repairs', text: 'Something broken? Report it with a photo and follow the fundi here.'),
          for (final r in rows) (RepairTicket(repair: r)),
        ]);
      },
    );
  }
}

/// The ticket card (canvas: "Ticket #R-2291 · Fundi assigned"), with share and copy.
class RepairTicket extends StatelessWidget {
  const RepairTicket({super.key, required this.repair});
  final Map<String, dynamic> repair;
  @override
  Widget build(BuildContext context) {
    final r = repair;
    final status = r['assigned_to'] != null && r['status'] == 'open' ? 'Fundi assigned' : RepairTile.statusLabel(r['status'] as String?);
    final text = 'Repair ticket #${r['number']}: ${r['title']} (${r['unit'] ?? ''} ${r['property'] ?? ''}). Status: $status.';
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Sc.blush, borderRadius: BorderRadius.circular(12)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('Ticket #${r['number']}', style: Sc.body(13, weight: 600))),
          Text(status, style: Sc.body(13, weight: 600, color: r['status'] == 'completed' ? Sc.ok : Sc.brand)),
        ]),
        const SizedBox(height: 8),
        Text('${r['title']}', style: Sc.body(15, weight: 600)),
        if (r['assigned_to'] != null) ...[
          const SizedBox(height: 10),
          Row(children: [
            Avatar(r['assigned_to'] as String?, size: 40, light: false),
            const SizedBox(width: 10),
            Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${r['assigned_to']}', style: Sc.body(13, weight: 600)),
              Text(r['scheduled_date'] != null ? 'Coming ${r['scheduled_date']}' : 'Will call before coming', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            ])),
          ]),
        ],
        if (photoUrls(r['photos']).isNotEmpty) ...[
          const SizedBox(height: 10),
          Row(children: [
            for (final p in photoUrls(r['photos']).take(3))
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ClipRRect(borderRadius: BorderRadius.circular(12), child: NetPhoto(p, size: 56)),
              ),
          ]),
        ],
        const SizedBox(height: 4),
        Row(children: [
          TextButton.icon(onPressed: () => shareOnWhatsApp(context, text), icon: const Icon(PhosphorIconsLight.whatsappLogo, size: 18), label: const Text('WhatsApp')),
          TextButton.icon(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: '${r['number']}'));
              if (context.mounted) toast(context, 'Ticket number copied.');
            },
            icon: const Icon(PhosphorIconsLight.copy, size: 18),
            label: const Text('Copy number'),
          ),
        ]),
      ]),
    );
  }
}

class RepairTile extends StatelessWidget {
  const RepairTile({super.key, required this.repair, this.onTap});
  final Map<String, dynamic> repair;
  final VoidCallback? onTap;
  static String statusLabel(String? s) => switch (s) {
        'open' => 'Open',
        'in_progress' => 'In progress',
        'pending_parts' => 'Waiting for parts',
        'completed' => 'Done',
        'cancelled' => 'Cancelled',
        _ => s ?? '',
      };
  @override
  Widget build(BuildContext context) {
    final r = repair;
    final urgent = r['priority'] == 'emergency' || r['priority'] == 'high';
    return Tile(
      onTap: onTap,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('#${r['number']} · ${r['unit'] ?? ''} ${r['property'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted))),
          Text(shortDate(r['created_at'] as String?), maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
        ]),
        const SizedBox(height: 4),
        Text('${r['title']}', style: Sc.body(15, weight: 600)),
        const SizedBox(height: 8),
        Wrap(spacing: 6, runSpacing: 6, children: [
          Pill(statusLabel(r['status'] as String?), tone: r['status'] == 'completed' ? 'ok' : 'neutral'),
          if (urgent) Pill(r['priority'] == 'emergency' ? 'Emergency' : 'Urgent', tone: 'alert'),
          if (r['assigned_to'] != null) Pill('${r['assigned_to']}'),
        ]),
      ]),
    );
  }
}

class ReportRepairScreen extends StatefulWidget {
  const ReportRepairScreen({super.key, required this.session});
  final Session session;
  @override
  State<ReportRepairScreen> createState() => _ReportRepairScreenState();
}

class _ReportRepairScreenState extends State<ReportRepairScreen> {
  static const _cats = {'plumbing': 'Plumbing', 'electrical': 'Electrical', 'structural': 'Doors & locks', 'pest_control': 'Pests', 'appliance': 'Appliance', 'other': 'Other'};
  String _cat = 'plumbing';
  final _what = TextEditingController();
  final _photos = <File>[];
  bool _caretaker = true;
  bool _busy = false;

  Future<void> _addPhoto() async {
    final src = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(PhosphorIconsLight.camera), title: const Text('Take a photo'), onTap: () => Navigator.of(c).pop(ImageSource.camera)),
          ListTile(leading: const Icon(PhosphorIconsLight.image), title: const Text('Choose from gallery'), onTap: () => Navigator.of(c).pop(ImageSource.gallery)),
        ]),
      ),
    );
    if (src == null) return;
    final x = await ImagePicker().pickImage(source: src, maxWidth: 1600, imageQuality: 75);
    if (x != null) setState(() => _photos.add(File(x.path)));
  }

  Future<void> _send() async {
    final text = _what.text.trim();
    if (text.length < 5) return toast(context, "Say what's wrong.");
    setState(() => _busy = true);
    try {
      await widget.session.api.upload('/tenant/repairs', {
        'title': text.length > 60 ? '${text.substring(0, 57)}…' : text,
        'description': text,
        'category': _cat,
        'caretaker_can_let_in': _caretaker ? '1' : '0',
      }, files: _photos);
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: ListView(padding: const EdgeInsets.fromLTRB(12, 0, 12, 24), children: [
          Text('Report a repair', style: Sc.body(18, weight: 600)),
          const SizedBox(height: 14),
          PillTabs<String>(options: [for (final c in _cats.entries) (c.key, c.value)], selected: _cat, onSelect: (c) => setState(() => _cat = c)),
          const SizedBox(height: 16),
          Text("What's wrong?", style: Sc.body(13, weight: 600)),
          const SizedBox(height: 6),
          TextField(controller: _what, maxLines: 3, decoration: const InputDecoration(hintText: 'Kitchen sink is leaking under the cabinet.')),
          const SizedBox(height: 14),
          Row(children: [
            for (final (i, p) in _photos.indexed)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: Stack(children: [
                  ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.file(p, width: 72, height: 72, cacheWidth: 72 * 3, fit: BoxFit.cover)),
                  Positioned(
                    right: 2,
                    top: 2,
                    child: InkWell(
                      onTap: () => setState(() => _photos.removeAt(i)),
                      child: const CircleAvatar(radius: 11, backgroundColor: Colors.white, child: Icon(PhosphorIconsLight.x, size: 14, color: Sc.ink)),
                    ),
                  ),
                ]),
              ),
            if (_photos.length < 3)
              InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: _addPhoto,
                child: Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFCA5A5), width: 2)),
                  child: Icon(PhosphorIconsLight.plus, color: Sc.brand, semanticLabel: 'Add a photo'),
                ),
              ),
          ]),
          const SizedBox(height: 8),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: _caretaker,
            onChanged: (v) => setState(() => _caretaker = v ?? false),
            title: Text("Caretaker can let the fundi in if I'm out", style: Sc.body(14)),
          ),
          const SizedBox(height: 12),
          FilledButton(onPressed: _busy ? null : _send, child: Text(_busy ? 'Sending…' : 'Send to my manager')),
        ]),
      );
}

// ---- Suggestion box (anonymous) ---------------------------------------------------------------------------------

class SuggestScreen extends StatefulWidget {
  const SuggestScreen({super.key, required this.session});
  final Session session;
  @override
  State<SuggestScreen> createState() => _SuggestScreenState();
}

class _SuggestScreenState extends State<SuggestScreen> {
  static const _topics = {'general': 'General', 'security': 'Security', 'cleanliness': 'Cleanliness', 'water': 'Water',
    'noise': 'Noise', 'management': 'Management'};
  String _topic = 'general';
  final _text = TextEditingController();
  bool _busy = false;
  bool _sent = false;

  Future<void> _send() async {
    if (_text.text.trim().length < 5) return toast(context, 'Write a few words first.');
    setState(() => _busy = true);
    try {
      await widget.session.api.post('/tenant/suggestions', {'topic': _topic, 'body': _text.text.trim()});
      setState(() {
        _sent = true;
        _text.clear();
      });
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
        Text('Suggestion box', style: Sc.title(18)),
        const SizedBox(height: 8),
        const Notice('Anonymous', 'Management sees your words and the topic only: not your name, unit or phone.'),
        if (_sent)
          Appear(
            child: Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: const Color(0xFFDCFCE7), borderRadius: BorderRadius.circular(12)),
              child: Row(children: [
                const Icon(PhosphorIconsFill.checkCircle, color: Sc.ok),
                const SizedBox(width: 10),
                Expanded(child: Text('Sent. Asante for helping make the building better.', style: Sc.body(14, weight: 600, color: Sc.ok))),
              ]),
            ),
          ),
        const SizedBox(height: 12),
        PillTabs<String>(options: [for (final t in _topics.entries) (t.key, t.value)], selected: _topic, onSelect: (t) => setState(() => _topic = t)),
        const SizedBox(height: 14),
        TextField(controller: _text, maxLines: 5, decoration: const InputDecoration(labelText: 'Your suggestion', hintText: 'The light at the back gate is off at night.')),
        const SizedBox(height: 16),
        FilledButton(onPressed: _busy ? null : _send, child: Text(_busy ? 'Sending…' : 'Send anonymously')),
      ]);
}

// ---- Pay (canvas: "Pay October") --------------------------------------------------------------------------------

/// The ways this landlord accepts rent: M-Pesa prompt, Sharet Pay, their paybill, bank standing order.
class PayScreen extends StatefulWidget {
  const PayScreen({super.key, required this.session, required this.bill});
  final Session session;
  final Map<String, dynamic> bill;
  @override
  State<PayScreen> createState() => _PayScreenState();
}

class _PayScreenState extends State<PayScreen> {
  String? _picked;
  bool _busy = false;
  late final _amount = TextEditingController(text: kes(widget.bill['balance'], symbol: false).replaceAll(',', ''));

  static const _icons = {'credit': PhosphorIconsLight.piggyBank, 'mpesa': PhosphorIconsLight.deviceMobile, 'sharet': PhosphorIconsLight.wallet, 'paybill': PhosphorIconsLight.numpad, 'bank': PhosphorIconsLight.bank};

  Future<void> _go(Map<String, dynamic> m) async {
    final amount = double.tryParse(_amount.text.replaceAll(',', ''));
    final key = m['key'] as String;
    if (key == 'mpesa' || key == 'sharet' || key == 'credit') {
      // One PIN (or the phone's lock) confirms every payment.
      final ok = await confirmPayment(context, widget.session, 'Pay ${kes(amount ?? widget.bill['balance'])} · ${m['title']}');
      if (ok == null || !mounted) return;
      if (key == 'credit') return _payFromCredit(ok, amount);
    }
    switch (key) {
      case 'mpesa':
        await openPayLink(context, m['url'] as String? ?? widget.bill['pay_url'] as String?);
      case 'sharet':
        setState(() => _busy = true);
        try {
          final r = await widget.session.api.post('/tenant/bills/${widget.bill['id']}/sharet', {'amount': ?amount});
          if (mounted) await openPayLink(context, r['url'] as String?);
        } catch (e) {
          if (mounted) toast(context, e);
        } finally {
          if (mounted) setState(() => _busy = false);
        }
      default:
        await Clipboard.setData(ClipboardData(text: '${m['key'] == 'paybill' ? m['account'] : m['account_number']}'));
        if (mounted) toast(context, m['key'] == 'paybill' ? 'Account number copied.' : 'Account number copied.');
    }
  }

  Future<void> _payFromCredit(PayConfirm ok, double? amount) async {
    setState(() => _busy = true);
    try {
      final r = await widget.session.api.post('/tenant/bills/${widget.bill['id']}/pay-credit', {...ok.fields, 'amount': ?amount});
      if (!mounted) return;
      toast(context, 'Paid ${kes(r['paid'])} from your credit. ${kes(r['credit'])} credit left.');
      Navigator.of(context).pop();
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: Loader<Map<String, dynamic>>(
          load: () => widget.session.api.get('/tenant/bills/${widget.bill['id']}/pay'),
          builder: (context, d, _) {
            // M-Pesa first, as drawn; then credit, Sharet Pay, paybill, bank.
            const order = ['mpesa', 'credit', 'sharet', 'paybill', 'bank'];
            final methods = ((d['methods'] as List?) ?? []).cast<Map<String, dynamic>>()
              ..sort((a, b) => order.indexOf('${a['key']}').compareTo(order.indexOf('${b['key']}')));
            final picked = methods.firstWhere((m) => m['key'] == (_picked ?? methods.first['key']), orElse: () => methods.first);
            final instant = picked['key'] == 'mpesa' || picked['key'] == 'sharet' || picked['key'] == 'credit';
            return ListView(padding: const EdgeInsets.fromLTRB(12, 0, 12, 24), children: [
              Text('Pay ${'${widget.bill['month_label'] ?? ''}'.split(' ').first}', style: Sc.body(18, weight: 600)),
              const SizedBox(height: 14),
              Text('Amount', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
              const SizedBox(height: 6),
              TextField(
                controller: _amount,
                enabled: instant,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                style: Sc.head(22),
                decoration: InputDecoration(
                  prefixText: 'KES ',
                  prefixStyle: Sc.head(22),
                  enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Sc.brand, width: 2)),
                  disabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Sc.blush2, width: 2)),
                ),
              ),
              const SizedBox(height: 6),
              Text('Paying less? Part payments are fine. Your manager sees the plan.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
              const SizedBox(height: 18),
              Text('Pay with', style: Sc.body(13, weight: 600)),
              const SizedBox(height: 8),
              for (final m in methods)
                KeyedSubtree(child: Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Material(
                      color: picked == m ? Colors.white : Sc.blush,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12), side: BorderSide(color: picked == m ? Sc.brand : Colors.transparent, width: 2)),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () => setState(() => _picked = m['key'] as String),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Row(children: [
                            Icon(_icons[m['key']] ?? PhosphorIconsLight.money, color: Sc.brand),
                            const SizedBox(width: 12),
                            Expanded(
                                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(m['key'] == 'mpesa' && m['phone'] != null ? 'M-Pesa · ${m['phone']}' : '${m['title']}', style: Sc.body(15, weight: 600)),
                              Text('${m['subtitle']}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                            ])),
                            Icon(picked == m ? PhosphorIconsFill.radioButton : PhosphorIconsLight.circle, color: picked == m ? Sc.brand : Sc.muted),
                          ]),
                        ),
                      ),
                    ),
                  ),
                ),
              if (picked['key'] == 'paybill') _details([('Paybill', '${picked['paybill']}'), ('Account', '${picked['account']}')],
                  'On M-Pesa: Lipa na M-Pesa › Pay Bill. Your payment is matched to your unit automatically.'),
              if (picked['key'] == 'bank') _details([
                    ('Bank', '${picked['bank'] ?? ''}'),
                    ('Account name', '${picked['account_name'] ?? ''}'),
                    ('Account number', '${picked['account_number'] ?? ''}'),
                    if (picked['branch'] != null) ('Branch', '${picked['branch']}'),
                    ('Reference', '${picked['reference']}'),
                    if (picked['monthly'] != null) ('Monthly amount', kes(picked['monthly'])),
                  ], '${picked['note'] ?? 'Ask your bank to pay this account every month, before the due day, with your unit as the reference.'}'),
              CheckboxListTile(
                contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                value: false,
                onChanged: null,
                title: Text('Auto-pay with M-Pesa on the 1st', style: Sc.body(15)),
                subtitle: Text('Coming soon', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
              ),
              const SizedBox(height: 10),
              FilledButton(
                onPressed: _busy ? null : () => _go(picked),
                child: Text(switch (picked['key']) {
                  'credit' => _busy ? 'Paying…' : 'Pay from my credit',
                  'mpesa' => 'Send M-Pesa prompt',
                  'sharet' => _busy ? 'Opening…' : 'Continue to Sharet Pay',
                  'paybill' => 'Copy account number',
                  _ => 'Copy account number',
                }),
              ),
            ]);
          },
        ),
      );

  Widget _details(List<(String, String)> rows, String note) => Appear(
        child: Container(
          margin: const EdgeInsets.only(top: 4, bottom: 6),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(border: Border.all(color: Sc.blush2), borderRadius: BorderRadius.circular(12)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            for (final r in rows) CopyLine(r.$1, r.$2),
            const SizedBox(height: 6),
            Text(note, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
          ]),
        ),
      );
}

// ---- Wallet: money paid ahead -----------------------------------------------------------------------------------

class WalletScreen extends StatelessWidget {
  const WalletScreen({super.key, required this.session});
  final Session session;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: Loader<Map<String, dynamic>>(
          load: () => session.api.get('/tenant/wallet'),
          builder: (context, w, _) {
            final tx = ((w['transactions'] as List?) ?? []).cast<Map<String, dynamic>>();
            final add = ((w['add_money'] as List?) ?? []).cast<Map<String, dynamic>>();
            return ListView(padding: const EdgeInsets.fromLTRB(12, 0, 12, 24), children: [
              Text('My wallet', style: Sc.body(18, weight: 600)),
              const SizedBox(height: 14),
              HeroCard(children: [
                Text('BALANCE', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .85))),
                const SizedBox(height: 6),
                CountUp(value: double.tryParse('${w['balance']}') ?? 0, format: kes, style: Sc.head(24, color: Colors.white)),
                const SizedBox(height: 4),
                Text('Money paid ahead. Your next bill can be paid from it.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .9))),
              ]),
              const SectionTitle('Add money', icon: PhosphorIconsLight.plusCircle),
              for (final a in add)
                Tile(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${a['title']}', style: Sc.body(15, weight: 600)),
                    if (a['paybill'] != null) ...[
                      const SizedBox(height: 6),
                      CopyLine('Paybill', '${a['paybill']}'),
                      CopyLine('Account', '${a['account']}'),
                    ],
                    const SizedBox(height: 4),
                    Text('${a['note']}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                  ]),
                ),
              const SectionTitle('History', icon: PhosphorIconsLight.clockCounterClockwise),
              if (tx.isEmpty) const EmptyPanel(icon: PhosphorIconsLight.wallet, title: 'Nothing here yet', text: 'Money you pay ahead, and bills paid from it, show here.'),
              for (final t in tx)
                KeyedSubtree(child: Tile(
                    child: Row(children: [
                      Icon(t['type'] == 'deposit' ? PhosphorIconsLight.arrowDownLeft : PhosphorIconsLight.arrowUpRight, color: t['type'] == 'deposit' ? Sc.ok : Sc.brand),
                      const SizedBox(width: 12),
                      Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${t['description']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: Sc.body(14, weight: 600)),
                        Text(dateTime(t['at'] as String?), maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                      ])),
                      Text('${t['type'] == 'deposit' ? '+' : '−'}${kes(t['amount'])}',
                          style: Sc.body(14, weight: 700, color: t['type'] == 'deposit' ? Sc.ok : Sc.ink)),
                    ]),
                  ),
                ),
            ]);
          },
        ),
      );
}
