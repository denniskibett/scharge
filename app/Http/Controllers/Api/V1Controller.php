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
            return response()->json(['object' => 'pay_link', 'provider' => 'mpesa', 'url' => route('public.invoice.show', $invoice), 'invoice' => $this->present($invoice)]);
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
