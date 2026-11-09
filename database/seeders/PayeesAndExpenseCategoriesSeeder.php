<?php

namespace Database\Seeders;

use App\Services\Expenses\BusinessBooks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives every business its own starting expense categories, chosen by what it is
 * (manager / bnb_host / developer, see App\Services\Expenses\BusinessBooks::DEFAULTS). A business that already
 * has its own keeps them untouched. New businesses get theirs the first time they open Expenses or Payees.
 *
 * Payees are unique (BusinessBooks::SHARED_PAYEES, matched by slug): duplicates are merged into them. Other shared
 * rows without a slug are removed when no expense uses them. Needs database/schema/2026_11_10_payee_slugs.sql.
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
        $p = DB::table('payees')->whereNull('company_id')->whereNull('slug')->whereNotIn('id', $usedPayees)->delete();
        $k = DB::table('expense_categories')->whereNull('company_id')->whereNotIn('id', $usedCats)->delete();

        $this->command?->info("Merged {$merged['merged']} duplicate payees into the shared ones.");
        $this->command?->info("Set up defaults for {$done} of {$companies->count()} businesses; removed {$p} unused shared payees and {$k} unused shared categories.");
    }

    /**
     * Payees are unique: every copy of a known payee (a business's own "Cleaning company", an old
     * "Cleaning company · Danaff Kenya", a shared row without a slug) is merged into the one slugged shared row,
     * and its expenses move with it. A business's own named payees (no match) are left alone.
     */
    private function unduplicatePayees(): array
    {
        BusinessBooks::ensureSharedPayees();
        $bySlug = DB::table('payees')->whereNotNull('slug')->pluck('id', 'slug');
        $byName = [];
        foreach (BusinessBooks::SHARED_PAYEES as $slug => [$name]) {
            $byName[mb_strtolower($name)] = $bySlug[$slug] ?? null;
        }
        $aliases = [
            'nairobi city county (rates & garbage)' => 'nairobi city county',
            'nairobi city county (approvals & rates)' => 'nairobi city county',
            'kenya power (kplc tokens)' => 'kenya power (kplc)',
        ];
        $merged = 0;

        foreach (DB::table('payees')->whereNull('slug')->orderBy('id')->get(['id', 'name']) as $p) {
            $base = mb_strtolower(trim(preg_replace('/\s+·\s+.*$/u', '', $p->name)));
            $target = $byName[$aliases[$base] ?? $base] ?? null;
            if ($target && $target !== $p->id) {
                DB::table('expenses')->where('payee_id', $p->id)->update(['payee_id' => $target]);
                DB::table('payees')->where('id', $p->id)->delete();
                $merged++;
            }
        }

        return ['merged' => $merged];
    }
}
