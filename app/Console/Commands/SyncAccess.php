<?php

namespace App\Console\Commands;

use App\Support\Access;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes App\Support\Access into Spatie: the permission catalogue and each built-in role's grants.
 * --keep-custom leaves permissions edited on the Roles page alone for roles that already have some.
 * Old, never-checked permissions ("view users" style) are removed.
 */
class SyncAccess extends Command
{
    protected $signature = 'access:sync {--keep-custom : Do not reset roles that already have catalogue permissions} {--dry-run}';

    protected $description = 'Create the permission catalogue and give built-in roles their default access';

    public function handle(): int
    {
        $catalogue = Access::permissions();
        $old = Permission::whereNotIn('name', $catalogue)->pluck('name');
        $this->line('Catalogue: '.count($catalogue).' permissions; old unused to remove: '.$old->count());
        if ($this->option('dry-run')) {
            foreach (Access::BUILT_IN as $role) {
                $this->line(sprintf('  %-17s %d permissions', $role, count(Access::grantsFor($role))));
            }

            return self::SUCCESS;
        }

        foreach ($catalogue as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Permission::whereIn('name', $old)->delete();

        foreach (Access::BUILT_IN as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $hasCatalogue = $role->permissions()->whereIn('name', $catalogue)->exists();
            if ($this->option('keep-custom') && $hasCatalogue) {
                $this->line("  {$name}: kept as edited");

                continue;
            }
            $role->syncPermissions(Access::grantsFor($name));
            $this->line(sprintf('  %-17s %d permissions', $name, count(Access::grantsFor($name))));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
