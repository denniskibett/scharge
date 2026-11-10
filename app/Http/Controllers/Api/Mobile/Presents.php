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
            'pay_url' => InvoiceLink::url($i),
            'created_at' => $i->created_at?->toIso8601String(),
        ];
        if ($withItems) {
            $out['items'] = $i->items->map(fn ($it) => ['description' => $it->description, 'type' => $it->item_type,
                'amount' => self::money($it->amount), 'paid' => self::money($it->paid_amount)])->values()->all();
        }

        return $out;
    }

    protected function payment($p): array
    {
        return [
            'id' => $p->id,
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
            'guests_count' => (int) $p->guests_count,
            'vehicle_plate' => $p->vehicle_plate,
            'unit' => $p->unit?->unit_number,
            'property' => $p->property?->name ?? $p->unit?->property?->name,
            'valid_from' => $p->valid_from?->toIso8601String(),
            'valid_until' => $p->valid_until?->toIso8601String(),
            'checked_in_at' => $p->checked_in_at?->toIso8601String(),
            'checked_out_at' => $p->checked_out_at?->toIso8601String(),
        ];
    }
}
