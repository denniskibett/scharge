<?php

namespace App\Console\Commands;

use App\Support\BillingLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Dry run: which companies the billing lock locks (or would lock, while it is off), and their overdue bills. */
class BillingLockReport extends Command
{
    protected $signature = 'billing:lock-report';

    protected $description = 'List companies with an unpaid scharge bill past due (locked when BILLING_LOCK_ENABLED=true)';

    public function handle(): int
    {
        $this->line('Billing lock: '.(BillingLock::enabled() ? 'ON' : 'off (dry run)').(config('billing.lock_from') ? ', bills due from '.config('billing.lock_from') : ', every unpaid bill counts'));
        $ids = BillingLock::overdueCompanyIds();
        if (! $ids) {
            $this->info('No company would be locked.');

            return self::SUCCESS;
        }
        $rows = [];
        foreach ($ids as $id) {
            $s = BillingLock::status($id);
            $c = DB::table('companies')->where('id', $id)->first(['name', 'is_active']);
            $users = DB::table('users')->where('company_id', $id)->count();
            $rows[] = [$id, $c->name ?? '?', $c && $c->is_active ? 'yes' : 'no', $users, count($s['overdue']),
                $s['currency'].' '.number_format(array_sum(array_column($s['overdue'], 'amount'))), $s['overdue'][0]['due_date'] ?? ''];
        }
        $this->table(['Company', 'Name', 'Active', 'Users (incl. tenants)', 'Overdue bills', 'Overdue amount', 'Oldest due'], $rows);

        return self::SUCCESS;
    }
}
