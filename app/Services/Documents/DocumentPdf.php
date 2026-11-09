<?php

namespace App\Services\Documents;

use App\Models\Company;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant invoices and payment receipts as PDF, in the one sheet format ported from Brevemaster
 * (resources/views/pdf/sheet.blade.php). The landlord company is the letterhead; the tenant and their
 * unit are "Bill To"; pay instructions (Sharet link, paybill, till, bank) go in the notes.
 */
class DocumentPdf
{
    public function invoice(Invoice $invoice): DomPdf
    {
        $invoice->loadMissing(['tenancy.tenant.user', 'tenancy.unit.property', 'items', 'payments']);
        $company = Company::find($invoice->owningCompanyId());
        $month = $invoice->billing_month ? Carbon::parse($invoice->billing_month) : null;

        return $this->render($company, 'pdf.invoice', [
            'invoice' => $invoice,
            'docDate' => ($invoice->created_at ?? now())->format('d/m/Y'),
            'title' => 'Invoice',
            'number' => $this->invoiceNumber($invoice),
            'party' => $this->tenantParty($invoice),
            'lines' => $invoice->items->map(fn ($i) => [
                'type' => ucfirst(str_replace('_', ' ', (string) $i->item_type)),
                'name' => $i->description ?: ucfirst((string) $i->item_type),
                'total' => (float) $i->amount,
            ])->values()->all(),
            'facts' => [
                ['Unit', $invoice->tenancy?->unit?->unit_number],
                ['Property', $invoice->tenancy?->unit?->property?->name],
                ['Billing month', $month?->format('F Y')],
            ],
            'payNotes' => $this->payNotes($invoice, $company),
            'currency' => 'KES',
        ]);
    }

    public function receipt(Payment $payment): DomPdf
    {
        $payment->loadMissing(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit.property']);
        $invoice = $payment->invoice;
        $company = Company::find($invoice?->owningCompanyId());

        return $this->render($company, 'pdf.receipt', [
            'payment' => $payment,
            'invoice' => $invoice,
            'docDate' => ($payment->created_at ?? now())->format('d/m/Y'),
            'title' => 'Receipt',
            'number' => 'RCT-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT),
            'party' => $invoice ? $this->tenantParty($invoice) : ['name' => 'Tenant', 'lines' => []],
            'invoiceNumber' => $invoice ? $this->invoiceNumber($invoice) : null,
            'currency' => 'KES',
        ]);
    }

    public function invoiceNumber(Invoice $invoice): string
    {
        return $invoice->invoice_number ?: 'INV-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT);
    }

    private function render(?Company $company, string $view, array $data): DomPdf
    {
        $letterhead = [
            'name' => $company?->name ?? 'scharge',
            'address' => $company?->address ?: $company?->location,
            'phone' => $company?->phone,
            'header_lines' => array_values(array_filter([$company?->address ?: $company?->location, $company?->phone, $company?->email])),
            'hide_powered_by' => false,
        ];
        $fromRows = array_values(array_filter([$letterhead['name'], $company?->address ?: $company?->location, $company?->phone, $company?->email,
            $company?->tax_id ? 'KRA PIN: '.$company->tax_id : null]));

        return Pdf::loadView($view, $data + ['letterhead' => $letterhead, 'logoFile' => $this->logo($company), 'fromRows' => $fromRows])
            ->setPaper('letter')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'Times-Roman']);
    }

    /** "Bill To": tenant name, unit and property, contact. */
    private function tenantParty(Invoice $invoice): array
    {
        $user = $invoice->tenancy?->tenant?->user;
        $unit = $invoice->tenancy?->unit;

        return ['name' => $user?->name ?? 'Tenant', 'lines' => array_values(array_filter([
            $unit ? 'Unit '.$unit->unit_number.($unit->property ? ', '.$unit->property->name : '') : null,
            $unit?->property?->location, $user?->phone, $user?->email,
        ]))];
    }

    /** How to pay: Sharet pay link if routed there, else the company's paybill / till / bank on file. */
    private function payNotes(Invoice $invoice, ?Company $company): array
    {
        $notes = ['Pay online: '.route('public.invoice.show', $invoice)];
        if (Schema::hasTable('company_payment_methods') && $company) {
            $methods = \Illuminate\Support\Facades\DB::table('company_payment_methods')->where('company_id', $company->id)->where('is_active', 1)->orderByDesc('is_default')->get();
            foreach ($methods->take(3) as $m) {
                $notes[] = match (true) {
                    filled($m->paybill_number) => 'M-Pesa Paybill '.$m->paybill_number.', account '.($m->account_number ?: ($invoice->tenancy?->unit?->unit_number ?? 'your unit number')),
                    filled($m->till_number) => 'M-Pesa Till '.$m->till_number,
                    filled($m->bank_name) => 'Bank: '.$m->bank_name.($m->branch_name ? ' ('.$m->branch_name.')' : '').', '.($m->account_name ? $m->account_name.', ' : '').'A/C '.$m->account_number,
                    default => trim((string) $m->instructions),
                };
            }
        }
        $notes[] = 'Quote invoice '.$this->invoiceNumber($invoice).' when paying. Thank you.';

        return array_values(array_filter($notes));
    }

    /** A PNG logo for the letterhead (DomPDF draws PNG reliably, SVG poorly): the system logo if it is a PNG, else the app icon. */
    private function logo(?Company $company): ?string
    {
        $system = \App\Helpers\SystemHelper::get('logo');
        foreach ([$system ? public_path($system) : null, public_path('icon-512.png'), public_path('icon-192.png'), public_path('apple-touch-icon.png')] as $file) {
            if ($file && is_file($file) && str_ends_with(strtolower($file), '.png')) {
                return $file;
            }
        }

        return null;
    }
}
