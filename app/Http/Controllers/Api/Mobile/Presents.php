<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Support\InvoiceLink;

/** How the scharge app sees invoices, payments, repairs, passes. Amounts are strings with 2 decimals, in KES. */
trait Presents
{
    protected static function money($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    protected function invoice($i, bool $withItems = false): array
    {
        $tenancy = $i->relationLoaded('tenancy') ? $i->tenancy : null;
        $out = [
            'id' => $i->id,
            'number' => $i->invoice_number ?: 'INV-'.$i->id,
            'type' => $i->invoice_type,
            'month' => $i->billing_month?->format('Y-m'),
            'month_label' => $i->billing_month?->format('F Y'),
            'status' => $i->status,
            'total' => self::money($i->total_amount),
            'paid' => self::money($i->total_paid),
            'balance' => self::money(max(0, (float) $i->total_amount - (float) $i->total_paid)),
            'currency' => 'KES',
            'tenant' => $tenancy?->tenant?->user?->name,
            'phone' => $tenancy?->tenant?->user?->phone,
            'unit' => $tenancy?->unit?->unit_number,
            'property' => $tenancy?->unit?->property?->name,
            // The secret bill link: M-Pesa prompt or Sharet, the same page tenants get by SMS.
            'pay_url' => InvoiceLink::url($i).'&from=app', // Pay presses from the app are counted as app (PaymentAttempts)
            'issued_by' => self::issuer($i->owningCompanyId()),
            'created_at' => $i->created_at?->toIso8601String(),
        ];
        if ($withItems) {
            $out['items'] = $i->items->map(fn ($it) => ['description' => $it->description, 'type' => $it->item_type,
                'amount' => self::money($it->amount), 'paid' => self::money($it->paid_amount)])->values()->all();
        }

        return $out;
    }

    /** The landlord's name as tenants should see it: the white label's name, else the company's. */
    protected static function issuer(?int $companyId): ?string
    {
        if (! $companyId) {
            return null;
        }
        $brand = class_exists(\App\Support\Brand::class) ? \App\Support\Brand::forCompany($companyId) : null;

        return $brand?->display_name ?? \App\Models\Company::whereKey($companyId)->value('name');
    }

    protected function payment($p): array
    {
        $inv = $p->relationLoaded('invoice') ? $p->invoice : null;
        $tenancy = $inv?->relationLoaded('tenancy') ? $inv->tenancy : null;

        return [
            'id' => $p->id,
            'receipt_no' => 'RCT-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT),
            'invoice_number' => $inv ? ($inv->invoice_number ?: 'INV-'.$inv->id) : null,
            'invoice_month' => $inv?->billing_month?->format('F Y'),
            'unit' => $tenancy?->unit?->unit_number,
            'property' => $tenancy?->unit?->property?->name,
            'issued_by' => $inv ? self::issuer($inv->owningCompanyId()) : null,
            'phone' => $p->tenant?->user?->phone ?? $p->user?->phone,
            'amount' => self::money($p->amount),
            'method' => $p->payment_method,
            'reference' => $p->external_reference ?: $p->transaction_reference,
            'status' => $p->status,
            'invoice_id' => $p->invoice_id,
            'payer' => $p->tenant?->user?->name ?? $p->user?->name,
            'paid_at' => $p->created_at?->toIso8601String(),
        ];
    }

    protected function repair($m): array
    {
        return [
            'id' => $m->id,
            'number' => $m->request_number,
            'title' => $m->name,
            'description' => $m->description,
            'category' => $m->category,
            'priority' => $m->priority,
            'status' => $m->status,
            'unit' => $m->unit?->unit_number,
            'property' => $m->unit?->property?->name,
            'tenant' => $m->tenant?->user?->name,
            'assigned_to' => $m->assignedStaff?->name,
            'assigned_phone' => $m->assignedStaff?->phone,
            'scheduled_date' => $m->scheduled_date?->format('j M'),
            'photos' => collect($m->images ?? [])->map(fn ($p) => asset('storage/'.$p))->values()->all(),
            'resolution_notes' => $m->resolution_notes,
            'cost' => $m->cost !== null ? self::money($m->cost) : null,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    protected function pass($p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'kind' => $p->kind,
            'status' => $p->status,
            'is_inside' => (bool) $p->is_inside,
            'guest_name' => $p->guest_name,
            'guest_phone' => $p->guest_phone,
            'guest_email' => $p->guest_email,
            'sent_by_email' => (bool) $p->email_sent_at,
            'sent_by_sms' => (bool) $p->sms_sent_at,
            'guests_count' => (int) $p->guests_count,
            'vehicle_plate' => $p->vehicle_plate,
            'unit' => $p->unit?->unit_number,
            'property' => $p->property?->name ?? $p->unit?->property?->name,
            'valid_from' => $p->valid_from?->toIso8601String(),
            'valid_until' => $p->valid_until?->toIso8601String(),
            'checked_in_at' => $p->checked_in_at?->toIso8601String(),
            'checked_out_at' => $p->checked_out_at?->toIso8601String(),
            'created_by' => $p->created_by,
            // The guest's own pass page, and what the QR "ticket" holds: the guard's phone opens the pass from it.
            'pass_url' => $p->token ? $p->publicUrl() : null,
            'qr' => url('/gate/verify?code='.$p->code),
        ];
    }
}
