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
 * Shared rows (company_id NULL) from the earlier one-list version are removed when no expense uses them, since
 * each business now has its own copies.
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

        $usedPayees = DB::table('expenses')->whereNotNull('payee_id')->distinct()->pluck('payee_id');
        $usedCats = DB::table('expenses')->whereNotNull('expense_category_id')->distinct()->pluck('expense_category_id');
        $p = DB::table('payees')->whereNull('company_id')->whereNotIn('id', $usedPayees)->delete();
        $k = DB::table('expense_categories')->whereNull('company_id')->whereNotIn('id', $usedCats)->delete();

        $this->command?->info("Set up defaults for {$done} of {$companies->count()} businesses; removed {$p} unused shared payees and {$k} unused shared categories.");
    }
}
