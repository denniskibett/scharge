<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CompanyIntegration;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Services\Bookings\Ical;
use App\Services\Integrations;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Illuminate\Http\Response;

/**
 * Integrations › Google Sheets & Calendar feeds: private, read-only live links (token in the path, made on Save).
 *   /feeds/{company}/{token}/payments.csv | bills.csv | arrears.csv | tenants.csv | calendar.ics
 * Google Sheets: =IMPORTDATA("<link>") (refreshes about hourly). Excel: Data › From web. Calendar: "subscribe by URL".
 */
class FeedController extends Controller
{
    public const KINDS = ['payments.csv' => 'Payments', 'bills.csv' => 'Bills', 'arrears.csv' => 'Arrears (unpaid bills)', 'tenants.csv' => 'Tenants', 'calendar.ics' => 'Calendar'];

    public function show(int $company, string $token, string $kind): Response
    {
        $row = Integrations::for($company, 'data_feeds');
        abort_unless($row && filled($row->get('feed_token')) && hash_equals((string) $row->get('feed_token'), $token) && isset(self::KINDS[$kind]), 404);
        $since = now()->subMonths((int) $row->get('months', 12))->startOfDay();

        return CompanyAccess::bypass(fn () => match ($kind) {
            'payments.csv' => $this->csv($kind, $this->payments($company, $since)),
            'bills.csv' => $this->csv($kind, $this->bills($company, $since, false)),
            'arrears.csv' => $this->csv($kind, $this->bills($company, $since, true)),
            'tenants.csv' => $this->csv($kind, $this->tenants($company)),
            'calendar.ics' => $this->calendar($company),
        });
    }

    /** The links for the Integrations card. */
    public static function links(CompanyIntegration $row): array
    {
        if (blank($row->get('feed_token'))) {
            return [];
        }

        return collect(self::KINDS)->map(fn ($label, $kind) => ['label' => $label,
            'value' => route('feeds.show', ['company' => $row->company_id, 'token' => $row->get('feed_token'), 'kind' => $kind]), 'copy' => true])->values()->all();
    }

    private function payments(int $company, $since): iterable
    {
        yield ['Date', 'Amount (KES)', 'Method', 'Reference', 'Bill', 'Tenant', 'Phone', 'Property', 'Unit'];
        $q = Payment::withoutGlobalScopes()->where('status', 'completed')->where('created_at', '>=', $since)
            ->whereHas('invoice', fn ($i) => $i->withoutGlobalScopes()->forCompany($company)->orWhereIn('tenancy_id', RentRun::ownedByQuery($company)->select('id')))
            ->with(['invoice' => fn ($i) => $i->withoutGlobalScopes()->with('tenancy.tenant.user', 'tenancy.unit.property')])->orderByDesc('id');
        foreach ($q->lazyById(500) as $p) {
            $t = $p->invoice?->tenancy;
            yield [$p->created_at?->timezone('Africa/Nairobi')->format('Y-m-d H:i'), (float) $p->amount, $p->payment_method,
                $p->external_reference ?: $p->transaction_reference, ($p->invoice ? \App\Services\Connectors\Payloads::number($p->invoice) : null), $t?->tenant?->user?->name, $t?->tenant?->user?->phone,
                $t?->unit?->property?->name, $t?->unit?->unit_number];
        }
    }

    private function bills(int $company, $since, bool $unpaidOnly): iterable
    {
        yield ['Bill', 'Month', 'Type', 'Total (KES)', 'Paid (KES)', 'Balance (KES)', 'Status', 'Tenant', 'Phone', 'Property', 'Unit', 'Created'];
        $q = Invoice::withoutGlobalScopes()->where(fn ($q) => $q->forCompany($company)->orWhereIn('tenancy_id', RentRun::ownedByQuery($company)->select('id')))
            ->when($unpaidOnly, fn ($q) => $q->whereColumn('total_paid', '<', 'total_amount')->whereNotIn('status', ['cancelled', 'void']),
                fn ($q) => $q->where('created_at', '>=', $since))
            ->with('tenancy.tenant.user', 'tenancy.unit.property');
        foreach ($q->lazyById(500) as $i) {
            $t = $i->tenancy;
            yield [\App\Services\Connectors\Payloads::number($i), $i->billing_month?->format('Y-m'), $i->invoice_type, (float) $i->total_amount, (float) $i->total_paid,
                round((float) $i->total_amount - (float) $i->total_paid, 2), $i->status, $t?->tenant?->user?->name, $t?->tenant?->user?->phone,
                $t?->unit?->property?->name, $t?->unit?->unit_number, $i->created_at?->timezone('Africa/Nairobi')->format('Y-m-d')];
        }
    }

    private function tenants(int $company): iterable
    {
        yield ['Tenant', 'Phone', 'Email', 'Property', 'Unit', 'Status', 'Moved in', 'Moved out'];
        foreach (RentRun::ownedByQuery($company)->with('tenant.user', 'unit.property')->lazyById(500) as $t) {
            yield [$t->tenant?->user?->name, $t->tenant?->user?->phone, $t->tenant?->user?->email, $t->unit?->property?->name, $t->unit?->unit_number,
                $t->status, $t->move_in_date?->toDateString(), $t->move_out_date?->toDateString()];
        }
    }

    private function calendar(int $company): Response
    {
        $events = [];
        foreach (Booking::where('company_id', $company)->where('status', 'confirmed')->whereDate('check_out', '>=', now()->subDays(60))->with('unit.property')->get() as $b) {
            $events[] = ['uid' => 'stay-'.$b->id.'@scharge', 'start' => $b->check_in->toDateString(), 'end' => $b->check_out->toDateString(),
                'summary' => trim(($b->guest_name ?: 'Guest').' · '.($b->unit?->unit_number ?? '').' '.($b->unit?->property?->name ?? ''))];
        }
        $tenancies = RentRun::ownedByQuery($company)->with('tenant.user', 'unit')
            ->where(fn ($q) => $q->whereDate('move_in_date', '>=', now()->subDays(60))->orWhereDate('move_out_date', '>=', now()->subDays(60)))->get();
        foreach ($tenancies as $t) {
            $who = ($t->tenant?->user?->name ?? 'Tenant').' · '.($t->unit?->unit_number ?? '');
            foreach (['move_in_date' => 'Moves in', 'move_out_date' => 'Moves out'] as $col => $label) {
                if ($t->$col && $t->$col->gte(now()->subDays(60))) {
                    $events[] = ['uid' => "$col-{$t->id}@scharge", 'start' => $t->$col->toDateString(), 'end' => $t->$col->addDay()->toDateString(), 'summary' => "$label: $who"];
                }
            }
        }
        $name = \App\Models\Company::find($company)?->name ?? 'scharge';

        return response(Ical::build($name.' · scharge', $events), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    private function csv(string $kind, iterable $rows): Response
    {
        $out = fopen('php://temp', 'w+');
        foreach ($rows as $r) {
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $r)); // no formula injection in Sheets/Excel
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$kind.'"', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
