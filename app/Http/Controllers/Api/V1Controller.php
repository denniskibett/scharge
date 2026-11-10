<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Modules\Payments\Models\Invoice;
use App\Services\Integrations;
use App\Services\Sharet\SharetClient;
use App\Services\Sharet\SharetCollections;
use Illuminate\Http\Request;

/**
 * scharge API v1 (for white-label clients and integrators). Every call is scoped to the key's company.
 *   GET  /api/v1/me
 *   GET  /api/v1/invoices/{id}
 *   POST /api/v1/invoices/{id}/pay-link   (scope: collect)
 *   GET  /api/v1/{collection}            properties, units, people, tenants, tenancies, invoices, invoice_items,
 *                                         payments, water_readings; ?after={id}&limit=1..500&updated_since=ISO-date
 *                                         (read API for white-label clients: docs/WHITE_LABEL.md)
 */
class V1Controller extends Controller
{
    public function me(Request $request)
    {
        $key = $this->key($request);

        return response()->json(['key' => $key->name, 'environment' => $key->environment, 'company' => $key->company?->name, 'scopes' => $key->scopes]);
    }

    public function invoice(Request $request, int $id)
    {
        return response()->json($this->present($this->invoiceFor($request, $id)));
    }

    public function payLink(Request $request, int $id, SharetCollections $collections)
    {
        $key = $this->key($request);
        $invoice = $this->invoiceFor($request, $id);
        $data = $request->validate(['amount' => 'nullable|numeric|min:1', 'phone' => 'nullable|string|max:20']);

        $platform = Integrations::sharet();
        $platformEnv = $platform ? SharetClient::fromIntegration($platform)->environment : null;
        if (! $platform || Integrations::daraja($invoice->owningCompanyId())) {
            // Not routed through Sharet: hand back scharge's own invoice page (M-Pesa prompt).
            return response()->json(['object' => 'pay_link', 'provider' => 'mpesa', 'url' => \App\Support\InvoiceLink::url($invoice), 'invoice' => $this->present($invoice)]);
        }
        if ($platformEnv !== $key->environment) {
            return response()->json(['error' => 'environment_mismatch', 'message' => "This is a {$key->environment} key but payments are running in {$platformEnv} mode."], 409);
        }

        try {
            $link = $collections->payLink($invoice, isset($data['amount']) ? (float) $data['amount'] : null, $data['phone'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'pay_link_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'object' => 'pay_link', 'provider' => 'sharet', 'id' => $link->sharet_id, 'status' => $link->status,
            'amount' => number_format((float) $link->amount, 2, '.', ''), 'currency' => 'KES',
            'url' => $link->pay_url, 'expires_at' => $link->expires_at?->toIso8601String(), 'invoice' => $this->present($invoice),
        ], 201);
    }

    public const COLLECTIONS = ['properties', 'units', 'people', 'tenants', 'tenancies', 'invoices', 'invoice_items', 'payments', 'water_readings'];

    /** A page of one kind of record, oldest id first. Same ownership rules and secret filtering as the data export. */
    public function collection(Request $request, string $collection, \App\Services\Data\CompanyExport $export)
    {
        abort_unless(in_array($collection, self::COLLECTIONS, true), 404, 'Unknown collection.');
        $data = $request->validate(['after' => 'nullable|integer|min:0', 'limit' => 'nullable|integer|min:1|max:500', 'updated_since' => 'nullable|date']);
        $query = $export->tables((int) $this->key($request)->company_id)[$collection] ?? null;
        abort_unless($query, 404, 'Not available.');
        $from = $query->from;
        $limit = (int) ($data['limit'] ?? 100);
        $rows = (clone $query)->where($from.'.id', '>', (int) ($data['after'] ?? 0))
            ->when($data['updated_since'] ?? null, fn ($q, $d) => \Illuminate\Support\Facades\Schema::hasColumn($from, 'updated_at') ? $q->where($from.'.updated_at', '>=', \Carbon\Carbon::parse($d)) : $q)
            ->orderBy($from.'.id')->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit)->map(fn ($r) => array_filter((array) $r, fn ($k) => ! preg_match('/password|remember_token|token|secret|hash|credentials|two_factor|import_url|api_key|passkey/i', (string) $k), ARRAY_FILTER_USE_KEY))->values();

        return response()->json(['object' => 'list', 'collection' => $collection, 'data' => $rows,
            'has_more' => $more, 'next_after' => $more ? $rows->last()['id'] : null]);
    }

    private function key(Request $request): ApiKey
    {
        return $request->attributes->get('api_key');
    }

    private function invoiceFor(Request $request, int $id): Invoice
    {
        $invoice = Invoice::forCompany((int) $this->key($request)->company_id)->find($id);
        abort_unless($invoice, 404, 'No such invoice for this company.');

        return $invoice;
    }

    private function present(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'object' => 'invoice',
            'number' => $invoice->invoice_number,
            'billing_month' => $invoice->billing_month,
            'status' => $invoice->status,
            'total' => number_format((float) $invoice->total_amount, 2, '.', ''),
            'paid' => number_format((float) $invoice->total_paid, 2, '.', ''),
            'balance' => number_format(max(0, (float) $invoice->total_amount - (float) $invoice->total_paid), 2, '.', ''),
            'currency' => 'KES',
        ];
    }
}
