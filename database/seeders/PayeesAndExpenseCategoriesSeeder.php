<?php

namespace Database\Seeders;

use App\Services\Expenses\BusinessBooks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives every business its own starting payees and expense categories, chosen by what it is
 * (manager / bnb_host / developer, see App\Services\Expenses\BusinessBooks::DEFAULTS). A business that already
 * has its own keeps them untouched. New businesses get theirs the first time they open Expenses or Payees.
 *
 * Shared payees (company_id NULL, BusinessBooks::SHARED_PAYEES: KPLC, KRA, NSSF…) exist once for everyone. Other
 * shared rows from the earlier one-list version are removed when no expense uses them.
 *
 *   php artisan db:seed --class=PayeesAndExpenseCategoriesSeeder --force
 */
class PayeesAndExpenseCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $companies = DB::table('companies')->where(fn ($q) => $q->whereNull('lifecycle')->orWhere('lifecycle', '!=', 'prospect'))->orderBy('id')->get(['id', 'name', 'kind']); // customers, not CRM prospects
        $done = 0;
        foreach ($companies as $c) {
            $before = DB::table('payees')->where('company_id', $c->id)->count() + DB::table('expense_categories')->where('company_id', $c->id)->count();
            BusinessBooks::ensureDefaults((int) $c->id);
            $after = DB::table('payees')->where('company_id', $c->id)->count() + DB::table('expense_categories')->where('company_id', $c->id)->count();
            $done += $after > $before ? 1 : 0;
        }

        $merged = $this->unduplicatePayees();

        $usedPayees = DB::table('expenses')->whereNotNull('payee_id')->distinct()->pluck('payee_id');
        $usedCats = DB::table('expenses')->whereNotNull('expense_category_id')->distinct()->pluck('expense_category_id');
        $p = DB::table('payees')->whereNull('company_id')->whereNotIn('name', array_keys(BusinessBooks::SHARED_PAYEES))->whereNotIn('id', $usedPayees)->delete();
        BusinessBooks::ensureSharedPayees();
        $k = DB::table('expense_categories')->whereNull('company_id')->whereNotIn('id', $usedCats)->delete();

        $this->command?->info("Merged {$merged['shared']} per-business copies into shared payees; added the business name to {$merged['renamed']} payees.");
        $this->command?->info("Set up defaults for {$done} of {$companies->count()} businesses; removed {$p} unused shared payees and {$k} unused shared categories.");
    }

    /**
     * Per-business copies of a national payee (KPLC, KRA…) become the one shared row (their expenses are moved to
     * it); every other per-business payee gets the business name, so no name repeats across businesses.
     */
    private function unduplicatePayees(): array
    {
        BusinessBooks::ensureSharedPayees();
        $shared = DB::table('payees')->whereNull('company_id')->pluck('id', 'name');
        $aliases = [
            'Nairobi City County (rates & garbage)' => 'Nairobi City County',
            'Nairobi City County (approvals & rates)' => 'Nairobi City County',
            'Kenya Power (KPLC tokens)' => 'Kenya Power (KPLC)',
        ];
        $merged = 0;
        $renamed = 0;
        $names = DB::table('companies')->pluck('name', 'id');

        foreach (DB::table('payees')->whereNotNull('company_id')->orderBy('id')->get(['id', 'company_id', 'name']) as $p) {
            $target = $aliases[$p->name] ?? $p->name;
            if (isset($shared[$target])) {
                DB::table('expenses')->where('payee_id', $p->id)->update(['payee_id' => $shared[$target]]);
                DB::table('payees')->where('id', $p->id)->delete();
                $merged++;

                continue;
            }
            $suffix = ' · '.BusinessBooks::shortName((string) ($names[$p->company_id] ?? ''));
            if ($suffix !== ' · ' && ! str_contains($p->name, ' · ')) {
                DB::table('payees')->where('id', $p->id)->update(['name' => $p->name.$suffix, 'updated_at' => now()]);
                $renamed++;
            }
        }

        return ['shared' => $merged, 'renamed' => $renamed];
    }
}
