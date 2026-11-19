<?php

namespace App\Services\Connectors;

use App\Models\Booking;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Properties\Models\Tenancy;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One place where scharge records reach the company's outside systems (docs/INTEGRATIONS.md):
 * webhooks out, KRA eTIMS and accounting sync. Called from model events in AppServiceProvider; never throws,
 * and does its work after the response so a slow outside system never holds up a payment.
 */
class Hub
{
    public static function invoiceIssued(Invoice $invoice): void
    {
        self::later(function () use ($invoice) {
            $invoice = self::freshInvoice($invoice->id);
            if (! $invoice || ! ($company = self::companyOfInvoice($invoice))) {
                return;
            }
            Webhooks::fire($company, 'invoice.issued', Payloads::invoice($invoice));
            Etims::queue($company, $invoice->id, 'issued');
            Accounting::queue($company, 'invoice', $invoice->id);
        });
    }

    public static function invoicePaid(Invoice $invoice): void
    {
        self::later(function () use ($invoice) {
            $invoice = self::freshInvoice($invoice->id);
            if (! $invoice || ! ($company = self::companyOfInvoice($invoice))) {
                return;
            }
            Webhooks::fire($company, 'invoice.paid', Payloads::invoice($invoice));
            Etims::queue($company, $invoice->id, 'paid');
        });
    }

    public static function paymentReceived(Payment $payment): void
    {
        if ($payment->status !== 'completed' || ! $payment->invoice_id) {
            return;
        }
        self::later(function () use ($payment) {
            $payment = CompanyAccess::bypass(fn () => Payment::find($payment->id));
            $invoice = $payment ? self::freshInvoice((int) $payment->invoice_id) : null;
            if (! $invoice || ! ($company = self::companyOfInvoice($invoice))) {
                return;
            }
            Webhooks::fire($company, 'payment.received', Payloads::payment($payment, $invoice));
            Accounting::queue($company, 'payment', $payment->id);
        });
    }

    public static function tenancyChanged(Tenancy $tenancy, bool $created): void
    {
        $event = match (true) {
            $created && $tenancy->status === 'active', ! $created && $tenancy->wasChanged('status') && $tenancy->status === 'active' => 'tenancy.started',
            ! $created && $tenancy->wasChanged('status') && in_array($tenancy->status, ['ended', 'terminated', 'moved_out'], true) => 'tenancy.ended',
            default => null,
        };
        if (! $event) {
            return;
        }
        self::later(function () use ($tenancy, $event) {
            $tenancy = CompanyAccess::bypass(fn () => Tenancy::with(['tenant.user', 'unit.property'])->find($tenancy->id));
            if ($tenancy && ($company = RentRun::companyOf($tenancy))) {
                Webhooks::fire($company, $event, Payloads::tenancy($tenancy));
            }
        });
    }

    public static function bookingCreated(Booking $booking): void
    {
        if (! $booking->company_id) {
            return;
        }
        self::later(fn () => Webhooks::fire((int) $booking->company_id, 'booking.created', Payloads::booking($booking->fresh(['unit.property']) ?? $booking)));
    }

    /** The landlord company of a bill: its own company_id, else its tenancy's. */
    public static function companyOfInvoice(Invoice $invoice): ?int
    {
        return $invoice->owningCompanyId() ?? ($invoice->tenancy ? RentRun::companyOf($invoice->tenancy) : null);
    }

    private static array $tables = [];

    public static function installed(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function forgetInstalled(): void
    {
        self::$tables = [];
    }

    private static function freshInvoice(int $id): ?Invoice
    {
        return CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()->with(['tenancy.tenant.user', 'tenancy.unit.property', 'items'])->find($id));
    }

    private static function later(callable $work): void
    {
        if (! \App\Services\Integrations::installed()) {
            return;
        }
        $run = function () use ($work) {
            try {
                $work();
            } catch (\Throwable $e) {
                Log::warning('Integration hub failed', ['error' => $e->getMessage()]);
            }
        };
        // After the surrounding transaction commits (a bill's lines are saved after the bill itself), and on the web after
        // the response, so a slow outside system never holds up a payment.
        app()->runningInConsole() ? \Illuminate\Support\Facades\DB::afterCommit($run) : dispatch($run)->afterResponse();
    }
}
