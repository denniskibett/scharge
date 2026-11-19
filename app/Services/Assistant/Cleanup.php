<?php

namespace App\Services\Assistant;

use App\Modules\CRM\Support\Normalizer;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\DB;

/**
 * Data clean-up (Claude assistant): fixed checks find records that do not add up; Claude explains each kind of problem
 * in plain words; a person approves on the Assistant page and only then apply() changes anything, re-checking each
 * record first. Problems that need a judgement (two tenants with one phone number) are shown for review, never fixed.
 */
class Cleanup
{
    public const CHECKS = [
        'occupied_without_tenant' => ['Units marked occupied with no tenant', 'set_vacant', 'Mark them vacant'],
        'vacant_with_tenant' => ['Units marked vacant that have an active tenant', 'set_occupied', 'Mark them occupied'],
        'bill_total_wrong' => ['Bills whose total is not the sum of their lines', 'fix_total', 'Set each total to the sum of its lines'],
        'bill_paid_wrong' => ['Bills whose "paid" does not match the payments received', 'fix_paid', 'Recalculate paid and status from the payments'],
        'phone_untidy' => ['Tenant phone numbers in the wrong format', 'fix_phone', 'Rewrite them as +254…'],
        'tenancy_past_move_out' => ['Tenancies still active more than a week after the move-out date', 'end_tenancy', 'End these tenancies'],
        'same_phone_tenants' => ['Different tenants sharing one phone number', null, 'Review by hand (merge or correct them)'],
    ];

    /** Run every check for a company and draft one explanation per kind of problem found. Returns drafts made. */
    public static function scan(int $companyId, bool $force = false): int
    {
        $n = 0;
        $week = now()->format('o-\WW').($force ? '-'.now()->format('dHis') : '');
        foreach (self::find($companyId) as $kind => $ids) {
            if (! $ids) {
                continue;
            }
            [$title, $action, $fix] = self::CHECKS[$kind];
            $n += (bool) Claude::draft($companyId, [
                'kind' => 'cleanup', 'ref' => "cleanup:{$kind}:{$week}", 'channel' => 'action', 'subject' => $title.' ('.count($ids).')',
                'task' => 'Explain this data problem to the office manager in 2 to 4 short plain sentences: what is wrong, why it matters for their money or tenants, and what the proposed fix does. No greeting or sign-off.',
                'facts' => ['problem' => $title, 'how_many' => count($ids), 'proposed_fix' => $fix, 'examples' => self::examples($kind, array_slice($ids, 0, 5))],
                'context' => ['check' => $kind, 'action' => $action, 'ids' => array_slice($ids, 0, 500)],
            ]);
        }

        return $n;
    }

    /** @return array<string, int[]> check => record ids with the problem */
    public static function find(int $companyId): array
    {
        return CompanyAccess::bypass(function () use ($companyId) {
            $tenancies = RentRun::ownedByQuery($companyId)->select('tenancies.id');
            $units = DB::table('units')->where(fn ($q) => $q->where('company_id', $companyId)
                ->orWhere(fn ($q) => $q->whereNull('company_id')->whereIn('property_id', DB::table('properties')->where('company_id', $companyId)->select('id'))));
            $active = RentRun::ownedByQuery($companyId)->where('status', 'active');
            $invoices = DB::table('invoices')->where(fn ($q) => $q->where('company_id', $companyId)->orWhereIn('tenancy_id', $tenancies));

            return [
                'occupied_without_tenant' => (clone $units)->where('status', 'occupied')->whereNotIn('id', (clone $active)->select('unit_id'))->pluck('id')->all(),
                'vacant_with_tenant' => (clone $units)->where('status', 'vacant')->whereIn('id', (clone $active)->select('unit_id'))->pluck('id')->all(),
                'bill_total_wrong' => (clone $invoices)->whereExists(fn ($q) => $q->from('invoice_items')->whereColumn('invoice_items.invoice_id', 'invoices.id'))
                    ->whereRaw('ABS(total_amount - (SELECT COALESCE(SUM(amount),0) FROM invoice_items WHERE invoice_items.invoice_id = invoices.id)) > 0.5')
                    ->pluck('id')->all(),
                'bill_paid_wrong' => (clone $invoices)
                    ->whereRaw("ABS(total_paid - (CASE WHEN total_amount < (SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = 'completed' AND payments.deleted_at IS NULL) THEN total_amount ELSE (SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = 'completed' AND payments.deleted_at IS NULL) END)) > 0.5")
                    ->pluck('id')->all(),
                'phone_untidy' => DB::table('users')->whereIn('id', DB::table('tenants')->whereIn('id', RentRun::ownedByQuery($companyId)->select('tenant_id'))->select('user_id'))
                    ->whereNotNull('phone')->where('phone', '!=', '')->get(['id', 'phone'])
                    ->filter(fn ($u) => ($p = Normalizer::phone($u->phone)) && $p !== $u->phone)->pluck('id')->values()->all(),
                'tenancy_past_move_out' => (clone $active)->whereNotNull('move_out_date')->whereDate('move_out_date', '<', now()->subDays(7))->pluck('id')->all(),
                'same_phone_tenants' => DB::table('users')->whereIn('id', DB::table('tenants')->whereIn('id', (clone $active)->select('tenant_id'))->select('user_id'))
                    ->whereNotNull('phone')->where('phone', '!=', '')->get(['id', 'phone'])->groupBy(fn ($u) => Normalizer::phone($u->phone) ?: $u->phone)
                    ->filter(fn ($g) => $g->count() > 1)->flatten()->pluck('id')->all(),
            ];
        });
    }

