<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off clean-up of rows saved without their company/property (models that couldn't save those fields), tenant users
 * without the tenant role, and tenant phone numbers in mixed formats. Dry run by default: --apply writes.
 * Things only the client can decide (units marked vacant that still have an active tenancy, placeholder phones) are
 * listed, never changed.
 */
class DataFix extends Command
{
    protected $signature = 'scharge:data-fix {--apply : write the changes (default is a dry run)}';

    protected $description = 'Fill missing company/property links, tenant roles and phone formats; list what needs a person';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLYING changes' : 'Dry run (nothing is written; add --apply)');

        $fixes = [
            'Tenants without a company (taken from their latest tenancy\'s unit)' => [
                'select' => "select count(*) from tenants t where t.company_id is null and exists (select 1 from tenancies x join units u on u.id = x.unit_id where x.tenant_id = t.id and u.company_id is not null)",
                'update' => "update tenants t join (select x.tenant_id, max(x.id) tid from tenancies x group by x.tenant_id) l on l.tenant_id = t.id join tenancies x2 on x2.id = l.tid join units u on u.id = x2.unit_id set t.company_id = u.company_id, t.property_id = coalesce(t.property_id, u.property_id) where t.company_id is null and u.company_id is not null",
            ],
            'Tenancies without a company or property (from their unit)' => [
                'select' => "select count(*) from tenancies x join units u on u.id = x.unit_id where (x.company_id is null and u.company_id is not null) or (x.property_id is null and u.property_id is not null)",
                'update' => "update tenancies x join units u on u.id = x.unit_id set x.company_id = coalesce(x.company_id, u.company_id), x.property_id = coalesce(x.property_id, u.property_id) where (x.company_id is null and u.company_id is not null) or (x.property_id is null and u.property_id is not null)",
            ],
            'Water readings without a company (from their unit)' => [
                'select' => "select count(*) from water_readings w join units u on u.id = w.unit_id where w.company_id is null and u.company_id is not null",
                'update' => "update water_readings w join units u on u.id = w.unit_id set w.company_id = u.company_id where w.company_id is null and u.company_id is not null",
            ],
            'Repairs without a company or property (from their unit)' => [
                'select' => "select count(*) from maintenances m join units u on u.id = m.unit_id where (m.company_id is null and u.company_id is not null) or (m.property_id is null and u.property_id is not null)",
                'update' => "update maintenances m join units u on u.id = m.unit_id set m.company_id = coalesce(m.company_id, u.company_id), m.property_id = coalesce(m.property_id, u.property_id) where (m.company_id is null and u.company_id is not null) or (m.property_id is null and u.property_id is not null)",
            ],
        ];

        foreach ($fixes as $label => $sql) {
            $n = (int) DB::selectOne(str_replace('select count(*)', 'select count(*) as n', $sql['select']))->n;
            $done = $apply && $n ? DB::affectingStatement($sql['update']) : 0;
            $this->line(sprintf('%-70s %5d %s', $label, $n, $apply ? "updated {$done}" : ''));
        }

        // Tenant users without the Spatie tenant role (they can't use the tenant portal or app)
        $role = DB::table('roles')->where('name', 'tenant')->where('guard_name', 'web')->value('id');
        $morph = (new \App\Models\User)->getMorphClass();
        $missing = DB::table('tenants')->whereNotNull('user_id')
            ->whereNotExists(fn ($q) => $q->from('model_has_roles')->whereColumn('model_has_roles.model_id', 'tenants.user_id')->where('model_type', $morph))
            ->pluck('user_id');
        $this->line(sprintf('%-70s %5d', 'Tenant users with no role (given "tenant")', $missing->count()));
        if ($apply && $role) {
            foreach ($missing as $uid) {
                DB::table('model_has_roles')->insertOrIgnore(['role_id' => $role, 'model_type' => $morph, 'model_id' => $uid]);
            }
        }

        // Tenant phones in one format: +2547XXXXXXXX / +2541XXXXXXXX (login matches on the last digits, so this is safe)
        $changes = [];
        $placeholders = [];
        foreach (DB::table('users')->whereIn('id', DB::table('tenants')->whereNotNull('user_id')->select('user_id'))->whereNotNull('phone')->get(['id', 'name', 'phone']) as $u) {
            $raw = trim((string) $u->phone);
            if ($raw === '') {
                continue;
            }
            $d = preg_replace('/\D+/', '', $raw);
            $norm = null;
            if (preg_match('/^0([17]\d{8})$/', $d, $m) || preg_match('/^254([17]\d{8})$/', $d, $m) || preg_match('/^([17]\d{8})$/', $d, $m)) {
                $norm = '+254'.$m[1];
            }
            if ($norm && $norm !== $raw) {
                $changes[$u->id] = [$raw, $norm];
            } elseif (! $norm && ! str_starts_with($raw, '+')) {
                $placeholders[] = "#{$u->id} {$u->name}: '{$raw}'";
            }
        }
        $this->line(sprintf('%-70s %5d', 'Tenant phones rewritten to +254 format', count($changes)));
        if ($apply) {
            foreach ($changes as $id => [, $norm]) {
                DB::table('users')->where('id', $id)->update(['phone' => $norm]);
            }
        }

        $this->newLine();
        $this->warn('For a person to decide (not changed):');
        $rows = DB::select("select c.name company, p.name property, u.unit_number, t.id tenancy_id from units u join tenancies t on t.unit_id = u.id and t.status = 'active' join properties p on p.id = u.property_id left join companies c on c.id = u.company_id where u.status = 'vacant' order by c.name, p.name, u.unit_number");
        $this->line(count($rows).' units marked vacant that still have an active tenancy (end the tenancy, or mark the unit occupied):');
        foreach ($rows as $r) {
            $this->line("  {$r->company} · {$r->property} · {$r->unit_number} (tenancy {$r->tenancy_id})");
        }
        $rows = DB::select("select c.name company, u.unit_number from units u left join companies c on c.id = u.company_id where u.status = 'occupied' and not exists (select 1 from tenancies t where t.unit_id = u.id and t.status = 'active')");
        $this->line(count($rows).' units marked occupied with no active tenancy:');
        foreach ($rows as $r) {
            $this->line("  {$r->company} · unit {$r->unit_number}");
        }
        $this->line(count($placeholders).' tenant phones that are not real numbers:');
        foreach ($placeholders as $p) {
            $this->line('  '.$p);
        }
        $dups = DB::select("select u.phone, group_concat(u.name separator ' / ') names from tenants t join users u on u.id = t.user_id where coalesce(u.phone, '') <> '' group by u.phone having count(*) > 1");
        $this->line(count($dups).' phone numbers shared by two or more tenants:');
        foreach ($dups as $r) {
            $this->line("  {$r->phone}: {$r->names}");
        }

        return self::SUCCESS;
    }
}
