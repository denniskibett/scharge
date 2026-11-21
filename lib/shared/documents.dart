import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'ph.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/session.dart';
import '../core/theme.dart';
import 'motion.dart';
import 'ui.dart';

/// Shares what is inside [key]'s RepaintBoundary as a PNG (receipt, invoice, gate ticket), with a text line.
Future<void> shareAsImage(BuildContext context, GlobalKey key, String name, String text) async {
  try {
    final boundary = key.currentContext!.findRenderObject() as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 3);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    final file = File('${(await getTemporaryDirectory()).path}/$name.png');
    await file.writeAsBytes(bytes!.buffer.asUint8List());
    await SharePlus.instance.share(ShareParams(files: [XFile(file.path, mimeType: 'image/png')], text: text));
  } catch (e) {
    if (context.mounted) toast(context, 'Could not share: $e');
  }
}

Future<void> shareOnWhatsApp(BuildContext context, String text, {String? phone}) async {
  final digits = (phone ?? '').replaceAll(RegExp(r'\D'), '');
  final to = digits.length >= 9 ? '254${digits.substring(digits.length - 9)}' : '';
  final ok = await launchUrl(Uri.parse('https://wa.me/$to?text=${Uri.encodeComponent(text)}'), mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) toast(context, 'WhatsApp is not installed.');
}

/// The letterhead on shared documents: the landlord (or white label) name, and scharge's mark.
class _DocHead extends StatelessWidget {
  const _DocHead({required this.title, required this.number, required this.issuer, this.status});
  final String title;
  final String number;
  final String? issuer;
  final Widget? status;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        color: Sc.brand,
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(issuer ?? 'scharge', style: Sc.body(13, weight: 600, color: Colors.white.withValues(alpha: .9))),
            const SizedBox(height: 6),
            Text(title, style: Sc.title(19, color: Colors.white)),
            const SizedBox(height: 2),
            Text(number, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Colors.white.withValues(alpha: .9))),
          ])),
          ?status,
        ]),
      );
}

Widget _kv(String k, String? v, {bool strong = false, Color? color, IconData? icon}) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (icon != null) ...[Icon(icon, size: 17, color: Sc.muted), const SizedBox(width: 8)],
        Text(k, style: Sc.body(12, color: Sc.muted)),
        const SizedBox(width: 12),
        // Values flush right, in one column with the amounts (invoice and receipt).
        Expanded(child: Text(v ?? '-', maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.right,
            style: Sc.body(strong ? 15 : 14, weight: strong ? 600 : 500, color: color))),
      ]),
    );

// The border is drawn on top (foregroundDecoration) so the red header reaches every edge of the card.
Widget _paper(Widget child) => Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
      ),
      foregroundDecoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: Sc.blush2)),
      clipBehavior: Clip.antiAlias,
      child: child,
    );

/// A label and a value on ONE line that can be copied (references, account numbers).
class CopyLine extends StatelessWidget {
  const CopyLine(this.label, this.value, {super.key, this.strong = true});
  final String label;
  final String? value;
  final bool strong;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Text(label, style: Sc.body(12, color: Sc.muted)),
          // Copy sits by the label, so the value stays flush right with the other figures.
          if (value != null && value!.isNotEmpty)
            InkResponse(
              radius: 18,
              onTap: () async {
                await Clipboard.setData(ClipboardData(text: value!));
                if (context.mounted) toast(context, '$label copied.');
              },
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                child: Icon(PhosphorIconsLight.copy, size: 16, color: Sc.brand, semanticLabel: 'Copy $label'),
              ),
            ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(value ?? '-', maxLines: 1, softWrap: false, overflow: TextOverflow.ellipsis, textAlign: TextAlign.right,
                style: Sc.body(strong ? 15 : 14, weight: strong ? 600 : 500)),
          ),
        ]),
      );
}

