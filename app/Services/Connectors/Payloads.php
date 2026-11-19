<?php

namespace App\Services\Connectors;

use App\Models\Booking;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Properties\Models\Tenancy;

/** The JSON scharge sends about its records (webhooks out). Stable field names: receivers depend on them. */
class Payloads
{
    /** The bill number tenants see (invoices have no number column: INV- and the zero-padded id, as on the invoice pages). */
    public static function number($invoice): string
    {
        return $invoice->invoice_number ?? 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
    }

    public static function invoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => self::number($invoice),
            'type' => $invoice->invoice_type,
            'billing_month' => $invoice->billing_month?->format('Y-m'),
            'total' => (float) $invoice->total_amount,
            'paid' => (float) $invoice->total_paid,
            'balance' => round((float) $invoice->total_amount - (float) $invoice->total_paid, 2),
            'status' => $invoice->status,
            'currency' => 'KES',
            'lines' => $invoice->items->map(fn ($i) => ['type' => $i->item_type, 'description' => $i->description, 'amount' => (float) $i->amount])->values()->all(),
            'created_at' => $invoice->created_at?->toIso8601String(),
        ] + ($invoice->tenancy ? self::people($invoice->tenancy) : []);
    }

    public static function payment(Payment $payment, Invoice $invoice): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'currency' => 'KES',
            'method' => $payment->payment_method,
            'reference' => $payment->external_reference,
            'receipt' => $payment->transaction_reference,
            'paid_at' => $payment->created_at?->toIso8601String(),
            'invoice' => ['id' => $invoice->id, 'number' => self::number($invoice), 'total' => (float) $invoice->total_amount,
                'paid' => (float) $invoice->total_paid, 'status' => $invoice->status],
        ] + ($invoice->tenancy ? self::people($invoice->tenancy) : []);
    }

    public static function tenancy(Tenancy $tenancy): array
    {
        return ['id' => $tenancy->id, 'status' => $tenancy->status,
            'move_in_date' => $tenancy->move_in_date?->toDateString(), 'move_out_date' => $tenancy->move_out_date?->toDateString()]
            + self::people($tenancy);
    }

    public static function booking(Booking $booking): array
    {
        return [
            'id' => $booking->id, 'source' => $booking->source, 'status' => $booking->status,
            'guest_name' => $booking->guest_name, 'guest_phone' => $booking->guest_phone, 'guests' => $booking->guests,
            'check_in' => $booking->check_in?->toDateString(), 'check_out' => $booking->check_out?->toDateString(),
            'nights' => $booking->check_in && $booking->check_out ? $booking->nights : null,
            'amount' => $booking->amount !== null ? (float) $booking->amount : null, 'currency' => 'KES',
            'unit' => $booking->unit ? ['id' => $booking->unit->id, 'number' => $booking->unit->unit_number] : null,
            'property' => $booking->unit?->property ? ['id' => $booking->unit->property->id, 'name' => $booking->unit->property->name] : null,
        ];
    }

    private static function people(Tenancy $tenancy): array
    {
        $user = $tenancy->tenant?->user;
        $unit = $tenancy->unit;

        return [
            'tenancy_id' => $tenancy->id,
            'tenant' => $tenancy->tenant ? ['id' => $tenancy->tenant->id, 'name' => $user?->name, 'phone' => $user?->phone, 'email' => $user?->email] : null,
            'unit' => $unit ? ['id' => $unit->id, 'number' => $unit->unit_number] : null,
            'property' => $unit?->property ? ['id' => $unit->property->id, 'name' => $unit->property->name] : null,
        ];
    }
}
