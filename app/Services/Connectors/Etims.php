<?php

namespace App\Services\Connectors;

use App\Models\CompanyIntegration;
use App\Modules\Payments\Models\Invoice;
use App\Services\Integrations;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * KRA eTIMS, Online Sales Control Unit (OSCU) API (Integrations › KRA eTIMS). Each bill is sent to KRA once, as a
 * sale (saveTrnsSalesOsdc), when it is issued or when it is fully paid (the company chooses); KRA's receipt number,
 * signature and QR link are kept in integration_links.data and shown on the invoice.
 *
 * Set-up, done by Test: selectInitOsdcInfo (registers the device, gives the cmcKey), then saveItem for one service
 * item ("Rent") that every bill line uses. Bills are numbered 1, 2, 3… per branch, as KRA requires.
 */
class Etims
{
    public static function queue(int $companyId, int $invoiceId, string $moment = 'paid'): void
    {
        $row = Integrations::for($companyId, 'etims');
        if (! $row || $row->get('when', 'paid') !== $moment || blank($row->get('cmc_key')) || ! Hub::installed('integration_links')) {
            return;
        }
        $link = Accounting::link($companyId, 'etims', 'invoice', $invoiceId);
        if ($link->status !== 'synced') {
            self::send($row, $link->id);
        }
    }

    public static function test(CompanyIntegration $row): array
    {
        $init = Http::acceptJson()->asJson()->timeout(30)->post(self::base($row).'/selectInitOsdcInfo', [
            'tin' => (string) $row->get('tin'), 'bhfId' => self::branch($row), 'dvcSrlNo' => (string) $row->get('device_serial'),
        ]);
        $code = (string) $init->json('resultCd');
        $cmcKey = $init->json('data.info.cmcKey');
        if (! $init->successful() || ! in_array($code, ['000', '902'], true)) {
            return [false, 'KRA did not accept the device: '.($init->json('resultMsg') ?: 'HTTP '.$init->status()).'. Check the PIN, branch and device serial.'];
        }
        if ($cmcKey) {
            $row->mergeCredentials(['cmc_key' => $cmcKey]);
        }
        if (blank($row->get('cmc_key'))) {
            return [false, 'KRA says this device is already set up, but scharge has no communication key for it. Ask KRA to reset the device, then Test again.'];
        }
        if (blank($row->get('item_code'))) {
            $itemCode = 'KE3NTU'.str_pad('1', 7, '0', STR_PAD_LEFT);
            $item = self::call($row, '/saveItem', [
                'itemCd' => $itemCode, 'itemClsCd' => self::itemClass($row), 'itemTyCd' => '3', 'itemNm' => 'Rent', 'itemStdNm' => null,
                'orgnNatCd' => 'KE', 'pkgUnitCd' => 'NT', 'qtyUnitCd' => 'U', 'taxTyCd' => $row->get('tax_type', 'A'), 'btchNo' => null, 'bcd' => null,
                'dftPrc' => 0, 'grpPrcL1' => 0, 'grpPrcL2' => 0, 'grpPrcL3' => 0, 'grpPrcL4' => 0, 'grpPrcL5' => null, 'addInfo' => null, 'sftyQty' => null,
                'isrcAplcbYn' => 'N', 'useYn' => 'Y', 'regrNm' => 'scharge', 'regrId' => 'scharge', 'modrNm' => 'scharge', 'modrId' => 'scharge',
            ]);
            if (! in_array((string) $item->json('resultCd'), ['000'], true)) {
                $row->save();

                return [false, 'Device registered, but KRA refused the Rent item: '.($item->json('resultMsg') ?: 'HTTP '.$item->status()).'. Check the item class code.'];
            }
            $row->mergeCredentials(['item_code' => $itemCode]);
        }
        $row->save();

        return [true, 'Connected to KRA eTIMS ('.($row->get('environment') === 'production' ? 'live' : 'sandbox').') for PIN '.$row->get('tin')
            .'. Bills are sent when they are '.($row->get('when', 'paid') === 'paid' ? 'fully paid' : 'issued').'.'];
    }