String _statusLabel(Map b) {
  final balance = double.tryParse('${b['balance']}') ?? 0;
  if (b['status'] == 'paid' || balance <= 0) return 'PAID';
  return b['status'] == 'partial' ? 'PART PAID' : 'UNPAID';
}

/// An invoice laid out like the dashboard's: details, billed to, line items with what is paid, summary, payments.
class InvoiceDocument extends StatelessWidget {
  const InvoiceDocument({super.key, required this.bill});
  final Map<String, dynamic> bill;

  @override
  Widget build(BuildContext context) {
    final b = bill;
    final items = ((b['items'] as List?) ?? []).cast<Map>();
    final payments = ((b['payments'] as List?) ?? []).cast<Map>();
    final label = _statusLabel(b);
    final paid = label == 'PAID';
    return _paper(Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      _DocHead(
        title: 'Invoice',
        number: '${b['number']}',
        issuer: b['issued_by'] as String?,
        status: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(999)),
          child: Text(label, style: Sc.body(11, weight: 700, color: paid ? Sc.ok : Sc.alert)),
        ),
      ),
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('BILLED TO', style: Sc.body(11, weight: 600, color: Sc.brand)),
            const SizedBox(height: 4),
            Text('${b['tenant'] ?? ''}', style: Sc.body(15, weight: 600)),
            Text('Unit ${b['unit'] ?? '-'}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            Text('${b['property'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('BILLING MONTH', style: Sc.body(11, weight: 600, color: Sc.brand)),
            const SizedBox(height: 4),
            Text('${b['month_label'] ?? '-'}', style: Sc.body(14, weight: 600)),
            const SizedBox(height: 8),
            Text('ISSUED', style: Sc.body(11, weight: 600, color: Sc.brand)),
            Text(shortDate(b['created_at'] as String?), style: Sc.body(14, weight: 600)),
          ]),
        ]),
      ),
      const SizedBox(height: 12),
      Container(
        color: Sc.blush,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(children: [
          Expanded(child: Text('ITEM', style: Sc.body(11, weight: 700, color: Sc.muted))),
          SizedBox(width: 82, child: Text('AMOUNT', textAlign: TextAlign.right, style: Sc.body(11, weight: 700, color: Sc.muted))),
          SizedBox(width: 70, child: Text('PAID', textAlign: TextAlign.right, style: Sc.body(11, weight: 700, color: Sc.muted))),
        ]),
      ),
      for (final it in items)
        KeyedSubtree(child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
            decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Sc.blush2))),
            child: Row(children: [
              Expanded(child: Text('${it['description']}', style: Sc.body(14))),
              SizedBox(width: 82, child: Text(kes(it['amount'], symbol: false), textAlign: TextAlign.right, style: Sc.body(14, weight: 500))),
              SizedBox(
                  width: 70,
                  child: Text(kes(it['paid'], symbol: false),
                      textAlign: TextAlign.right, style: Sc.body(13, color: (double.tryParse('${it['paid']}') ?? 0) > 0 ? Sc.ok : Sc.muted))),
            ]),
          ),
        ),
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
        child: Column(children: [
          _kv('Total', kes(b['total'])),
          _kv('Amount paid', kes(b['paid']), color: Sc.ok),
          const Divider(color: Sc.blush2),
          Row(children: [
            Expanded(child: Text('Balance due', style: Sc.body(15, weight: 600))),
            Text(kes(b['balance']), style: Sc.head(20, color: paid ? Sc.ok : Sc.brand)),
          ]),
        ]),
      ),
      if (payments.isNotEmpty) ...[
        Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 4), child: Text('PAYMENTS', style: Sc.body(11, weight: 700, color: Sc.muted))),
        for (final p in payments)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(children: [
              const Icon(PhosphorIconsFill.checkCircle, size: 18, color: Sc.ok),
              const SizedBox(width: 8),
              Expanded(child: Text('${p['reference'] ?? p['method'] ?? ''} · ${shortDate(p['paid_at'] as String?)}', style: Sc.body(13))),
              Text(kes(p['amount']), style: Sc.body(13, weight: 600)),
            ]),
          ),
      ],
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
        child: Text('Thank you. Pay with M-Pesa from the scharge app or the link in your SMS.',
            textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
      ),
    ]));
  }
}

