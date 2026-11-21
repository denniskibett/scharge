import 'package:flutter/material.dart';

import '../core/session.dart';
import '../core/theme.dart';
import '../shared/motion.dart';
import '../shared/ui.dart';

/// Water for the flat the tenant lives in (canvas: "Water · B04"): this month, monthly bars, the last reading with
/// who read it and the photo, the rate, and "Query this reading".
class WaterScreen extends StatelessWidget {
  const WaterScreen({super.key, required this.session});
  final Session session;

  @override
  Widget build(BuildContext context) => Loader<Map<String, dynamic>>(
        load: () => session.api.get('/tenant/water'),
        builder: (context, w, reload) {
          final history = ((w['history'] as List?) ?? []).cast<Map<String, dynamic>>();
          final now = w['this_month'] as Map<String, dynamic>?;
          final last = w['last_reading'] as Map<String, dynamic>?;
          final avg = (w['average'] as num?)?.toDouble();
          final used = (now?['consumption'] as num?)?.toDouble() ?? 0;
          final unitOf = (w['meter']?['unit_of_measure'] ?? 'm³').toString().replaceAll('m3', 'm³');
          final max = history.fold<double>(0, (m, h) => ((h['consumption'] as num?)?.toDouble() ?? 0) > m ? (h['consumption'] as num).toDouble() : m);
          final high = avg != null && avg > 0 && used > avg * 1.5;
          Widget tile(String label, Widget value) => Expanded(
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: Sc.blush, borderRadius: BorderRadius.circular(12)),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(label, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                    const SizedBox(height: 4),
                    FittedBox(alignment: Alignment.centerLeft, fit: BoxFit.scaleDown, child: value),
                  ]),
                ),
              );
          return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
            Text('Water · ${w['unit'] ?? ''}', style: Sc.body(18, weight: 600)),
            const SizedBox(height: 14),
            Appear(
              child: Row(children: [
                if (w['billing'] == 'fixed')
                  tile('Fixed each month', Text(kes(w['fixed_charge']), style: Sc.head(20)))
                else ...[
                  tile('This month', CountUp(value: used, format: (v) => '${v.toStringAsFixed(1)} $unitOf', style: Sc.head(20))),
                  const SizedBox(width: 10),
                  tile('Bill', Text(kes(now?['charge'] ?? 0), style: Sc.head(20))),
                ],
              ]),
            ),
            if (avg != null && w['billing'] != 'fixed')
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(high ? 'Much more than your usual ${avg.toStringAsFixed(1)} $unitOf. Check for leaks.' : 'Your usual month: ${avg.toStringAsFixed(1)} $unitOf.',
                    style: Sc.body(13, color: high ? Sc.alert : Sc.muted)),
              ),
            if (history.isNotEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 18, 4, 4),
                child: SizedBox(
                  height: 150,
                  child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    for (final (i, h) in history.indexed)
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 5),
                          child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
                            Tooltip(
                              message: '${((h['consumption'] as num?) ?? 0).toStringAsFixed(1)} $unitOf · ${kes(h['charge'])}',
                              child: TweenAnimationBuilder<double>(
                                tween: Tween(begin: 0, end: max > 0 ? (((h['consumption'] as num?) ?? 0) / max) : 0),
                                duration: Duration(milliseconds: reduceMotion(context) ? 0 : 500 + i * 60),
                                curve: Curves.easeOutCubic,
                                builder: (context, v, _) => Container(
                                  height: 8 + 112 * v,
                                  decoration: BoxDecoration(
                                    color: i == history.length - 1 ? Sc.brand : Sc.blush2,
                                    borderRadius: const BorderRadius.vertical(top: Radius.circular(8), bottom: Radius.circular(2)),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text('${h['label']}', style: Sc.body(11, color: Sc.muted)),
                          ]),
                        ),
                      ),
                  ]),
                ),
              ),
            if (last != null)
              Appear(
                child: Container(
                  margin: const EdgeInsets.only(top: 14),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(border: Border.all(color: Sc.blush2), borderRadius: BorderRadius.circular(12)),
                  child: Row(children: [
                    Container(
                      width: 80,
                      height: 64,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(color: const Color(0xFF2F3538), borderRadius: BorderRadius.circular(10)),
                      child: FittedBox(
                        child: Padding(
                          padding: const EdgeInsets.all(6),
                          child: Text('${w['meter']?['serial_number'] ?? ''}'.split(RegExp(r'[-\s]')).last,
                              style: Sc.head(13, color: const Color(0xFFF2D9A0))),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('Read ${shortDate(last['read_at'] as String?)}${last['read_by'] != null ? ' by ${'${last['read_by']}'.split(' ').first}' : ''}',
                            style: Sc.body(13, weight: 600)),
                        const SizedBox(height: 3),
                        Text('Previous ${last['previous']} → now ${last['reading']} $unitOf', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
                        if (last['status'] == 'flagged') Text('Being checked by your manager', style: Sc.body(12, color: Sc.alert)),
                        if (last['photo_url'] != null)
                          InkWell(
                            onTap: () => showDialog(
                              context: context,
                              builder: (c) => Dialog(
                                clipBehavior: Clip.antiAlias,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                child: InteractiveViewer(child: Image.network('${last['photo_url']}', fit: BoxFit.contain,
                                    errorBuilder: (_, _, _) => const Padding(padding: EdgeInsets.all(24), child: Text('The meter photo is not available.')))),
                              ),
                            ),
                            child: Padding(
                              padding: const EdgeInsets.only(top: 3),
                              child: Text('See photo proof',
                                  style: Sc.body(12, color: Sc.brand).copyWith(decoration: TextDecoration.underline, decorationColor: Sc.brand)),
                            ),
                          ),
                      ]),
                    ),
                  ]),
                ),
              ),
            if (w['rate'] != null && w['billing'] != 'fixed')
              Padding(padding: const EdgeInsets.only(top: 12), child: Text('Rate ${kes(w['rate'])} per $unitOf, set in your lease.', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted))),
            const SizedBox(height: 28),
            if (session.screen('tenant_water')?.can('query') == true)
              OutlinedButton(
                style: OutlinedButton.styleFrom(side: const BorderSide(color: Color(0xFFFCA5A5))),
                onPressed: () => _query(context),
                child: const Text('Dispute this reading'),
              ),
          ]);
        },
      );

  Future<void> _query(BuildContext context) async {
    final c = TextEditingController();
    final note = await showDialog<String>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('Dispute this reading'),
        content: TextField(controller: c, maxLines: 3, autofocus: true, decoration: const InputDecoration(hintText: 'What looks wrong?')),
        actions: [
          TextButton(onPressed: () => Navigator.of(d).pop(), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(d).pop(c.text.trim()), child: const Text('Send')),
        ],
      ),
    );
    if (note == null || note.length < 5 || !context.mounted) return;
    try {
      final r = await session.api.post('/tenant/water/query', {'note': note});
      if (context.mounted) toast(context, '${r['message']}');
    } catch (e) {
      if (context.mounted) toast(context, e);
    }
  }
}

/// Planned for tenants, shown as "Coming soon" so nobody expects them to work yet.
class ComingSoonScreen extends StatelessWidget {
  const ComingSoonScreen({super.key, required this.title, required this.text, required this.icon});
  final String title;
  final String text;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title)),
        body: Padding(
          padding: const EdgeInsets.all(28),
          child: Appear(
            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Container(
                width: 84,
                height: 84,
                decoration: const BoxDecoration(color: Sc.blush, shape: BoxShape.circle),
                child: Icon(icon, size: 40, color: Sc.brand),
              ),
              const SizedBox(height: 16),
              const Pill('Coming soon', tone: 'brand'),
              const SizedBox(height: 12),
              Text(title, style: Sc.title(18), textAlign: TextAlign.center),
              const SizedBox(height: 8),
              Text(text, style: Sc.body(15, color: Sc.muted), textAlign: TextAlign.center),
            ]),
          ),
        ),
      );
}