    /** Send one bill to KRA (one integration_links row). */
    public static function send(CompanyIntegration $row, int $linkId): bool
    {
        return DB::transaction(function () use ($row, $linkId) {
            $link = DB::table('integration_links')->where('id', $linkId)->lockForUpdate()->first();
            if (! $link || $link->status === 'synced') {
                return true;
            }
            $invoice = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()->with('items', 'tenancy.tenant.user')->find($link->object_id));
            if (! $invoice || (float) $invoice->total_amount <= 0) {
                Accounting::done($linkId, false, 'Bill not found or zero.', final: true);

                return false;
            }
            // KRA needs gap-free numbers per branch: take the next one under the row lock and keep it on the link.
            $data = json_decode((string) $link->data, true) ?: [];
            if (empty($data['invc_no'])) {
                $fresh = CompanyIntegration::withoutGlobalScope('company')->whereKey($row->id)->lockForUpdate()->first();
                $data['invc_no'] = (int) $fresh->get('next_invc_no', 1);
                $fresh->mergeCredentials(['next_invc_no' => $data['invc_no'] + 1]);
                $fresh->save();
                DB::table('integration_links')->where('id', $linkId)->update(['data' => json_encode($data)]);
            }
            $res = self::call($row, '/saveTrnsSalesOsdc', self::sale($row, $invoice, (int) $data['invc_no']));
            if ((string) $res->json('resultCd') !== '000') {
                Accounting::done($linkId, false, 'KRA: '.($res->json('resultMsg') ?: 'HTTP '.$res->status()));

                return false;
            }
            $r = $res->json('data') ?? [];
            $data += ['receipt_no' => $r['rcptNo'] ?? null, 'total_receipt_no' => $r['totRcptNo'] ?? null, 'internal_data' => $r['intrlData'] ?? null,
                'signature' => $r['rcptSign'] ?? null, 'sdc_id' => $r['sdcId'] ?? null, 'mrc_no' => $r['mrcNo'] ?? null, 'signed_at' => $r['vsdcRcptPbctDate'] ?? null,
                'qr_url' => self::qrUrl($row, (string) ($r['rcptSign'] ?? ''))];
            DB::table('integration_links')->where('id', $linkId)->update(['data' => json_encode($data)]);
            Accounting::done($linkId, true, null, (string) ($r['rcptNo'] ?? $data['invc_no']));

            return true;
        });
    }

    /** KRA receipt details for an invoice page / PDF, if it was signed. */
    public static function receipt(int $invoiceId): ?array
    {
        if (! Hub::installed('integration_links')) {
            return null;
        }
        $link = DB::table('integration_links')->where('provider', 'etims')->where('object_type', 'invoice')->where('object_id', $invoiceId)->where('status', 'synced')->first();

        return $link ? json_decode((string) $link->data, true) : null;
    }

    public static function sale(CompanyIntegration $row, Invoice $invoice, int $invcNo): array
    {
        $tax = (string) $row->get('tax_type', 'A');
        $rates = ['A' => 0, 'B' => 16, 'C' => 0, 'D' => 0, 'E' => 8];
        $rate = $rates[$tax] ?? 0;
        $user = $invoice->tenancy?->tenant?->user;
        $items = $invoice->items->filter(fn ($i) => (float) $i->amount > 0)->values();
        if ($items->isEmpty()) {
            $items = collect([(object) ['description' => 'Rent '.$invoice->billing_month?->format('M Y'), 'amount' => $invoice->total_amount]]);
        }
        $lines = [];
        $total = 0.0;
        foreach ($items as $n => $i) {
            $amt = round((float) $i->amount, 2);
            $total += $amt;
            $taxAmt = round($amt * $rate / (100 + $rate), 2);
            $lines[] = ['itemSeq' => $n + 1, 'itemCd' => $row->get('item_code'), 'itemClsCd' => self::itemClass($row), 'itemNm' => mb_substr((string) ($i->description ?: 'Rent'), 0, 200),
                'bcd' => null, 'pkgUnitCd' => 'NT', 'pkg' => 1, 'qtyUnitCd' => 'U', 'qty' => 1, 'prc' => $amt, 'splyAmt' => $amt, 'dcRt' => 0, 'dcAmt' => 0,
                'isrccCd' => null, 'isrccNm' => null, 'isrcRt' => null, 'isrcAmt' => null, 'taxTyCd' => $tax, 'taxblAmt' => $amt, 'taxAmt' => $taxAmt, 'totAmt' => $amt];
        }
        $total = round($total, 2);
        $taxTotal = round(array_sum(array_column($lines, 'taxAmt')), 2);
        $now = now('Africa/Nairobi');
        $by = fn ($t) => $t === $tax ? $total : 0;
        $taxBy = fn ($t) => $t === $tax ? $taxTotal : 0;

        return [
            'invcNo' => $invcNo, 'orgInvcNo' => 0, 'custTin' => null, 'custNm' => mb_substr((string) ($user?->name ?? 'Tenant'), 0, 60),
            'salesTyCd' => 'N', 'rcptTyCd' => 'S', 'pmtTyCd' => '01', 'salesSttsCd' => '02',
            'cfmDt' => $now->format('YmdHis'), 'salesDt' => $now->format('Ymd'), 'stockRlsDt' => null, 'cnclReqDt' => null, 'cnclDt' => null, 'rfdDt' => null, 'rfdRsnCd' => null,
            'totItemCnt' => count($lines),
            'taxblAmtA' => $by('A'), 'taxblAmtB' => $by('B'), 'taxblAmtC' => $by('C'), 'taxblAmtD' => $by('D'), 'taxblAmtE' => $by('E'),
            'taxRtA' => 0, 'taxRtB' => 16, 'taxRtC' => 0, 'taxRtD' => 0, 'taxRtE' => 8,
            'taxAmtA' => $taxBy('A'), 'taxAmtB' => $taxBy('B'), 'taxAmtC' => $taxBy('C'), 'taxAmtD' => $taxBy('D'), 'taxAmtE' => $taxBy('E'),
            'totTaxblAmt' => $total, 'totTaxAmt' => $taxTotal, 'totAmt' => $total,
            'prchrAcptcYn' => 'N', 'remark' => 'scharge bill '.Payloads::number($invoice),
            'regrId' => 'scharge', 'regrNm' => 'scharge', 'modrId' => 'scharge', 'modrNm' => 'scharge',
            'receipt' => ['custTin' => null, 'custMblNo' => $user?->phone, 'rptNo' => null, 'trdeNm' => null, 'adrs' => null,
                'topMsg' => null, 'btmMsg' => 'Thank you', 'prchrAcptcYn' => 'N'],
            'itemList' => $lines,
        ];
    }

    private static function call(CompanyIntegration $row, string $path, array $body)
    {
        return Http::acceptJson()->asJson()->timeout(30)
            ->withHeaders(['tin' => (string) $row->get('tin'), 'bhfId' => self::branch($row), 'cmcKey' => (string) $row->get('cmc_key')])
            ->post(self::base($row).$path, $body);
    }

    private static function qrUrl(CompanyIntegration $row, string $signature): ?string
    {
        if ($signature === '') {
            return null;
        }
        $host = $row->get('environment') === 'production' ? 'https://etims.kra.go.ke' : 'https://etims-sbx.kra.go.ke';

        return $host.'/common/link/etims/receipt/indexEtimsReceiptData?Data='.urlencode($row->get('tin').self::branch($row).$signature);
    }

    private static function branch(CompanyIntegration $row): string
    {
        return str_pad((string) ($row->get('bhf_id') ?: '00'), 2, '0', STR_PAD_LEFT);
    }

    private static function itemClass(CompanyIntegration $row): string
    {
        return (string) ($row->get('item_class') ?: '80131500');
    }

    private static function base(CompanyIntegration $row): string
    {
        return $row->get('environment') === 'production' ? 'https://etims-api.kra.go.ke/etims-api' : 'https://etims-api-sbx.kra.go.ke/etims-api';
    }
}