/// A receipt to look at and share (canvas: tenant receipts).
class ReceiptScreen extends StatelessWidget {
  ReceiptScreen({super.key, required this.payment, required this.session});
  final Map<String, dynamic> payment;
  final Session session;
  final _key = GlobalKey();

  @override
  Widget build(BuildContext context) {
    final p = payment;
    final text = 'Receipt ${p['receipt_no']}: ${kes(p['amount'])} received${p['invoice_month'] != null ? ' for ${p['invoice_month']}' : ''}'
        '${p['unit'] != null ? ', unit ${p['unit']}' : ''}. Ref ${p['reference'] ?? '-'}. ${p['issued_by'] ?? 'scharge'}';
    return Scaffold(
      appBar: AppBar(title: const Text('Receipt')),
      body: ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
        RepaintBoundary(
          key: _key,
          child: _paper(Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            _DocHead(title: 'Receipt', number: '${p['receipt_no']}', issuer: p['issued_by'] as String?),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 22, 20, 8),
              child: Column(children: [
                Container(
                  width: 64,
                  height: 64,
                  decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFFDCFCE7)),
                  child: const Icon(PhosphorIconsLight.check, size: 38, color: Sc.ok),
                ),
                const SizedBox(height: 12),
                Text('Payment received', maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
                const SizedBox(height: 4),
                CountUp(value: double.tryParse('${p['amount']}') ?? 0, format: kes, style: Sc.head(26)),
              ]),
            ),
            const Padding(padding: EdgeInsets.symmetric(horizontal: 20), child: Divider(color: Sc.blush2)),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
              child: Column(children: [
                _kv('From', p['payer'] as String?, icon: PhosphorIconsLight.user),
                if (p['unit'] != null) _kv('Unit', '${p['unit']} · ${p['property'] ?? ''}', icon: PhosphorIconsLight.house),
                if (p['invoice_number'] != null) _kv('For bill', '${p['invoice_number']} · ${p['invoice_month'] ?? ''}', icon: PhosphorIconsLight.fileText),
                _kv('Paid with', '${p['method'] ?? '-'}'.replaceAll('_', ' ').toUpperCase(), icon: PhosphorIconsLight.deviceMobile),
                CopyLine('Reference', p['reference'] as String?),
                _kv('Date', dateTime(p['paid_at'] as String?), icon: PhosphorIconsLight.calendarBlank),
              ]),
            ),
          ])),
        ),
        const SizedBox(height: 20),
        FilledButton.icon(
          onPressed: () => shareAsImage(context, _key, '${p['receipt_no']}', text),
          icon: const Icon(PhosphorIconsLight.export),
          label: const Text('Share receipt'),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => shareOnWhatsApp(context, text),
          icon: const Icon(PhosphorIconsLight.whatsappLogo),
          label: const Text('Send on WhatsApp'),
        ),
      ]),
    );
  }
}

