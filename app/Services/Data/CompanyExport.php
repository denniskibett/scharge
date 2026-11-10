<?php

namespace App\Services\Data;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything one landlord company owns, its whole history, as one zip of CSV files (open in Excel or Google Sheets),
 * plus README.txt and manifest.json. Used by Settings › White label & data › Download, `php artisan company:export`
 * and the nightly copy to the company's own storage (CompanyBackup).
 *
 * Ownership follows the same rules as the app (CompanyAccess, RentRun::ownedBy): a row's company_id, or for older rows
 * the tenancy / unit / property it belongs to. Secrets never leave: passwords, tokens, API key hashes, integration
 * credentials and channel calendar links are left out.
 */
class CompanyExport
{
    private const SECRET = '/password|remember_token|token|secret|hash|credentials|two_factor|import_url|api_key|passkey/i';

    /** People columns that may be exported (never passwords or tokens). */
    private const USER_COLUMNS = ['id', 'name', 'email', 'phone', 'phone2', 'company_id', 'country', 'created_at', 'updated_at'];

    /** @return array<string, int> table => rows */
    public function counts(int $companyId): array
    {
        return collect($this->tables($companyId))->map(fn (Builder $q) => (clone $q)->count())->all();
    }

    /** Builds the zip in storage/app/private/exports and returns its path. */
    public function zip(int $companyId): string
    {
        $company = DB::table('companies')->where('id', $companyId)->first();
        abort_unless($company, 404);
        $dir = storage_path('app/private/exports');
        @mkdir($dir, 0700, true);
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $company->name)), '-') ?: 'company';
        $path = $dir.'/'.$slug.'-'.$companyId.'-'.now('Africa/Nairobi')->format('Ymd-His').'.zip';

        $zip = new \ZipArchive();
        abort_unless($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500, 'Could not create the export file.');
        $manifest = [];
        foreach ($this->tables($companyId) as $table => $query) {
            $tmp = fopen('php://temp', 'w+');
            $rows = 0;
            $columns = null;
            $from = $query->from;
            foreach ((clone $query)->lazyById(500, $from.'.id', 'id') as $row) {
                $row = $this->clean($table, (array) $row);
                if ($columns === null) {
                    $columns = array_keys($row);
                    fputcsv($tmp, $columns);
                }
                fputcsv($tmp, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), array_values($row)));
                $rows++;
            }
            if ($columns === null) {
                fputcsv($tmp, array_keys($this->clean($table, array_flip($query->columns ?: Schema::getColumnListing($from)))));
            }
            rewind($tmp);
            $zip->addFromString($table.'.csv', "\xEF\xBB\xBF".stream_get_contents($tmp)); // BOM: Excel reads it as UTF-8
            fclose($tmp);
            $manifest[$table] = $rows;
        }
        $zip->addFromString('manifest.json', json_encode(['company_id' => $companyId, 'company' => $company->name,
            'generated_at' => now()->toIso8601String(), 'rows' => $manifest], JSON_PRETTY_PRINT));
        $zip->addFromString('README.txt', "Data of {$company->name} on scharge, exported ".now('Africa/Nairobi')->format('j M Y H:i')." (Nairobi).\r\n\r\n"
            ."One CSV file per kind of record; open them in Excel or Google Sheets. Ids link the files together:\r\n"
            ."units.property_id -> properties.id, tenancies.unit_id -> units.id, tenancies.tenant_id -> tenants.id,\r\n"
            ."tenants.user_id -> people.id, invoices.tenancy_id -> tenancies.id, invoice_items.invoice_id and payments.invoice_id -> invoices.id.\r\n"
            ."Amounts are in KES. Passwords and access keys are never included.\r\n\r\nRows per file:\r\n"
            .collect($manifest)->map(fn ($n, $t) => "  {$t}.csv: {$n}")->implode("\r\n")."\r\n");
        $zip->close();

        return $path;
    }

    /** @return array<string, Builder> file name => query (only tables that exist) */
    public function tables(int $companyId): array
    {
        $has = fn (string $t) => Schema::hasTable($t);
        $col = fn (string $t, string $c) => Schema::hasColumn($t, $c);
        $byCompany = fn (string $t) => DB::table($t)->where($t.'.company_id', $companyId);

        $propertyIds = DB::table('properties')->where('company_id', $companyId)->select('id');
        $unitIds = DB::table('units')->where(fn ($q) => $q->where('company_id', $companyId)
            ->orWhere(fn ($q) => $q->whereNull('company_id')->whereIn('property_id', DB::table('properties')->where('company_id', $companyId)->select('id'))))->select('id');
        $tenancyIds = DB::table('tenancies')->where(fn ($q) => $q->where('company_id', $companyId)
            ->orWhere(fn ($q) => $q->whereNull('company_id')->whereIn('unit_id', (clone $unitIds))))->select('id');
        $invoiceIds = DB::table('invoices')->where(fn ($q) => $q->where('company_id', $companyId)
            ->orWhere(fn ($q) => $q->whereNull('company_id')->whereIn('tenancy_id', (clone $tenancyIds))))->select('id');
        $tenantIds = DB::table('tenants')->where(fn ($q) => $q->where('company_id', $companyId)
            ->orWhereIn('id', DB::table('tenancies')->whereIn('id', (clone $tenancyIds))->select('tenant_id')))->select('id');

        $t = [
            'company' => DB::table('companies')->where('id', $companyId),
            'properties' => DB::table('properties')->whereIn('id', $propertyIds),
            'units' => DB::table('units')->whereIn('id', $unitIds),
            'people' => DB::table('users')->where(fn ($q) => $q->where('company_id', $companyId)
                ->orWhereIn('id', DB::table('tenants')->whereIn('id', (clone $tenantIds))->select('user_id')))
                ->when($has('model_has_roles'), fn ($q) => $q->whereNotIn('id', DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereIn('roles.name', ['sysadmin', 'account_manager'])->select('model_has_roles.model_id'))) // scharge staff are not the company's people
                ->select(array_values(array_intersect(self::USER_COLUMNS, Schema::getColumnListing('users')))),
            'tenants' => DB::table('tenants')->whereIn('id', $tenantIds),
            'tenancies' => DB::table('tenancies')->whereIn('id', $tenancyIds),
            'invoices' => DB::table('invoices')->whereIn('id', $invoiceIds),
            'invoice_items' => DB::table('invoice_items')->whereIn('invoice_id', (clone $invoiceIds)),
            'payments' => DB::table('payments')->whereIn('invoice_id', (clone $invoiceIds)),
        ];
        foreach (['tenancy_charges', 'lease_agreements', 'house_checklists', 'rent_holds'] as $table) {
            if ($has($table)) {
                $t[$table] = DB::table($table)->whereIn('tenancy_id', (clone $tenancyIds));
            }
        }
        if ($has('water_readings')) {
            $t['water_readings'] = DB::table('water_readings')->where(fn ($q) => $q->whereIn('unit_id', (clone $unitIds))->when($col('water_readings', 'company_id'), fn ($q) => $q->orWhere('company_id', $companyId)));
        }
        if ($has('mpesa_stks')) {
            $t['mpesa_stk_requests'] = DB::table('mpesa_stks')->whereIn('invoice_id', (clone $invoiceIds));
        }
        if ($has('wallets') && $has('transactions')) {
            $tenantClass = [\App\Models\Tenant::class, \App\Modules\Tenants\Models\Tenant::class, (new \App\Models\Tenant)->getMorphClass()];
            $walletIds = DB::table('wallets')->whereIn('holder_type', $tenantClass)->whereIn('holder_id', (clone $tenantIds))->select('id');
            $t['tenant_wallets'] = DB::table('wallets')->whereIn('id', $walletIds);
            $t['tenant_wallet_transactions'] = DB::table('transactions')->whereIn('wallet_id', (clone $walletIds));
        }
        foreach (['expenses', 'expense_categories', 'payees', 'maintenances', 'meters', 'meter_readings', 'gate_passes', 'visitors', 'visitor_places', 'security',
            'sms_templates', 'calendar_events', 'company_payment_methods', 'rent_messages', 'paybill_payments', 'bookings', 'unit_channels', 'unit_photos', 'unit_managements'] as $table) {
            if ($has($table) && $col($table, 'company_id')) {
                $t[$table] = $byCompany($table);
            }
        }
        if (isset($t['expenses']) && $has('expense_payments')) {
            $t['expense_payments'] = DB::table('expense_payments')->whereIn('expense_id', DB::table('expenses')->where('company_id', $companyId)->select('id'));
        }

        return $t;
    }

    private function clean(string $table, array $row): array
    {
        return array_filter($row, fn ($k) => ! preg_match(self::SECRET, (string) $k), ARRAY_FILTER_USE_KEY);
    }
}