    /** Apply an approved clean-up draft. Each record is checked again first. Returns how many were fixed. */
    public static function apply(object $draft): int
    {
        $ctx = json_decode((string) $draft->context, true) ?: [];
        $kind = $ctx['check'] ?? null;
        $action = self::CHECKS[$kind][1] ?? null;
        $companyId = (int) $draft->company_id;
        if (! $action || ! $companyId) {
            return 0;
        }
        $still = array_values(array_intersect(array_map('intval', $ctx['ids'] ?? []), self::find($companyId)[$kind] ?? []));

        return DB::transaction(fn () => match ($action) {
            'set_vacant' => DB::table('units')->whereIn('id', $still)->update(['status' => 'vacant', 'updated_at' => now()]),
            'set_occupied' => DB::table('units')->whereIn('id', $still)->update(['status' => 'occupied', 'updated_at' => now()]),
            'fix_total' => collect($still)->sum(fn ($id) => DB::table('invoices')->where('id', $id)->update([
                'total_amount' => DB::raw('(SELECT COALESCE(SUM(amount),0) FROM invoice_items WHERE invoice_items.invoice_id = invoices.id)'), 'updated_at' => now()]) && self::restatus($id)),
            'fix_paid' => collect($still)->sum(fn ($id) => (int) self::restatus($id, true)),
            'fix_phone' => collect($still)->sum(fn ($id) => DB::table('users')->where('id', $id)->update(['phone' => Normalizer::phone(DB::table('users')->where('id', $id)->value('phone')), 'updated_at' => now()])),
            'end_tenancy' => DB::table('tenancies')->whereIn('id', $still)->update(['status' => 'ended', 'updated_at' => now()]),
        });
    }

    private static function restatus(int $invoiceId, bool $paidFromPayments = false): bool
    {
        $inv = DB::table('invoices')->find($invoiceId);
        $paid = $paidFromPayments
            ? min((float) $inv->total_amount, (float) DB::table('payments')->where('invoice_id', $invoiceId)->where('status', 'completed')->whereNull('deleted_at')->sum('amount'))
            : min((float) $inv->total_paid, (float) $inv->total_amount);
        $status = $paid >= (float) $inv->total_amount && (float) $inv->total_amount > 0 ? 'paid' : ($paid > 0 ? 'partial' : ($inv->status === 'draft' ? 'draft' : 'unpaid'));

        return (bool) DB::table('invoices')->where('id', $invoiceId)->update(['total_paid' => $paid, 'status' => $status, 'updated_at' => now()]);
    }

    private static function examples(string $kind, array $ids): array
    {
        return CompanyAccess::bypass(fn () => match ($kind) {
            'occupied_without_tenant', 'vacant_with_tenant' => DB::table('units')->leftJoin('properties', 'properties.id', '=', 'units.property_id')->whereIn('units.id', $ids)
                ->get(['units.unit_number', 'properties.name as building'])->map(fn ($r) => (array) $r)->all(),
            'bill_total_wrong', 'bill_paid_wrong' => DB::table('invoices')->whereIn('id', $ids)->get(['id', 'billing_month', 'total_amount', 'total_paid', 'status'])
                ->map(fn ($r) => ['bill' => 'INV-'.str_pad((string) $r->id, 6, '0', STR_PAD_LEFT), 'month' => $r->billing_month, 'total' => (float) $r->total_amount, 'paid' => (float) $r->total_paid, 'status' => $r->status])->all(),
            'phone_untidy', 'same_phone_tenants' => DB::table('users')->whereIn('id', $ids)->get(['name', 'phone'])->map(fn ($r) => ['tenant' => $r->name, 'phone' => $r->phone, 'tidy' => Normalizer::phone($r->phone)])->all(),
            'tenancy_past_move_out' => DB::table('tenancies')->whereIn('id', $ids)->get(['id', 'move_out_date'])->map(fn ($r) => (array) $r)->all(),
            default => [],
        });
    }
}