/// A payment row for lists: tenant receipts and staff payments.
class PaymentRow extends StatelessWidget {
  const PaymentRow({super.key, required this.payment, required this.session, this.order = 0, this.showPayer = false});
  final Map<String, dynamic> payment;
  final Session session;
  final int order;
  final bool showPayer;
  @override
  Widget build(BuildContext context) {
    final p = payment;
    final method = '${p['method'] ?? ''}'.toLowerCase();
    final icon = method.contains('mpesa') || method.contains('m-pesa')
        ? PhosphorIconsLight.deviceMobile
        : method.contains('bank')
            ? PhosphorIconsLight.bank
            : method.contains('wallet')
                ? PhosphorIconsLight.wallet
                : PhosphorIconsLight.money;
    return KeyedSubtree(
      child: Tile(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ReceiptScreen(payment: p, session: session))),
        child: Row(children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(color: Sc.accentSoft, borderRadius: BorderRadius.circular(14)),
            child: Icon(icon, color: Sc.accent == Sc.gold ? Sc.ink : Sc.accent),
          ),
          const SizedBox(width: 12),
          Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(showPayer ? '${p['payer'] ?? 'Payment'}' : '${p['invoice_month'] ?? 'Payment'}', style: Sc.body(15, weight: 600)),
            const SizedBox(height: 2),
            Text([if (p['unit'] != null) p['unit'], p['reference'], shortDate(p['paid_at'] as String?)].where((v) => v != null && '$v'.isNotEmpty).join(' · '),
                maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('+${kes(p['amount'])}', style: Sc.body(15, weight: 700, color: Sc.ok)),
            const SizedBox(height: 2),
            Text('Receipt', style: Sc.body(12, weight: 600, color: Sc.brand)),
          ]),
        ]),
      ),
    );
  }
}

/// The gate pass as a ticket (canvas: tenant "Gate pass"): red card, name, QR in a white box, time and host unit.
class PassTicket extends StatelessWidget {
  const PassTicket({super.key, required this.pass, required this.boundaryKey});
  final Map<String, dynamic> pass;
  final GlobalKey boundaryKey;

  static String _when(Map p) {
    final from = DateTime.tryParse('${p['valid_from']}')?.toLocal();
    final until = DateTime.tryParse('${p['valid_until']}')?.toLocal();
    if (from == null) return '';
    String t(DateTime d) => d.minute == 0 ? '${d.hour % 12 == 0 ? 12 : d.hour % 12} ${d.hour < 12 ? 'am' : 'pm'}' : '${d.hour % 12 == 0 ? 12 : d.hour % 12}:${d.minute.toString().padLeft(2, '0')} ${d.hour < 12 ? 'am' : 'pm'}';
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    final day = '${days[from.weekday - 1]} ${from.day} ${months[from.month - 1]}';
    return until == null ? '$day · ${t(from)}' : '$day · ${t(from)} – ${t(until)}';
  }

  @override
  Widget build(BuildContext context) {
    final p = pass;
    return Center(
      child: RepaintBoundary(
        key: boundaryKey,
        child: Container(
          width: double.infinity,
          constraints: const BoxConstraints(maxWidth: 420),
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(color: Sc.brand, borderRadius: BorderRadius.circular(18)),
          child: Column(children: [
            Text('${p['kind']} · ${p['property'] ?? ''}'.toUpperCase(),
                textAlign: TextAlign.center, style: Sc.body(12, color: Colors.white.withValues(alpha: .85)).copyWith(letterSpacing: 1.6)),
            const SizedBox(height: 10),
            Text('${p['guest_name']}', textAlign: TextAlign.center, style: Sc.head(17, color: Colors.white)),
            const SizedBox(height: 12),
            Container(
              width: 180,
              height: 180,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
              child: p['qr'] == null
                  ? const SizedBox.shrink()
                  : QrImageView(
                      data: '${p['qr']}',
                      padding: EdgeInsets.zero,
                      eyeStyle: const QrEyeStyle(eyeShape: QrEyeShape.square, color: Sc.ink),
                      dataModuleStyle: const QrDataModuleStyle(dataModuleShape: QrDataModuleShape.square, color: Sc.ink),
                    ),
            ),
            const SizedBox(height: 12),
            Text('${_when(p)}${p['unit'] != null ? ' · Host ${p['unit']}' : ''}', textAlign: TextAlign.center, style: Sc.body(13, color: Colors.white)),
            const SizedBox(height: 8),
            Text('${p['code']}', style: Sc.head(22, color: Colors.white).copyWith(letterSpacing: 5)),
          ]),
        ),
      ),
    );
  }
}
