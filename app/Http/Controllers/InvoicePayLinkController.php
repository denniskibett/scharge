<?php

namespace App\Http\Controllers;

use App\Modules\Payments\Models\Invoice;
use App\Services\Integrations;
use App\Services\Sharet\SharetCollections;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /invoices/{invoice}/pay-link: staff send a tenant a link to pay an invoice.
 * The link is the Sharet pay page when the invoice is routed through Sharet,
 * otherwise scharge's own public invoice page (M-Pesa prompt).
 */
class InvoicePayLinkController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, SharetCollections $collections, SmsService $sms)
    {
        $user = $request->user();
        abort_unless($user->hasRole('sysadmin') || (int) $user->company_id === (int) $invoice->owningCompanyId(), 403);

        $data = $request->validate(['send_sms' => 'sometimes|boolean', 'phone' => 'nullable|string|max:20']);
        $tenant = $invoice->tenancy?->tenant;
        $phone = $data['phone'] ?? $tenant?->user?->phone ?? $tenant?->phone ?? null;

        $viaSharet = ! Integrations::daraja($invoice->owningCompanyId()) && SharetCollections::available();
        try {
            $url = $viaSharet
                ? $collections->payLink($invoice, null, $phone)->pay_url
                : route('public.invoice.show', $invoice);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $sent = false;
        if (($data['send_sms'] ?? true) && $phone) {
            $balance = number_format(max(0, (float) $invoice->total_amount - (float) $invoice->total_paid));
            try {
                $sms->send($phone, "Your bill of KES {$balance} is ready. Pay securely here: {$url}");
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('Pay link SMS failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'url' => $url,
            'provider' => $viaSharet ? 'sharet' : 'mpesa',
            'sms_sent' => $sent,
            'message' => $sent ? 'Pay link sent by SMS.' : 'Pay link ready. Copy it and share it with the tenant.',
        ]);
    }
}
