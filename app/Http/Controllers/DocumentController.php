<?php

namespace App\Http\Controllers;

use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Services\Documents\DocumentPdf;
use Illuminate\Http\Request;

/** Invoice and receipt PDFs: the invoice's company (staff) or the tenant it belongs to. */
class DocumentController extends Controller
{
    public function invoice(Request $request, int $id, DocumentPdf $pdf)
    {
        $invoice = Invoice::with('tenancy.tenant')->findOrFail($id);
        $this->authorizeFor($request, $invoice);

        return $pdf->invoice($invoice)->stream($pdf->invoiceNumber($invoice).'.pdf');
    }

    public function receipt(Request $request, int $id, DocumentPdf $pdf)
    {
        $payment = Payment::with('invoice.tenancy.tenant')->findOrFail($id);
        abort_unless($payment->invoice, 404, 'This payment is not linked to an invoice.');
        $this->authorizeFor($request, $payment->invoice);

        return $pdf->receipt($payment)->stream('Receipt-RCT-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT).'.pdf');
    }

    private function authorizeFor(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        $ownTenant = $invoice->tenancy?->tenant?->user_id === $user->id;
        $sameCompany = (int) $user->company_id === (int) $invoice->owningCompanyId() && ! $user->hasRole('tenant');
        abort_unless($ownTenant || $sameCompany || $user->hasRole('sysadmin'), 403);
    }
}
